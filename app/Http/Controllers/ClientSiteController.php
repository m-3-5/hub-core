<?php

namespace App\Http\Controllers;

use App\Models\Promo;
use App\Models\Tenant;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ClientSiteController extends Controller
{
    /**
     * Pagina promo ottimizzata per incorporamento su dominio cliente (iframe o redirect).
     */
    public function embedPage(Tenant $tenant, Promo $promo): View
    {
        abort_unless($promo->tenant_id === $tenant->id, 404);
        abort_unless($promo->status === 'published', 404);

        return view('promo.show', [
            'tenant' => $tenant,
            'promo' => $promo,
            'embedMode' => true,
            'themeIcons' => app(\App\Services\PromoThemeIcons::class)->iconsForPromo($promo->offers ?? [], $promo->description),
            'icons' => app(\App\Services\PromoThemeIcons::class),
        ]);
    }

    /**
     * Snippet HTML da incollare su beautyofimage.com per pagina dedicata via iframe.
     */
    public function iframeSnippet(Tenant $tenant, Promo $promo): Response
    {
        abort_unless($promo->tenant_id === $tenant->id, 404);

        $src = route('client.promo.embed', [$tenant, $promo]);

        $html = <<<HTML
<!-- Hub Core: pagina promo su {$tenant->website} -->
<div id="hub-core-promo-page" style="max-width:100%;margin:0 auto">
  <iframe src="{$src}" title="{$promo->title}" style="width:100%;min-height:900px;border:0;border-radius:12px" loading="lazy"></iframe>
</div>
HTML;

        return response($html, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
