/*
 * Every site and every kiosk, drawn on a Leaflet map.
 *
 * One layer, two maps: the dashboard's Project Sites map and the map on the
 * Sites page (Michael, 2026-10-03: "dapat meron naring dito tulad ng sa
 * dashboard"). Both read GET /dashboard/map, so a site's pin, its range and
 * where each kiosk's GPS last put it look the same wherever they are shown.
 *
 * A site is the red pin with its name and range beside it, and its range as a
 * dashed ring. A kiosk is a round badge, green in its site's range and red out
 * of it — two colours and no more (Michael, 2026-10-03: "in range and out of
 * range nalang ang kulay"). One gone quiet or without a fix is coloured by
 * where it last was. A kiosk out of range has a dashed red line to the site
 * it is set to ("putol na redline papunta sa designated site").
 *
 * GPS is said by a pulse round the badge, apart from its colour (Michael,
 * 2026-10-04: "pulsing loader paikot sa fingerprint icon ... kapag may gps
 * signal yung kiosk, kapag wala naman red pulse"). Green rings ripple out of
 * a kiosk that has a signal; a red ring beats round one that has none.
 *
 *   const layer = JeyancoSiteMap(map, {
 *       mapUrl:   '/dashboard/map',
 *       sitesUrl: '/sites',          // the key links there; leave out to omit
 *       statusEl: element,           // optional: "1 kiosk: 1 in range"
 *       autoFit:  () => true,        // optional: may the first load move the view?
 *   });
 *   layer.refresh();  layer.fitAll();  layer.hideSite(id);  layer.focusSite(id);
 */
(function () {
    'use strict';

    // How close the map opens on a site and its kiosks. 16 was street
    // level and felt cramped; 15 shows the neighbourhood around the site
    // with its range ring still clear. The + button still goes closer.
    const FIT_ZOOM = 15;

    // The same red pin the Sites page puts down.
    const PIN = '<svg width="28" height="38" viewBox="0 0 28 38" aria-hidden="true"><path d="M14 37C14 37 1.5 22.6 1.5 13.8a12.5 12.5 0 0 1 25 0C26.5 22.6 14 37 14 37z" fill="#ef4444" stroke="#fff" stroke-width="1.6"/><circle cx="14" cy="13.5" r="4.6" fill="#fff"/></svg>';

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const far = m => m < 1000 ? Math.round(m) + ' m' : (m / 1000).toFixed(m < 10000 ? 1 : 0) + ' km';
    const ago = s => s == null ? 'never' : s < 60 ? s + 's ago' : s < 3600 ? Math.round(s / 60) + ' min ago'
                   : s < 86400 ? Math.round(s / 3600) + ' h ago' : Math.round(s / 86400) + ' d ago';
    const plural = (n, one) => n + ' ' + one + (n === 1 ? '' : 's');
    const brand = () => getComputedStyle(document.documentElement).getPropertyValue('--brand').trim() || '#1668DC';

    // The line under a kiosk's name, and the first line of its popup.
    function kioskLine(k) {
        if (k.range === 'in') return k.distance_m != null ? `In range · ${far(k.distance_m)}` : `In range of ${k.at_site}`;
        return k.distance_m != null ? `Out of range · ${far(k.distance_m)}` : 'Out of range';
    }

    const siteIcon = s => L.divIcon({
        className: 'dm-mark', iconSize: null, iconAnchor: [14, 37], popupAnchor: [0, -34],
        html: `<div class="dm-site">${PIN}<span class="dm-chip"><b>${esc(s.name)}</b>` +
              `<small>${s.radius_m} m range · ${plural(s.kiosks, 'kiosk')}</small></span></div>`,
    });

    // Whether the kiosk has a GPS signal now: a fix, on a heartbeat still
    // fresh. No satellites, or gone quiet, is none.
    const signal = k => k.gps === 'fix' ? 'gps-on' : 'gps-off';
    const gpsLine = k => k.gps === 'fix' ? 'GPS signal' : 'No GPS signal';

    const kioskHtml = k =>
        `<div class="dm-kiosk is-${k.range} ${signal(k)}" data-range="${k.range}"><span class="dm-av"><i class="fas fa-fingerprint"></i></span>` +
        `<span class="dm-chip"><b>${esc(k.name)}</b><small>${esc(kioskLine(k))}</small></span></div>`;

    const kioskIcon = html => L.divIcon({
        className: 'dm-mark', iconSize: null, iconAnchor: [16, 16], popupAnchor: [0, -14], html,
    });

    function sitePopup(s, kiosks) {
        const here = kiosks.filter(k => k.site_id === s.id);
        return `<div class="dm-pop"><b>${esc(s.name)}</b>` +
            (s.location ? `<div class="dm-pop-sub">${esc(s.location)}</div>` : '') +
            `<div>Range: ${s.radius_m} m from the pin</div>` +
            (here.length
                ? here.map(k => k.range
                    ? `<div class="dm-pop-k is-${k.range}"><i class="fas fa-fingerprint"></i> ${esc(k.name)} · ${esc(kioskLine(k))}` +
                      (k.gps === 'fix' ? '' : ' · no GPS signal') + '</div>'
                    : `<div class="dm-pop-sub"><i class="fas fa-fingerprint"></i> ${esc(k.name)} · no position yet</div>`).join('')
                : '<div class="dm-pop-sub">No kiosk is set to this site.</div>') +
            '</div>';
    }

    function kioskPopup(k) {
        return `<div class="dm-pop"><b>${esc(k.name)}</b> <span class="dm-pop-sub">${esc(k.code)}</span>` +
            `<div class="dm-pop-k is-${k.range}">${esc(kioskLine(k))}</div>` +
            `<div class="dm-pop-g ${signal(k)}"><i></i>${gpsLine(k)}${k.gps === 'fix' ? '' : ' · last known position'}</div>` +
            `<div>Set to ${k.site ? '<b>' + esc(k.site) + '</b>' + (k.radius_m ? ` · ${k.radius_m} m range` : ' · not pinned') : 'no site'}</div>` +
            (k.at_site && k.at_site !== k.site ? `<div class="dm-pop-sub">Standing in ${esc(k.at_site)}'s range</div>` : '') +
            `<div class="dm-pop-sub">Heard ${ago(k.seen_ago)}</div></div>`;
    }

    // Names that would land on top of one another. Each name tries its
    // usual side, then the others; one with no free side steps back
    // until hovered. A kiosk out of range is placed first, a site last.
    // Every badge and pin stays, and no name is put over one.
    const URGENCY = { out: 0, in: 1 };
    const SIDES = { kiosk: ['', 'at-left'], site: ['', 'at-below', 'at-right', 'at-left'] };

    window.JeyancoSiteMap = function (map, opts) {
        opts = opts || {};
        const mapEl    = map.getContainer();
        const statusEl = opts.statusEl || null;
        const autoFit  = opts.autoFit || (() => true);

        // A key to the colours, what is not on the map, and where pins are set.
        const legend = L.control({ position: 'bottomleft' });
        legend.onAdd = () => {
            const box = L.DomUtil.create('div', 'dm-legend');
            L.DomEvent.disableClickPropagation(box);
            L.DomEvent.disableScrollPropagation(box);
            return box;
        };
        legend.addTo(map);

        // Redrawn only when it says something new, as the badges are: a pulse
        // that starts over on every heartbeat would never look steady.
        let keyed = '';
        function drawLegend(sites, kiosks) {
            const unplaced = kiosks.filter(k => k.lat == null).length;
            const unpinned = sites.filter(s => s.lat == null).length;
            const missing = [
                unplaced ? plural(unplaced, 'kiosk') + ' with no position' : '',
                unpinned ? plural(unpinned, 'site') + ' not pinned' : '',
            ].filter(Boolean).join(' · ');
            const html =
                '<div class="dm-legend-keys">' +
                    '<span class="is-in"><i></i>In range</span>' +
                    '<span class="is-out"><i></i>Out of range</span>' +
                    (Object.keys(strays).length ? '<span class="is-out dm-legend-way"><i></i>Line to its site</span>' : '') +
                '</div>' +
                '<div class="dm-legend-keys dm-legend-gps">' +
                    '<span class="gps-on"><i></i>GPS signal</span>' +
                    '<span class="gps-off"><i></i>No GPS signal</span>' +
                '</div>' +
                (missing ? `<div class="dm-legend-note">Not on the map: ${missing}</div>` : '') +
                (opts.sitesUrl ? `<a href="${opts.sitesUrl}">Pins are set on the Sites page <i class="fas fa-arrow-right"></i></a>` : '');
            if (html === keyed) return;
            keyed = html;
            legend.getContainer().innerHTML = html;
        }

        // One marker per site and per kiosk, kept between refreshes and moved,
        // so a popup somebody has open does not close on every heartbeat.
        const siteMarks = {}, kioskMarks = {}, strays = {};
        let fitted = false, hidden = null, last = { sites: [], kiosks: [] };

        function draw(sites, kiosks) {
            last = { sites, kiosks };
            const seen = new Set();
            sites.filter(s => s.lat != null && s.lng != null && s.id !== hidden).forEach(s => {
                seen.add(s.id);
                const at = [s.lat, s.lng];
                let m = siteMarks[s.id];
                if (!m) {
                    m = siteMarks[s.id] = {
                        circle: L.circle(at, { radius: s.radius_m, color: brand(), weight: 1.5, dashArray: '5 4', fillColor: brand(), fillOpacity: .12, interactive: false }).addTo(map),
                        pin: L.marker(at, { icon: siteIcon(s), keyboard: false }).addTo(map).bindPopup(''),
                    };
                } else {
                    m.circle.setLatLng(at).setRadius(s.radius_m).setStyle({ color: brand(), fillColor: brand() });
                    m.pin.setLatLng(at).setIcon(siteIcon(s));
                }
                m.pin.setPopupContent(sitePopup(s, kiosks));
            });
            Object.keys(siteMarks).forEach(id => {
                if (seen.has(+id)) return;
                map.removeLayer(siteMarks[id].circle); map.removeLayer(siteMarks[id].pin);
                delete siteMarks[id];
            });

            const placed = new Set();
            kiosks.filter(k => k.lat != null && k.lng != null).forEach(k => {
                placed.add(k.id);
                const at = [k.lat, k.lng];
                const html = kioskHtml(k);
                let m = kioskMarks[k.id];
                if (!m) {
                    m = kioskMarks[k.id] = L.marker(at, { icon: kioskIcon(html), zIndexOffset: 1000, keyboard: false })
                        .addTo(map).bindPopup(kioskPopup(k));
                } else {
                    m.setLatLng(at).setPopupContent(kioskPopup(k));
                    // A new icon is a new badge, and its pulse would start over.
                    if (m.drawn !== html) m.setIcon(kioskIcon(html));
                }
                m.drawn = html;
            });
            Object.keys(kioskMarks).forEach(id => {
                if (placed.has(+id)) return;
                map.removeLayer(kioskMarks[id]);
                delete kioskMarks[id];
            });

            // A kiosk out of range: a dashed red line from it to the pin of
            // the site it is set to, where that pin is on the map.
            const astray = new Set();
            kiosks.filter(k => k.range === 'out' && siteMarks[k.site_id]).forEach(k => {
                astray.add(k.id);
                const way = [[k.lat, k.lng], siteMarks[k.site_id].pin.getLatLng()];
                if (strays[k.id]) strays[k.id].setLatLngs(way);
                else strays[k.id] = L.polyline(way, { className: 'dm-way', weight: 2.5, dashArray: '8 7', interactive: false }).addTo(map);
            });
            Object.keys(strays).forEach(id => {
                if (astray.has(+id)) return;
                map.removeLayer(strays[id]);
                delete strays[id];
            });

            drawLegend(sites, kiosks);
            drawStatus(kiosks);

            // The first time, show everything there is: every site and every
            // kiosk that has a position.
            if (!fitted) {
                fitted = true;
                if (autoFit()) fitAll();
            }
            declutter();
        }

        function declutter() {
            const hit = (a, b) => a.left < b.right && b.left < a.right && a.top < b.bottom && b.top < a.bottom;
            const kiosks = Object.values(kioskMarks)
                .map(m => m.getElement()?.querySelector('.dm-kiosk'))
                .filter(Boolean)
                .sort((a, b) => URGENCY[a.dataset.range] - URGENCY[b.dataset.range]);
            const sites = Object.values(siteMarks).map(m => m.pin.getElement()?.querySelector('.dm-site')).filter(Boolean);
            // The map's own controls — zoom, the key, the credit — are in the way too.
            const taken = kiosks.map(k => k.querySelector('.dm-av').getBoundingClientRect())
                .concat(sites.map(s => s.querySelector('svg').getBoundingClientRect()))
                .concat([...mapEl.querySelectorAll('.leaflet-control')].map(c => c.getBoundingClientRect()));
            const frame = mapEl.getBoundingClientRect();
            const inside = r => r.left >= frame.left + 2 && r.right <= frame.right - 2 && r.top >= frame.top + 2 && r.bottom <= frame.bottom - 2;

            const place = (chip, sides) => {
                for (const side of sides) {
                    chip.classList.remove('is-hidden', 'at-below', 'at-right', 'at-left');
                    if (side) chip.classList.add(side);
                    const r = chip.getBoundingClientRect();
                    if (inside(r) && !taken.some(t => hit(t, r))) { taken.push(r); return; }
                }
                chip.classList.remove('at-below', 'at-right', 'at-left');
                chip.classList.add('is-hidden');
            };
            kiosks.forEach(k => place(k.querySelector('.dm-chip'), SIDES.kiosk));
            sites.forEach(s => place(s.querySelector('.dm-chip'), SIDES.site));
        }
        map.on('zoomend moveend', declutter);

        // The rings take the theme's brand colour; the tiles and the labels
        // follow the theme through CSS.
        new MutationObserver(() => {
            Object.values(siteMarks).forEach(m => m.circle.setStyle({ color: brand(), fillColor: brand() }));
        }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });

        function fitAll() {
            const points = Object.values(siteMarks).map(m => m.pin.getLatLng())
                .concat(Object.values(kioskMarks).map(m => m.getLatLng()));
            if (points.length === 1) map.setView(points[0], FIT_ZOOM);
            else if (points.length > 1) map.fitBounds(L.latLngBounds(points), { padding: [60, 60], maxZoom: FIT_ZOOM });
        }

        // The header says how the kiosks stand: in range or out of it.
        function drawStatus(kiosks) {
            if (!statusEl) return;
            if (!kiosks.length) {
                statusEl.innerHTML = '<i class="dm-dot"></i> No kiosks registered';
                return;
            }
            const n = r => kiosks.filter(k => k.range === r).length;
            const inn = n('in'), out = n('out'), none = n(null);
            const tone = out ? 'is-out' : inn ? 'is-in' : '';
            const parts = [
                inn ? `${inn} in range` : '',
                out ? `${out} out of range` : '',
                none ? `${none} with no position` : '',
            ].filter(Boolean).join(' · ');
            statusEl.innerHTML = `<i class="dm-dot ${tone}"></i> ${plural(kiosks.length, 'kiosk')}: ${parts}`;
        }

        let busy = false;
        async function refresh() {
            if (busy) return;
            busy = true;
            try {
                const res = await fetch(opts.mapUrl, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error(res.status);
                const d = await res.json();
                draw(d.sites || [], d.kiosks || []);
            } catch (e) {
                if (statusEl) statusEl.innerHTML = '<i class="dm-dot"></i> Could not load the map';
            } finally {
                busy = false;
            }
        }

        return {
            refresh,
            fitAll,
            declutter,

            /** Leave one site off the map, as the Sites page does while it is being edited. */
            hideSite(id) {
                if (hidden === (id ?? null)) return;
                hidden = id ?? null;
                draw(last.sites, last.kiosks);
            },

            /** Go to a site and open what its pin says. */
            focusSite(id, zoom) {
                const m = siteMarks[id];
                if (!m) return false;
                map.setView(m.pin.getLatLng(), zoom || 17);
                m.pin.openPopup();
                return true;
            },
        };
    };
})();
