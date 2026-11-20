<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Annunci dei privati (scadenza 30 giorni, foto piccola, da dove arrivano) e buoni credito vinti ai giochi di piramide35.com.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classified_ads', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('published_at');
            $table->string('thumb')->nullable()->after('images');
            $table->string('source', 40)->nullable()->after('status');
            $table->index(['status', 'zone']);
        });

        Schema::create('game_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique(); // un codice si usa una volta sola, da chiunque
            $table->unsignedInteger('cents');
            $table->date('expires_on');
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->index(['tenant_id', 'redeemed_at']);
            $table->index(['user_id', 'redeemed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_vouchers');

        Schema::table('classified_ads', function (Blueprint $table) {
            $table->dropIndex(['status', 'zone']);
            $table->dropColumn(['expires_at', 'thumb', 'source']);
        });
    }
};
