<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;

class DashboardController extends Controller
{
    public function index()
    {
        $tenants = Tenant::withCount('promos')
            ->with(['promos' => fn ($q) => $q->latest()->limit(8)])
            ->orderBy('name')
            ->get();

        return view('admin.dashboard', compact('tenants'));
    }
}
