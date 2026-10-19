#!/bin/bash
# Opens the ONLINE kiosk screen full screen, once the fingerprint service is up.
# Started by the desktop's autostart (setup.sh adds it). --start-fullscreen,
# not --kiosk: the kiosk's ⛶ / ▁ buttons need a way out of full screen.

set -a
. /home/admin/jeyanco/kiosk.env
set +a

URL="${JEYANCO_WEB%/}/kiosk?kiosk=${JEYANCO_KIOSK_CODE}"

# Wait (up to ~60 s) for the fingerprint service, so the first screen
# already shows the sensor as online.
for i in $(seq 1 30); do
    curl -fs http://127.0.0.1:5000/ping >/dev/null 2>&1 && break
    sleep 2
done

BROWSER=$(command -v chromium-browser || command -v chromium)

# A crash or power cut must not leave a "Restore pages?" bar on the screen.
PREFS="$HOME/.config/chromium/Default/Preferences"
if [ -f "$PREFS" ]; then
    sed -i 's/"exited_cleanly":false/"exited_cleanly":true/; s/"exit_type":"Crashed"/"exit_type":"Normal"/' "$PREFS"
fi

exec "$BROWSER" \
    --start-fullscreen \
    --noerrdialogs \
    --disable-infobars \
    --disable-session-crashed-bubble \
    --disable-features=Translate \
    --check-for-update-interval=31536000 \
    --password-store=basic \
    --overscroll-history-navigation=0 \
    --app="$URL"
