<?php

namespace M35\HubSitebuilder\Http\Controllers\Public;

use App\Models\Tenant;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function show(Tenant $tenant): View
    {
        $site = $tenant->generatedSite;

        // Online solo se pubblicato E con una email vera confermata: un sito senza titolare verificato non si vede.
        // Il titolare (e l'amministratore) lo vede comunque in anteprima.
        $user = auth()->user();
        $preview = $user && ($user->isSuperAdmin() || $user->belongsToTenant($tenant));

        abort_unless($site && ($site->isLive() || ($preview && $site->isPublished())), 404);

        return view('hub-sitebuilder::public.show', [
            'tenant' => $tenant,
            'site' => $site,
        ]);
    }
}
