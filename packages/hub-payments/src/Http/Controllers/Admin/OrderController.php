<?php

namespace M35\HubPayments\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\View\View;
use M35\HubPayments\Support\OrderSummary;
use M35\HubPayments\Support\TenantCommission;

/** Ordini pagati del tenant (carrello, preventivi, link di pagamento) con riepilogo per canale. */
class OrderController extends Controller
{
    public function index(Tenant $tenant): View
    {
        $orders = OrderSummary::paid($tenant->id)->latest('paid_at')->limit(100)->get();

        return view('hub-payments::admin.orders.index', [
            'tenant' => $tenant,
            'orders' => $orders,
            'summary' => OrderSummary::byChannel($tenant),
            'thisMonth' => OrderSummary::byChannel($tenant, now()->format('Y-m')),
            'commissionActive' => TenantCommission::isActive($tenant) || $orders->sum('commission_cents') > 0,
        ]);
    }
}
