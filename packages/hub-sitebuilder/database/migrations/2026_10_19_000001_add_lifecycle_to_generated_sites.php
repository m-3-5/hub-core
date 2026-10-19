<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_sites', function (Blueprint $table) {
            // Link segreto per modificare il sito senza account: si salva solo l'impronta, il testo del link va solo per email.
            $table->string('edit_token_hash', 64)->nullable()->index();
            $table->timestamp('edit_link_sent_at')->nullable();
            // Vita del sito: ultima modifica, avviso di rimozione e data dopo la quale viene archiviato.
            $table->timestamp('last_edited_at')->nullable();
            $table->timestamp('warned_at')->nullable();
            $table->timestamp('delete_after')->nullable();
        });

        // Archivio riservato all'amministratore: i siti rimossi restano qui (solo testi, non le immagini).
        Schema::create('archived_sites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('tenant_name');
            $table->string('tenant_slug');
            $table->string('owner_email')->nullable();
            $table->json('answers')->nullable();
            $table->string('reason', 60);
            $table->string('reason_label')->nullable();
            $table->timestamp('site_created_at')->nullable();
            $table->timestamp('archived_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_sites');

        Schema::table('generated_sites', function (Blueprint $table) {
            $table->dropColumn(['edit_token_hash', 'edit_link_sent_at', 'last_edited_at', 'warned_at', 'delete_after']);
        });
    }
};
