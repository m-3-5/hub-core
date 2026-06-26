<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('plan')->default('subscription')->after('primary_color');
            $table->string('workspace_database')->nullable()->after('plan');
            $table->string('workspace_url')->nullable()->after('workspace_database');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['plan', 'workspace_database', 'workspace_url']);
        });
    }
};
