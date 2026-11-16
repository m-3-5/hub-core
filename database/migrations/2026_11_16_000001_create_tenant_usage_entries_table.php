<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paghi quello che pubblichi: ogni promo, prodotto o servizio pubblicato lascia una riga.
 * Si pubblica subito; la carta salvata viene addebitata dopo un mese, tutto in una volta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_usage_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('reason', 20); // use | included
            $table->string('status', 20)->default('pending'); // pending | billed | failed | free
            $table->string('item_type', 20)->nullable(); // promo | product | service
            $table->unsignedBigInteger('item_id')->nullable();
            $table->timestamp('bill_after')->nullable();
            $table->timestamp('billed_at')->nullable();
            $table->string('payment_intent_id')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'item_type', 'item_id']);
            $table->index(['status', 'bill_after']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_usage_entries');
    }
};
