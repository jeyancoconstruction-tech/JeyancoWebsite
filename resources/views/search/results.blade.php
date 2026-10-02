@extends('layouts')

@section('page_title', 'Search Results')

@section('content')
<div class="search-page">

    {{-- The query and the count sit beside the title: the layout's own
         container is the page's frame, so there is not a second one here. --}}
    <x-page-header :title="__('Search Results')">
        <x-slot:badge>"{{ $query }}" &middot; {{ $results['total'] }} {{ __('result(s)') }}</x-slot:badge>
    </x-page-header>

    @if($results['total'] == 0)
        <div class="sr-none">
            <i data-lucide="search"></i>
            <div>
                {{ __('No results found for') }} "<strong>{{ $query }}</strong>".
                {{ __('Try a name, an Employee ID (e.g.') }} <code>12</code>), {{ __('a date (e.g.') }} <code>09/15/2026</code>),
                {{ __('a site, or a page (e.g.') }} <code>{{ __('payroll') }}</code>, <code>{{ __('attendance') }}</code>, <code>{{ __('settings') }}</code>).
            </div>
        </div>
    @else
        @foreach($results['categories'] as $cat)
        <div class="mb-4">
            <div class="d-flex align-items-center mb-3 sr-cat">
                <i data-lucide="{{ $cat['icon'] }}" class="me-2"></i>
                <h5 class="mb-0 fw-bold">{{ $cat['label'] }} <span class="sr-count">({{ count($cat['items']) }})</span></h5>
            </div>
            <div class="row g-3">
                @foreach($cat['items'] as $item)
                <div class="col-md-6 col-lg-4">
                    <a href="{{ $item['url'] }}" class="text-decoration-none">
                        <div class="card h-100 result-card">
                            <div class="card-body d-flex align-items-start justify-content-between">
                                <div class="pe-2" style="min-width: 0;">
                                    <h6 class="mb-1 sr-title">{{ $item['title'] }}</h6>
                                    <p class="small mb-0 sr-sub">{{ $item['subtitle'] }}</p>
                                </div>
                                <i data-lucide="arrow-right" class="sr-go"></i>
                            </div>
                        </div>
                    </a>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    @endif

    <div class="mt-4">
        <a href="{{ url()->previous() }}" class="btn btn-light border fw-600">
            <i data-lucide="arrow-left" class="me-1" style="width: 16px; height: 16px;"></i> {{ __('Back') }}
        </a>
    </div>
</div>

{{-- Theme tokens throughout: the titles were a navy that all but vanished
     on the dark theme's cards. --}}
<style>
    .sr-cat svg, .sr-cat i[data-lucide] { width: 20px; height: 20px; color: var(--brand); }
    .sr-cat h5 { color: var(--text-primary); }
    .sr-count { color: var(--text-muted); font-weight: 400; }
    .result-card { border: 1px solid var(--border) !important; background: var(--surface); box-shadow: var(--shadow-xs); transition: var(--transition); }
    .result-card:hover { box-shadow: var(--shadow-md) !important; transform: translateY(-2px); border-color: var(--brand) !important; }
    .sr-title { color: var(--brand); font-weight: 600; overflow-wrap: anywhere; }
    .sr-sub { color: var(--text-muted); overflow-wrap: anywhere; }
    .sr-go { flex: none; width: 18px; height: 18px; color: var(--brand); opacity: 0.6; }
    .sr-none { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-radius: var(--radius-md);
               background: var(--brand-subtle); border: 1px solid var(--border); color: var(--text-secondary); }
    .sr-none svg, .sr-none i[data-lucide] { flex: none; width: 22px; height: 22px; color: var(--brand); }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
</script>
@endsection
