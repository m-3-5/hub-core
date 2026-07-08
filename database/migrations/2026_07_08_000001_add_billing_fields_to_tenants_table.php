<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('trial_ends_at')->nullable()->after('plan');
            $table->string('subscription_status')->default('trialing')->after('trial_ends_at');
            $table->string('billing_interval')->nullable()->after('subscription_status');
            $table->string('stripe_customer_id')->nullable()->after('billing_interval');
            $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'trial_ends_at',
                'subscription_status',
                'billing_interval',
                'stripe_customer_id',
                'stripe_subscription_id',
            ]);
        });
    }
};
