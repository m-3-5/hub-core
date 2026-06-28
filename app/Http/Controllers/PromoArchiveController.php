<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\View\View;

class PromoArchiveController extends Controller
{
    public function __invoke(Tenant $tenant): View
    {
        $active = $tenant->promos()
            ->published()
            ->active()
            ->latest('published_at')
            ->get();

        $expired = $tenant->promos()
            ->published()
            ->expired()
            ->latest('ends_at')
            ->get();

        return view('promo.archive', compact('tenant', 'active', 'expired'));
    }
}
