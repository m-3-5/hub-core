<?php

namespace App\Http\Controllers;

use App\Models\ClassifiedAd;
use App\Models\Tenant;
use App\Notifications\ClassifiedContactRequestNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use M35\HubPayments\Support\ProtectedCheckout;
use RuntimeException;
use Throwable;

class ClassifiedPublicController extends Controller
{
    /** Bacheca globale (tutti i tenant) se $tenant è null, altrimenti solo quella dell'azienda. */
    public function board(Request $request, ?Tenant $tenant = null): View
    {
        $query = ClassifiedAd::published()->with('tenant')->latest('published_at');

        if ($tenant) {
            $query->where('tenant_id', $tenant->id);
        }

        $search = trim((string) $request->query('q'));
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(fn ($q) => $q->where('zone', 'like', $like)->orWhere('title', 'like', $like));
        }

        // Quanti annunci per categoria (con la stessa bacheca e ricerca, senza il filtro categoria).
        $counts = (clone $query)->reorder()->selectRaw('category, count(*) as c')->groupBy('category')->pluck('c', 'category')->all();

        $category = $request->query('categoria');
        if ($category && array_key_exists($category, ClassifiedAd::CATEGORIES)) {
            $query->where('category', $category);
        } else {
            $category = null;
        }

        return view('classifieds.board', [
            'tenant' => $tenant,
            'ads' => $query->paginate(18)->withQueryString(),
            'category' => $category,
            'search' => $search,
            'counts' => $counts,
            'total' => array_sum($counts),
            'qs' => $search !== '' ? ['q' => $search] : [],
        ]);
    }

    public function show(Tenant $tenant, ClassifiedAd $classifiedAd): View
    {
        abort_unless($classifiedAd->tenant_id === $tenant->id && $classifiedAd->isPublished(), 404);

        return view('classifieds.show', [
            'tenant' => $tenant,
            'ad' => $classifiedAd,
            'protectedBuy' => $classifiedAd->offersProtectedSale() && ProtectedCheckout::available($tenant),
        ]);
    }

    /** «Compra con pagamento protetto» dall'annuncio: il compratore accetta le condizioni e paga a Hub Core. */
    public function buy(Request $request, Tenant $tenant, ClassifiedAd $classifiedAd): RedirectResponse
    {
        abort_unless($classifiedAd->tenant_id === $tenant->id, 404);

        if (! $classifiedAd->offersProtectedSale() || ! ProtectedCheckout::available($tenant)) {
            return back()->withErrors(['buy' => $classifiedAd->isSold()
                ? 'Questo oggetto è già stato venduto.'
                : 'Questo annuncio non si può acquistare online: contatta l\'inserzionista.']);
        }

        $data = $request->validate(['customer_email' => ['nullable', 'email', 'max:190']]);

        if (! $request->boolean('accept_buyer_terms')) {
            return back()->withErrors(['buy' => 'Per acquistare devi accettare le condizioni di acquisto protetto.']);
        }

        try {
            $url = ProtectedCheckout::beginForAd($tenant, $classifiedAd, $data['customer_email'] ?? null, $request->ip());
        } catch (RuntimeException $e) {
            Log::warning('Pagamento protetto annuncio: sessione non creata', ['ad' => $classifiedAd->id, 'message' => $e->getMessage()]);

            return back()->withErrors(['buy' => 'Non riesco ad aprire il pagamento in questo momento. Riprova tra poco.']);
        }

        return redirect()->away($url);
    }

    public function contact(Request $request, Tenant $tenant, ClassifiedAd $classifiedAd): RedirectResponse
    {
        abort_unless($classifiedAd->tenant_id === $tenant->id && $classifiedAd->isPublished(), 404);

        if ($request->filled('website')) {
            return back()->with('contact_success', true);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $tenant->customerTickets()->create([
            'classified_ad_id' => $classifiedAd->id,
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'message' => $validated['message'],
            'status' => 'new',
        ]);

        // Utenti dell'attività con un indirizzo vero (gli account ospite hanno un indirizzo segnaposto che rimbalza).
        $recipients = $tenant->users->reject(fn ($u) => ! $u->email || str_ends_with(strtolower($u->email), '@guest.hub-core.local'));

        $notification = new ClassifiedContactRequestNotification(
            $classifiedAd,
            $validated['name'],
            $validated['email'] ?? null,
            $validated['phone'] ?? null,
            $validated['message'],
        );

        try {
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, $notification);
            }

            // Email di contatto dell'annuncio: usata solo da Hub per inoltrare i messaggi, mai mostrata al pubblico.
            $contact = strtolower(trim((string) $classifiedAd->contact_email));
            $alreadyNotified = $recipients->contains(fn ($u) => strtolower($u->email) === $contact);

            if ($contact !== '' && filter_var($contact, FILTER_VALIDATE_EMAIL) && ! $alreadyNotified) {
                Notification::route('mail', $contact)->notify($notification);
            }
        } catch (Throwable $e) {
            // Il messaggio è già salvato tra i "Messaggi clienti": non far fallire la richiesta.
        }

        return back()->with('contact_success', true);
    }
}
