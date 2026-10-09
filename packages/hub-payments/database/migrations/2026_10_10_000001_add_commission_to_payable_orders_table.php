<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payable_orders', function (Blueprint $table) {
            // Commissione maturata sull'ordine (canale "hub"), fissata al momento del pagamento. Default 0.
            $table->unsignedInteger('commission_cents')->default(0)->after('amount_cents');
            // Voce del registro addebiti (tenant_module_charges) in cui è stata addebitata.
            $table->unsignedBigInteger('commission_charge_id')->nullable()->after('commission_cents');

            $table->index(['tenant_id', 'channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payable_orders', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'channel', 'status']);
            $table->dropColumn(['commission_cents', 'commission_charge_id']);
        });
    }
};
