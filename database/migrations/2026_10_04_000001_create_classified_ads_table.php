<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classified_ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('category')->default('affitto');
            $table->string('title');
            $table->string('zone');
            $table->decimal('price', 10, 2)->nullable();
            $table->string('price_unit')->nullable();
            $table->text('description');
            $table->json('features')->nullable();
            $table->json('images')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'category']);
        });

        Schema::table('customer_tickets', function (Blueprint $table) {
            $table->foreignId('classified_ad_id')->nullable()->after('promo_id')
                ->constrained('classified_ads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('classified_ad_id');
        });

        Schema::dropIfExists('classified_ads');
    }
};
