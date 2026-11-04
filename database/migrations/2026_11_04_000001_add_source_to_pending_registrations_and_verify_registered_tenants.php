<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Da dove arriva chi si registra (es. «piramide35» dal tasto «Crealo da solo»).
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->string('source', 40)->nullable()->after('first_module');
        });

        // Chi si è registrato da /registrati ha confermato l'email col link, ma l'attività restava
        // «ospite da confermare»: il suo sito non andava mai online. Ripara chi ha già un titolare
        // con email vera (gli ospiti hanno solo l'account @guest.hub-core.local).
        $verifiedTenantIds = DB::table('tenant_user')
            ->join('users', 'users.id', '=', 'tenant_user.user_id')
            ->where('users.email', 'not like', '%@guest.hub-core.local')
            ->pluck('tenant_user.tenant_id')
            ->unique();

        DB::table('tenants')
            ->whereNull('guest_verified_at')
            ->whereIn('id', $verifiedTenantIds)
            ->update(['guest_verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('pending_registrations', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
