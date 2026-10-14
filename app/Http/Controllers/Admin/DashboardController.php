<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TenantOverview;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use M35\HubPayments\Models\PayableOrder;
use M35\HubPayments\Models\PayableService;

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
        $summaries = TenantOverview::summaries($tenants);

        return view('admin.dashboard', compact('tenants', 'openTicketsCount', 'summaries'));
    }

    /** Scheda cliente: tutto il lavoro di un'attività in una pagina (solo super admin). */
    public function show(Tenant $tenant): View
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $s = TenantOverview::summaries(collect([$tenant]))[$tenant->id];

        $promos = $tenant->promos()->latest()->limit(10)->get();
        $offers = PayableService::query()->where('tenant_id', $tenant->id)->where('status', '!=', 'archived')->latest()->limit(10)->get();
        $ads = $tenant->classifiedAds()->latest()->limit(10)->get();
        $orders = PayableOrder::query()->where('tenant_id', $tenant->id)->where('status', 'paid')->latest('paid_at')->limit(6)->get();
        $users = $tenant->users()->orderBy('name')->get();

        return view('admin.tenants.show', compact('tenant', 's', 'promos', 'offers', 'ads', 'orders', 'users'));
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
