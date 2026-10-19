<?php

namespace M35\HubSitebuilder\Http\Controllers\Public;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use M35\HubSitebuilder\Models\GeneratedSite;
use M35\HubSitebuilder\Services\SiteContent;
use M35\HubSitebuilder\Services\SiteGuard;

/** Modifica del sito con il link segreto arrivato per email: nessun account, la email vera è la chiave. */
class SiteEditController extends Controller
{
    public function show(string $token): View
    {
        $site = $this->site($token);

        return view('hub-sitebuilder::public.edit', [
            'site' => $site,
            'tenant' => $site->tenant,
            'token' => $token,
            'answers' => $site->answers ?? [],
        ]);
    }

    public function update(Request $request, string $token, SiteGuard $guard): RedirectResponse
    {
        $site = $this->site($token);
        $tenant = $site->tenant;

        $validated = $request->validate(SiteContent::rules());
        $answers = SiteContent::answers($validated);

        $check = $guard->check($tenant, $answers);

        if (! $check['ok']) {
            return back()->withInput()->withErrors(['tagline' => $check['unavailable']
                ? $check['reason']
                : 'Non posso pubblicare questo sito: '.$check['reason']]);
        }

        SiteContent::save($tenant, $answers);

        return redirect()->route('site.edit.show', $token)->with('saved', true);
    }

    private function site(string $token): GeneratedSite
    {
        $site = GeneratedSite::findByEditToken($token);

        abort_unless($site && $site->tenant, 404);

        return $site;
    }
}
