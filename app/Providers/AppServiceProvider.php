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
    }
}
