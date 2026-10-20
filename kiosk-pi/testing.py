from flask import Flask, jsonify, request
from flask_cors import CORS
import adafruit_fingerprint
import serial
import threading
import requests
import time
import json
import os
import sys

# Maraming print dito ang may emoji. Kapag hindi UTF-8 ang stdout — hal.
# systemd journal na C locale ang takbo — ang print ay nagtatapon ng
# UnicodeEncodeError. Dahil nasa LOOB ito ng route handler, 500 ang
# isinasagot ng Flask kahit tama naman ang ginawa: napalitan ang site pero
# error ang nakikita ng kiosk. Isang linya ang pumapatay sa buong uri ng
# problemang ito.
try:
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")
except Exception:
    pass

# ══════════════════════════════════════════════════════════════
#  v13: CONFIG MULA SA /home/admin/jeyanco/kiosk.env
# ══════════════════════════════════════════════════════════════
#
#  Online na ang kiosk screen (https://<web>/kiosk). Ang Pi ay sensor na
#  lang + tulay papunta sa web. Lahat ng nagbabago kada device ay nasa
#  kiosk.env (binabasa ng systemd: EnvironmentFile=), hindi na sa code:
#
#    JEYANCO_WEB          https://jeyancowebsite-production.up.railway.app
#    JEYANCO_KIOSK_CODE   SITE_A   ← dapat kapareho ng code sa System
#                                     Settings → Kiosks (isa kada device)
#    JEYANCO_DEVICE_KEY   <secret> ← kapareho ng KIOSK_DEVICE_KEY sa Railway
#    JEYANCO_SERIAL       /dev/serial0
#    JEYANCO_MIN_CONFIDENCE 0
#
#  Dahil dito, pwedeng magdagdag ng pangalawa, pangatlong kiosk: parehong
#  code ang ilalagay sa bawat Pi, iba lang ang JEYANCO_KIOSK_CODE.

def _env(name, default=""):
    v = os.environ.get(name)
    return v.strip() if v and v.strip() else default

LARAVEL_BASE   = _env("JEYANCO_WEB", "https://jeyancowebsite-production.up.railway.app").rstrip("/")
LARAVEL_API    = LARAVEL_BASE + "/api/kiosk"
KIOSK_CODE     = _env("JEYANCO_KIOSK_CODE", "SITE_A")
DEVICE_KEY     = _env("JEYANCO_DEVICE_KEY", "")
SERIAL_PORT    = _env("JEYANCO_SERIAL", "/dev/serial0")
BAUD_RATE      = int(_env("JEYANCO_BAUD", "57600"))
MIN_CONFIDENCE = int(_env("JEYANCO_MIN_CONFIDENCE", "0"))

# Sino lang ang pwedeng tumawag sa service na ito mula sa browser: ang kiosk
# page sa web. Ibang website na mabuksan sa Pi ay hindi makakagamit ng sensor.
ALLOWED_ORIGINS = [o.strip().rstrip("/") for o in
                   _env("JEYANCO_ALLOWED_ORIGINS", LARAVEL_BASE).split(",") if o.strip()]

app = Flask(__name__)
CORS(app, origins=ALLOWED_ORIGINS)


@app.after_request
def _private_network(resp):
    # Ang https na page (Railway) na tumatawag sa http://127.0.0.1 ay
    # "Private Network Access" sa Chromium: kailangan ng pahintulot na ito sa
    # sagot, kung hindi ay hinaharang ng browser ang tawag nang tahimik.
    resp.headers["Access-Control-Allow-Private-Network"] = "true"
    return resp

# ── v10: IISANG KONEKSYON SA WEB ──────────────────────────────
# Dati, bawat tawag sa Laravel (requests.get / requests.post) ay nagbubukas
# ng BAGONG HTTPS connection: TCP + TLS handshake muna bago pa maipadala ang
# mismong tanong. Sa mobile data, iyon ang malaking bahagi ng hintay sa bawat
# scan — at dalawang tawag ang isang time in (/scan-attendance, /attendance).
#
# Ang Session ay pinananatiling bukas ang koneksyon (keep-alive) at ginagamit
# ulit ng scan loop, ng sync loop at ng mga proxy route. Ang board ng kiosk
# (kada 15s) at ang sync (kada 60s) ang nagpapanatiling mainit nito.
#
# Retry: ISANG ulit lang, at para lang sa pagkabigong KUMONEKTA (hindi pa
# naipapadala ang request) — kaya ligtas kahit sa POST: walang maitatalang
# doble. Ang timeout sa pagbasa at ang sagot ng server ay hindi inuulit.
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

http = requests.Session()
_adapter = HTTPAdapter(
    pool_connections=2, pool_maxsize=10,
    max_retries=Retry(total=1, connect=1, read=0, status=0, other=0,
                      allowed_methods=None, backoff_factor=0.2),
)
http.mount("https://", _adapter)
http.mount("http://", _adapter)

# v13: ang device key sa BAWAT tawag sa web. Ito ang patunay na Pi ang
# nagsasabing "may daliring nabasa" — hindi kayang gayahin ng browser.
http.headers.update({"Accept": "application/json"})
if DEVICE_KEY:
    http.headers.update({"X-Kiosk-Key": DEVICE_KEY})

# ══════════════════════════════════════════════════════════════
#  v8: WINDOW CONTROLS — ang ▁ (minimize) at ⛶ (full screen)
# ══════════════════════════════════════════════════════════════
#
#  Dati nasa kiosk_window.py ito. Kapag wala o pumapalya ang file na iyon,
#  tahimik na patay ang dalawang button. Nandito na lang, at SINASABI nito
#  kung ano ang kulang (tingnan: curl http://127.0.0.1:5000/window/status).
#
#  Gumagana sa dalawang uri ng desktop ng Raspberry Pi OS:
#    Wayland (labwc / wayfire — default ng Bookworm): wlrctl + wtype
#    X11:                                             xdotool
#  I-install minsan:  sudo apt install -y wlrctl wtype xdotool
#
#  Ang ⛶ ay pinipindot ang F11 ng Chromium. Hindi ito gagana kung
#  naka --kiosk ang Chromium (walang labasan ang --kiosk); dapat
#  --start-fullscreen ang nasa autostart. Sasabihin ito ng kiosk.

import glob
import shutil
import subprocess

WINDOW_RESTORE_SECONDS = 300          # kusang ibabalik ang kiosk pagkalipas nito
_restore_timer = None


def _desktop():
    """Aling desktop ang tumatakbo, at ang env para maabot ito mula sa service."""
    env = dict(os.environ)
    socks = sorted(p for p in glob.glob("/run/user/*/wayland-*") if not p.endswith(".lock"))
    if socks:
        env["XDG_RUNTIME_DIR"] = os.path.dirname(socks[0])
        env["WAYLAND_DISPLAY"] = os.path.basename(socks[0])
        return "wayland", env
    if glob.glob("/tmp/.X11-unix/X*"):
        env.setdefault("DISPLAY", ":0")
        if "XAUTHORITY" not in env:
            auths = sorted(glob.glob("/home/*/.Xauthority"), key=os.path.getmtime, reverse=True)
            if auths:
                env["XAUTHORITY"] = auths[0]
        return "x11", env
    return None, env


def _run(cmd, env, timeout=4):
    try:
        r = subprocess.run(cmd, env=env, capture_output=True, text=True, timeout=timeout)
        return r.returncode, (r.stdout or "") + (r.stderr or "")
    except Exception as e:
        return 1, str(e)


def _chromium_flags():
    """Ang command line ng Chromium — para malaman kung naka --kiosk."""
    for cmd in glob.glob("/proc/[0-9]*/cmdline"):
        try:
            with open(cmd, "rb") as f:
                parts = f.read().split(b"\0")
        except OSError:
            continue
        exe = os.path.basename(parts[0].decode(errors="ignore")) if parts and parts[0] else ""
        if "chrom" in exe and not any(p.startswith(b"--type=") for p in parts):
            return [p.decode(errors="ignore") for p in parts if p]
    return []


def _wayland_ids(env):
    """app_id ng mga window ng Chromium, mula sa `wlrctl toplevel list`."""
    code, out = _run(["wlrctl", "toplevel", "list"], env)
    ids = []
    for line in out.splitlines():
        app_id = line.split(":", 1)[0].strip()
        if app_id and ("chrom" in app_id.lower()) and app_id not in ids:
            ids.append(app_id)
    return ids


def _x11_ids(env, visible=True):
    cmd = ["xdotool", "search"] + (["--onlyvisible"] if visible else []) + ["--class", "chromium"]
    code, out = _run(cmd, env)
    return [l.strip() for l in out.splitlines() if l.strip().isdigit()]


def _missing(tools):
    return [t for t in tools if not shutil.which(t)]


def _fail(code, msg, **extra):
    print(f"Window: {msg}")
    return jsonify(dict({"success": False, "code": code, "message": msg}, **extra))


def _restore():
    kind, env = _desktop()
    if kind == "wayland" and shutil.which("wlrctl"):
        for app_id in _wayland_ids(env):
            _run(["wlrctl", "toplevel", "focus", f"app_id:{app_id}"], env)
    elif kind == "x11" and shutil.which("xdotool"):
        for wid in _x11_ids(env, visible=False):
            _run(["xdotool", "windowmap", wid], env)
            _run(["xdotool", "windowactivate", wid], env)
    print("Window: ibinalik ang kiosk")


@app.route("/window/status")
def window_status():
    kind, env = _desktop()
    flags = _chromium_flags()
    need = {"wayland": ["wlrctl", "wtype"], "x11": ["xdotool"]}.get(kind, [])
    return jsonify({
        "success": True,
        "desktop": kind,
        "missing_tools": _missing(need),
        "chromium_running": bool(flags),
        "chromium_kiosk_flag": "--kiosk" in flags,
        "chromium_windows": (_wayland_ids(env) if kind == "wayland" and shutil.which("wlrctl")
                             else _x11_ids(env, False) if kind == "x11" and shutil.which("xdotool") else []),
        "env": {k: env.get(k) for k in ("WAYLAND_DISPLAY", "XDG_RUNTIME_DIR", "DISPLAY", "XAUTHORITY")},
    })


@app.route("/window/minimize", methods=["POST"])
def window_minimize():
    global _restore_timer
    kind, env = _desktop()
    if kind is None:
        return _fail("no_display", "Walang nakitang screen (Wayland o X11)")

    if kind == "wayland":
        miss = _missing(["wlrctl"])
        if miss:
            return _fail("no_tool", "Kulang: " + ", ".join(miss), tool=" ".join(miss))
        ids = _wayland_ids(env)
        if not ids:
            return _fail("no_window", "Walang nakitang Chromium window")
        ok = any(_run(["wlrctl", "toplevel", "minimize", f"app_id:{i}"], env)[0] == 0 for i in ids)
    else:
        miss = _missing(["xdotool"])
        if miss:
            return _fail("no_tool", "Kulang: xdotool", tool="xdotool")
        ids = _x11_ids(env)
        if not ids:
            return _fail("no_window", "Walang nakitang Chromium window")
        ok = any(_run(["xdotool", "windowminimize", i], env)[0] == 0 for i in ids)

    if not ok:
        return _fail("failed", "Hindi na-minimize ng desktop ang window")

    if _restore_timer:
        _restore_timer.cancel()
    _restore_timer = threading.Timer(WINDOW_RESTORE_SECONDS, _restore)
    _restore_timer.daemon = True
    _restore_timer.start()
    print(f"Window: na-minimize ({kind}); babalik sa loob ng {WINDOW_RESTORE_SECONDS}s")
    return jsonify({"success": True, "desktop": kind, "restore_in": WINDOW_RESTORE_SECONDS})


@app.route("/window/restore", methods=["POST"])
def window_restore():
    global _restore_timer
    if _restore_timer:
        _restore_timer.cancel()
        _restore_timer = None
    _restore()
    return jsonify({"success": True})


@app.route("/window/fullscreen", methods=["POST"])
def window_fullscreen():
    """Pindutin ang F11 ng Chromium: full screen <-> window."""
    kind, env = _desktop()
    if kind is None:
        return _fail("no_display", "Walang nakitang screen (Wayland o X11)")
    if "--kiosk" in _chromium_flags():
        return _fail("kiosk_mode", "Naka --kiosk ang Chromium; gawing --start-fullscreen sa autostart")

    if kind == "wayland":
        miss = _missing(["wtype"])
        if miss:
            return _fail("no_tool", "Kulang: wtype", tool="wtype")
        code, out = _run(["wtype", "-k", "F11"], env)
    else:
        miss = _missing(["xdotool"])
        if miss:
            return _fail("no_tool", "Kulang: xdotool", tool="xdotool")
        ids = _x11_ids(env)
        code, out = _run(["xdotool", "key", "--window", ids[0], "F11"] if ids else ["xdotool", "key", "F11"], env)

    if code != 0:
        return _fail("failed", "Hindi naipadala ang F11: " + out.strip()[:120])
    return jsonify({"success": True, "desktop": kind})


# ── CONFIG ────────────────────────────────────────────────────
# Nasa itaas na (v13, mula sa kiosk.env): SERIAL_PORT, BAUD_RATE,
# LARAVEL_API, KIOSK_CODE, DEVICE_KEY, MIN_CONFIDENCE.
# HAWAK NITO ANG ttyAMA0. Ang gps_tracker.py ay DAPAT nasa /dev/ttyAMA3 —
# isang programa lang ang pwedeng humawak ng isang UART.

# ── SERIAL SETUP ──────────────────────────────────────────────
uart   = serial.Serial(SERIAL_PORT, baudrate=BAUD_RATE, timeout=1)
finger = adafruit_fingerprint.Adafruit_Fingerprint(uart)

# v14: BANTAY SA SENSOR. Pagkatapos ng matagal na walang gumagamit, minsan
# nagugulo ang usapan sa R307 (naiiwang kalahating packet sa serial): akala
# ng loop ay may daliri pero hindi mabasa, kaya SCANNING… nang walang tigil
# hanggang i-restart ang service. Ngayon, kapag ganoon nang SENSOR_STUCK_SECONDS
# (sunod-sunod na sirang basa, malabong basa o error), kusang isinasara at
# binubuksan ulit ang serial — parang restart, pero sensor lang.
SENSOR_STUCK_SECONDS = 15


def reopen_sensor(why):
    """Isara at buksan ulit ang serial sa R307. DAPAT hawak ang sensor_lock."""
    global uart, finger
    print(f"🔄 Binubuksan ulit ang sensor: {why}")
    try:
        uart.close()
    except Exception:
        pass
    time.sleep(0.5)
    try:
        uart = serial.Serial(SERIAL_PORT, baudrate=BAUD_RATE, timeout=1)
        uart.reset_input_buffer()
        finger = adafruit_fingerprint.Adafruit_Fingerprint(uart)   # nagve-verify na ito
        print("✅ Sumagot ulit ang sensor.")
    except Exception as e:
        # Susubukan ulit ng loop sa susunod; ang mga finger.* ay magre-raise
        # lang at sasaluhin ng loop.
        print(f"⚠ Hindi pa sumasagot ang sensor ({e}) — susubukan ulit.")

# ── SHARED STATE ──────────────────────────────────────────────
scan_result   = {"status": "no_finger"}

# v4: bawat resulta ay may numero (seq). Dahil dito, isang beses lang
# itong mababasa ng kiosk — kahit maalis agad ang daliri bago pa ito
# makapag-poll. Dati, kapag mabilis inalis ang daliri, halos agad
# bumabalik sa "no_finger" at may pasok na hindi naitatala.
scan_seq      = 0
MIN_RESULT_SECONDS = 2.0   # gaano katagal nakikita ang resulta, pinakamaikli

# Ang huling daliring na-tag na, na hindi pa naaalis sa sensor.
#
# Isang dampi = isang tala. Kung wala nito, ang taong nakapatong pa ang
# daliri ay muling nate-tag: time_in, tapos time_out agad, gayong isang
# beses lang siyang dumampi. Nanatili itong None hanggang sa makitang
# walang daliri — doon lang muling puwedeng mag-tag.
held_fid      = None
held_result   = None   # ang ipinakitang resulta para sa daliring iyon

# v9: ang held_fid kapag HINDI nakilala ang daliring nakapatong. Dati, habang
# nakapatong ang hindi kilalang daliri, "NOT RECOGNISED" ulit-ulit kada 2 seg.
# Ngayon isang beses lang — hanggang maalis at idampi ulit.
UNKNOWN_HELD  = -1
enroll_status = {"status": "idle", "fingerprint_id": None, "message": ""}
is_enrolling  = False  # flag para hindi mag-scan habang nag-eenroll

# v9: iisang enroll lang kahit dalawang beses napindot ang START SCAN nang
# sabay. Dati, parehong nakakalusot ang dalawang request bago pa maitakda ang
# is_enrolling — dalawang thread ang sabay humahawak sa sensor.
enroll_gate   = threading.Lock()

# Oras ng huling matagumpay na enroll. Ginagamit ng sync para hindi
# agad burahin ang bagong enrolled finger na hindi pa naisa-submit sa
# web (may grace period). Kung 0, walang kamakailang enroll.
last_enroll_time = 0

# Ang slot na kaka-enroll lang at HINDI PA naikakabit sa isang tao.
#
# May puwang sa pagitan ng dalawa: nailalagay na ang template sa sensor,
# pero hindi pa nasasabihan ang web kung kanino ito. Kung may makabasa
# ng daliri sa loob ng puwang na iyon, isang hindi kilalang daliri ang
# ipo-post — at dati, gumagawa ang web ng "Unregistered Worker" para
# doon. Hindi na ito ipinapadala hangga't hindi naikakabit.
awaiting_attach  = None
attach_since     = 0
ATTACH_TIMEOUT   = 180   # segundo bago sumuko at ituring na normal
ENROLL_GRACE     = 180  # 3 minuto — panahon para ma-submit sa web bago i-sync

# Iisa lang ang serial port sa R307. Lahat ng finger.* na tumatawag sa
# sensor ay DAPAT nasa loob ng lock na ito, para hindi mag-overlap ang
# packets ng dalawang thread (scan loop + enrollment + sync) = iwas
# "Incorrect packet data" / "Failed to read data from sensor".
sensor_lock = threading.Lock()

# ── SYNC CONFIG ───────────────────────────────────────────────
# Kada ilang segundo, tatanungin ng Pi ang Laravel kung anong
# fingerprint IDs ang buhay pa. Kung may nasa sensor na WALA na sa
# listahan (kasi na-delete sa web), buburahin sa sensor. Ganito
# nagsy-sync ang delete mula web papuntang kiosk.
SYNC_INTERVAL = 60  # segundo bawat sync check

# v9: bantay laban sa pagbura ng LAHAT. Kapag sumagot ang web ng walang laman
# o kulang na listahan (sirang deploy, problema sa database), dati binubura ng
# sync ang lahat ng daliri sa sensor — kailangang mag-enroll ulit ang lahat.
# Ngayon: hindi nagbubura kapag walang laman ang listahan, at hindi nagbubura
# ng higit sa SYNC_MAX_DELETE nang sabay kung kalahati o higit pa ng sensor ang
# mabubura. Ang hindi nabura ay walang pinsala: "not registered" lang ang sagot
# ng web kapag ginamit.
SYNC_MAX_DELETE = 3

# ══════════════════════════════════════════════════════════════
#  ACTIVE SITE — kung saang site nakatayo ang kiosk ngayon
# ══════════════════════════════════════════════════════════════
#
#  Iisa ang device natin at binubuhat sa Site A, Site B, atbp. Ang
#  piniling site ay kailangan ng TATLO:
#
#    1. ng Laravel — para malaman kung saan naitala ang attendance
#    2. ng gps_tracker.py — para maikumpara ang GPS sa pinili mo
#    3. ng kiosk UI — para ipakita kung saan ka nakatayo
#
#  Kaya isinusulat ito sa file na binabasa ng dalawang programa.
#  Ang slug ("site-b") ay galing sa /api/kiosk/sites — iisa ang wika
#  ng kiosk, ng tracker at ng server.

ACTIVE_SITE_FILE = "/home/admin/active_site.json"

# Listahan ng sites mula sa Laravel. Dati hardcoded ang Site A / Site B
# sa kiosk UI, kaya kahit magdagdag ka sa web, hindi lumalabas dito.
_sites_cache    = []
_sites_fetched  = 0
SITES_REFRESH   = 300   # segundo


def load_sites(force=False):
    """Kunin ang sites sa Laravel. Kapag pumalya, ang huling nakuha."""
    global _sites_cache, _sites_fetched

    if _sites_cache and not force and (time.time() - _sites_fetched) < SITES_REFRESH:
        return _sites_cache

    try:
        res  = http.get(f"{LARAVEL_API}/sites", timeout=10)
        data = res.json()
        if data.get("success") and data.get("sites"):
            _sites_cache   = data["sites"]
            _sites_fetched = time.time()
            print(f"Sites mula sa web: {', '.join(s['name'] for s in _sites_cache)}")
    except Exception as e:
        print(f"⚠ Hindi makuha ang sites ({e}) — gagamitin ang huling listahan.")

    return _sites_cache


def read_active_site():
    """
    Anong site ang naka-select. Ibinabalik ang buong record
    {slug, id, name} para may site_id agad ang attendance.
    """
    try:
        with open(ACTIVE_SITE_FILE, "r") as f:
            data = json.load(f)
            if data.get("active_site"):
                return {
                    "slug": data.get("active_site"),
                    "id":   data.get("active_site_id"),
                    "name": data.get("active_site_name"),
                }
    except (FileNotFoundError, json.JSONDecodeError, KeyError, OSError, TypeError):
        pass
    return {"slug": None, "id": None, "name": None}


def write_active_site(site):
    """
    Isulat ang piniling site. Binabasa ito ng gps_tracker.py, kaya ang
    key na `active_site` ay DAPAT ang slug — yun ang hinahanap nito.
    """
    payload = {
        "active_site":      site["slug"],
        "active_site_id":   site["id"],
        "active_site_name": site["name"],
        "updated_at":       time.time(),
    }

    # Sumulat muna sa tabi, saka palitan — para hindi mabasa ng tracker
    # ang kalahating file kung sabay silang tumama.
    tmp = ACTIVE_SITE_FILE + ".tmp"
    with open(tmp, "w") as f:
        json.dump(payload, f)
    os.replace(tmp, ACTIVE_SITE_FILE)

    return payload


def site_params():
    """
    Ang site na isasama sa bawat tawag sa Laravel. Kapag walang napili,
    walang ipinapadala — mas mabuti nang walang sagot kaysa maling sagot;
    gagamitin ng server ang huling alam nitong site.
    """
    site   = read_active_site()
    params = {"kiosk_code": KIOSK_CODE}
    if site["id"]:
        params["site_id"] = site["id"]
    return params


# ══════════════════════════════════════════════════════════════
#  SENSOR SYNC LOOP — binubura ang orphan fingerprints
#  (na-delete na sa web pero nasa sensor pa)
# ══════════════════════════════════════════════════════════════

def sensor_sync_loop():
    global is_enrolling

    # Maghintay muna ng ilang segundo bago mag-umpisa, para matapos
    # ang serial setup at unang scan cycle.
    time.sleep(10)

    while True:
        try:
            # 1. Kunin ang listahan ng buhay na fingerprint IDs sa Laravel.
            try:
                res = http.get(f"{LARAVEL_API}/active-fingerprints", timeout=10)
                data = res.json()
            except Exception as e:
                print(f"Sync: hindi maabot ang Laravel ({e}) — susubukan ulit mamaya.")
                time.sleep(SYNC_INTERVAL)
                continue

            if not data.get("success"):
                time.sleep(SYNC_INTERVAL)
                continue

            # Set ng mga ID na DAPAT nasa sensor (galing sa web).
            valid_ids = set(int(i) for i in data.get("fingerprint_ids", []))

            # v9: walang laman = hindi pinagkakatiwalaan. Walang buburahin.
            if not valid_ids:
                print("Sync: walang laman ang listahan mula sa web — walang buburahin.")
                time.sleep(SYNC_INTERVAL)
                continue

            # 2. Skip kung nag-eenroll — huwag hahawakan ang sensor.
            if is_enrolling:
                time.sleep(SYNC_INTERVAL)
                continue

            # 2b. Skip din kung may KAMAKAILANG enroll na baka hindi pa
            # naisa-submit sa web (grace period). Iwas mabura ang bagong
            # finger bago pa ma-register sa web ang may-ari nito.
            if last_enroll_time and (time.time() - last_enroll_time) < ENROLL_GRACE:
                remaining = int(ENROLL_GRACE - (time.time() - last_enroll_time))
                print(f"Sync: naka-grace period pa ({remaining}s) — laktawan muna.")
                time.sleep(SYNC_INTERVAL)
                continue

            # 3. Kunin ang lock, basahin ang nasa sensor, burahin ang orphan.
            with sensor_lock:
                if is_enrolling:   # double-check pagkakuha ng lock
                    pass
                else:
                    if finger.read_templates() != adafruit_fingerprint.OK:
                        print("Sync: hindi mabasa ang sensor templates.")
                    else:
                        on_sensor = set(finger.templates)   # nasa sensor ngayon
                        # Orphan = nasa sensor pero WALA na sa web
                        orphans = on_sensor - valid_ids

                        if orphans and len(orphans) > SYNC_MAX_DELETE and len(orphans) * 2 >= len(on_sensor):
                            # v9: masyadong marami nang sabay — mas malamang na
                            # mali ang sagot ng web kaysa totoong binura lahat.
                            print(f"Sync: {len(orphans)} sa {len(on_sensor)} ang mabubura — sobra, "
                                  f"walang binura. Tingnan ang web: {sorted(orphans)}")
                        elif orphans:
                            print(f"Sync: may {len(orphans)} orphan(s) na buburahin: {sorted(orphans)}")
                            for fid in orphans:
                                if finger.delete_model(fid) == adafruit_fingerprint.OK:
                                    print(f"  🗑 Nabura sa sensor ang orphan slot #{fid}")
                                else:
                                    print(f"  ⚠ Hindi mabura ang slot #{fid}")
                        else:
                            # Tahimik kung walang gagawin (para di magulo ang logs)
                            pass

        except Exception as e:
            print(f"Sync loop error: {e}")

        time.sleep(SYNC_INTERVAL)

# ══════════════════════════════════════════════════════════════
#  ATTENDANCE SCAN LOOP — palaging tumatakbo sa background
# ══════════════════════════════════════════════════════════════

def _look():
    """
    v9: Isang tingin sa sensor, hawak ang lock — at WALA nang iba. Dati may
    time.sleep() sa loob ng lock (hanggang 2 seg kapag hindi nakilala ang
    daliri), kaya naghihintay ang enroll at ang sync. Ngayon ang lahat ng
    paghinto ay nasa labas na ng lock.

    Ibinabalik: (ano, fid, confidence) — ano ay "busy", "none", "bad",
    "blur", "unknown" o "match".
    """
    global scan_result
    with sensor_lock:
        # Double-check pagkatapos makuha ang lock: baka nag-umpisang
        # mag-enroll habang naghihintay tayo ng lock.
        if is_enrolling:
            return "busy", None, None

        img = finger.get_image()
        if img == adafruit_fingerprint.NOFINGER:
            return "none", None, None
        if img != adafruit_fingerprint.OK:
            return "bad", None, None

        # v14: ang daliring nakapatong pa at may resulta na ay hindi na
        # "scanning" — dati kumikislap ang SCANNING… sa ibabaw ng resulta.
        if held_fid is None:
            scan_result = {"status": "scanning"}

        # Convert image to template (CharBuffer1)
        if finger.image_2_tz(1) != adafruit_fingerprint.OK:
            return "blur", None, None

        # Search in sensor database
        if finger.finger_search() != adafruit_fingerprint.OK:
            return "unknown", None, None

        return "match", finger.finger_id, finger.confidence


def _unknown_finger():
    """Hindi kilala ang daliri. Sabihin nang isang beses habang nakapatong."""
    global scan_result, held_fid, held_result, scan_seq
    if held_fid == UNKNOWN_HELD and held_result is not None:
        scan_result = held_result          # parehong seq — hindi na uulitin ng kiosk
        time.sleep(0.4)
        return
    scan_seq += 1
    scan_result = {"status": "not_found", "seq": scan_seq}
    held_fid, held_result = UNKNOWN_HELD, scan_result
    time.sleep(MIN_RESULT_SECONDS)         # sa labas na ng lock


def post_scan_attendance(payload):
    """
    v14: Ang /scan-attendance, na may isang ulit sa bagong koneksyon.

    Pagkatapos ng matagal na walang scan, ang keep-alive na koneksyon sa web
    ay tahimik nang pinutol ng mobile data / ng Railway. Ang unang scan ay
    naghihintay sa patay na koneksyon hanggang mag-timeout — SCANNING… sa
    screen, tapos "Cannot connect". Kapag pumalya, itinatapon ang mga lumang
    koneksyon (http.close()) at inuulit nang isang beses sa bago.

    Ligtas ulitin: kinikilala lang ng /scan-attendance ang tao, walang
    naitatala (ang kiosk ang nagtatala, ayon sa pinindot ng worker).
    """
    try:
        return http.post(f"{LARAVEL_API}/scan-attendance", json=payload, timeout=(4, 6))
    except (requests.ConnectionError, requests.Timeout) as e:
        print(f"⚠ Patay ang koneksyon sa web ({e.__class__.__name__}) — bagong koneksyon, isang ulit.")
        http.close()
        return http.post(f"{LARAVEL_API}/scan-attendance", json=payload, timeout=(4, 8))


def attendance_scan_loop():
    global scan_result, is_enrolling, held_fid, held_result, awaiting_attach, scan_seq

    # v14: kailan nagsimula ang sunod-sunod na hindi mabasa (bad / blur /
    # error). None kapag maayos ang huling tingin.
    trouble_since = None

    while True:
        try:
            # Skip kung nag-eenroll — WAG hahawakan ang serial port.
            if is_enrolling:
                trouble_since = None
                time.sleep(0.5)
                continue

            # v14: matagal nang hindi mabasa — buksan ulit ang sensor.
            if trouble_since and (time.time() - trouble_since) > SENSOR_STUCK_SECONDS:
                with sensor_lock:
                    if not is_enrolling:
                        reopen_sensor(f"{SENSOR_STUCK_SECONDS}s nang hindi mabasa")
                trouble_since = None
                held_fid = None
                held_result = None
                scan_result = {"status": "no_finger"}
                continue

            what, fid, confidence = _look()

            if what == "busy":
                time.sleep(0.3)
                continue

            if what in ("bad", "blur"):
                trouble_since = trouble_since or time.time()
            else:
                trouble_since = None

            if what == "none":
                held_fid = None          # naalis na — puwede nang mag-tag ulit
                held_result = None
                scan_result = {"status": "no_finger"}
                time.sleep(0.3)
                continue

            if what == "bad":
                time.sleep(0.5)
                continue

            if what == "blur":
                # Malabong basa. Kung may resulta pa para sa daliring ito,
                # iyon pa rin ang nasa screen.
                scan_result = held_result if held_result is not None else {"status": "no_finger"}
                time.sleep(1)
                continue

            if what == "unknown":
                _unknown_finger()
                continue

            # Kaka-enroll lang at hindi pa naikakabit sa kanino man.
            # Huwag munang ipadala sa web: walang may-ari pa ito, at
            # ang pagpapadala ay siyang gumagawa ng phantom na row.
            if awaiting_attach is not None and fid == awaiting_attach:
                if (time.time() - attach_since) < ATTACH_TIMEOUT:
                    scan_result = {"status": "scanning"}
                    time.sleep(0.5)
                    continue
                awaiting_attach = None   # sumuko na — ituloy nang normal

            # Kaka-tag lang nito at nakapatong pa — huwag nang ulitin.
            # Panatilihin ang huling resulta sa screen. Kung hahayaan
            # itong manatiling "scanning", mananatili ang kiosk sa
            # SCANNING… habang nakapatong ang daliri, at hindi na
            # makikita ng tao ang sarili niyang TIME IN.
            if held_fid is not None and fid == held_fid:
                if held_result is not None:
                    scan_result = held_result
                time.sleep(0.4)
                continue

            # Tanggihan ang mahihinang match (false accept). Itaas ang
            # MIN_CONFIDENCE kung may nakakapasok na maling tao; ang 0
            # ay ibig sabihin tinatanggap lahat ng sinasabi ng sensor.
            if confidence < MIN_CONFIDENCE:
                print(f"⚠ Low confidence ({confidence}) for ID #{fid} — ignored")
                _unknown_finger()
                continue

            # ── Lock RELEASED dito ──
            # Ang Laravel HTTP call (~5s) ay nasa LABAS ng lock para hindi
            # ma-block ang enrollment habang nag-aantay ng network.
            print(f"✅ Fingerprint match: ID #{fid}, Confidence: {confidence}")

            try:
                # Isama kung saang site nakatayo ang kiosk — dito naitatala
                # ang attendance. Kung wala nito, mapupunta ang pasok sa
                # Site B sa Site A.
                payload = {"fingerprint_id": str(fid)}
                payload.update(site_params())

                res  = post_scan_attendance(payload)
                data = res.json()

                if data.get("success"):
                    # v3: ang /scan-attendance ay KUMIKILALA lang ng tao at
                    # nagmumungkahi ng aksyon. Walang naitatala dito — ang
                    # kiosk ang nagtatala, ayon sa pinindot ng worker.
                    scan_result = {
                        "status":   "success",
                        "type":     data.get("type"),      # mungkahi lang: time_in / time_out
                        "employee": data.get("employee"),  # {id, name, position, shift}
                        # v13: patunay ng scan na ito. Kailangan ito ng web
                        # para magtala ng pasok/labas, at para buksan ang DTR
                        # o reklamo ng taong ito. Isang beses lang magagamit.
                        "scan_token": data.get("scan_token"),
                    }
                    who = (data.get("employee") or {}).get("name", "?")
                    print(f"✅ {who} — kilala (mungkahi: {str(data.get('type')).upper()})")
                else:
                    # Isinasama pa rin ang employee kapag tinanggihan. Kilala
                    # pa rin ang tao — kailangan ito ng Payroll Assistant.
                    scan_result = {
                        "status":   "error",
                        "code":     data.get("code"),
                        "message":  data.get("message", "Failed"),
                        "employee": data.get("employee"),
                        # Kilala pa rin ang tao: puwede pa niyang buksan ang
                        # sarili niyang DTR o magreklamo.
                        "scan_token": data.get("scan_token"),
                    }
                    print(f"⚠ {data.get('message')}")

            except Exception as e:
                print(f"Laravel error: {e}")
                scan_result = {
                    "status":  "error",
                    "message": "Cannot connect to server"
                }

            # v4: lagyan ng numero ang resulta, at tandaan kung kailan ipinakita.
            scan_seq   += 1
            scan_result = dict(scan_result, seq=scan_seq)
            shown_at    = time.time()

            held_fid    = fid       # isang tala na lang hanggang maalis
            held_result = scan_result

            # Sandaling hintayin ang pag-alis para makita pa niya ang resulta.
            # Hindi ito kailangang matapos: kahit hindi maalis ang daliri,
            # pinipigilan na ng held_fid ang pangalawang tala.
            # Ang lock ay kinukuha kada tingin lang, hindi hawak sa buong
            # paghihintay — para hindi ma-block ang enrollment.
            for _ in range(6):                  # ~3s
                with sensor_lock:
                    if is_enrolling:
                        break
                    if finger.get_image() == adafruit_fingerprint.NOFINGER:
                        held_fid = None
                        break
                time.sleep(0.5)

            # v4: panatilihin ang resulta nang sapat na tagal para mabasa ng
            # kiosk, kahit naalis agad ang daliri.
            left = MIN_RESULT_SECONDS - (time.time() - shown_at)
            if left > 0:
                time.sleep(left)

            scan_result = {"status": "no_finger"}

        except Exception as e:
            print(f"Scan loop error: {e}")
            scan_result = {"status": "no_finger"}
            # v14: error sa serial = hindi rin mabasa. Linisin ang naiwang
            # kalahating packet; kapag tuloy-tuloy, bubuksan ulit sa itaas.
            trouble_since = trouble_since or time.time()
            try:
                with sensor_lock:
                    uart.reset_input_buffer()
            except Exception:
                pass
            time.sleep(1)

# ══════════════════════════════════════════════════════════════
#  ENROLLMENT FLOW
# ══════════════════════════════════════════════════════════════

def find_existing(allow_slot=None):
    """
    v8: Nasa sensor na ba ang daliring nasa CharBuffer1?

    Ibinabalik ang slot kung naka-save na ito sa IBANG slot, None kung wala.
    Ang allow_slot ay ang sariling slot ng taong pinapalitan ang daliri —
    hindi iyon doble. Hinahanap lang (HISPEEDSEARCH); hindi ginagalaw ang
    CharBuffer1, kaya tuloy pa rin ang enroll kung walang katugma.
    """
    try:
        if finger.finger_search() == adafruit_fingerprint.OK:
            fid = finger.finger_id
            if allow_slot is not None and int(fid) == int(allow_slot):
                return None
            return fid
    except Exception as e:
        print(f"Duplicate check error: {e}")
    return None


def wait_finger_lifted(max_polls=33):
    """Hintaying maalis ang daliri (~10s max) habang hawak pa ang lock."""
    n = 0
    while finger.get_image() != adafruit_fingerprint.NOFINGER:
        time.sleep(0.3)
        n += 1
        if n > max_polls:
            break


def reject_duplicate(fid):
    """v8: Isang daliri = isang tao. Walang ise-save sa sensor."""
    global enroll_status
    print(f"⛔ Naka-register na ang daliring ito sa slot #{fid} — hindi ise-save.")
    enroll_status = {
        "status":         "duplicate",
        "fingerprint_id": None,
        "slot":           fid,
        "message":        f"This finger is already registered (slot #{fid})",
    }
    wait_finger_lifted()


def do_enrollment(allow_slot=None):
    global enroll_status, is_enrolling, last_enroll_time, awaiting_attach, attach_since

    is_enrolling = True
    # Bigyan ng sandali ang scan loop para matapos ang kasalukuyang serial
    # transaction at bumitaw sa lock bago tayo pumasok.
    time.sleep(0.6)

    # Hawakan ang serial port sa BUONG enrollment. Dahil is_enrolling=True,
    # hindi na susubok ang scan loop kumuha ng lock, kaya walang overlap.
    with sensor_lock:
      try:
        # ── Step 1: First scan ──────────────────────────────
        enroll_status = {"status": "scanning_1", "fingerprint_id": None, "message": ""}
        print("📷 Step 1: Waiting for finger...")

        timeout = 0
        while True:
            img = finger.get_image()
            if img == adafruit_fingerprint.OK:
                break
            if timeout > 60:
                enroll_status = {"status": "error", "fingerprint_id": None, "message": "Timeout waiting for finger"}
                return
            time.sleep(0.5)
            timeout += 1

        if finger.image_2_tz(1) != adafruit_fingerprint.OK:
            enroll_status = {"status": "error", "fingerprint_id": None, "message": "Bad image, try again"}
            return

        # v8 — bantay A: naka-register na ba ito sa iba? Titigil agad dito,
        # bago pa pumunta sa pangalawang dampi.
        dup = find_existing(allow_slot)
        if dup is not None:
            reject_duplicate(dup)
            return

        # ── Step 2: Remove finger ───────────────────────────
        enroll_status = {"status": "remove", "fingerprint_id": None, "message": ""}
        print("✋ Step 2: Remove finger...")

        # v10: may hangganan na ang paghihintay. Dati walang limit ito: kapag
        # hindi nabasa ng sensor na naalis na ang daliri (maduming salamin,
        # basang daliri), hawak nito ang sensor HABAMBUHAY — patay ang
        # attendance scan hanggang i-restart ang service.
        lifted = False
        for _ in range(50):                      # ~15 segundo
            if finger.get_image() == adafruit_fingerprint.NOFINGER:
                lifted = True
                break
            time.sleep(0.3)
        if not lifted:
            print("⚠ Hindi naalis ang daliri sa loob ng 15s — itinigil ang enroll.")
            enroll_status = {"status": "error", "fingerprint_id": None,
                             "message": "Finger was not lifted. Wipe the sensor and try again"}
            return

        # ── Step 3: Second scan ─────────────────────────────
        enroll_status = {"status": "scanning_2", "fingerprint_id": None, "message": ""}
        print("📷 Step 3: Place finger again...")
        time.sleep(0.5)

        timeout = 0
        while True:
            img = finger.get_image()
            if img == adafruit_fingerprint.OK:
                break
            if timeout > 60:
                enroll_status = {"status": "error", "fingerprint_id": None, "message": "Timeout on second scan"}
                return
            time.sleep(0.5)
            timeout += 1

        if finger.image_2_tz(2) != adafruit_fingerprint.OK:
            enroll_status = {"status": "error", "fingerprint_id": None, "message": "Bad second image, try again"}
            return

        # ── Step 4: Create model (nasa CharBuffer1 na ang bagong template) ──
        if finger.create_model() != adafruit_fingerprint.OK:
            enroll_status = {"status": "error", "fingerprint_id": None, "message": "Fingerprints did not match, try again"}
            return

        # v8 — bantay B: isa pang hanap gamit ang pinagsamang template, bago
        # i-save. Sinasalo nito ang ibang pagkakadampi sa unang beses.
        dup = find_existing(allow_slot)
        if dup is not None:
            reject_duplicate(dup)
            return

        # ── Step 5: Find next available slot ───────────────
        # HUWAG gumamit ng load_model() para mag-probe — sinisira nito ang
        # CharBuffer1 na hawak pa ang bagong create_model(). read_templates()
        # ay binabasa lang ang index table — HINDI ginagalaw ang buffer.
        if finger.read_templates() != adafruit_fingerprint.OK:
            enroll_status = {"status": "error", "fingerprint_id": None, "message": "Cannot read sensor templates"}
            return

        # v13: ang buong laman ng sensor (R307: 1000), hindi 127 — dalawa o
        # higit pang daliri na ang bawat manggagawa.
        capacity = int(getattr(finger, "library_size", 0) or 127)
        fid = 1
        while fid in finger.templates and fid < capacity:
            fid += 1

        if fid >= capacity:
            enroll_status = {"status": "error", "fingerprint_id": None, "message": "Sensor full (no free slot)"}
            return

        if finger.store_model(fid) != adafruit_fingerprint.OK:
            enroll_status = {"status": "error", "fingerprint_id": None, "message": "Failed to store fingerprint"}
            return

        # ── Hintayin munang MAALIS ang daliri bago tapusin ───────
        # Kung hindi natin hihintayin na maalis ang daliri, agad itong
        # ma-scan ng attendance loop (dahil naka-store na sa sensor).
        # Hinihintay natin dito habang HAWAK PA ang lock — kaya hindi
        # makakapasok ang scan loop hangga't nakapatong pa ang daliri.
        print(f"✅ Enrolled at ID #{fid}")
        print("✋ Hintaying maalis ang daliri bago ibalik ang scan loop...")
        removal_timeout = 0
        while finger.get_image() != adafruit_fingerprint.NOFINGER:
            time.sleep(0.3)
            removal_timeout += 1
            if removal_timeout > 33:   # ~10s max, para hindi ma-stuck
                print("⚠ Timeout naghihintay maalis ang daliri — nagpapatuloy pa rin.")
                break

        # ── Done! ───────────────────────────────────────────
        last_enroll_time = time.time()   # simula ng grace period para sa sync
        awaiting_attach  = fid           # walang may-ari pa ang slot na ito
        attach_since     = time.time()
        enroll_status = {
            "status":         "done",
            "fingerprint_id": fid,
            "message":        f"Enrolled at ID #{fid}"
        }

      except Exception as e:
        print(f"Enrollment error: {e}")
        enroll_status = {"status": "error", "fingerprint_id": None, "message": str(e)}

      finally:
        is_enrolling = False

# ══════════════════════════════════════════════════════════════
#  FLASK ROUTES
# ══════════════════════════════════════════════════════════════

@app.route('/ping')
def ping():
    return jsonify({"status": "ok", "sensor": "connected"})

@app.route('/scan')
def scan():
    return jsonify(scan_result)

@app.route('/enroll/start', methods=['POST'])
def enroll_start():
    global enroll_status, is_enrolling

    # v8: ang sariling slot ng taong pinapalitan ang daliri (kung meron).
    body = request.get_json(silent=True) or {}
    allow_slot = body.get("allow_slot")
    try:
        allow_slot = int(allow_slot) if allow_slot not in (None, "", "null") else None
    except (TypeError, ValueError):
        allow_slot = None

    # v9: tingnan at itakda nang sabay — dalawang pindot ay isang enroll lang.
    with enroll_gate:
        if is_enrolling:
            return jsonify({"success": False, "message": "Enrollment already in progress"})
        is_enrolling  = True
        enroll_status = {"status": "idle", "fingerprint_id": None, "message": ""}

    t = threading.Thread(target=do_enrollment, args=(allow_slot,), daemon=True)
    t.start()

    return jsonify({"success": True, "message": "Enrollment started"})

@app.route('/enroll/status')
def enroll_status_route():
    return jsonify(enroll_status)

# ── SITE SWITCHER ─────────────────────────────────────────────
# Dito kinukuha ng kiosk UI ang mga site. Galing sa Laravel — magdagdag
# ka doon, lalabas dito.

@app.route('/sites')
def sites():
    sites  = load_sites(force=request.args.get('refresh') == '1')
    active = read_active_site()
    return jsonify({
        "success":    True,
        "sites":      sites,
        "active":     active,
        "count":      len(sites),
        # Ang browser ay hindi na kailangang mag-hardcode ng device code.
        "kiosk_code": KIOSK_CODE,
    })


@app.route('/set-site', methods=['POST'])
@app.route('/set-active-site', methods=['POST'])   # alias — dating tinatawag ng kiosk
def set_site():
    """
    Piliin kung saang site nakatayo ang kiosk. Tinatanggap ang slug
    ("site-b") o ang id (2) — kung ano ang mas madali sa UI.

    Pagkatapos nito, ang kiosk mismo ang nagsasabi sa web
    (/api/kiosk/active-site) para malaman agad ng mapa.
    """
    body = request.get_json(silent=True) or {}
    want = (body.get('site') or body.get('site_id')
            or body.get('slug') or body.get('active_site'))

    if want is None:
        return jsonify({"success": False, "message": "Walang site na ipinasa"}), 400

    want   = str(want)
    chosen = None
    for s in load_sites():
        if want == s['slug'] or want == str(s['id']) or want.lower() == s['name'].lower():
            chosen = s
            break

    if not chosen:
        return jsonify({
            "success": False,
            "message": f"Hindi kilala ang site '{want}'. Idagdag muna ito sa web.",
        }), 404

    try:
        saved = write_active_site({
            "slug": chosen['slug'],
            "id":   chosen['id'],
            "name": chosen['name'],
        })
    except OSError as e:
        # Hindi nakasulat = hindi malalaman ng GPS tracker ang pinili.
        return jsonify({"success": False, "message": f"Hindi masulat ang active site: {e}"}), 500

    print(f"📍 Lumipat ang kiosk sa {chosen['name']}")
    return jsonify({"success": True, "active": saved})


@app.route('/active-site')
def active_site():
    return jsonify({
        "success":    True,
        "active":     read_active_site(),
        "kiosk_code": KIOSK_CODE,
    })


# ── GPS STATUS (para sa chip sa kiosk screen) ────────────────
# Ang gps_tracker.py ay hiwalay na programa. Isinusulat nito ang lagay niya
# sa file na ito kada ~2 segundo; dito lang binabasa. Kapag luma na ang file,
# ibig sabihin hindi tumatakbo ang tracker.
GPS_STATUS_FILE  = "/dev/shm/jeyanco_gps_status.json"   # DAPAT kapareho ng STATUS_FILE sa gps_tracker.py
GPS_STATUS_STALE = 40    # segundo: ang tracker ay puwedeng abala nang ~25s sa mabagal na net
GPS_SENT_STALE   = 100   # segundo: tatlong heartbeat na ang lumipas nang walang tinanggap ang web


@app.route('/gps-status')
def gps_status():
    """
    state:  "off"       hindi tumatakbo ang gps_tracker.py
            "no_module" tumatakbo, pero walang data mula sa NEO-M8L
            "no_fix"    buhay ang module, wala pang satellite lock
            "fix"       may posisyon
    tracked: True kapag tinanggap ng web ang isang heartbeat kamakailan.
    """
    try:
        with open(GPS_STATUS_FILE, "r") as f:
            st = json.load(f)
        age = time.time() - float(st.get("updated_at") or 0)
    except (OSError, ValueError, TypeError):
        return jsonify({"success": True, "state": "off", "tracked": False})

    if age > GPS_STATUS_STALE:
        return jsonify({"success": True, "state": "off", "tracked": False, "age": round(age)})

    state = "no_module" if not st.get("module") else ("fix" if st.get("fix") else "no_fix")
    sent_at  = st.get("sent_at")
    sent_age = round(time.time() - sent_at) if sent_at else None
    tracked  = bool(st.get("sent_ok")) and sent_age is not None and sent_age <= GPS_SENT_STALE

    return jsonify({
        "success":    True,
        "state":      state,
        "tracked":    tracked,
        "sent_age":   sent_age,
        "sent_code":  st.get("sent_code"),
        "in_view":    st.get("in_view"),
        "used":       st.get("used"),
        "site":       st.get("site"),
        "distance_m": st.get("distance_m"),
        "age":        round(age),
    })


# ── ATTENDANCE + MONITOR PROXIES ─────────────────────────────
# Dinaraan sa Flask para iisa lang ang nakakaalam ng piniling site:
# ang file na isinulat ng /set-site. Ganito rin kinukuha ng gps_tracker.py
# ang site, kaya imposibleng hindi sila magkasundo.

@app.route('/attendance', methods=['POST'])
def attendance_proxy():
    body = request.get_json(silent=True) or {}

    payload = dict(body)
    payload.update(site_params())          # kiosk_code + site_id

    try:
        res = http.post(f"{LARAVEL_API}/attendance", json=payload, timeout=10)
        return jsonify(res.json()), res.status_code
    except Exception as e:
        return jsonify({"success": False, "message": f"Hindi maabot ang server: {e}"}), 502


@app.route('/today-attendance')
def today_attendance_proxy():
    """
    Ang board ng "sino ang nasa site ngayon". Kailangan din nito ng site —
    kung wala, lahat ng nag-clock kahit saan ang lalabas sa kiosk mo.
    """
    try:
        res = http.get(f"{LARAVEL_API}/today-attendance",
                           params=site_params(), timeout=10)
        return jsonify(res.json()), res.status_code
    except Exception as e:
        return jsonify({"success": False, "message": str(e), "records": []}), 502


# ── ROSTER + ENROLLMENT ──────────────────────────────────────
# Ang admin ang naglalagay ng detalye sa web. Ang kiosk ay listahan
# lang ang ipinapakita at daliri ang kinukuha — walang tinatype dito.

@app.route('/roster')
def roster_proxy():
    """Sino ang nasa site na ito, at sino ang wala pang daliri."""
    try:
        res = http.get(f"{LARAVEL_API}/roster", params=site_params(), timeout=10)
        return jsonify(res.json()), res.status_code
    except Exception as e:
        return jsonify({"success": False, "message": str(e), "employees": []}), 502


@app.route('/save-fingerprint', methods=['POST'])
def save_fingerprint_proxy():
    """Ikabit ang bagong-enroll na daliri sa isang taong nasa web na."""
    global awaiting_attach

    body = request.get_json(silent=True) or {}

    payload = dict(body)
    payload.update(site_params())

    try:
        res  = http.post(f"{LARAVEL_API}/save-fingerprint", json=payload, timeout=10)
        data = res.json()

        # May may-ari na ang slot — puwede nang basahin nang normal.
        if data.get("success"):
            awaiting_attach = None

        return jsonify(data), res.status_code
    except Exception as e:
        return jsonify({"success": False, "message": f"Hindi maabot ang server: {e}"}), 502


# ── v13: MY DTR + REPORT (reklamo) ───────────────────────────
# Binubuksan gamit ang scan_token ng daliring kababasa lang. Dumadaan dito
# para maidagdag ang device key — ang browser ay walang hawak na sikreto.

@app.route('/my-dtr')
def my_dtr_proxy():
    params = {"scan_token": request.args.get("scan_token", ""),
              "week": request.args.get("week", "0")}
    params.update(site_params())
    try:
        res = http.get(f"{LARAVEL_API}/my-dtr", params=params, timeout=20)
        return jsonify(res.json()), res.status_code
    except Exception as e:
        return jsonify({"success": False, "message": f"Hindi maabot ang server: {e}"}), 502


@app.route('/complaints', methods=['GET', 'POST'])
def complaints_proxy():
    try:
        if request.method == 'GET':
            params = {"scan_token": request.args.get("scan_token", "")}
            params.update(site_params())
            res = http.get(f"{LARAVEL_API}/complaints", params=params, timeout=15)
        else:
            payload = dict(request.get_json(silent=True) or {})
            payload.update(site_params())
            res = http.post(f"{LARAVEL_API}/complaints", json=payload, timeout=15)
        return jsonify(res.json()), res.status_code
    except Exception as e:
        return jsonify({"success": False, "message": f"Hindi maabot ang server: {e}"}), 502


# ── REGISTERED EMPLOYEES LIST (para sa view sa kiosk) ─────────
@app.route('/registered-employees')
def registered_employees():
    try:
        res = http.get(f"{LARAVEL_API}/active-fingerprints", timeout=10)
        return jsonify(res.json())
    except Exception as e:
        return jsonify({"success": False, "message": str(e), "employees": []}), 502


# ── REGISTER EMPLOYEE (proxy, may site) ──────────────────────
@app.route('/register-employee', methods=['POST'])
def register_employee():
    body = request.get_json(silent=True) or {}

    payload = dict(body)
    payload.update(site_params())

    try:
        res = http.post(f"{LARAVEL_API}/register-employee", json=payload, timeout=10)
        return jsonify(res.json()), res.status_code
    except Exception as e:
        return jsonify({"success": False, "message": str(e)}), 502


# ══════════════════════════════════════════════════════════════
#  MAIN
# ══════════════════════════════════════════════════════════════

if __name__ == '__main__':
    print("🚀 Starting Jeyanco Fingerprint Service (kiosk v13)...")
    print(f"   Serial: {SERIAL_PORT} @ {BAUD_RATE} baud")
    print(f"   Laravel: {LARAVEL_API}")
    print(f"   Kiosk device: {KIOSK_CODE}")
    print(f"   Device key: {'naka-set' if DEVICE_KEY else 'WALA — itakda ang JEYANCO_DEVICE_KEY'}")
    print(f"   Kiosk screen: {LARAVEL_BASE}/kiosk  (allowed origins: {', '.join(ALLOWED_ORIGINS)})")
    print("   Flask: http://127.0.0.1:5000")
    print("")

    # Kunin agad ang sites para handa na ang switcher pagbukas ng kiosk.
    load_sites(force=True)
    active = read_active_site()
    if active["name"]:
        print(f"📍 Kasalukuyang site: {active['name']}")
    else:
        print("📍 Walang piniling site pa — pumili sa kiosk header.")
    print("")

    # Start attendance scan loop sa background
    scan_thread = threading.Thread(target=attendance_scan_loop, daemon=True)
    scan_thread.start()

    # Start sensor sync loop (binubura ang orphan na na-delete sa web)
    sync_thread = threading.Thread(target=sensor_sync_loop, daemon=True)
    sync_thread.start()

    print("✅ Fingerprint service running!")
    print(f"   Sync: kada {SYNC_INTERVAL}s (delete sa web → mabura sa sensor)")
    app.run(host='127.0.0.1', port=5000, debug=False)
