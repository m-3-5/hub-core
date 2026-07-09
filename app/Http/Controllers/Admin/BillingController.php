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
            'modules' => $this->modulesWithStatus($tenant),
        ]);
    }

    public function toggleModule(Tenant $tenant, string $module): RedirectResponse
    {
        $moduleConfig = config('hub.modules.'.$module);

        abort_unless($moduleConfig && $moduleConfig['active'] && $module !== 'billing', 404);

        $settings = $tenant->settings ?? [];
        $currentlyEnabled = $settings['modules'][$module] ?? $moduleConfig['active'];
        $settings['modules'][$module] = ! $currentlyEnabled;

        $tenant->forceFill(['settings' => $settings])->save();

        return redirect()
            ->route('admin.billing.show', $tenant)
            ->with('status', 'Modulo "'.$moduleConfig['label'].'" '.(! $currentlyEnabled ? 'attivato' : 'disattivato').'.');
    }

    /** @return array<int, array<string, mixed>> */
    private function modulesWithStatus(Tenant $tenant): array
    {
        $enabledOverrides = $tenant->settings['modules'] ?? [];

        $activationCharges = $tenant->moduleCharges()
            ->where('charge_type', 'activation')
            ->get()
            ->keyBy('module');

        $ledgerKeys = ['services' => 'servizi', 'promo' => 'promo'];

        return collect(config('hub.modules'))
            ->reject(fn (array $m) => $m['key'] === 'billing')
            ->map(function (array $m) use ($enabledOverrides, $activationCharges, $ledgerKeys) {
                $ledgerKey = $ledgerKeys[$m['key']] ?? null;
                $charge = $ledgerKey ? $activationCharges->get($ledgerKey) : null;

                return [
                    ...$m,
                    'buildable' => $m['active'],
                    'enabled' => (bool) ($enabledOverrides[$m['key']] ?? $m['active']),
                    'activation_paid' => $charge?->paid,
                ];
            })
            ->values()
            ->all();
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
