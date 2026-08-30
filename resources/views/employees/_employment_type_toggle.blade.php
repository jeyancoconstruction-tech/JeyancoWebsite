{{-- Swaps the pay fields to match the employee type.

     Regular is paid by the hour off a labor type. Contractual is settled
     against a contract total with an end date, and this payroll computes no
     wages for them — so the two sets of fields are mutually exclusive rather
     than both being on screen with one of them meaningless. --}}
<script>
(function () {
    const sel = document.getElementById('employment_type');
    if (!sel) return;

    const regular  = document.querySelectorAll('.js-regular-only');
    const contract = document.querySelectorAll('.js-contract-only');

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

    function sync() {
        const contractual = sel.value === 'contractual';
        setGroup(regular, !contractual);
        setGroup(contract, contractual);

        // Only demand a labor type and rate while they are on screen.
        ['labor_type_selector', 'rate_per_hour', 'labor_type_select'].forEach(function (id) {
            const field = document.getElementById(id);
            if (field) field.required = !contractual;
        });
    }

    sel.addEventListener('change', sync);
    sync();
})();
</script>
