<?php

namespace M35\HubPayments\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use M35\HubPayments\Models\PayableOrder;

/** Riepiloghi degli ordini pagati, per tenant e per canale di vendita (site | hub). */
class OrderSummary
{
    /**
     * @return array<string, array{count: int, total_cents: int, commission_cents: int, uncharged_cents: int}> per canale
     */
    public static function byChannel(Tenant $tenant, ?string $period = null): array
    {
        $rows = self::paid($tenant->id, $period)
            ->select('channel', DB::raw('COUNT(*) as n'), DB::raw('SUM(amount_cents) as total'), DB::raw('SUM(commission_cents) as commission'),
                DB::raw('SUM(CASE WHEN commission_charge_id IS NULL THEN commission_cents ELSE 0 END) as uncharged'))
            ->groupBy('channel')
            ->get();

        $summary = [];

        foreach (['site', 'hub'] as $channel) {
            $row = $rows->firstWhere('channel', $channel);
            $summary[$channel] = [
                'count' => (int) ($row->n ?? 0),
                'total_cents' => (int) ($row->total ?? 0),
                'commission_cents' => (int) ($row->commission ?? 0),
                'uncharged_cents' => (int) ($row->uncharged ?? 0),
            ];
        }

        return $summary;
    }

    /** @return \Illuminate\Database\Eloquent\Builder<PayableOrder> */
    public static function paid(int $tenantId, ?string $period = null)
    {
        $query = PayableOrder::query()->where('tenant_id', $tenantId)->where('status', 'paid');

        if ($period && preg_match('/^\d{4}-\d{2}$/', $period)) {
            [$year, $month] = array_map('intval', explode('-', $period));
            $start = \Illuminate\Support\Carbon::create($year, $month, 1)->startOfMonth();
            $query->whereBetween('paid_at', [$start, $start->copy()->endOfMonth()]);
        }

        return $query;
    }
}
