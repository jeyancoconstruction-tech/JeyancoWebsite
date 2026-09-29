{{-- How much of Register Employee is filled in: a chip on each section
     ("3 / 7") and a bar beside the Register button.

     Display only. It reads the form and changes nothing in it; it counts the
     fields that are required right now, so a contractual worker's hidden
     labor type and rate drop out and their contract fields come in, exactly
     as _employment_type_toggle.blade.php switches them. --}}
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

    function paint() {
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
    paint();
    // The address look-ups fill their boxes from a script of their own.
    window.addEventListener('load', paint);
})();
</script>
