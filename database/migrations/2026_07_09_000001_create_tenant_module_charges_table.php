<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_module_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('module'); // servizi, promo
            $table->string('charge_type'); // activation, monthly, extra_item
            $table->string('period')->nullable(); // es. 2026-07, solo per monthly/extra_item
            $table->string('description')->nullable();
            $table->unsignedInteger('amount_cents');
            $table->boolean('paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_module_charges');
    }
};
