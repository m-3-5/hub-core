<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro dei post importati da fuori (Facebook, Instagram…).
     * La riga resta anche se la bozza viene eliminata: così lo stesso post non crea mai un doppione.
     */
    public function up(): void
    {
        Schema::create('promo_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('source', 30)->default('facebook');
            $table->string('external_id', 190);
            $table->foreignId('promo_id')->nullable()->constrained()->nullOnDelete();
            $table->string('permalink', 2000)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_sources');
    }
};
