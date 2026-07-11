<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\Tenant;
use Illuminate\View\View;

class PromoPreviewController extends Controller
{
    public function show(Tenant $tenant, Promo $promo): View
    {
        abort_unless($promo->tenant_id === $tenant->id, 404);

        return view($promo->templateView(), [
            'tenant' => $tenant,
            'promo' => $promo,
            'previewMode' => true,
            'decorImages' => $promo->decorImages(),
        ]);
    }
}
