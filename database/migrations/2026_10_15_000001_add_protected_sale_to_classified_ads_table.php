<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classified_ads', function (Blueprint $table) {
            // L'inserzionista offre «Compra con pagamento protetto» (solo categoria «oggetti», con un prezzo fisso).
            $table->boolean('protected_sale')->default(false)->after('price_unit');
        });
    }

    public function down(): void
    {
        Schema::table('classified_ads', function (Blueprint $table) {
            $table->dropColumn('protected_sale');
        });
    }
};
