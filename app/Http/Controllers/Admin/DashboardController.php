<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
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

        $openTicketsCount = Ticket::where('status', 'open')->count();

        return view('admin.dashboard', compact('tenants', 'openTicketsCount'));
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $orphanUserIds = $tenant->users()
            ->where('is_super_admin', false)
            ->get()
            ->filter(fn (User $user) => $user->tenants()->where('tenants.id', '!=', $tenant->id)->doesntExist())
            ->pluck('id');

        $tenantName = $tenant->name;
        $tenant->delete();

        User::whereIn('id', $orphanUserIds)->delete();

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Tenant "'.$tenantName.'" eliminato, con tutti i suoi dati (servizi, promo, registro pagamenti).');
    }
}
