<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Payslip') }} — {{ $item->employee_name }} — {{ $run->code }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('design-tokens.css') }}">
    <style>
        body { margin: 0; padding: 24px; background: #fff; font-family: Inter, system-ui, sans-serif; }
        @media print { body { padding: 0; } }
    </style>
</head>
<body>
    @include('payslips._slip_styles')
    @include('payslips._slip', ['item' => $item, 'run' => $run, 'company' => $company])

    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>

