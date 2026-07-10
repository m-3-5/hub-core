<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $logs = ActivityLog::with(['tenant', 'user'])
            ->when($request->filled('tenant'), fn ($q) => $q->whereHas('tenant', fn ($t) => $t->where('slug', $request->input('tenant'))))
            ->latest()
            ->limit(200)
            ->get();

        return view('admin.activity.index', compact('logs'));
    }
}
