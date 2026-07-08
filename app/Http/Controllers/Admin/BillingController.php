<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\HubBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class BillingController extends Controller
{
    public function show(Tenant $tenant): View
    {
        return view('admin.billing.show', [
            'tenant' => $tenant,
            'monthlyPrice' => config('services.hub_billing.monthly_price_eur'),
            'annualPrice' => config('services.hub_billing.annual_price_eur'),
            'configured' => (bool) config('services.hub_billing.secret_key'),
        ]);
    }

    public function checkout(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'interval' => ['required', 'in:month,year'],
        ]);

        $secretKey = config('services.hub_billing.secret_key');

        if (! $secretKey) {
            return back()->withErrors(['billing' => 'Fatturazione hub non ancora configurata.']);
        }

        try {
            $session = (new HubBillingService($secretKey))->createCheckoutSession($tenant, $validated['interval']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['billing' => $e->getMessage()]);
        }

        return redirect()->away($session['url']);
    }
}
