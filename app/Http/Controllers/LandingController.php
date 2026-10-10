<?php

namespace App\Http\Controllers;

use App\Models\SiteLead;
use App\Notifications\SiteLeadNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

/** Landing «Siti web e app a Corigliano-Rossano»: offerta a tempo, tre pacchetti, modulo contatti. */
class LandingController extends Controller
{
    public function show(): View
    {
        $endsAt = Carbon::parse(config('landing.offer_ends'))->endOfDay();
        $offerActive = now()->lte($endsAt);

        $plans = collect(config('landing.plans'))->map(function (array $plan) use ($offerActive) {
            $plan['price'] = $offerActive ? $plan['price_offer'] : $plan['price_regular'];
            $plan['discount'] = $offerActive && $plan['price_regular'] > 0
                ? (int) round((1 - $plan['price_offer'] / $plan['price_regular']) * 100)
                : 0;

            return $plan;
        });

        return view('landing.siti-web', [
            'plans' => $plans,
            'offerActive' => $offerActive,
            'endsAt' => $endsAt,
            'zones' => config('landing.zones'),
            'whatsapp' => preg_replace('/\D+/', '', (string) config('landing.whatsapp')) ?: null,
            'phone' => config('landing.phone') ?: null,
            'renderedAt' => time(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $packages = collect(config('landing.plans'))->pluck('key')->push('app', 'altro')->all();

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['required', 'string', 'regex:/^[0-9+()\s.\-]{6,25}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'package' => ['nullable', 'in:'.implode(',', $packages)],
            'message' => ['nullable', 'string', 'max:800'],
            'consent' => ['accepted'],
        ], [
            'name.required' => 'Scrivi il tuo nome.',
            'phone.required' => 'Lascia un numero di telefono per ricontattarti.',
            'phone.regex' => 'Controlla il numero di telefono: usa solo cifre.',
            'email.email' => 'Controlla l\'indirizzo email.',
            'consent.accepted' => 'Serve il consenso per poterti ricontattare.',
        ]);

        // Se manca qualcosa si torna direttamente al modulo (#contatti), non in cima alla pagina.
        if ($validator->fails()) {
            return redirect()->to(route('landing.web').'#contatti')->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        $back = redirect()->to(route('landing.web').'#contatti');

        // Anti-spam senza fastidi per le persone: campo trappola nascosto e invio troppo rapido (< 3 secondi dal caricamento).
        $tooFast = (time() - (int) $request->input('rendered_at', 0)) < 3;

        if ($request->filled('company') || $tooFast) {
            return $back->with('lead_sent', true);
        }

        $lead = SiteLead::create([
            'name' => trim($data['name']),
            'phone' => trim($data['phone']),
            'email' => $data['email'] ?? null,
            'package' => $data['package'] ?? null,
            'message' => isset($data['message']) ? trim($data['message']) : null,
            'ip_hash' => hash('sha256', $request->ip().'|'.config('app.key')),
        ]);

        $to = config('landing.leads_email') ?: config('mail.leads_monitor_email');

        if ($to) {
            try {
                Notification::route('mail', $to)->notify(new SiteLeadNotification($lead));
            } catch (Throwable $e) {
                // La richiesta è già salvata in Dashboard → Richieste sito: non perderla per un problema di posta.
                Log::warning('Email richiesta sito non inviata', ['lead' => $lead->id, 'message' => $e->getMessage()]);
            }
        }

        return $back->with('lead_sent', true);
    }
}
