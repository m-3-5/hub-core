<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\TenantBrandManager;
use App\Support\AppHomeTiles;
use App\Support\HubModules;
use App\Support\TenantOverview;
use Illuminate\View\View;
use M35\HubPayments\Models\PayableService;
use M35\HubSitebuilder\Models\GeneratedSite;

class AppController extends Controller
{
    public function index(AppHomeTiles $tiles): View
    {
        $user = auth()->user();

        // App aperta senza account (anche dall'icona sul telefono): tutti i servizi, si prova subito da ospite.
        if (! $user) {
            return view('app.guest', [
                'tiles' => $tiles->forGuest(),
                'launchEur' => (int) config('services.hub_billing.launch_offer_price_eur', 1),
            ]);
        }

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
        $newCustomerTicketsCount = $tenant->customerTickets()->where('status', 'new')->count();

        $overview = TenantOverview::summaries(collect([$tenant]))[$tenant->id];

        // Riquadro grande «Sito web a 1 €» (solo per chi può avere un sito: aziende ed enti)
        $website = collect($hubModules)->firstWhere('key', 'website');
        $showSite = $website && $website['fits_type'];
        $hasSite = $showSite && class_exists(GeneratedSite::class)
            && GeneratedSite::query()->where('tenant_id', $tenant->id)->whereIn('status', ['ready', 'published'])->exists();
        $siteUrl = $showSite ? ($website['url'] ?? route('admin.billing.show', $tenant)) : null;
        $launchEur = (int) config('services.hub_billing.launch_offer_price_eur', 1);
        $tiles = app(AppHomeTiles::class)->forTenant($tenant);

        return view('app.home', compact(
            'tenant', 'user', 'hubModules', 'recentPromos', 'archivedPromos', 'expiredCount', 'recentServices',
            'brandHasColor', 'brandHasLogo', 'newCustomerTicketsCount', 'overview',
            'showSite', 'hasSite', 'siteUrl', 'launchEur', 'tiles',
        ));
    }
}
