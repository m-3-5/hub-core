<?php

namespace App\Http\Controllers;

use App\Models\Promo;
use App\Models\Tenant;
use App\Services\PromoThemeIcons;
use Illuminate\View\View;

class PromoPublicController extends Controller
{
    public function show(Tenant $tenant, Promo $promo, PromoThemeIcons $icons): View
    {
        abort_unless($promo->tenant_id === $tenant->id, 404);
        abort_unless($promo->status === 'published', 404);

        return view('promo.show', [
            'tenant' => $tenant,
            'promo' => $promo,
            'themeIcons' => $icons->iconsForPromo($promo->offers ?? [], $promo->description),
            'icons' => $icons,
        ]);
    }
}
