@extends('layouts')
@section('page_title', 'Users & Roles')

@section('content')
<div class="mod-page">

    @include('modules._head', [
        'title' => __('Users & Roles'),
        'sub'   => __('Which role each account carries, and what that role may open. Creating accounts, disabling them and resetting passwords stay in Account Management.'),
        'actions' => auth()->user()?->isAdmin()
            ? '<a class="mod-btn" href="' . route('accounts.index') . '"><i class="fas fa-user-gear"></i> ' . __('Account Management') . '</a>'
            : '',
    ])

    @include('modules._flash')

    <div class="mod-stats">
        @foreach($roles as $key => $label)
            <div class="mod-stat">
                <p class="mod-stat-label">{{ $label }}</p>
                <p class="mod-stat-value">{{ $counts[$key] ?? 0 }}</p>
                <p class="mod-stat-sub">{{ __('account(s)') }}</p>
            </div>
        @endforeach
    </div>

    <div class="mod-card">
        <form method="GET" class="mod-filters">
            <div class="mod-filter mod-filter-grow">
                <label for="uq">{{ __('Search') }}</label>
                <input id="uq" class="form-control" type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Name or username') }}">
            </div>
            <div class="mod-filter">
                <label for="urole">{{ __('Role') }}</label>
                <select id="urole" class="form-select" name="role">
                    <option value="">{{ __('All roles') }}</option>
                    @foreach($roles as $k => $v)
                        <option value="{{ $k }}" @selected(request('role') === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mod-filter-actions">
                <button class="mod-btn primary" type="submit"><i class="fas fa-magnifying-glass"></i> {{ __('Apply') }}</button>
                <a class="mod-btn" href="{{ route('users-roles.index') }}">{{ __('Reset') }}</a>
            </div>
        </form>

        <div class="mod-table-wrap">
            <table class="mod-table">
                <thead>
                    <tr>
                        <th>{{ __('Account') }}</th>
                        <th>{{ __('Username') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Role') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($users as $u)
                    <tr>
                        <td>@include('modules._person', ['name' => $u->name ?: $u->username, 'sub' => $u->email ?: ''])</td>
                        <td class="muted">{{ $u->username }}</td>
                        <td>
                            <span class="mod-badge {{ $u->is_active ? 'ok' : 'muted' }}">
                                <span class="dot"></span>{{ $u->is_active ? __('Active') : __('Disabled') }}
                            </span>
                        </td>
                        <td colspan="2">
                            <form method="POST" action="{{ route('users-roles.update', $u) }}"
                                  style="display:flex;gap:8px;align-items:center;">
                                @csrf @method('PATCH')
                                <select class="form-select" name="role" style="height:34px;font-size:13px;max-width:200px;">
                                    @foreach($roles as $k => $v)
                                        <option value="{{ $k }}" @selected($u->role === $k)>{{ $v }}</option>
                                    @endforeach
                                </select>
                                <button class="mod-btn sm" type="submit"><i class="fas fa-check"></i> {{ __('Save') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    @include('modules._empty', ['cols' => 5, 'icon' => 'fa-shield-halved', 'title' => __('No accounts found')])
                @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())<div class="mod-pager">{{ $users->links() }}</div>@endif
    </div>

    <div class="mod-card">
        <div class="mod-card-head">
            <h2 class="mod-card-title"><i class="fas fa-table-cells"></i> {{ __('Permission Matrix') }}</h2>
            <span class="mod-sub" style="margin:0;">{{ __('Applies to the modules below only; older screens keep their own guards.') }}</span>
        </div>
        <div class="mod-table-wrap">
            <table class="mod-table">
                <thead>
                    <tr>
                        <th>{{ __('Module') }}</th>
                        @foreach($roles as $label)<th style="text-align:center;">{{ $label }}</th>@endforeach
                    </tr>
                </thead>
                <tbody>
                @foreach($modules as $key => $label)
                    <tr>
                        <td class="strong">{{ $label }}</td>
                        @foreach($roles as $roleKey => $roleLabel)
                            <td style="text-align:center;">
                                @if($matrix[$roleKey][$key] ?? false)
                                    <i class="fas fa-check" style="color:var(--success);" title="{{ __('Allowed') }}"></i>
                                @else
                                    <span style="color:var(--text-muted);" title="{{ __('No access') }}">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('modules._kit')
@endsection

