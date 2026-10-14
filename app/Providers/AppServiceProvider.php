<?php

namespace App\Providers;

use App\Support\SeoSchema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Dati strutturati per Google e per le ricerche con IA, aggiunti alle pagine pubbliche senza toccare i controller.
        View::composer('welcome', fn ($view) => $view->with('jsonLd', SeoSchema::home()));
        View::composer('promo.archive', fn ($view) => $view->with('jsonLd', SeoSchema::promoList(
            collect($view->getData()['active'] ?? []),
            'Promozioni — '.$view->getData()['tenant']->name,
            route('promo.archive', $view->getData()['tenant']),
        )));
        View::composer('promo.hub-archive', fn ($view) => $view->with('jsonLd', SeoSchema::promoList(collect($view->getData()['promos'] ?? []), 'Tutte le promozioni — Hub Core', route('promo.hub-archive'))));
        View::composer('hub-payments::public.archive', fn ($view) => $view->with('jsonLd', SeoSchema::catalog(
            $view->getData()['tenant'],
            collect($view->getData()['services'] ?? []),
            collect($view->getData()['products'] ?? []),
        )));
        View::composer('classifieds.board', fn ($view) => $view->with('jsonLd', SeoSchema::classifiedBoard($view->getData()['tenant'] ?? null, collect($view->getData()['ads']->items()))));
        View::composer('classifieds.show', fn ($view) => $view->with('jsonLd', SeoSchema::classifiedAd($view->getData()['tenant'], $view->getData()['ad'])));

        // Contenuti nuovi o modificati: la sitemap si rigenera subito e i motori di ricerca vengono avvisati (IndexNow).
        $changed = function (string $url): void {
            \Illuminate\Support\Facades\Cache::forget('sitemap.xml');
            \App\Services\IndexNow::queue($url);
        };

        \App\Models\Promo::saved(function (\App\Models\Promo $promo) use ($changed) {
            if ($promo->isPublished() && ($promo->wasRecentlyCreated || $promo->wasChanged(['status', 'title', 'description', 'image_path', 'offers', 'ends_at']))) {
                $changed($promo->publicUrl());
            }
        });

        \App\Models\ClassifiedAd::saved(function (\App\Models\ClassifiedAd $ad) use ($changed) {
            if ($ad->isPublished() && ($ad->wasRecentlyCreated || $ad->wasChanged(['status', 'title', 'description', 'price', 'price_unit', 'images', 'features', 'zone']))) {
                $changed($ad->publicUrl());
            }
        });

        \M35\HubPayments\Models\PayableService::saved(function (\M35\HubPayments\Models\PayableService $item) use ($changed) {
            if ($item->status === 'active' && $item->published_to_site && $item->tenant
                && in_array($item->type, ['service', 'product'], true)
                && ($item->wasRecentlyCreated || $item->wasChanged(['status', 'published_to_site', 'title', 'description', 'amount_cents', 'cover_image_path']))) {
                $changed(route('services.public.show', [$item->tenant, $item]));
            }
        });
    }
}
