<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetBillingTestDataTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug, array $extra = []): Tenant
    {
        return Tenant::create(array_merge(['name' => ucfirst($slug), 'slug' => $slug, 'type' => 'azienda', 'plan' => 'demo'], $extra));
    }

    public function test_by_default_it_only_lists_and_changes_nothing(): void
    {
        $t = $this->tenant('beauty', ['stripe_customer_id' => 'cus_test', 'stripe_subscription_id' => 'sub_test', 'subscription_status' => 'active']);

        $this->artisan('hub:reset-billing', ['--all' => true])->assertExitCode(0);

        $t->refresh();
        $this->assertSame('cus_test', $t->stripe_customer_id);
        $this->assertSame('active', $t->subscription_status);
    }

    public function test_apply_clears_stripe_ids_and_asks_paid_tenants_to_activate_again(): void
    {
        $paid = $this->tenant('beauty', ['stripe_customer_id' => 'cus_test', 'stripe_subscription_id' => 'sub_test', 'subscription_status' => 'active', 'billing_interval' => 'month']);
        $free = $this->tenant('privato1', ['type' => 'privato', 'subscription_status' => 'free', 'stripe_customer_id' => 'cus_free']);
        $untouched = $this->tenant('nuovo', ['subscription_status' => 'trialing']);

        $this->artisan('hub:reset-billing', ['--all' => true, '--apply' => true])->assertExitCode(0);

        $paid->refresh();
        $this->assertNull($paid->stripe_customer_id);
        $this->assertNull($paid->stripe_subscription_id);
        $this->assertNull($paid->billing_interval);
        $this->assertSame('trialing', $paid->subscription_status);
        $this->assertTrue($paid->trialExpired());

        $free->refresh();
        $this->assertNull($free->stripe_customer_id);
        $this->assertSame('free', $free->subscription_status);

        $this->assertSame('trialing', $untouched->fresh()->subscription_status);
    }

    public function test_it_needs_a_target(): void
    {
        $this->artisan('hub:reset-billing')->assertExitCode(1);
    }
}
