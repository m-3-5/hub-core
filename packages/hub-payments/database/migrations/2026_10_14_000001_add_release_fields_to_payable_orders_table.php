<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payable_orders', function (Blueprint $table) {
            $table->string('stripe_charge_id')->nullable()->after('stripe_payment_intent_id');
            $table->string('stripe_transfer_id')->nullable()->after('stripe_charge_id');
            $table->string('stripe_refund_id')->nullable()->after('stripe_transfer_id');
            // Commissione reale di Stripe sul pagamento e importo girato al venditore (incasso − commissione Stripe − commissione Hub Core).
            $table->unsignedInteger('stripe_fee_cents')->nullable()->after('commission_charge_id');
            $table->unsignedInteger('payout_cents')->nullable()->after('stripe_fee_cents');
            $table->timestamp('confirmed_at')->nullable()->after('release_at');
            $table->timestamp('released_at')->nullable()->after('confirmed_at');
            $table->timestamp('problem_reported_at')->nullable()->after('released_at');
            $table->text('problem_message')->nullable()->after('problem_reported_at');
            $table->timestamp('refunded_at')->nullable()->after('problem_message');
            // Condizioni di acquisto accettate dal compratore prima di pagare (versione, data e indirizzo IP).
            $table->string('buyer_terms_version', 20)->nullable()->after('buyer_token');
            $table->timestamp('buyer_terms_accepted_at')->nullable()->after('buyer_terms_version');
            $table->string('buyer_terms_ip', 45)->nullable()->after('buyer_terms_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('payable_orders', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_charge_id', 'stripe_transfer_id', 'stripe_refund_id', 'stripe_fee_cents', 'payout_cents',
                'confirmed_at', 'released_at', 'problem_reported_at', 'problem_message', 'refunded_at',
                'buyer_terms_version', 'buyer_terms_accepted_at', 'buyer_terms_ip',
            ]);
        });
    }
};
