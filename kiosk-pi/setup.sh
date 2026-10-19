#!/bin/bash
# Jeyanco kiosk — one-time setup of a Raspberry Pi for the ONLINE kiosk.
#
#   cd ~/kiosk-pi && sudo bash setup.sh
#
# Puts the fingerprint service and the GPS tracker on systemd, makes
# Chromium open https://<web>/kiosk full screen at login, and lets that page
# talk to the sensor on 127.0.0.1. Safe to run again after an update.

set -e
HERE="$(cd "$(dirname "$0")" && pwd)"
DEST=/home/admin/jeyanco
OWNER=admin

if [ "$(id -u)" -ne 0 ]; then echo "Run with sudo: sudo bash setup.sh"; exit 1; fi

echo "▶ Packages"
apt-get update -y
apt-get install -y python3-flask python3-flask-cors python3-requests python3-serial curl wlrctl wtype xdotool || true
python3 -c "import adafruit_fingerprint" 2>/dev/null || pip3 install --break-system-packages adafruit-circuitpython-fingerprint

echo "▶ Files → $DEST"
mkdir -p "$DEST"
cp "$HERE/testing.py" "$HERE/gps_tracker.py" "$HERE/start-kiosk.sh" "$DEST/"
chmod +x "$DEST/start-kiosk.sh"
if [ -f "$HERE/kiosk.env" ]; then
    # A kiosk.env prepared beside this script (with the device key) wins.
    tr -d '\r' < "$HERE/kiosk.env" > "$DEST/kiosk.env"
elif [ ! -f "$DEST/kiosk.env" ]; then
    cp "$HERE/kiosk.env.example" "$DEST/kiosk.env"
    echo "  ⚠ $DEST/kiosk.env created — fill in JEYANCO_KIOSK_CODE and JEYANCO_DEVICE_KEY, then re-run."
fi
chown -R $OWNER:$OWNER "$DEST"
chmod 600 "$DEST/kiosk.env"

# The web address, for the Chromium policy below.
WEB=$(grep -E '^JEYANCO_WEB=' "$DEST/kiosk.env" | cut -d= -f2- | tr -d '\r' | sed 's#/*$##')
WEB=${WEB:-https://jeyancowebsite-production.up.railway.app}

echo "▶ systemd services"
cp "$HERE/jeyanco-fingerprint.service" "$HERE/jeyanco-gps.service" /etc/systemd/system/
# An older hand-made service for testing.py would fight over the sensor.
for old in fingerprint.service jeyanco.service kiosk.service; do
    if systemctl list-unit-files | grep -q "^$old"; then systemctl disable --now "$old" || true; fi
done
systemctl daemon-reload
systemctl enable --now jeyanco-fingerprint.service jeyanco-gps.service

echo "▶ Chromium policy (lets $WEB reach the sensor on 127.0.0.1)"
for dir in /etc/chromium/policies/managed /etc/chromium-browser/policies/managed; do
    mkdir -p "$dir"
    sed "s#https://jeyancowebsite-production.up.railway.app#$WEB#g" "$HERE/chromium-policy.json" > "$dir/jeyanco.json"
done

echo "▶ Autostart at login"
LINE="$DEST/start-kiosk.sh &"
# labwc (Raspberry Pi OS Bookworm, newer images)
mkdir -p /home/$OWNER/.config/labwc
touch /home/$OWNER/.config/labwc/autostart
grep -q start-kiosk.sh /home/$OWNER/.config/labwc/autostart || echo "$LINE" >> /home/$OWNER/.config/labwc/autostart
# wayfire (Bookworm, older images)
if [ -f /home/$OWNER/.config/wayfire.ini ] && ! grep -q start-kiosk.sh /home/$OWNER/.config/wayfire.ini; then
    if grep -q '^\[autostart\]' /home/$OWNER/.config/wayfire.ini; then
        sed -i "/^\[autostart\]/a jeyanco = $DEST/start-kiosk.sh" /home/$OWNER/.config/wayfire.ini
    else
        printf '\n[autostart]\njeyanco = %s/start-kiosk.sh\n' "$DEST" >> /home/$OWNER/.config/wayfire.ini
    fi
fi
# X11 / LXDE (Bullseye)
mkdir -p /home/$OWNER/.config/lxsession/LXDE-pi
touch /home/$OWNER/.config/lxsession/LXDE-pi/autostart
grep -q start-kiosk.sh /home/$OWNER/.config/lxsession/LXDE-pi/autostart || echo "@$DEST/start-kiosk.sh" >> /home/$OWNER/.config/lxsession/LXDE-pi/autostart
chown -R $OWNER:$OWNER /home/$OWNER/.config

echo
echo "✔ Done. Check:"
echo "   systemctl status jeyanco-fingerprint   (should be active)"
echo "   curl http://127.0.0.1:5000/ping        (should say ok)"
echo "   Then reboot: the kiosk opens $WEB/kiosk by itself."
