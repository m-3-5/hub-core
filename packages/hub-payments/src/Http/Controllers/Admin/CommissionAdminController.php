<?php

namespace M35\HubPayments\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use M35\HubPayments\Support\OrderSummary;
use M35\HubPayments\Support\TenantCommission;

/** Solo super admin: commissione per tenant sulle vendite del canale hub e riepilogo di tutte le aziende. */
class CommissionAdminController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $rows = Tenant::query()->orderBy('name')->get()->map(fn (Tenant $tenant) => [
            'tenant' => $tenant,
            'summary' => OrderSummary::byChannel($tenant),
            'percent' => TenantCommission::percent($tenant),
            'fixed_cents' => TenantCommission::fixedCents($tenant),
        ])->filter(fn (array $row) => $row['summary']['site']['count'] + $row['summary']['hub']['count'] > 0
            || $row['percent'] > 0 || $row['fixed_cents'] > 0)->values();

        return view('hub-payments::admin.commissions.index', [
            'rows' => $rows,
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $data = $request->validate([
            'percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'fixed' => ['required', 'numeric', 'min:0', 'max:1000'],
        ]);

        TenantCommission::store($tenant, (float) $data['percent'], (int) round(((float) $data['fixed']) * 100));

        return redirect()->route('admin.commissions.index')
            ->with('status', 'Commissione di '.$tenant->name.' salvata: '.TenantCommission::percent($tenant->fresh()).'% + '
                .number_format(TenantCommission::fixedCents($tenant->fresh()) / 100, 2, ',', '.').' € per ordine. Vale per i nuovi ordini del canale hub.');
    }
}
