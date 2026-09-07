{{-- The session messages every module controller sets, in the app's colours. --}}
@if(session('success'))
    <div class="mod-alert ok" role="status">
        <i class="fas fa-circle-check"></i>
        <div>{{ session('success') }}</div>
    </div>
@endif
@if(session('error'))
    <div class="mod-alert err" role="alert">
        <i class="fas fa-circle-exclamation"></i>
        <div>{{ session('error') }}</div>
    </div>
@endif
@if($errors->any())
    <div class="mod-alert err" role="alert">
        <i class="fas fa-circle-exclamation"></i>
        <div>
            <strong>{{ __('Please fix the following:') }}</strong>
            <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    </div>
@endif

