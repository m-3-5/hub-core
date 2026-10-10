<?php

namespace App\Http\Controllers;

use App\Models\SiteLead;
use App\Models\SiteOrder;
use App\Notifications\SiteLeadNotification;
use App\Services\SiteOrderBilling;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

/** Landing «Siti web e app a Corigliano-Rossano»: offerta a tempo, tre pacchetti, modulo contatti. */
class LandingController extends Controller
{
    /** Pacchetti con il prezzo del momento (offerta o listino) e la rata mensile della promo «1 € per partire». */
    private function plans(bool $offerActive): Collection
    {
        $months = max(1, (int) config('landing.start.months'));

        return collect(config('landing.plans'))->map(function (array $plan) use ($offerActive, $months) {
            $plan['price'] = $offerActive ? $plan['price_offer'] : $plan['price_regular'];
            $plan['discount'] = $offerActive && $plan['price_regular'] > 0
                ? (int) round((1 - $plan['price_offer'] / $plan['price_regular']) * 100)
                : 0;
            $plan['installment_cents'] = SiteOrderBilling::installmentCents($plan['price'], $months);

            return $plan;
        });
    }

    public function show(): View
    {
        $endsAt = Carbon::parse(config('landing.offer_ends'))->endOfDay();
        $offerActive = now()->lte($endsAt);

        $plans = $this->plans($offerActive);

        return view('landing.siti-web', [
            'plans' => $plans,
            'startActive' => $offerActive && config('landing.start.enabled') && (bool) config('services.hub_billing.secret_key'),
            'start' => config('landing.start'),
            'offerActive' => $offerActive,
            'endsAt' => $endsAt,
            'zones' => config('landing.zones'),
            'whatsapp' => preg_replace('/\D+/', '', (string) config('landing.whatsapp')) ?: null,
            'phone' => config('landing.phone') ?: null,
            'renderedAt' => time(),
        ]);
    }

    /** «1 € per partire»: salva il cliente e lo manda al pagamento reale su Stripe. */
    public function start(Request $request): RedirectResponse
    {
        $endsAt = Carbon::parse(config('landing.offer_ends'))->endOfDay();
        $plans = $this->plans(now()->lte($endsAt))->keyBy('key');
        $back = redirect()->to(route('landing.web').'#parti');

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['required', 'string', 'regex:/^[0-9+()\s.\-]{6,25}$/'],
            'email' => ['required', 'email', 'max:190'],
            'package' => ['required', 'in:'.$plans->keys()->implode(',')],
            'consent' => ['accepted'],
        ], [
            'name.required' => 'Scrivi il tuo nome.',
            'phone.required' => 'Lascia un numero di telefono per ricontattarti.',
            'phone.regex' => 'Controlla il numero di telefono: usa solo cifre.',
            'email.required' => 'Serve la tua email: ti mandiamo la conferma e la ricevuta.',
            'email.email' => 'Controlla l\'indirizzo email.',
            'package.required' => 'Scegli il sito che vuoi.',
            'package.in' => 'Scegli il sito che vuoi.',
            'consent.accepted' => 'Serve il consenso alle condizioni per poter partire.',
        ]);

        if ($validator->fails()) {
            return $back->withErrors($validator, 'start')->withInput();
        }

        $secretKey = config('services.hub_billing.secret_key');

        if (! config('landing.start.enabled') || now()->gt($endsAt) || ! $secretKey) {
            return $back->withErrors(['package' => 'L\'offerta non è al momento disponibile online: scrivici su WhatsApp o lascia una richiesta qui sotto.'], 'start');
        }

        // Anti-spam senza fastidi: campo trappola nascosto e invio troppo rapido.
        if ($request->filled('company') || (time() - (int) $request->input('rendered_at', 0)) < 3) {
            return $back->withErrors(['package' => 'Riprova tra qualche secondo.'], 'start');
        }

        $data = $validator->validated();
        $plan = $plans[$data['package']];
        $months = max(1, (int) config('landing.start.months'));

        $lead = SiteLead::create([
            'name' => trim($data['name']),
            'phone' => trim($data['phone']),
            'email' => $data['email'],
            'package' => $data['package'],
            'message' => '1 € per partire: in attesa del pagamento',
            'source' => 'landing-1-euro',
            'ip_hash' => hash('sha256', $request->ip().'|'.config('app.key')),
        ]);

        $order = SiteOrder::create([
            'site_lead_id' => $lead->id,
            'name' => $lead->name,
            'phone' => $lead->phone,
            'email' => $lead->email,
            'plan' => $plan['key'],
            'start_cents' => (int) config('landing.start.start_eur') * 100,
            'trial_days' => (int) config('landing.start.trial_days'),
            'installments' => $months,
            'installment_cents' => $plan['installment_cents'],
            'package_cents' => $plan['price'] * 100,
        ]);

        try {
            $session = (new SiteOrderBilling($secretKey))->createCheckout($order);
        } catch (Throwable $e) {
            report($e);

            return $back->withErrors(['package' => 'Il pagamento non è partito: riprova tra poco o scrivici su WhatsApp, ti aiutiamo noi.'], 'start')->withInput();
        }

        $order->update(['stripe_session_id' => $session['id']]);

        return redirect()->away($session['url']);
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
