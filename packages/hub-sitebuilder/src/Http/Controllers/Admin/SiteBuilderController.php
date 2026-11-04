<?php

namespace M35\HubSitebuilder\Http\Controllers\Admin;

use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use M35\HubSitebuilder\Notifications\SiteEditLinkNotification;
use M35\HubSitebuilder\Services\SiteBuilderHeroGenerator;
use M35\HubSitebuilder\Services\SiteContent;
use M35\HubSitebuilder\Services\SiteCopyWriter;
use M35\HubSitebuilder\Services\SiteGuard;
use M35\HubSitebuilder\Services\SiteOwners;

class SiteBuilderController extends Controller
{
    public function show(Tenant $tenant): View|RedirectResponse
    {
        if ($locked = $this->premiumOnly($tenant)) {
            return $locked;
        }

        $site = $tenant->generatedSite;

        return view('hub-sitebuilder::admin.wizard', [
            'tenant' => $tenant,
            'site' => $site,
            'hasRealEmail' => SiteOwners::for($tenant)->isNotEmpty(),
        ]);
    }

    public function generate(Request $request, Tenant $tenant, SiteBuilderHeroGenerator $heroGenerator, SiteGuard $guard): RedirectResponse
    {
        if ($locked = $this->premiumOnly($tenant)) {
            return $locked;
        }

        $validated = $request->validate([
            ...SiteContent::rules(),
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s.\-]{6,30}$/'],
        ], ['phone.regex' => 'Controlla il numero di telefono: usa solo cifre.']);
        $answers = SiteContent::answers($validated);

        // Solo siti seri: niente spam né contenuti vietati. Se il controllo non risponde, non si pubblica.
        $check = $guard->check($tenant, $answers);

        if (! $check['ok']) {
            return back()->withInput()->withErrors(['tagline' => $check['unavailable']
                ? $check['reason']
                : 'Non posso pubblicare questo sito: '.$check['reason']]);
        }

        // Il telefono serve al pulsante «Chiamaci» e ai contatti del sito.
        if (filled($validated['phone'] ?? null)) {
            $tenant->update(['phone' => trim($validated['phone'])]);
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

    /** L'IA scrive frase, servizi, «chi siamo» e pulsante da poche parole (solo proposte: l'utente le rivede). */
    public function suggest(Request $request, Tenant $tenant, SiteCopyWriter $writer): JsonResponse
    {
        if ($this->premiumOnly($tenant)) {
            return response()->json(['ok' => false, 'message' => 'Il sito web è incluso nel livello Premium.'], 403);
        }

        // Risposta JSON anche in errore (l'app rende JSON solo per /api/*).
        $validator = Validator::make($request->all(), [
            'description' => ['required', 'string', 'min:10', 'max:600'],
            'city' => ['nullable', 'string', 'max:80'],
        ], [
            'description.required' => 'Scrivi qualche parola sulla tua attività.',
            'description.min' => 'Scrivi qualche parola in più (almeno 10 lettere).',
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();
        $copy = $writer->write($tenant, strip_tags($data['description']), isset($data['city']) ? strip_tags($data['city']) : null);

        if (! $copy) {
            return response()->json(['ok' => false, 'message' => 'L\'IA non risponde in questo momento: scrivi tu i testi, oppure riprova tra un minuto.']);
        }

        return response()->json(['ok' => true, ...$copy]);
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

    /** Il sito con Max è incluso nel livello Premium (e durante la prova). */
    private function premiumOnly(Tenant $tenant): ?RedirectResponse
    {
        if ($tenant->hasPremiumFeatures() || auth()->user()?->isSuperAdmin()) {
            return null;
        }

        return redirect()->route('admin.billing.show', $tenant)->withErrors(['billing' => 'Il sito web con Max è incluso nel livello Premium: passa a Premium per usarlo.']);
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
