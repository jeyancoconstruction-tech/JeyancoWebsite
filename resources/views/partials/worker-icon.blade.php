{{-- A worker in a hard hat — the one picture of a worker everywhere a worker
     is listed (Employees, Leave & Advances, Payslips, Payroll Records, the
     Dashboard, Device Monitoring). The office's own icon
     (public/images/worker-icon.png), used as a mask so it takes the colour of
     whatever holds it. public/ui-fixes.css then makes it black in the light
     theme and white in the dark, on a neutral grey tile, on every page. --}}
<span class="wk-ico" aria-hidden="true"
      style="display:block;width:{{ $size ?? '64%' }};height:{{ $size ?? '64%' }};color:inherit !important;background-color:currentColor;
             -webkit-mask:url('{{ asset('images/worker-icon.png') }}') center / contain no-repeat;
             mask:url('{{ asset('images/worker-icon.png') }}') center / contain no-repeat;"></span>
