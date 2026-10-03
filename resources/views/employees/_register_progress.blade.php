{{-- How much of Register Employee is filled in: a chip on each section
     ("3 / 7") and a bar beside the Register button. On Edit Employee
     (data-mode="edit") it also marks each field that differs from what was
     loaded, counts them beside Save, and keeps the name at the top in step
     with the name fields.

     Display only. It reads the form and changes nothing in it; it counts the
     fields marked required. --}}
<script>
(function () {
    const form = document.getElementById('rgxForm');
    if (!form) return;

    const bar   = document.getElementById('rgxBar');
    const done  = document.getElementById('rgxDone');
    const total = document.getElementById('rgxTotal');

    function required(scope) {
        return Array.prototype.filter.call(
            scope.querySelectorAll('input[required], select[required], textarea[required]'),
            function (f) { return !f.disabled && f.type !== 'hidden'; }
        );
    }
    function filled(f) { return String(f.value || '').trim() !== ''; }

    const sections = Array.prototype.map.call(form.querySelectorAll('.ep-section'), function (sec) {
        const chip = document.createElement('span');
        chip.className = 'rgx-chip';
        const head = sec.querySelector('.ep-section-head');
        if (head) head.appendChild(chip);
        return { sec: sec, chip: chip };
    });

    // Edit Employee: what each field held when the page opened.
    const edit  = form.dataset.mode === 'edit';
    const dirty = document.getElementById('rgxDirty');
    const who   = document.getElementById('rgxWhoName');
    const watched = edit ? Array.prototype.filter.call(
        form.querySelectorAll('.ep-section input, .ep-section select, .ep-section textarea'),
        function (f) { return f.type !== 'hidden' && !f.readOnly; }
    ) : [];
    const start = new Map(watched.map(function (f) { return [f, f.value]; }));

    function paintEdits() {
        let n = 0;
        watched.forEach(function (f) {
            const changed = !f.disabled && f.value !== start.get(f);
            if (changed) n++;
            const col = f.closest('.row > *');
            if (col) col.classList.toggle('rgx-changed', changed);
        });
        if (dirty) {
            dirty.textContent = n ? (n === 1 ? '1 change' : n + ' changes') : 'No changes yet';
            dirty.classList.toggle('is-on', n > 0);
        }
        if (who) {
            const name = ['first_name', 'middle_name', 'last_name', 'name_suffix']
                .map(function (id) { const f = document.getElementById(id); return f ? f.value.trim() : ''; })
                .filter(Boolean).join(' ');
            if (name) who.textContent = name;
        }
    }

    function paint() {
        if (edit) paintEdits();
        let all = 0, ok = 0;
        sections.forEach(function (s) {
            const req = required(s.sec);
            const n = req.filter(filled).length;
            all += req.length; ok += n;
            if (!req.length) {
                s.chip.textContent = 'Optional';
                s.chip.className = 'rgx-chip is-opt';
            } else {
                s.chip.textContent = n + ' / ' + req.length;
                s.chip.className = 'rgx-chip' + (n === req.length ? ' is-done' : '');
            }
        });
        if (done)  done.textContent  = ok;
        if (total) total.textContent = all;
        if (bar) {
            bar.style.width = (all ? Math.round(ok / all * 100) : 0) + '%';
            bar.classList.toggle('is-done', all > 0 && ok === all);
        }
    }

    form.addEventListener('input', paint);
    form.addEventListener('change', function () { setTimeout(paint, 0); });
    // Values the page's own scripts set on load (Position from the labor
    // type, the address look-ups) count as where the form started.
    window.addEventListener('load', function () {
        watched.forEach(function (f) { start.set(f, f.value); });
        paint();
    });
    paint();
})();
</script>
