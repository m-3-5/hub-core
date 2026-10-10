<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\GeminiPromoGenerator;
use App\Support\TenantPromoQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use M35\HubPayments\Support\CatalogKind;
use M35\HubPayments\Support\TenantStripeConfig;
use Throwable;

/**
 * Creazione guidata a schermo intero (una domanda per schermata) di promo, prodotti e servizi.
 * Il passo «foto» fa leggere l'immagine all'IA, che propone titolo, descrizione e (se scritto) prezzo.
 * Il salvataggio vero lo fanno i controller esistenti (PromoController / ServiceController).
 */
class WizardController extends Controller
{
    private const KINDS = ['promo', 'product', 'service'];

    public function show(Tenant $tenant, string $kind): View|RedirectResponse
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);
        $this->guardModule($tenant, $kind);

        if ($kind !== 'promo' && ! TenantStripeConfig::isConfigured($tenant)) {
            // Si inizia a vendere: prima si sceglie come ricevere i soldi (con le guide a portata di mano).
            return redirect()
                ->route('admin.payout.setup', $tenant)
                ->with('status', 'Prima di vendere scegli come vuoi ricevere i soldi: ti accompagniamo passo passo.');
        }

        $meta = $kind === 'promo'
            ? [
                'action' => route('admin.promos.store', $tenant),
                'exit' => route('admin.promos.index', $tenant),
                'advanced' => route('admin.promos.create', $tenant),
                'noun' => 'promo',
                'fields' => ['title' => 'manual_title', 'description' => 'manual_description', 'file' => 'image'],
            ]
            : (function () use ($tenant, $kind) {
                $catalog = CatalogKind::for($kind);

                return [
                    'action' => route('admin.'.$catalog['route'].'.store', $tenant),
                    'exit' => route('admin.'.$catalog['route'].'.index', $tenant),
                    'advanced' => route('admin.'.$catalog['route'].'.create', $tenant),
                    'noun' => $catalog['singular'],
                    'fields' => ['title' => 'title', 'description' => 'description', 'file' => 'cover_image'],
                ];
            })();

        return view('admin.wizard.index', [
            'tenant' => $tenant,
            'kind' => $kind,
            'meta' => $meta,
            'color' => $tenant->primary_color ?: '#e91e8c',
            'overQuota' => $kind === 'promo' && ! TenantPromoQuota::hasIncludedSlot($tenant),
            'defaultEnd' => now()->addMonth()->toDateString(),
            'today' => now()->toDateString(),
            // Ospite: l'ultimo passo è la registrazione (email) per poter pubblicare.
            'guestPending' => $kind === 'promo' && $tenant->isGuestPending(),
        ]);
    }

    /** Legge la foto con l'IA e propone titolo, descrizione e prezzo (se visibile). */
    public function suggest(Request $request, Tenant $tenant, GeminiPromoGenerator $generator): JsonResponse
    {
        // Risposta JSON anche in errore (l'app rende JSON solo per /api/*): la procedura guidata deve poterla leggere.
        $validator = Validator::make($request->all(), [
            'kind' => ['required', 'in:'.implode(',', self::KINDS)],
            'image' => ['required', 'image', 'max:10240'],
        ]);

        if ($validator->fails()) {
            return response()->json(['ok' => false, 'message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();

        $this->guardModule($tenant, $data['kind']);

        $file = $request->file('image');

        try {
            $result = $generator->generateFromImage($file->getRealPath(), $file->getMimeType(), null, $data['kind']);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Non sono riuscito a leggere la foto: scrivi tu il titolo, va benissimo.',
            ]);
        }

        $price = trim((string) ($result['price'] ?? ($result['offers'][0]['price'] ?? '')));
        unset($result['_gemini_model']);

        return response()->json([
            'ok' => true,
            'title' => Str::limit(strip_tags((string) ($result['title'] ?? '')), 120, ''),
            'description' => Str::limit(strip_tags((string) ($result['description'] ?? '')), 600, ''),
            'price' => $this->numericPrice($price),
            'priceText' => Str::limit(strip_tags($price), 40, ''),
            // Solo per le promo: il salvataggio riusa questa lettura senza richiamare Gemini.
            'payload' => $data['kind'] === 'promo' ? $result : null,
        ]);
    }

    private function guardModule(Tenant $tenant, string $kind): void
    {
        $moduleKey = $kind === 'promo' ? 'promo' : 'services';
        $module = collect(config('hub.modules'))->firstWhere('key', $moduleKey);
        $allowed = $module['for_types'] ?? ['azienda', 'privato', 'ente'];

        abort_unless(in_array($tenant->type ?: 'azienda', $allowed, true), 403, 'Questa sezione non è disponibile per il tuo tipo di attività.');
    }

    /** "12,50 €" → "12.50"; vuoto se non è un importo semplice. */
    private function numericPrice(string $text): string
    {
        if (! preg_match('/(\d{1,5}(?:[.,]\d{1,2})?)/', $text, $m)) {
            return '';
        }

        return str_replace(',', '.', $m[1]);
    }
}
