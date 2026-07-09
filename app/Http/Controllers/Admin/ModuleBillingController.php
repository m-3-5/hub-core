<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantModuleCharge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleBillingController extends Controller
{
    public function show(Tenant $tenant): View
    {
        $charges = $tenant->moduleCharges()->orderByDesc('period')->orderByDesc('id')->get();

        return view('admin.module-billing.show', [
            'tenant' => $tenant,
            'charges' => $charges,
            'pricing' => config('module_pricing'),
            'unpaidTotalCents' => $charges->where('paid', false)->sum('amount_cents'),
        ]);
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'module' => ['required', 'in:servizi,promo'],
            'charge_type' => ['required', 'in:activation,monthly,extra_item'],
            'period' => ['nullable', 'string', 'max:7'],
            'description' => ['nullable', 'string', 'max:190'],
            'amount' => ['required', 'numeric', 'min:0'],
            'paid' => ['boolean'],
        ]);

        TenantModuleCharge::create([
            'tenant_id' => $tenant->id,
            'module' => $validated['module'],
            'charge_type' => $validated['charge_type'],
            'period' => $validated['period'] ?? null,
            'description' => $validated['description'] ?? null,
            'amount_cents' => (int) round(((float) $validated['amount']) * 100),
            'paid' => $request->boolean('paid'),
            'paid_at' => $request->boolean('paid') ? now() : null,
        ]);

        return redirect()
            ->route('admin.module-billing.show', $tenant)
            ->with('status', 'Voce aggiunta al registro.');
    }

    public function togglePaid(Tenant $tenant, TenantModuleCharge $charge): RedirectResponse
    {
        abort_unless($charge->tenant_id === $tenant->id, 404);

        $nowPaid = ! $charge->paid;

        $charge->update([
            'paid' => $nowPaid,
            'paid_at' => $nowPaid ? now() : null,
        ]);

        return redirect()
            ->route('admin.module-billing.show', $tenant)
            ->with('status', $nowPaid ? 'Segnato come pagato.' : 'Segnato come non pagato.');
    }

    public function destroy(Tenant $tenant, TenantModuleCharge $charge): RedirectResponse
    {
        abort_unless($charge->tenant_id === $tenant->id, 404);

        $charge->delete();

        return redirect()
            ->route('admin.module-billing.show', $tenant)
            ->with('status', 'Voce eliminata.');
    }
}
