@if($e->laborType)
    <span class="rm-badge rm-badge-labor"><i class="fas fa-briefcase"></i> {{ $e->laborType->name }}</span>
@else
    <span class="rm-dash">—</span>
@endif
{{-- Contractual is the exception, so only it is called out; leaving every
     daily worker tagged would be noise on a list where nearly all are daily. --}}
@if($e->isContractual())
    <span class="rm-badge rm-badge-contract" title="{{ __('Contractual — pareho pa rin ang computation ng sweldo sa ngayon') }}">
        <i class="fas fa-file-signature"></i> {{ __('Contractual') }}
    </span>
@endif
