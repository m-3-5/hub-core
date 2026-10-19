<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CreateTenantCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_the_client_with_the_owner_and_sends_the_password_link(): void
    {
        Notification::fake();

        $this->artisan('hub:create-tenant', [
            'slug' => 'iflem', 'name' => 'IFLEM S.n.c.', 'owner_email' => 'pmelfi@iflem.it',
            '--owner-name' => 'Pasquale Melfi', '--website' => 'https://iflem.it',
        ])->assertSuccessful();

        $tenant = Tenant::where('slug', 'iflem')->firstOrFail();
        $this->assertSame('azienda', $tenant->type);
        $this->assertSame('free', $tenant->subscription_status);
        $this->assertNotNull($tenant->guest_verified_at);
        $this->assertFalse($tenant->trialExpired());

        $owner = User::where('email', 'pmelfi@iflem.it')->firstOrFail();
        $this->assertSame('Pasquale Melfi', $owner->name);
        $this->assertFalse($owner->isSuperAdmin());
        $this->assertTrue($owner->belongsToTenant($tenant));

        Notification::assertSentTo($owner, ResetPasswordNotification::class);

        // Il titolare vede la sua app ma non quella degli altri.
        $other = Tenant::create(['name' => 'Altro', 'slug' => 'altro', 'type' => 'azienda', 'plan' => 'demo']);
        $this->actingAs($owner)->get(route('app.home', $tenant))->assertOk()->assertSee('IFLEM S.n.c.');
        $this->actingAs($owner)->get(route('app.home', $other))->assertForbidden();
    }

    public function test_refuses_existing_slug_bad_input_and_never_touches_other_clients(): void
    {
        $existing = Tenant::create(['name' => 'Beauty', 'slug' => 'beauty', 'type' => 'azienda', 'plan' => 'demo']);

        $this->artisan('hub:create-tenant', ['slug' => 'beauty', 'name' => 'Altro', 'owner_email' => 'a@example.com'])->assertFailed();
        $this->artisan('hub:create-tenant', ['slug' => 'Nome Con Spazi', 'name' => 'X', 'owner_email' => 'a@example.com'])->assertFailed();
        $this->artisan('hub:create-tenant', ['slug' => 'ok', 'name' => 'X', 'owner_email' => 'non-una-email'])->assertFailed();
        $this->artisan('hub:create-tenant', ['slug' => 'ok', 'name' => 'X', 'owner_email' => 'a@example.com', '--type' => 'boh'])->assertFailed();
        $this->artisan('hub:create-tenant', ['slug' => 'ok', 'name' => 'X', 'owner_email' => 'a@example.com', '--website' => 'http://x.it'])->assertFailed();

        $this->assertSame('Beauty', $existing->fresh()->name);
        $this->assertSame(1, Tenant::count());
        $this->assertSame(0, User::count());
    }

    public function test_an_existing_user_is_attached_and_keeps_the_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'pmelfi@iflem.it']);
        $hash = $user->password;

        $this->artisan('hub:create-tenant', ['slug' => 'iflem', 'name' => 'IFLEM', 'owner_email' => 'pmelfi@iflem.it', '--no-email' => true])->assertSuccessful();

        $this->assertSame($hash, $user->fresh()->password);
        $this->assertTrue($user->fresh()->belongsToTenant(Tenant::where('slug', 'iflem')->first()));
        Notification::assertNothingSent();
    }
}
