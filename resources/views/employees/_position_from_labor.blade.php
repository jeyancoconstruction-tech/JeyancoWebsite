{{-- Binds Position to Labor Type.

     The position IS the labor type: `position`, the column payroll reads, is
     derived from it on save whatever the Position box says. Leaving that box
     free to type invited a second, different answer to the same question, so
     it is filled from the labor type and locked. --}}
<script>
(function () {
    const jobTitle = document.getElementById('job_title');
    const hint     = document.querySelector('.js-position-hint');

    // Register Employee and Edit Employee named the same control differently.
    const laborSelect = document.getElementById('labor_type_selector')
                     || document.getElementById('labor_type_select');

    if (!jobTitle || !laborSelect) return;

    /** Copy the chosen labor type's name into Position. */
    function fillPosition() {
        const opt  = laborSelect.options[laborSelect.selectedIndex];
        const name = opt && opt.value ? (opt.dataset.name || '') : '';
        // Nothing chosen yet, or an older record whose labor type was cleared:
        // leave what is there rather than blanking a field nobody can retype.
        if (name) jobTitle.value = name;
    }

    jobTitle.readOnly = true;
    jobTitle.classList.add('ep-locked');
    if (hint) hint.textContent = 'Follows the Labor Type.';

    laborSelect.addEventListener('change', fillPosition);
    fillPosition();
})();
</script>
