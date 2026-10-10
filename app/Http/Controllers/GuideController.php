<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/** Guide pubbliche su come ricevere i soldi delle vendite + condizioni economiche. */
class GuideController extends Controller
{
    /** @return array<string, array{title: string, emoji: string, summary: string, level: string}> */
    public static function guides(): array
    {
        return [
            'pagamenti-protetti' => ['title' => 'Pagamenti protetti Hub Core', 'emoji' => '🛡️', 'level' => 'Consigliato', 'summary' => 'Il cliente paga tramite Hub Core e tu ricevi i soldi dopo la consegna. Compratore e venditore sono tutelati.'],
            'stripe' => ['title' => 'Aprire un conto Stripe', 'emoji' => '💳', 'level' => 'Vendita diretta', 'summary' => 'Incassi con carta direttamente sul tuo sito, con il tuo conto Stripe.'],
            'paypal' => ['title' => 'Aprire un conto PayPal', 'emoji' => '🅿️', 'level' => 'Vendita diretta', 'summary' => 'Ricevi pagamenti con PayPal: come aprire un conto business e cosa controllare.'],
            'bonifico' => ['title' => 'Ricevere con bonifico', 'emoji' => '🏦', 'level' => 'Vendita diretta', 'summary' => 'Il cliente ti paga con bonifico sul tuo IBAN: come farlo in modo ordinato e sicuro.'],
            'carte-e-conti-online' => ['title' => 'Carte prepagate e conti online', 'emoji' => '🪪', 'level' => 'Utile a tutti', 'summary' => 'Come avere un IBAN o una carta dedicata alle vendite, senza mescolare i soldi personali.'],
        ];
    }

    public function index(): View
    {
        return view('guides.index', ['guides' => self::guides()]);
    }

    public function show(string $slug): View
    {
        $guides = self::guides();

        abort_unless(isset($guides[$slug]), 404);

        return view('guides.show', ['slug' => $slug, 'guide' => $guides[$slug], 'guides' => $guides]);
    }

    public function economicTerms(): View
    {
        return view('guides.economic-terms');
    }
}
