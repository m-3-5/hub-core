<?php

namespace App\Http\Controllers;

use App\Models\Promo;
use Illuminate\View\View;

class HubPromoArchiveController extends Controller
{
    public function __invoke(): View
    {
        $promos = Promo::with('tenant')
            ->published()
            ->active()
            ->whereHas('tenant')
            ->latest('published_at')
            ->limit(60)
            ->get();

        return view('promo.hub-archive', compact('promos'));
    }
}
