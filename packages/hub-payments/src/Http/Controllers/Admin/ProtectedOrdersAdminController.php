<?php

namespace M35\HubPayments\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use M35\HubPayments\Models\PayableOrder;
use M35\HubPayments\Support\ProtectedPayout;
use RuntimeException;

/** Solo team Hub Core: tutti i pagamenti protetti, con rilascio manuale, rimborso e ripresa dopo una segnalazione. */
class ProtectedOrdersAdminController extends Controller
{
    public function index(): View
    {
        $this->authorizeSuperAdmin();

        $orders = PayableOrder::query()->with('tenant')->where('flow', 'protected')->where('status', 'paid')
            ->orderByRaw("CASE payout_status WHEN 'frozen' THEN 0 WHEN 'held' THEN 1 ELSE 2 END")
            ->latest('paid_at')->limit(200)->get();

        return view('hub-payments::admin.protected-orders.index', [
            'orders' => $orders,
            'heldCents' => (int) $orders->where('payout_status', 'held')->sum('amount_cents'),
            'frozenCount' => $orders->where('payout_status', 'frozen')->count(),
        ]);
    }

    public function release(PayableOrder $order): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        try {
            $done = ProtectedPayout::release($order);
        } catch (RuntimeException $e) {
            return back()->withErrors(['order' => 'Rilascio non riuscito: '.$e->getMessage()]);
        }

        ActivityLog::record($order->tenant, 'protected_order_released', input: ['order' => $order->id, 'manual' => true]);

        return back()->with('status', $done ? 'Pagamento rilasciato al venditore.' : 'Niente da rilasciare: ordine bloccato o già rilasciato.');
    }

    public function refund(PayableOrder $order): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        try {
            ProtectedPayout::refund($order);
        } catch (RuntimeException $e) {
            return back()->withErrors(['order' => 'Rimborso non riuscito: '.$e->getMessage()]);
        }

        ActivityLog::record($order->tenant, 'protected_order_refunded', input: ['order' => $order->id]);

        return back()->with('status', 'Compratore rimborsato.');
    }

    /** Problema chiarito: l'ordine torna «trattenuto» e si rilascia dopo 2 giorni (il compratore può ancora segnalare). */
    public function resume(PayableOrder $order): RedirectResponse
    {
        $this->authorizeSuperAdmin();

        abort_unless($order->isFrozen(), 404);

        $order->forceFill(['payout_status' => 'held', 'release_at' => now()->addDays(2)])->save();
        ActivityLog::record($order->tenant, 'protected_order_resumed', input: ['order' => $order->id]);

        return back()->with('status', 'Ordine ripreso: verrà rilasciato tra 2 giorni.');
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }
}
