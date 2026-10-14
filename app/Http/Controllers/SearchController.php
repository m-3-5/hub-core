<?php

namespace App\Http\Controllers;

use App\Models\ClassifiedAd;
use App\Models\Promo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use M35\HubPayments\Models\PayableService;

/**
 * Ricerca interna su tutto ciò che è pubblico (promo attive, servizi e prodotti, annunci). Legge direttamente
 * dal database: ogni contenuto nuovo è subito ricercabile, senza indici da aggiornare.
 */
class SearchController extends Controller
{
    private const LIMIT = 12;

    public function index(Request $request): View
    {
        $q = trim(mb_substr((string) $request->query('q'), 0, 80));
        $results = ['promos' => collect(), 'offers' => collect(), 'ads' => collect()];

        if (mb_strlen($q) >= 2) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';

            $results['promos'] = Promo::query()->published()->active()->with('tenant')
                ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('description', 'like', $like))
                ->latest('published_at')->limit(self::LIMIT)->get();

            $results['offers'] = PayableService::query()
                ->whereIn('type', ['service', 'product'])->where('status', 'active')->where('published_to_site', true)
                ->with('tenant')
                ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('description', 'like', $like))
                ->latest()->limit(self::LIMIT)->get();

            $results['ads'] = ClassifiedAd::query()->published()->with('tenant')
                ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('zone', 'like', $like)->orWhere('description', 'like', $like))
                ->latest('published_at')->limit(self::LIMIT)->get();
        }

        return view('search', [
            'q' => $q,
            'results' => $results,
            'total' => $results['promos']->count() + $results['offers']->count() + $results['ads']->count(),
        ]);
    }

    /** Descrizione OpenSearch: permette ai browser di aggiungere la ricerca del sito tra i motori di ricerca. */
    public function openSearch(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/">'
            .'<ShortName>Hub Core</ShortName><Description>Cerca promo, servizi, prodotti e annunci su Hub Core</Description>'
            .'<InputEncoding>UTF-8</InputEncoding>'
            .'<Url type="text/html" template="'.htmlspecialchars(route('search').'?q={searchTerms}', ENT_XML1).'"/>'
            .'</OpenSearchDescription>';

        return response($xml, 200, ['Content-Type' => 'application/opensearchdescription+xml; charset=UTF-8']);
    }
}
