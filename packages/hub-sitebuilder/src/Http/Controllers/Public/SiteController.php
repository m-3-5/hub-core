<?php

namespace M35\HubSitebuilder\Http\Controllers\Public;

use App\Models\Tenant;
use Illuminate\Routing\Controller;
use M35\HubSitebuilder\Services\SiteTranslations;
use Illuminate\View\View;

class SiteController extends Controller
{
    /** Scheda per gli assistenti con l'IA (solo con l'opzione «Fatti trovare dall'IA»). */
    public function llms(Tenant $tenant): \Illuminate\Http\Response
    {
        $site = $tenant->generatedSite;

        abort_unless($site && $site->isLive() && \M35\HubSitebuilder\Services\SiteAddons::active($site, 'ai'), 404);

        return response(\M35\HubSitebuilder\Services\SiteAddons::llmsText($tenant, $site), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function show(Tenant $tenant, ?string $lang = null): View
    {
        $site = $tenant->generatedSite;

        // Online solo se pubblicato E con una email vera confermata: un sito senza titolare verificato non si vede.
        // Il titolare (e l'amministratore) lo vede comunque in anteprima.
        $user = auth()->user();
        $preview = $user && ($user->isSuperAdmin() || $user->belongsToTenant($tenant));

        abort_unless($site && ($site->isLive() || ($preview && $site->isPublished())), 404);

        // Altre lingue: solo quelle pagate e già tradotte.
        $lang = $lang ?: 'it';
        abort_unless($lang === 'it' || in_array($lang, SiteTranslations::active($site), true), 404);

        return view('hub-sitebuilder::public.show', [
            'tenant' => $tenant,
            'site' => $site,
            'lang' => $lang,
            'langs' => SiteTranslations::active($site),
            'localAnswers' => SiteTranslations::localized($site->answers ?? [], $lang),
        ]);
    }
}
