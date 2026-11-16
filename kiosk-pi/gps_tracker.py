#!/usr/bin/env python3
"""
=======================================================================
 JEYANCO KIOSK - GPS TRACKER (NEO-M8L)  v5.2
=======================================================================

 Binabasa ang live GPS ng NEO-M8L, tinitignan kung nasa loob ng aling
 site, at ipinapadala sa Laravel kada 30 segundo.

 ANG MGA SITE AY GALING NA SA WEB - wala nang hardcoded na coordinates
 dito. Kung saan mo inilagay ang pin sa dashboard map, yun ang susundin.

 MAHALAGA: nagpapadala ito ng heartbeat KAHIT walang GPS fix. Ganoon
 lang malalaman ng system ang pagkakaiba ng "nawawala ang kiosk" at
 "nandiyan lang, walang signal ang satellite."

 v2 - bakit mas mabilis lumabas sa dashboard:
   * Tuloy-tuloy na binabasa ang GPS. Dati, 12 segundo lang ang basa
     bawat 30 segundo; ang natitirang 18 ay tulog - kaya ang fix na
     dumating habang tulog ay naghintay pa ng isang buong cycle.
   * Ang UNANG fix (galing sa walang fix) ay ipinapadala AGAD, hindi na
     hinihintay ang susunod na 30 segundo.
   * Walang lumang data: hindi na naiipon sa serial buffer ang mga
     lumang linya habang tulog.
   * Sinasabi ng log kung ilang satellite ang nakikita at ginagamit, para
     malaman agad kung antenna/puwesto ang problema (0 satellite) o
     naghihintay lang ng lock (may satellite pero wala pang fix).

 v3 - heartbeat KAHIT WALANG DATA ang GPS module:
   * Dati, kapag walang NMEA talaga (maluwag na wire, maling port, patay na
     module), paikot-ikot lang ang tracker sa paghahanap ng baud at WALANG
     naipapadala - kaya "offline" ang kiosk sa web at na-a-alert ang admins,
     kahit bukas at gumagana pa ito. Ngayon, isang baud lang ang sinusubukan
     kada ikot, at tuloy ang heartbeat (no_fix) habang hinahanap ang module.
   * Kapag tumahimik ang module habang tumatakbo (nabunot ang wire),
     napapansin ito sa loob ng 15 segundo at binubuksan ulit ang port.

 v4 - nakikita na sa kiosk screen ang lagay ng GPS:
   * Isinusulat ng tracker ang lagay nito sa STATUS_FILE kada ~2 segundo
     (may data ba ang module, may fix ba, ilang satellite, tinanggap ba ng
     web ang huling heartbeat). Binabasa ito ng Flask (/gps-status) at
     ipinapakita ng kiosk bilang maliit na chip sa ATTENDANCE tab.
   * Nasa /dev/shm ang file (RAM, hindi SD card), kaya walang pagod sa card
     at nababasa ng Flask kahit ibang user ang nagpapatakbo.

 v5 - mabilis na fix pagbukas (hot start sa halip na cold start):
   * Bago patayin (sudo poweroff / restart ng service), pinapatigil ang
     GNSS at sine-save ng module ang satellite data (ephemeris, almanac)
     sa sarili nitong flash - UBX-UPD-SOS. Sine-save din ang huling
     position sa LAST_FIX_FILE.
   * Pagbukas, kusang ibinabalik ng module ang na-save na data. Ibinibigay
     din dito ang kasalukuyang oras (mula sa internet time ng Pi, kung
     naka-sync) at ang huling position - UBX-MGA-INI. Kaya ilang segundo
     na lang ang unang fix imbes na 30s hanggang ilang minuto.
   * Sinasabi ng log kung ilang segundo bago ang unang fix.
   * KAILANGAN: maayos na shutdown. Kapag hinugot lang ang saksakan, walang
     nase-save - cold start pa rin sa susunod (pero gumagana pa rin).

 Setup:  sudo apt install python3-serial python3-requests
         pip3 install pynmea2
=======================================================================
"""

import json
import os
import signal
import struct
import sys
import time
from datetime import datetime, timezone
from math import atan2, cos, radians, sin, sqrt

import pynmea2
import requests
import serial

# --- CONFIG -----------------------------------------------------------
# HUWAG gawing /dev/serial0. Yun ay ttyAMA0, at hawak na yun ng R307
# fingerprint sensor sa Flask (app.py) @ 57600. Isang programa lang ang
# pwedeng humawak ng isang UART.
GPS_PORT      = '/dev/ttyAMA3'
BAUD_TRY      = [9600, 57600, 38400, 115200]   # susubukan sunod-sunod
# v5.2 (maraming kiosk): ang code ng kiosk na ito, mula sa kiosk.env
# (JEYANCO_KIOSK_CODE, kapareho ng code sa System Settings -> Kiosks). Dati
# 'jeyanco-01' para sa lahat, kaya ang GPS ng bawat Pi ay napupunta sa UNANG
# kiosk sa web. Ngayon, sa sariling kiosk na ito napupunta.
KIOSK_ID      = (os.environ.get('JEYANCO_KIOSK_CODE') or '').strip() or 'jeyanco-01'

BASE_URL      = ((os.environ.get('JEYANCO_WEB') or '').strip().rstrip('/')
                 or 'https://jeyancowebsite-production.up.railway.app')
LOCATION_URL  = BASE_URL + '/api/location'
SITES_URL     = BASE_URL + '/api/kiosk/sites'

SEND_INTERVAL = 30       # segundo bawat heartbeat (offline_after ng server = 120s)
FIX_FRESH     = 5        # ang fix ay "kasalukuyan" kung nabasa sa loob ng ilang segundo
GPS_SILENT    = 15       # segundo na walang NMEA bago ituring na nawala ang module
SITES_REFRESH = 300      # segundo bago kunin ulit ang listahan ng sites
DEBUG         = False    # True = ipakita ang bawat GGA/RMC na linya (maingay)

# Ang lagay ng tracker, para sa kiosk screen (binabasa ng Flask /gps-status).
# DAPAT kapareho ng GPS_STATUS_FILE sa testing.py.
STATUS_FILE         = '/dev/shm/jeyanco_gps_status.json'
STATUS_EVERY        = 2        # segundo sa pagitan ng mga sulat

# v5: ang huling position, para maibigay sa GPS pagbukas (SD card, kaya
# isinusulat lang sa unang fix, kada 10 minuto, at bago mag-shutdown).
LAST_FIX_FILE       = '/home/admin/jeyanco/gps_last_fix.json'
LAST_FIX_EVERY      = 600
# Naka-sync ba ang orasan ng Pi sa internet (systemd-timesyncd). Kapag hindi,
# HINDI ibinibigay ang oras sa GPS - mas masama ang maling oras kaysa wala.
TIME_SYNCED_FILE    = '/run/systemd/timesync/synchronized'

ACTIVE_SITE_FILE    = '/home/admin/active_site.json'
DEFAULT_ACTIVE_SITE = None      # None = hayaang ang GPS ang magsabi

NMEA_PREFIXES = ('$GNGGA', '$GPGGA', '$GNRMC', '$GPRMC')
GSV_PREFIXES  = ('$GPGSV', '$GLGSV', '$GAGSV', '$GBGSV', '$GNGSV')


def log(*args):
    """Print na agad lumalabas sa journalctl (walang buffering)."""
    print(*args)
    sys.stdout.flush()


# --- SITES (galing sa web) --------------------------------------------
_sites = {}
_sites_at = 0.0


def load_sites(force=False):
    """
    Kunin ang sites mula sa web. Nilalaktawan ang walang coordinates.
    Kapag pumalya ang network, ginagamit ang huling nakuha.
    """
    global _sites, _sites_at

    if _sites and not force and (time.time() - _sites_at) < SITES_REFRESH:
        return _sites

    try:
        r = requests.get(SITES_URL, timeout=10)
        r.raise_for_status()
        payload = r.json()

        found = {}
        skipped = []
        for s in payload.get('sites', []):
            if s.get('latitude') is None or s.get('longitude') is None:
                skipped.append(s.get('name', '?'))
                continue
            found[s['slug']] = {
                'name':   s['name'],
                'lat':    float(s['latitude']),
                'lng':    float(s['longitude']),
                'radius': int(s.get('radius') or 150),
            }

        if found:
            _sites, _sites_at = found, time.time()
            log('Sites mula sa web:', ', '.join(v['name'] for v in found.values()))
            if skipped:
                log('  (walang pin sa map, nilaktawan: ' + ', '.join(skipped) + ')')
        else:
            log('[!] Walang site na may coordinates. Maglagay ng pin sa Sites page.')
    except (requests.RequestException, ValueError, KeyError, TypeError) as e:
        log('[!] Hindi makuha ang sites (%s) - gagamitin ang huling listahan.' % e)

    return _sites


# --- GEOFENCING -------------------------------------------------------
def haversine(lat1, lng1, lat2, lng2):
    """Distansya sa METERS ng dalawang GPS point."""
    R = 6371000
    dlat = radians(lat2 - lat1)
    dlng = radians(lng2 - lng1)
    a = sin(dlat / 2) ** 2 + cos(radians(lat1)) * cos(radians(lat2)) * sin(dlng / 2) ** 2
    return R * 2 * atan2(sqrt(a), sqrt(1 - a))


def detect_site(lat, lng):
    """
    Aling site ang pinakamalapit AT nasa loob ng radius nito.
    (slug, distansya) kung may tugma; (None, pinakamalapit) kung wala;
    (None, None) kung walang site na may pin.
    """
    sites = load_sites()
    if not sites:
        return None, None

    best_id, best_dist = None, float('inf')
    nearest_dist = float('inf')

    for site_id, s in sites.items():
        dist = haversine(lat, lng, s['lat'], s['lng'])
        nearest_dist = min(nearest_dist, dist)
        if dist <= s['radius'] and dist < best_dist:
            best_id, best_dist = site_id, dist

    if best_id:
        return best_id, best_dist
    return None, (nearest_dist if nearest_dist != float('inf') else None)


def read_active_site():
    """Anong site ang naka-select sa kiosk (sinusulat ng Flask)."""
    try:
        with open(ACTIVE_SITE_FILE, 'r') as f:
            site = json.load(f).get('active_site')
            if site and site in load_sites():
                return site
    except (FileNotFoundError, json.JSONDecodeError, KeyError, OSError, TypeError):
        pass
    return DEFAULT_ACTIVE_SITE


# --- GPS SERIAL (auto-detect baud) ------------------------------------
def sniff_nmea(ser, seconds=5):
    """True kung may tunay na NMEA sa loob ng ilang segundo - tamang baud."""
    deadline = time.time() + seconds
    while time.time() < deadline:
        try:
            line = ser.readline().decode('ascii', errors='replace').strip()
        except Exception:
            continue
        if line.startswith(NMEA_PREFIXES):
            return True
    return False


_baud_i = 0


def try_open_gps():
    """
    Subukan ang ISANG baud (mga 5 segundo) at bumalik. Ang serial port kung
    may NMEA, None kung wala. Hindi na ito umiikot nang walang katapusan:
    ang main loop ang tatawag ulit, at sa pagitan ay tuloy ang heartbeat.
    """
    global _baud_i
    baud = BAUD_TRY[_baud_i % len(BAUD_TRY)]

    log('Sinusubukan ang %s @ %s baud...' % (GPS_PORT, baud))
    try:
        ser = serial.Serial(GPS_PORT, baud, timeout=1)
    except serial.SerialException as e:
        log('  Hindi mabuksan: %s' % e)
        _baud_i += 1
        time.sleep(2)
        return None

    # v5.1: simulan muna ang GNSS. Ang module na pinatigil ng huling
    # shutdown (pero hindi namatayan ng kuryente, hal. reboot) ay WALANG
    # inilalabas na NMEA, kaya hindi ito makikita ng sniff kung hindi muna
    # gigisingin. Walang epekto kung tumatakbo na o mali ang baud.
    ubx_send(ser, ubx(0x06, 0x04, struct.pack('<HBB', 0x0000, 0x09, 0)))
    time.sleep(0.3)

    if sniff_nmea(ser):
        log('[OK] Gumana sa %s baud - may NMEA data.' % baud)
        try:
            ser.reset_input_buffer()          # simulan sa sariwang data
        except Exception:
            pass
        STATE['heard_at'] = time.time()
        warm_up(ser)                          # v5: oras + huling position
        return ser                            # _baud_i: dito ulit magsisimula sa susunod

    log('  Walang NMEA sa %s baud (check wiring at antenna).' % baud)
    try:
        ser.close()
    except Exception:
        pass
    _baud_i += 1
    return None


# --- v5: HOT START (UBX) ----------------------------------------------
def ubx(cls, mid, payload=b''):
    """Isang UBX message: B5 62, class, id, haba, laman, checksum."""
    body = struct.pack('<BBH', cls, mid, len(payload)) + payload
    a = b = 0
    for byte in body:
        a = (a + byte) & 0xFF
        b = (b + a) & 0xFF
    return b'\xb5\x62' + body + bytes((a, b))


def ubx_send(ser, msg):
    try:
        ser.write(msg)
        ser.flush()
        return True
    except Exception as e:
        log('  [!] Hindi maipadala sa GPS: %s' % e)
        return False


def wait_sos_reply(ser, seconds=4):
    """Hintayin ang UBX-UPD-SOS na sagot (09 14). Ang response byte, o None."""
    buf = b''
    deadline = time.time() + seconds
    while time.time() < deadline:
        try:
            buf += ser.read(256)
        except Exception:
            return None
        i = buf.find(b'\xb5\x62\x09\x14')
        if i >= 0 and len(buf) >= i + 11:
            return buf[i + 10]                     # payload[4] = response
    return None


def save_last_fix(force=False):
    """Isulat ang huling fix sa SD card (hindi madalas)."""
    fix = STATE['fix']
    if not fix:
        return
    if not force and (time.time() - STATE.get('saved_at', 0.0)) < LAST_FIX_EVERY:
        return
    try:
        tmp = LAST_FIX_FILE + '.tmp'
        with open(tmp, 'w') as f:
            json.dump({'lat': fix['lat'], 'lng': fix['lng'], 'at': STATE['fix_at']}, f)
        os.replace(tmp, LAST_FIX_FILE)
        STATE['saved_at'] = time.time()
    except Exception:
        pass


def warm_up(ser):
    """
    Tawagin pagbukas ng port. Simulan ang GNSS (baka pinatigil ng huling
    shutdown), tapos ibigay ang oras at ang huling position.
    """
    # UBX-CFG-RST: controlled GNSS start (0x09). Walang epekto kung tumatakbo na.
    ubx_send(ser, ubx(0x06, 0x04, struct.pack('<HBB', 0x0000, 0x09, 0)))

    given = []
    if os.path.exists(TIME_SYNCED_FILE):
        now = datetime.now(timezone.utc)
        # UBX-MGA-INI-TIME_UTC: oras ng Pi, +/- 2 segundo.
        payload = struct.pack('<BBBbHBBBBBBIHHI', 0x10, 0x00, 0x00, -128,
                              now.year, now.month, now.day, now.hour, now.minute, now.second,
                              0, now.microsecond * 1000, 2, 0, 0)
        if ubx_send(ser, ubx(0x13, 0x40, payload)):
            given.append('oras')
    try:
        with open(LAST_FIX_FILE) as f:
            last = json.load(f)
        # UBX-MGA-INI-POS_LLH: huling position, +/- 50 km (baka lumipat ng site).
        payload = struct.pack('<BBHiiiI', 0x01, 0x00, 0,
                              int(round(last['lat'] * 1e7)), int(round(last['lng'] * 1e7)), 0, 5000000)
        if ubx_send(ser, ubx(0x13, 0x40, payload)):
            given.append('huling position')
    except (FileNotFoundError, ValueError, KeyError, TypeError, OSError):
        pass
    log('Hot start: ibinigay sa GPS ang %s.' % (', '.join(given) if given else 'wala (walang oras/position na alam)'))


def backup_on_shutdown():
    """
    Bago mamatay ang service: patigilin ang GNSS at i-save ng module ang
    satellite data sa flash nito, para hot start sa susunod na bukas.
    """
    save_last_fix(force=True)
    if gps is None:
        log('Shutdown: walang bukas na GPS port - walang na-save sa module.')
        return
    try:
        gps.reset_input_buffer()
    except Exception:
        pass
    # UBX-CFG-RST: controlled GNSS stop (0x08), tapos UBX-UPD-SOS: create backup.
    ubx_send(gps, ubx(0x06, 0x04, struct.pack('<HBB', 0x0000, 0x08, 0)))
    time.sleep(0.5)
    # Burahin muna ang lumang backup (kung naiwan ng restart na hindi
    # pinatay ang module), tapos gumawa ng bago.
    ubx_send(gps, ubx(0x09, 0x14, bytes((0x01, 0, 0, 0))))
    time.sleep(1.0)
    try:
        gps.reset_input_buffer()
    except Exception:
        pass
    ubx_send(gps, ubx(0x09, 0x14, bytes((0x00, 0, 0, 0))))
    reply = wait_sos_reply(gps)
    if reply == 1:
        log('Shutdown: [OK] backup saved - na-save ang satellite data sa GPS module.')
    elif reply == 0:
        log('Shutdown: [!] tinanggihan ng GPS module ang backup.')
    else:
        log('Shutdown: walang sagot ang GPS module sa backup (baka luma ang firmware).')


def drop_gps(why):
    """Isara ang port at kalimutan ang lumang bilang ng satellite."""
    global gps
    log(why)
    try:
        if gps is not None:
            gps.close()
    except Exception:
        pass
    gps = None
    STATE['in_view'] = {}
    STATE['used'] = None


# Walang port sa simula: ang main loop ang magbubukas, isang baud kada ikot.
gps = None

# Ang huling nabasa sa GPS. Tuloy-tuloy itong ina-update ng pump().
STATE = {
    'fix':      None,   # {'lat','lng'} - huling VALID na fix
    'fix_at':   0.0,    # kailan ito nabasa
    'used':     None,   # satellite na ginagamit sa fix (GGA)
    'in_view':  {},     # satellite na nakikita, bawat sistema (GSV)
    'heard_at': 0.0,    # kailan huling may NMEA mula sa module
    'saved_at': 0.0,    # kailan huling naisulat ang LAST_FIX_FILE
}
STARTED_AT = time.time()


# Ang huling heartbeat: kailan, at kung tinanggap ng web.
LAST_SEND = {'at': 0.0, 'ok': None, 'code': None, 'site': None, 'distance': None}
_status_at = 0.0


def write_status(force=False):
    """
    Isulat ang lagay ng tracker para sa kiosk screen. Hindi ito dapat
    makapigil sa tracker kahit kailan: anumang error ay nilulunok.
    """
    global _status_at
    now = time.time()
    if not force and (now - _status_at) < STATUS_EVERY:
        return
    _status_at = now

    fix = STATE['fix'] if (STATE['fix'] and (now - STATE['fix_at']) <= max(FIX_FRESH, 15)) else None
    payload = {
        'updated_at': now,
        'module':     gps is not None,                 # may NMEA mula sa NEO-M8L
        'fix':        fix is not None,
        'lat':        fix['lat'] if fix else None,
        'lng':        fix['lng'] if fix else None,
        'in_view':    sum(STATE['in_view'].values()),
        'used':       STATE['used'],
        'sent_at':    LAST_SEND['at'] or None,         # huling heartbeat sa web
        'sent_ok':    LAST_SEND['ok'],
        'sent_code':  LAST_SEND['code'],
        'site':       LAST_SEND['site'],
        'distance_m': LAST_SEND['distance'],
        'kiosk_id':   KIOSK_ID,
    }
    try:
        tmp = STATUS_FILE + '.tmp'
        with open(tmp, 'w') as f:
            json.dump(payload, f)
        os.replace(tmp, STATUS_FILE)
        os.chmod(STATUS_FILE, 0o644)
    except Exception:
        pass


def parse_fix(msg):
    """
    lat/lng ng isang GGA/RMC - pero VALID na fix lang. Ang GGA na may
    gps_qual=0 at ang RMC na may status='V' ay walang satellite lock.
    """
    if hasattr(msg, 'gps_qual') and int(msg.gps_qual or 0) == 0:
        return None
    if hasattr(msg, 'status') and msg.status == 'V':
        return None

    lat = getattr(msg, 'latitude', None)
    lng = getattr(msg, 'longitude', None)
    if lat is None or lng is None:
        return None
    if lat == 0 and lng == 0:
        return None

    return {'lat': round(lat, 6), 'lng': round(lng, 6)}


def pump(seconds):
    """
    Basahin ang GPS nang `seconds` segundo at i-update ang STATE.
    True kapag may bagong VALID na fix na nabasa.
    """
    global gps

    # Walang bukas na port: subukan ang isang baud, tapos bumalik para
    # makapagpadala pa rin ng heartbeat ang main loop.
    if gps is None:
        write_status(force=True)              # sabihin sa kiosk habang naghahanap
        gps = try_open_gps()
        if gps is None:
            return False

    got_fix = False
    deadline = time.time() + seconds
    while time.time() < deadline:
        try:
            line = gps.readline().decode('ascii', errors='replace').strip()
        except serial.SerialException:
            drop_gps('Serial nadiskonekta, bubuksan ulit...')
            return got_fix
        except Exception:
            continue

        if not line.startswith('$'):
            continue
        STATE['heard_at'] = time.time()

        try:
            msg = pynmea2.parse(line)
        except pynmea2.ParseError:
            continue

        # Ilang satellite ang nakikita, bawat sistema (GPS, GLONASS, ...).
        if line.startswith(GSV_PREFIXES):
            try:
                STATE['in_view'][line[1:3]] = int(msg.num_sv_in_view or 0)
            except (AttributeError, ValueError, TypeError):
                pass
            continue

        if not line.startswith(NMEA_PREFIXES):
            continue
        if DEBUG:
            log('NMEA:', line)

        if hasattr(msg, 'num_sats'):
            try:
                STATE['used'] = int(msg.num_sats or 0)
            except (ValueError, TypeError):
                pass

        fix = parse_fix(msg)
        if fix:
            STATE['fix'], STATE['fix_at'] = fix, time.time()
            got_fix = True

    # Bukas ang port pero tahimik: nabunot ang wire o namatay ang module.
    if (time.time() - STATE['heard_at']) > GPS_SILENT:
        drop_gps('Walang data mula sa GPS module sa loob ng %ss - bubuksan ulit ang port.' % GPS_SILENT)

    return got_fix


def current_fix():
    """Ang fix kung sariwa pa (nabasa sa loob ng FIX_FRESH segundo), kung hindi None."""
    if STATE['fix'] and (time.time() - STATE['fix_at']) <= FIX_FRESH:
        return STATE['fix']
    return None


def sats_text():
    in_view = sum(STATE['in_view'].values())
    used = STATE['used']
    return 'satellite: %s nakikita, %s ginagamit' % (in_view, used if used is not None else '?')


# --- SEND SA LARAVEL --------------------------------------------------
def send(coords, detected, distance, active):
    """
    Ipadala ang heartbeat. Tumatakbo ito KAHIT walang fix (coords=None).
    """
    sites = load_sites()

    payload = {
        'kiosk_id': KIOSK_ID,
        'lat':      coords['lat'] if coords else None,
        'lng':      coords['lng'] if coords else None,
        'status':   'fix' if coords else 'no_fix',
    }

    if coords:
        payload['detected_site'] = detected
        payload['detected_name'] = sites[detected]['name'] if detected else 'Out of range'
        payload['active_site']   = active
        payload['active_name']   = sites.get(active, {}).get('name') if active else None
        payload['in_geofence']   = detected is not None
        payload['distance_m']    = round(distance, 1) if distance is not None else None
        payload['site_match']    = (detected == active) if (active and detected) else None

    payload = dict((k, v) for k, v in payload.items()
                   if v is not None or k in ('lat', 'lng'))

    try:
        r = requests.post(LOCATION_URL, json=payload, timeout=10)

        if coords:
            tag = sites[detected]['name'] if detected else 'OUT-OF-RANGE'
            extra = ' %.0fm' % distance if distance is not None else ''
            if payload.get('site_match') is False:
                extra += '  [!] MISMATCH (kiosk naka-set sa %s)' % payload.get('active_name')
            log('Sent [%s%s]: %s, %s  (%s)  ->  %s'
                % (tag, extra, coords['lat'], coords['lng'], sats_text(), r.status_code))
        else:
            log('Sent [NO-FIX heartbeat] (%s)  ->  %s' % (sats_text(), r.status_code))

        if r.status_code != 200:
            log('  Response body:', r.text[:300])

        LAST_SEND.update(at=time.time(), ok=(r.status_code == 200), code=r.status_code,
                         site=(sites[detected]['name'] if (coords and detected) else None),
                         distance=(round(distance, 1) if (coords and distance is not None) else None))
    except requests.RequestException as e:
        log('Send failed:', e)
        LAST_SEND.update(at=time.time(), ok=False, code=None)

    write_status(force=True)


def report_and_send():
    coords = current_fix()
    if coords:
        save_last_fix()
        detected, distance = detect_site(coords['lat'], coords['lng'])
        send(coords, detected, distance, read_active_site())
    else:
        in_view = sum(STATE['in_view'].values())
        if gps is None:
            log('WALANG DATA mula sa GPS module (check ang wire, ang port %s, at ang '
                'power ng module). Tuloy pa rin ang heartbeat.' % GPS_PORT)
        elif in_view == 0:
            log('Walang fix at WALANG satellite na nakikita - check ang antenna '
                '(nakakabit ba, nakaharap sa langit, walang bubong/bakal sa ibabaw).')
        else:
            log('Walang fix pa - %s. Naghihintay ng lock (unang lock pagkagaling '
                'sa loob ng bahay: 30s hanggang ilang minuto).' % sats_text())
        send(None, None, None, None)
    return coords is not None


# --- MAIN LOOP --------------------------------------------------------
if __name__ == '__main__':
    log('GPS tracker v5.2 started - kiosk %s, web %s.' % (KIOSK_ID, BASE_URL))
    log('GPS tracker v5.2: - hot start, tuloy-tuloy ang basa, agad ang unang fix, heartbeat kahit walang GPS data.')

    # systemd (poweroff, reboot, restart ng service) ay nagpapadala ng
    # SIGTERM: lumabas nang maayos para ma-save ang satellite data.
    def _stop(signum, frame):
        raise SystemExit(0)
    signal.signal(signal.SIGTERM, _stop)

    load_sites(force=True)

    had_fix = False
    first_logged = False
    last_sent = 0.0

    try:
        while True:
            # Basahin ang GPS nang isang segundo; hindi na natutulog nang walang basa.
            # (Kapag walang bukas na port, isang baud ang sinusubukan dito, ~5s.)
            new_fix = pump(1.0)

            due = (time.time() - last_sent) >= SEND_INTERVAL
            first_fix = new_fix and not had_fix     # galing sa walang fix: ipadala AGAD

            if new_fix and not first_logged:
                first_logged = True
                log('[OK] Unang fix sa loob ng %ds mula nang magsimula (%s).'
                    % (time.time() - STARTED_AT, sats_text()))
                save_last_fix(force=True)

            if due or first_fix:
                if first_fix and not due:
                    log('[OK] Unang fix - ipinapadala agad.')
                had_fix = report_and_send()
                last_sent = time.time()

            write_status()
    finally:
        backup_on_shutdown()
