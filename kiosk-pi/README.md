# Jeyanco kiosk v13: online deployment (Railway + Raspberry Pi + R307)

Ang kiosk screen ay nasa web na (`https://<app>.up.railway.app/kiosk`).
Ang Pi ay sensor at tulay na lang:

```
R307 ──UART──► testing.py (Flask, 127.0.0.1:5000) ──HTTPS + X-Kiosk-Key──► Railway (Laravel)
                     ▲
Chromium (full screen) ── binubuksan ang https://<app>/kiosk, tumatawag sa 127.0.0.1:5000 para sa sensor
```

Bakit gumagana pa rin ang R307 kahit online ang page: ang page ay tumatakbo
sa Chromium **sa mismong Pi**, kaya naaabot pa rin nito ang `127.0.0.1:5000`.
Pinapayagan ng Chromium ang https page na tumawag sa 127.0.0.1. Ang
`chromium-policy.json` ang nag-a-approve nito para walang permission prompt sa
kiosk.

## A. Railway (gawin muna ito)

1. I-push ang updated na project. Hintaying matapos ang build.
2. Patakbuhin ang migration (Railway → service → Shell, o idagdag sa start command):
   `php artisan migrate --force`
   Mga bagong table: `employee_fingerprints`, `complaints`.
3. Railway → Variables:
   - `KIOSK_DEVICE_KEY` = mahabang random na sikreto (`openssl rand -hex 32`)
   - optional: `KIOSK_FINGERS_REQUIRED=2`, `KIOSK_FINGERS_MAX=3`, `KIOSK_SCAN_TOKEN_TTL=180`
4. Buksan ang `https://<app>.up.railway.app/kiosk` sa laptop. Dapat lumabas ang
   screen. Normal na "Sensor offline" ito sa laptop dahil walang R307 doon.
5. System Settings → Kiosks: isang kiosk bawat Pi, may code (hal. `SITE_A`) at site.

> **Order:** kapag naka-set na ang `KIOSK_DEVICE_KEY`, hindi na makakapag-record
> ang Pi na luma pa ang `testing.py`. I-update ang Pi sa parehong araw.

## B. Raspberry Pi (bawat kiosk)

```bash
# kopyahin ang buong kiosk-pi folder sa Pi (USB o scp), tapos:
cd ~/kiosk-pi
sudo bash setup.sh                       # gagawa ng /home/admin/jeyanco/kiosk.env
nano /home/admin/jeyanco/kiosk.env       # JEYANCO_KIOSK_CODE at JEYANCO_DEVICE_KEY
sudo bash setup.sh                       # ulitin para mag-start ang services
sudo reboot
```

Pagkatapos ng reboot, kusang bubukas ang kiosk page nang full screen.

**Tanggalin ang lumang autostart** na nagbubukas ng local `index.html` (karaniwan
sa `~/.config/labwc/autostart`, `~/.config/wayfire.ini` o
`~/.config/lxsession/LXDE-pi/autostart`). Kung hindi, dalawang window ang
bubukas.

Kung dati ay pinapatakbo mo ang `testing.py` sa ibang service o sa terminal,
patayin iyon. Isang programa lang ang puwedeng humawak ng `/dev/serial0`.

## C. Pag-check

| Check | Utos / Saan | Dapat |
|---|---|---|
| Service | `systemctl status jeyanco-fingerprint` | `active (running)` |
| Sensor | `curl http://127.0.0.1:5000/ping` | `{"status":"ok"...}` |
| Logs | `journalctl -u jeyanco-fingerprint -f` | `Device key: naka-set` |
| Screen | header ng kiosk | 2 berdeng ilaw: Sensor at Server |
| Scan | pindutin ang TIME IN, i-scan | `AM IN · TIMED IN` + oras ng server |

## Kapag may problema

- **Sensor offline (pula)**: patay ang `testing.py`. Tingnan ang `journalctl -u jeyanco-fingerprint`.
- **"Only a registered kiosk can do this"**: hindi magkapareho ang `JEYANCO_DEVICE_KEY` sa Pi at ang `KIOSK_DEVICE_KEY` sa Railway.
- **"Scan your finger again"**: luma na ang scan (lampas 3 min) o ibang tao ang nag-scan. Mag-scan ulit.
- **Laging "Waiting for the office to set this kiosk's site"**: walang site ang kiosk code na ito sa System Settings → Kiosks, o mali ang `JEYANCO_KIOSK_CODE`.
- **Hindi naaabot ng page ang sensor kahit tumatakbo ang service**: tingnan kung nasa `/etc/chromium/policies/managed/jeyanco.json` ang tamang Railway URL, tapos i-restart ang Chromium. Kung may custom domain, idagdag ito sa `JEYANCO_ALLOWED_ORIGINS`.
