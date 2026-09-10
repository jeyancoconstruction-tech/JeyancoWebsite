{{-- Validation only.

     session('success') and session('error') used to be echoed here as well.
     They are toasts now — see _notify.blade.php — so printing them here too
     would say the same thing twice, in two shapes, in two places. This file
     is included by ten module pages, so the ten are fixed together.

     The error list stays: it belongs beside the form it is about, and a
     toast is the wrong shape for eight validation messages at once. --}}
@if($errors->any())
    <div class="mod-alert err" role="alert">
        <i class="fas fa-circle-exclamation"></i>
        <div>
            <strong>{{ __('Please fix the following:') }}</strong>
            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    </div>
@endif

