<?php

namespace App\Http\Controllers;

use App\Models\Promo;
use App\Models\Tenant;
use Illuminate\View\View;

class PromoPublicController extends Controller
{
    public function show(Tenant $tenant, Promo $promo): View
    {
        abort_unless($promo->tenant_id === $tenant->id, 404);
        abort_unless($promo->status === 'published', 404);

        return view($promo->templateView(), [
            'tenant' => $tenant,
            'promo' => $promo,
            'decorImages' => $promo->decorImages(),
            'shareLinks' => \App\Support\PromoShareLinks::for($promo),
            'isExpiredPromo' => $promo->isExpired(),
        ]);
    }
}
