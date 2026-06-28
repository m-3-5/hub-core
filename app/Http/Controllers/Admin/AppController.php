<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\HubModules;
use Illuminate\View\View;

class AppController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $tenants = $user->accessibleTenants();

        return view('app.index', compact('tenants', 'user'));
    }

    public function home(Tenant $tenant, HubModules $modules): View
    {
        $user = auth()->user();
        $hubModules = $modules->forTenant($tenant);
        $recentPromos = $tenant->promos()->published()->active()->latest('published_at')->limit(4)->get();
        $expiredCount = $tenant->promos()->expired()->count();

        return view('app.home', compact('tenant', 'user', 'hubModules', 'recentPromos', 'expiredCount'));
    }
}
