<?php

namespace App\Http\Controllers;

use App\Models\ClassifiedAd;
use App\Models\Promo;
use Illuminate\View\View;

class WelcomeController extends Controller
{
    public function __invoke(): View
    {
        $slides = collect(config('welcome.slides'))->map(function (array $slide) {
            $slide['image_url'] = asset($slide['image']);
            $slide['cta_url'] = match ($slide['href']) {
                'login' => route('admin.login'),
                'register' => route('welcome').'#registrazione',
                default => route('admin.login'),
            };

            return $slide;
        });

        $livePromos = Promo::with('tenant')
            ->published()
            ->active()
            ->whereHas('tenant')
            ->whereNotNull('image_path')
            ->latest('published_at')
            ->limit(4)
            ->get();

        $liveAds = ClassifiedAd::with('tenant')
            ->published()
            ->whereHas('tenant')
            ->latest('published_at')
            ->limit(4)
            ->get();

        return view('welcome', [
            'slides' => $slides,
            'livePromos' => $livePromos,
            'liveAds' => $liveAds,
        ]);
    }
}
