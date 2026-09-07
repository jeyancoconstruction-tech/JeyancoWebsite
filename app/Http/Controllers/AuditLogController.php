<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/** Read-only. There is no write, update or delete action on this screen. */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::with('user')
            ->when($request->filled('module'), fn ($q) => $q->where('module', $request->module))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->when($request->filled('q'), fn ($q) => $q->where('description', 'like', '%' . $request->q . '%'))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('audit.index', [
            'logs'    => $logs,
            'modules' => AuditLog::select('module')->distinct()->orderBy('module')->pluck('module'),
            'actions' => AuditLog::select('action')->distinct()->orderBy('action')->pluck('action'),
            'users'   => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}

