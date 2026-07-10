<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantBrandManager;
use App\Support\HubModules;
use Illuminate\View\View;
use M35\HubPayments\Models\PayableService;

class AppController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $tenants = $user->accessibleTenants();

        return view('app.index', compact('tenants', 'user'));
    }

    public function home(Tenant $tenant, HubModules $modules, TenantBrandManager $brand): View
    {
        $user = auth()->user();
        $hubModules = $modules->forTenant($tenant);
        $recentPromos = $tenant->promos()->published()->active()->latest('published_at')->limit(8)->get();
        $archivedPromos = $tenant->promos()->expired()->latest('ends_at')->limit(8)->get();
        $expiredCount = $archivedPromos->count();
        $recentServices = $tenant->type !== 'privato'
            ? PayableService::query()
                ->where('tenant_id', $tenant->id)
                ->where('type', 'service')
                ->where('status', '!=', 'archived')
                ->latest()
                ->limit(8)
                ->get()
            : collect();
        $brandHasColor = $brand->hasColor($tenant);
        $brandHasLogo = $brand->hasLogo($tenant);

        return view('app.home', compact(
            'tenant', 'user', 'hubModules', 'recentPromos', 'archivedPromos', 'expiredCount', 'recentServices',
            'brandHasColor', 'brandHasLogo',
        ));
    }
}
