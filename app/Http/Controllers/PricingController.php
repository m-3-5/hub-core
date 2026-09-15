<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PricingController extends Controller
{
    public function show(): View
    {
        $comingSoon = collect(config('hub.modules'))
            ->filter(fn (array $m) => ! $m['active'] && $m['key'] !== 'billing')
            ->values();

        return view('pricing', [
            'hubBilling' => config('services.hub_billing'),
            'modulePricing' => config('module_pricing'),
            'aiFlyerPrice' => config('hub.promo_ai_flyer_price'),
            'comingSoon' => $comingSoon,
        ]);
    }
}
