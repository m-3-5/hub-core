<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $tenants = Tenant::withCount('promos')
            ->with(['promos' => fn ($q) => $q->latest()->limit(8)])
            ->orderBy('name')
            ->get();

        return view('admin.dashboard', compact('tenants'));
    }
}
