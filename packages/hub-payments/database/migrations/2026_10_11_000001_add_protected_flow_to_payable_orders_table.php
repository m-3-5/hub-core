<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payable_orders', function (Blueprint $table) {
            // "direct": il cliente paga sul conto Stripe del venditore (come prima) | "protected": paga Hub Core, che gira i soldi dopo la consegna.
            $table->string('flow', 12)->default('direct')->after('channel');
            // Solo flusso protetto: held (soldi trattenuti) | released (girati al venditore) | frozen (segnalazione aperta) | refunded.
            $table->string('payout_status', 12)->nullable()->after('status');
            $table->string('connect_account_id')->nullable()->after('payout_status');
            $table->string('stripe_payment_intent_id')->nullable()->after('stripe_session_id');
            $table->timestamp('release_at')->nullable()->after('paid_at');
            // Codice segreto nel link che il compratore riceve per vedere l'ordine e confermare «Ho ricevuto».
            $table->string('buyer_token', 64)->nullable()->unique()->after('release_at');

            $table->index(['payout_status', 'release_at']);
        });
    }

    public function down(): void
    {
        Schema::table('payable_orders', function (Blueprint $table) {
            $table->dropIndex(['payout_status', 'release_at']);
            $table->dropUnique(['buyer_token']);
            $table->dropColumn(['flow', 'payout_status', 'connect_account_id', 'stripe_payment_intent_id', 'release_at', 'buyer_token']);
        });
    }
};
