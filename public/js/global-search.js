/*
 * The top bar's search (Michael, 2026-10-02: everything reachable from it).
 *
 * Click into the box and it lists every page and section this account can
 * open, under the sidebar's own headings. Type and it narrows that list, and
 * from the second character it searches the records too — workers, attendance,
 * sites, leave, cash advances, accounts, kiosks, labor types, holidays, the
 * audit log (SearchController). Up and Down move through the list, Enter opens
 * the one picked, or the full results page when none is; Esc closes it.
 * Ctrl/⌘ K puts the cursor in the box from anywhere.
 *
 * Built with textContent-safe escaping: a result carries a worker's name, and
 * one of them will hold an apostrophe or an angle bracket sooner or later.
 */
(function () {
    var input = document.getElementById('global-search-input');
    var box   = document.getElementById('search-suggestions');
    if (!input || !box) return;

    var suggestUrl = input.dataset.suggestUrl;
    var searchUrl  = input.dataset.searchUrl;

    var timer  = null;
    var asked  = 0;      // the newest request; a slower, older answer is dropped
    var active = -1;

    function esc(text) {
        return String(text == null ? '' : text).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
        });
    }

    function rows() {
        return box.querySelectorAll('.gs-item');
    }

    function close() {
        box.classList.remove('open');
        input.setAttribute('aria-expanded', 'false');
        active = -1;
    }

    function open() {
        box.classList.add('open');
        input.setAttribute('aria-expanded', 'true');
    }

    function pick(index) {
        var list = rows();
        if (!list.length) return;
        active = (index + list.length) % list.length;
        list.forEach(function (row, i) {
            row.classList.toggle('is-active', i === active);
            row.setAttribute('aria-selected', i === active ? 'true' : 'false');
        });
        list[active].scrollIntoView({ block: 'nearest' });
    }

    function draw(items, query) {
        var html = '';
        active = -1;

        if (!items.length) {
            html = '<div class="gs-empty">' + esc(box.dataset.none) + ' “' + esc(query) + '”</div>';
        } else {
            var category = null;
            html = '<div class="gs-list" role="listbox">';
            items.forEach(function (item) {
                if (item.category !== category) {
                    category = item.category;
                    html += '<div class="gs-cat">' + esc(category) + '</div>';
                }
                html += '<a class="gs-item" role="option" aria-selected="false" href="' + esc(item.url) + '">'
                      +   '<span class="gs-ico"><i data-lucide="' + esc(item.icon || 'search') + '"></i></span>'
                      +   '<span class="gs-text">'
                      +     '<span class="gs-title">' + esc(item.text) + '</span>'
                      +     (item.subtitle ? '<span class="gs-sub">' + esc(item.subtitle) + '</span>' : '')
                      +   '</span>'
                      + '</a>';
            });
            html += '</div>';
        }

        // With a query, the way to everything it found; without one, what the box can do.
        html += '<div class="gs-foot">'
              + (query.length >= 2
                    ? '<a class="gs-all" href="' + esc(searchUrl) + '?q=' + encodeURIComponent(query) + '">' + esc(box.dataset.all) + ' →</a>'
                    : '<span>' + esc(box.dataset.hint) + '</span>')
              + '<span class="gs-keys">↑ ↓ · Enter</span>'
              + '</div>';

        box.innerHTML = html;
        open();
        if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    function load(query, delay) {
        clearTimeout(timer);
        timer = setTimeout(function () {
            var mine = ++asked;
            fetch(suggestUrl + '?q=' + encodeURIComponent(query), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(function (items) {
                    // Answered late, or the box has been left since it was asked.
                    if (mine !== asked || document.activeElement !== input) return;
                    draw(Array.isArray(items) ? items : [], query);
                })
                .catch(function (error) { console.error('Search error:', error); });
        }, delay);
    }

    input.addEventListener('input', function () { load(input.value.trim(), 180); });
    input.addEventListener('focus', function () { load(input.value.trim(), 0); });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            if (!box.classList.contains('open')) return;
            e.preventDefault();
            pick(active + (e.key === 'ArrowDown' ? 1 : -1));
        } else if (e.key === 'Enter') {
            var list  = rows();
            var query = input.value.trim();
            if (active >= 0 && list[active]) {
                e.preventDefault();
                window.location.href = list[active].href;
            } else if (query.length >= 2) {
                window.location.href = searchUrl + '?q=' + encodeURIComponent(query);
            }
        } else if (e.key === 'Tab') {
            asked++;
            clearTimeout(timer);
            close();
        } else if (e.key === 'Escape') {
            asked++;                 // an answer still on its way does not reopen it
            clearTimeout(timer);
            close();
            input.blur();
        }
    });

    // Close on a click anywhere else. A click inside the list is a link.
    document.addEventListener('click', function (e) {
        if (e.target === input || box.contains(e.target)) return;
        asked++;
        clearTimeout(timer);
        close();
    });

    // Ctrl/⌘ K (and Ctrl/⌘ /) from anywhere on the page.
    document.addEventListener('keydown', function (e) {
        if ((e.key === '/' || e.key === 'k' || e.key === 'K') && (e.ctrlKey || e.metaKey) && document.activeElement !== input) {
            e.preventDefault();
            input.focus();
            input.select();
        }
    });
})();
