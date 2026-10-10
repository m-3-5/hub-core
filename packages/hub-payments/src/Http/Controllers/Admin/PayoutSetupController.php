<?php

namespace M35\HubPayments\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\NewTicketNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use M35\HubPayments\Support\TenantConnect;
use M35\HubPayments\Support\TenantStripeConfig;

/** «Come vuoi ricevere i soldi?»: compare quando si inizia a vendere e guida alla scelta, con le guide a portata di mano. */
class PayoutSetupController extends Controller
{
    private const INTEREST = ['paypal' => 'PayPal', 'bonifico' => 'Bonifico', 'altro' => 'Altro / non so ancora'];

    public function show(Tenant $tenant): View
    {
        return view('hub-payments::admin.payout.setup', [
            'tenant' => $tenant,
            'connectState' => TenantConnect::state($tenant),
            'ownStripe' => TenantStripeConfig::isConfigured($tenant),
        ]);
    }

    /** PayPal / bonifico: per ora si attivano con noi, quindi registriamo l'interesse e avvisiamo il team. */
    public function interest(Request $request, Tenant $tenant): RedirectResponse
    {
        $method = $request->validate(['method' => ['required', 'in:'.implode(',', array_keys(self::INTEREST))]])['method'];

        $ticket = $tenant->tickets()->create([
            'user_id' => auth()->id(),
            'context_type' => 'payout_method',
            'context_label' => 'Come ricevere i soldi: '.self::INTEREST[$method],
            'message' => 'Vorrei ricevere i pagamenti con: '.self::INTEREST[$method].'. Ho letto la guida e vorrei attivarlo.',
            'status' => 'open',
        ]);

        ActivityLog::record($tenant, 'payout_method_interest', input: ['method' => $method], subject: $ticket);

        Notification::send(User::where('is_super_admin', true)->get(), new NewTicketNotification($ticket));

        return redirect()
            ->route('admin.payout.setup', $tenant)
            ->with('status', 'Fatto! Ti scriviamo noi per attivare «'.self::INTEREST[$method].'». Intanto puoi leggere la guida.');
    }
}
