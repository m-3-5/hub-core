<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payable_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Canale di vendita: "site" (sito del cliente, chiamata firmata) | "hub" (pagine pubbliche inm35.it).
            $table->string('channel', 20)->default('site');
            $table->string('status', 20)->default('pending');
            $table->string('stripe_session_id')->nullable()->unique();
            $table->json('items');
            $table->string('currency', 3)->default('eur');
            $table->unsignedInteger('amount_cents');
            $table->string('customer_email')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 40)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payable_orders');
    }
};
