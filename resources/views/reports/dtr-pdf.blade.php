{{-- A worker's DTR as a PDF file: the sheet the pop-up shows, in ink, on bond
     paper. Drawn by dompdf (PayrollReportController::dtrPdf), which reads
     plain CSS only — no variables, flex or grid — so the colours are written
     out here. A month fits one page; a longer range runs on, with the
     column headings again on each. --}}
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ __('Daily Time Record') }} · {{ $e->name }} · {{ $period }}</title>
<style>
@page { size: letter portrait; margin: 10mm 11mm; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 7.8pt; line-height: 1.15; color: #111; margin: 0; }
table { width: 100%; border-collapse: collapse; }
.dtr-top td { padding: 0 0 6pt; vertical-align: bottom; }
.dtr-top td.r { text-align: right; }
.dtr-co { font-size: 13pt; font-weight: bold; }
.dtr-addr { font-size: 7.4pt; color: #555; }
.dtr-doc { margin-top: 2pt; font-size: 7.4pt; letter-spacing: 1.4pt; text-transform: uppercase; font-weight: bold; color: #444; }
.dtr-k { font-size: 6.8pt; letter-spacing: .8pt; text-transform: uppercase; font-weight: bold; color: #555; }
.dtr-per { font-size: 10.5pt; font-weight: bold; }
.dtr-info { margin-bottom: 6pt; }
.dtr-info th, .dtr-info td { border: .6pt solid #9a9a9a; padding: 3pt 6pt; text-align: left; vertical-align: top; }
.dtr-info th { width: 16%; background: #eef0f3; font-size: 6.8pt; letter-spacing: .6pt; text-transform: uppercase; color: #444; }
.dtr-info td { width: 34%; }
.dtr-days th, .dtr-days td { border: .6pt solid #9a9a9a; padding: 1.9pt 3.5pt; text-align: center; white-space: nowrap; }
/* Remarks takes what the other columns leave, and wraps inside it. */
.dtr-days .rem { white-space: normal; }
.dtr-days thead th { background: #eef0f3; font-size: 6.6pt; letter-spacing: .4pt; text-transform: uppercase; color: #444; }
.dtr-days .l { text-align: left; }
.dtr-days td.date span { color: #666; font-size: 7.2pt; margin-left: 3pt; }
.dtr-days td.miss { color: #b91c1c; font-weight: bold; font-size: 7.6pt; }
.dtr-days td.late { color: #b45309; font-weight: bold; }
.dtr-days td.ot { color: #1d4ed8; font-weight: bold; }
.dtr-days td.z { color: #777; }
.dtr-days tr.is-off td, .dtr-days tr.is-none td { background: #f7f8f9; }
.dtr-rem { font-weight: bold; }
.dtr-rem.good { color: #15803d; }
.dtr-rem.bad { color: #b91c1c; }
.dtr-rem.mute { color: #444; }
.dtr-rem.live { color: #1d4ed8; }
.dtr-days td small { color: #666; font-size: 7pt; margin-left: 3pt; }
.dtr-days tr.total td { background: #eef0f3; font-weight: bold; border-top: 1.2pt solid #555; }
.dtr-days tr.total td.l { font-size: 7pt; letter-spacing: .4pt; text-transform: uppercase; color: #333; }
.dtr-days tr.total td.rem { letter-spacing: 0; text-transform: none; }
.dtr-note { margin: 4pt 0 0; font-size: 7.2pt; color: #555; }
.dtr-sign { margin-top: 26pt; page-break-inside: avoid; }
.dtr-sign td { width: 42%; padding: 0; text-align: center; vertical-align: top; }
.dtr-sign td.gap { width: 16%; }
.dtr-sign .line { border-top: .8pt solid #111; margin-bottom: 3pt; }
.dtr-sign b { display: block; font-size: 8.6pt; }
.dtr-sign span { display: block; font-size: 6.8pt; letter-spacing: .6pt; text-transform: uppercase; font-weight: bold; color: #444; }
.dtr-gen { margin-top: 8pt; font-size: 6.8pt; color: #666; }
</style>
</head>
<body>
@include('reports._dtr_sheet')
</body>
</html>
