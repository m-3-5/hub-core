<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\Tenant;
use App\Services\PromoThemeIcons;
use Illuminate\View\View;

class PromoPreviewController extends Controller
{
    public function show(Tenant $tenant, Promo $promo, PromoThemeIcons $icons): View
    {
        abort_unless($promo->tenant_id === $tenant->id, 404);

        return view('promo.show', [
            'tenant' => $tenant,
            'promo' => $promo,
            'previewMode' => true,
            'themeIcons' => $icons->iconsForPromo($promo->offers ?? [], $promo->description),
            'icons' => $icons,
        ]);
    }
}
