<?php

namespace App\Http\Controllers;

use App\Models\ClassifiedAd;
use App\Models\Promo;
use App\Models\Tenant;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use M35\HubPayments\Models\PayableService;

/**
 * /sitemap.xml: l'elenco di tutte le pagine pubbliche che Google deve poter trovare
 * (pagine fisse, landing, promo, servizi/prodotti e annunci pubblicati). Si aggiorna da sola.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), fn () => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** @return array<int, array{loc: string, lastmod: ?Carbon, priority: string}> */
    public function urls(): array
    {
        $urls = [
            ['loc' => route('welcome'), 'lastmod' => null, 'priority' => '1.0'],
            ['loc' => route('landing.web'), 'lastmod' => null, 'priority' => '0.9'],
            ['loc' => route('pricing.show'), 'lastmod' => null, 'priority' => '0.8'],
            ['loc' => route('promo.hub-archive'), 'lastmod' => null, 'priority' => '0.7'],
            ['loc' => route('classifieds.board'), 'lastmod' => null, 'priority' => '0.7'],
        ];

        // Guide su come ricevere i soldi + condizioni economiche.
        $urls[] = ['loc' => route('guides.index'), 'lastmod' => null, 'priority' => '0.6'];
        foreach (array_keys(\App\Http\Controllers\GuideController::guides()) as $slug) {
            $urls[] = ['loc' => route('guides.show', $slug), 'lastmod' => null, 'priority' => '0.5'];
        }
        $urls[] = ['loc' => route('terms.economic'), 'lastmod' => null, 'priority' => '0.4'];

        // Promo attive: archivio dell'azienda e ogni landing.
        $promos = Promo::query()->published()->active()->with('tenant:id,slug')->get();

        foreach ($promos->groupBy('tenant_id') as $group) {
            $tenant = $group->first()->tenant;

            if (! $tenant) {
                continue;
            }

            $urls[] = ['loc' => route('promo.archive', $tenant), 'lastmod' => $group->max('updated_at'), 'priority' => '0.6'];

            foreach ($group as $promo) {
                $urls[] = ['loc' => route('promo.show', [$tenant, $promo]), 'lastmod' => $promo->updated_at, 'priority' => '0.7'];
            }
        }

        // Servizi e prodotti pubblicati sul sito (mai i preventivi).
        $items = PayableService::query()
            ->whereIn('type', ['service', 'product'])
            ->where('status', 'active')
            ->where('published_to_site', true)
            ->with('tenant:id,slug')
            ->get();

        foreach ($items->groupBy('tenant_id') as $group) {
            $tenant = $group->first()->tenant;

            if (! $tenant) {
                continue;
            }

            $urls[] = ['loc' => route('services.public.archive', $tenant), 'lastmod' => $group->max('updated_at'), 'priority' => '0.6'];

            foreach ($group as $item) {
                $urls[] = ['loc' => route('services.public.show', [$tenant, $item]), 'lastmod' => $item->updated_at, 'priority' => '0.6'];
            }
        }

        // Annunci pubblicati.
        $ads = ClassifiedAd::query()->published()->with('tenant:id,slug')->get();

        foreach ($ads->groupBy('tenant_id') as $group) {
            $tenant = $group->first()->tenant;

            if (! $tenant) {
                continue;
            }

            $urls[] = ['loc' => route('classifieds.tenant-board', $tenant), 'lastmod' => $group->max('updated_at'), 'priority' => '0.6'];

            foreach ($group as $ad) {
                $urls[] = ['loc' => route('classifieds.show', [$tenant, $ad]), 'lastmod' => $ad->updated_at, 'priority' => '0.6'];
            }
        }

        return $urls;
    }

    private function build(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($this->urls() as $url) {
            $xml .= '  <url><loc>'.htmlspecialchars($url['loc'], ENT_XML1).'</loc>';

            if ($url['lastmod']) {
                $xml .= '<lastmod>'.$url['lastmod']->toAtomString().'</lastmod>';
            }

            $xml .= '<priority>'.$url['priority'].'</priority></url>'."\n";
        }

        return $xml.'</urlset>'."\n";
    }
}
