<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Richieste di preventivo arrivate dalla landing «siti web e app» (contatti di M 3.5, non di un tenant).
        Schema::create('site_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('phone', 30);
            $table->string('email', 190)->nullable();
            $table->string('package', 30)->nullable();
            $table->text('message')->nullable();
            $table->string('source', 60)->default('landing-siti-web');
            $table->string('status', 20)->default('new'); // new | contacted | closed
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_leads');
    }
};
