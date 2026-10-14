<?php

namespace Tests\Feature;

use App\Models\ClassifiedAd;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MakeTestSellerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_private_test_seller_with_a_protected_ad_linked_to_super_admins_only(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $other = User::factory()->create();

        $this->artisan('hub:make-test-seller')->assertExitCode(0);

        $tenant = Tenant::where('slug', 'prova-privato')->sole();
        $this->assertSame('privato', $tenant->type);
        $this->assertTrue($tenant->users->contains($admin));
        $this->assertFalse($tenant->users->contains($other));

        $ad = ClassifiedAd::sole();
        $this->assertSame('oggetti', $ad->category);
        $this->assertTrue($ad->offersProtectedSale());
        $this->assertSame(100, $ad->priceCents());

        // Rilanciato non duplica nulla.
        $this->artisan('hub:make-test-seller')->assertExitCode(0);
        $this->assertSame(1, Tenant::where('slug', 'prova-privato')->count());
        $this->assertSame(1, ClassifiedAd::count());
    }

    public function test_cleanup_removes_only_test_sellers(): void
    {
        User::factory()->create(['is_super_admin' => true]);
        $real = Tenant::create(['name' => 'Beauty', 'slug' => 'beauty', 'type' => 'azienda', 'plan' => 'demo']);
        $this->artisan('hub:make-test-seller')->assertExitCode(0);

        $this->artisan('hub:make-test-seller', ['--cleanup' => true])->assertExitCode(0);
        $this->assertSame(0, Tenant::where('slug', 'prova-privato')->count());
        $this->assertSame(0, ClassifiedAd::count());

        // Un identificativo che non inizia con «prova-» è rifiutato: non si tocca un'attività vera.
        $this->artisan('hub:make-test-seller', ['slug' => 'beauty', '--cleanup' => true])->assertExitCode(1);
        $this->assertTrue(Tenant::whereKey($real->id)->exists());
    }
}
