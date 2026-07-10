<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('guest_verified_at')->nullable()->after('subscription_status');
            $table->string('guest_email_token')->nullable()->unique()->after('guest_verified_at');
            $table->timestamp('guest_email_token_expires_at')->nullable()->after('guest_email_token');
        });

        // Existing tenants were never "guests" awaiting verification — backfill so
        // the new publish-gate in PromoController only ever applies to genuinely
        // new guest-created tenants, not every tenant that predates this column.
        DB::table('tenants')->whereNull('guest_verified_at')->update([
            'guest_verified_at' => DB::raw('created_at'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['guest_verified_at', 'guest_email_token', 'guest_email_token_expires_at']);
        });
    }
};
