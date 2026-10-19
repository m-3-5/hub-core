<?php

namespace M35\HubSitebuilder\Http\Controllers\Admin;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use M35\HubSitebuilder\Notifications\SiteEditLinkNotification;
use M35\HubSitebuilder\Services\SiteBuilderHeroGenerator;
use M35\HubSitebuilder\Services\SiteContent;
use M35\HubSitebuilder\Services\SiteGuard;
use M35\HubSitebuilder\Services\SiteOwners;

class SiteBuilderController extends Controller
{
    public function show(Tenant $tenant): View
    {
        $site = $tenant->generatedSite;

        return view('hub-sitebuilder::admin.wizard', [
            'tenant' => $tenant,
            'site' => $site,
            'hasRealEmail' => SiteOwners::for($tenant)->isNotEmpty(),
        ]);
    }

    public function generate(Request $request, Tenant $tenant, SiteBuilderHeroGenerator $heroGenerator, SiteGuard $guard): RedirectResponse
    {
        $validated = $request->validate(SiteContent::rules());
        $answers = SiteContent::answers($validated);

        // Solo siti seri: niente spam né contenuti vietati. Se il controllo non risponde, non si pubblica.
        $check = $guard->check($tenant, $answers);

        if (! $check['ok']) {
            return back()->withInput()->withErrors(['tagline' => $check['unavailable']
                ? $check['reason']
                : 'Non posso pubblicare questo sito: '.$check['reason']]);
        }

        $hero = $heroGenerator->generate($tenant, SiteContent::heroDir($tenant));
        $site = SiteContent::save($tenant, $answers, $hero);

        // Il link segreto per modificare il sito parte alla prima pubblicazione, solo se c'è una email vera.
        $linkSent = false;

        if (! $site->edit_link_sent_at) {
            $linkSent = $this->sendEditLink($tenant, $site);
        }

        $message = 'Sito creato! Eccolo qui: '.route('site.public.show', $tenant);

        if ($tenant->isGuestPending()) {
            $message .= ' — sarà visibile al pubblico appena confermi la tua email.';
        } elseif ($linkSent) {
            $message .= ' — ti abbiamo scritto un link segreto per modificarlo quando vuoi.';
        }

        return redirect()->route('admin.sitebuilder.show', $tenant)->with('success', $message);
    }

    /** Nuovo link segreto per modificare il sito (il precedente smette di funzionare). */
    public function editLink(Tenant $tenant): RedirectResponse
    {
        $site = $tenant->generatedSite;

        abort_unless($site, 404);

        if (! $this->sendEditLink($tenant, $site)) {
            return back()->withErrors(['tagline' => 'Serve una email vera del titolare: aggiungila dal Profilo e riprova. Il link arriva solo a quella.']);
        }

        return back()->with('success', 'Ti abbiamo scritto un nuovo link segreto per modificare il sito. Quello di prima non funziona più.');
    }

    private function sendEditLink(Tenant $tenant, $site): bool
    {
        $owners = SiteOwners::for($tenant);

        if ($owners->isEmpty()) {
            return false;
        }

        $token = $site->issueEditToken();
        Notification::send($owners, new SiteEditLinkNotification($tenant, $token));

        return true;
    }
}
