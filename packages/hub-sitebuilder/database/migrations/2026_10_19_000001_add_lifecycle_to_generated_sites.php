<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Colonne aggiunte a generated_sites (ripetibile: in produzione la prima esecuzione si era fermata a metà). */
    private const COLUMNS = ['edit_token_hash', 'edit_link_sent_at', 'last_edited_at', 'warned_at', 'delete_after'];

    public function up(): void
    {
        $missing = array_values(array_filter(self::COLUMNS, fn ($c) => ! Schema::hasColumn('generated_sites', $c)));

        if ($missing !== []) {
            Schema::table('generated_sites', function (Blueprint $table) use ($missing) {
                // Link segreto per modificare il sito senza account: si salva solo l'impronta, il testo del link va solo per email.
                if (in_array('edit_token_hash', $missing, true)) {
                    $table->string('edit_token_hash', 64)->nullable()->index();
                }
                // Vita del sito: ultima modifica, avviso di rimozione e data dopo la quale viene archiviato.
                foreach (['edit_link_sent_at', 'last_edited_at', 'warned_at', 'delete_after'] as $column) {
                    if (in_array($column, $missing, true)) {
                        $table->timestamp($column)->nullable();
                    }
                }
            });
        }

        // Archivio riservato all'amministratore: i siti rimossi restano qui (solo testi, non le immagini).
        if (! Schema::hasTable('archived_sites')) {
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
                // Nullable: MySQL in modalità rigida rifiuta un timestamp obbligatorio senza valore predefinito. Lo scrive sempre il codice.
                $table->timestamp('archived_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('archived_sites');

        Schema::table('generated_sites', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNS);
        });
    }
};
