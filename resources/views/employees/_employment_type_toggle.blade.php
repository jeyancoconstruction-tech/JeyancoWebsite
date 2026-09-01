{{-- Swaps the pay fields to match the employee type, and binds Position to
     Labor Type.

     Regular is paid by the hour off a labor type. Contractual is settled
     against a contract total with an end date, and this payroll computes no
     wages for them — so the two sets of fields are mutually exclusive rather
     than both being on screen with one of them meaningless.

     Position follows the same split. For a regular worker the position IS the
     labor type: `position`, the column payroll reads, is derived from it on
     save whatever the Position box says. Leaving that box free to type invited
     a second, different answer to the same question, so it is filled from the
     labor type and locked. A contractual worker has no labor type to take it
     from, so there it is theirs to write. --}}
<script>
(function () {
    const sel = document.getElementById('employment_type');
    if (!sel) return;

    const regular  = document.querySelectorAll('.js-regular-only');
    const contract = document.querySelectorAll('.js-contract-only');
    const jobTitle = document.getElementById('job_title');
    const hint     = document.querySelector('.js-position-hint');

    // Register Employee and Edit Employee named the same control differently.
    const laborSelect = document.getElementById('labor_type_selector')
                     || document.getElementById('labor_type_select');

    // A hidden field must not be submitted or validated. `disabled` keeps it
    // out of the POST entirely, which is what lets the server treat "no labor
    // type" as legitimate for a contractual worker rather than a missing
    // required field.
    function setGroup(nodes, shown) {
        nodes.forEach(function (node) {
            node.hidden = !shown;
            node.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = !shown;
            });
        });
    }

    /** Copy the chosen labor type's name into Position. */
    function fillPosition() {
        if (!jobTitle || !laborSelect) return;
        const opt  = laborSelect.options[laborSelect.selectedIndex];
        const name = opt && opt.value ? (opt.dataset.name || '') : '';
        // Nothing chosen yet, or an older record whose labor type was cleared:
        // leave what is there rather than blanking a field nobody can retype.
        if (name) jobTitle.value = name;
    }

    function setPositionMode(contractual) {
        if (!jobTitle) return;
        jobTitle.readOnly = !contractual;
        jobTitle.classList.toggle('ep-locked', !contractual);
        if (hint) {
            hint.textContent = contractual
                ? 'Type the role agreed in the contract.'
                : 'Follows the Labor Type.';
        }
        if (!contractual) fillPosition();
    }

    function sync() {
        const contractual = sel.value === 'contractual';
        setGroup(regular, !contractual);
        setGroup(contract, contractual);

        // Only demand a field while it is on screen. A hidden `required` input
        // cannot be focused, so the browser refuses to submit the form and
        // shows nothing to explain why — the pay fields must follow the toggle.
        ['labor_type_selector', 'rate_per_hour', 'labor_type_select'].forEach(function (id) {
            const field = document.getElementById(id);
            if (field) field.required = !contractual;
        });
        ['contract_rate', 'end_of_contract'].forEach(function (id) {
            const field = document.getElementById(id);
            if (field) field.required = contractual;
        });

        setPositionMode(contractual);
    }

    sel.addEventListener('change', sync);
    if (laborSelect) {
        laborSelect.addEventListener('change', function () {
            if (sel.value !== 'contractual') fillPosition();
        });
    }
    sync();
})();
</script>
