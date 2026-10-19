<?php

namespace Tests\Feature;

use App\Models\Promo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HubUiTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::create(['name' => 'Demo', 'slug' => 'demo', 'type' => 'azienda', 'plan' => 'demo', 'guest_verified_at' => now()]);
    }

    public function test_public_pages_load_the_shared_stylesheet_and_show_every_section_in_the_top_bar(): void
    {
        $this->get(route('welcome'))->assertOk()
            ->assertSee('css/hub-ui.css', false)
            ->assertSee('ui-pub', false)
            ->assertSee('Promo')->assertSee('Annunci')->assertSee('Siti web')->assertSee('Prezzi')->assertSee('Accedi');

        $this->get(route('pricing.show'))->assertOk()->assertSee('ui-pub', false);
        $this->get(route('classifieds.board'))->assertOk()->assertSee('ui-pub', false);
    }

    public function test_home_shows_live_promos_and_ads_with_the_same_photo_frame(): void
    {
        $tenant = $this->tenant();
        $tenant->promos()->create(['title' => 'Offerta viva', 'slug' => 'offerta-viva', 'status' => 'published', 'image_path' => 'promos/demo/a.jpg', 'always_active' => true, 'offers' => [], 'published_at' => now()]);
        $tenant->classifiedAds()->create(['slug' => 'bici', 'category' => 'oggetti', 'title' => 'Bici elettrica', 'zone' => 'Corigliano', 'price' => 500, 'description' => 'x', 'status' => 'published', 'published_at' => now()]);

        $this->get(route('welcome'))->assertOk()
            ->assertSee('Promozioni attive adesso')->assertSee('Offerta viva')
            ->assertSee('Ultimi annunci')->assertSee('Bici elettrica')
            ->assertSee('class="ui-media"', false);
    }

    public function test_tenant_pages_do_not_get_the_platform_top_bar(): void
    {
        $tenant = $this->tenant();

        $this->get(route('classifieds.tenant-board', $tenant))->assertOk()
            ->assertSee('css/hub-ui.css', false)
            ->assertDontSee('ui-pub__brand', false);
    }

    public function test_tenant_owner_sees_the_whole_menu_at_once(): void
    {
        $tenant = $this->tenant();
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);

        $this->actingAs($user)->get(route('app.home', $tenant))->assertOk()
            ->assertSee('ui-side', false)
            ->assertSee('ui-tabbar', false)
            ->assertSee(route('admin.promos.index', $tenant), false)
            ->assertSee(route('admin.services.index', $tenant), false)
            ->assertSee(route('admin.classifieds.index', $tenant), false)
            ->assertSee(route('admin.customer-tickets.index', $tenant), false)
            ->assertSee(route('admin.profile.edit', $tenant), false);
    }

    public function test_super_admin_sees_the_management_menu(): void
    {
        $this->tenant();
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('ui-side', false)
            ->assertSee(route('admin.tickets.index'), false)
            ->assertSee(route('admin.protected-orders.index'), false)
            ->assertSee(route('admin.leads.index'), false);
    }

    public function test_promo_cards_use_the_same_photo_frame(): void
    {
        $tenant = $this->tenant();
        $tenant->promos()->create(['title' => 'Offerta', 'slug' => 'offerta', 'status' => 'published', 'image_path' => 'promos/demo/a.jpg', 'always_active' => true, 'offers' => [], 'published_at' => now()]);
        $this->assertSame(1, Promo::count());

        $this->get(route('promo.archive', $tenant))->assertOk()->assertSee('promo-card__media', false);

        $css = file_get_contents(public_path('css/hub-ui.css'));
        $this->assertStringContainsString('aspect-ratio: var(--ui-ratio)', $css);
        $this->assertStringContainsString('object-fit: cover', $css);
    }
}
