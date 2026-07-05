@extends('layouts')

@section('page_title', 'Search Results')

@section('content')
<div class="container-fluid px-4 py-4">

    <div class="search-header" style="background: linear-gradient(135deg, #1e3a8a, #1e40af); padding: 26px 24px; border-radius: 12px; color: #fff; margin-bottom: 24px;">
        <h2 class="mb-1" style="font-weight: 800;">Search Results</h2>
        <p class="mb-0" style="color: rgba(255,255,255,0.85);">
            Query: <strong style="color:#fff;">"{{ $query }}"</strong> &middot;
            <strong style="color:#fff;">{{ $results['total'] }}</strong> result(s)
        </p>
    </div>

    @if($results['total'] == 0)
        <div class="alert d-flex align-items-center" style="background: #dbeafe; border: 1px solid #bfdbfe; color: #1e40af; border-radius: 10px;">
            <i data-lucide="search" class="me-3" style="width: 22px; height: 22px;"></i>
            <div>
                No results found for "<strong>{{ $query }}</strong>". Try a name, Employee ID (e.g. <code>12</code>),
                a date, or a module (e.g. <code>payroll</code>, <code>attendance</code>, <code>settings</code>).
            </div>
        </div>
    @else
        @foreach($results['categories'] as $cat)
        <div class="mb-4">
            <div class="d-flex align-items-center mb-3">
                <i data-lucide="{{ $cat['icon'] }}" class="me-2" style="width: 20px; height: 20px; color: #1e3a8a;"></i>
                <h5 class="mb-0 fw-bold">{{ $cat['label'] }} <span class="text-muted fw-normal">({{ count($cat['items']) }})</span></h5>
            </div>
            <div class="row g-3">
                @foreach($cat['items'] as $item)
                <div class="col-md-6 col-lg-4">
                    <a href="{{ $item['url'] }}" class="text-decoration-none">
                        <div class="card h-100 border-0 shadow-sm result-card">
                            <div class="card-body d-flex align-items-start justify-content-between">
                                <div class="pe-2">
                                    <h6 class="mb-1" style="color: #1e3a8a; font-weight: 600;">{{ $item['title'] }}</h6>
                                    <p class="small text-muted mb-0">{{ $item['subtitle'] }}</p>
                                </div>
                                <i data-lucide="arrow-right" style="width: 18px; height: 18px; color: #1e3a8a; opacity: 0.6;"></i>
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
            <i data-lucide="arrow-left" class="me-1" style="width: 16px; height: 16px;"></i> Back
        </a>
    </div>
</div>

<style>
    .result-card { border: 1px solid #e2e8f0 !important; background: #ffffff; transition: all 0.2s ease; }
    .result-card:hover { box-shadow: 0 10px 22px rgba(30,58,138,0.12) !important; transform: translateY(-2px); border-color: #bfdbfe !important; }
    [data-bs-theme="dark"] .result-card { background: #151d2e; border-color: #283449 !important; }
    [data-bs-theme="dark"] .search-header { box-shadow: 0 4px 14px rgba(0,0,0,0.4); }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
</script>
@endsection
