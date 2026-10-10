<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ordini «1 € per partire» della landing siti web: 1 € subito, prova, poi rate mensili a riscatto (contatti di M 3.5, non di un tenant).
        Schema::create('site_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_lead_id')->nullable()->constrained('site_leads')->nullOnDelete();
            $table->string('name', 80);
            $table->string('phone', 30);
            $table->string('email', 190);
            $table->string('plan', 30);
            $table->unsignedInteger('start_cents');
            $table->unsignedInteger('trial_days');
            $table->unsignedSmallInteger('installments');
            $table->unsignedInteger('installment_cents');
            $table->unsignedInteger('package_cents');
            $table->string('status', 20)->default('pending'); // pending | trial | paying | past_due | completed | canceled
            $table->string('stripe_session_id')->nullable()->index();
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('completes_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_orders');
    }
};
