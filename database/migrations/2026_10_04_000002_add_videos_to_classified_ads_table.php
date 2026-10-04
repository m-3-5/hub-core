<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classified_ads', function (Blueprint $table) {
            $table->json('videos')->nullable()->after('images');
        });
    }

    public function down(): void
    {
        Schema::table('classified_ads', function (Blueprint $table) {
            $table->dropColumn('videos');
        });
    }
};
