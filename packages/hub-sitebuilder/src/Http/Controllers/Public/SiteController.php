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

        abort_unless($site && $site->isPublished(), 404);

        return view('hub-sitebuilder::public.show', [
            'tenant' => $tenant,
            'site' => $site,
        ]);
    }
}
