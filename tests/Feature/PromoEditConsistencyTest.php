<?php

namespace Tests\Feature;

use App\Models\Promo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoEditConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function setupPromo(): array
    {
        $tenant = Tenant::create(['name' => 'Sonia', 'slug' => 'sonia', 'type' => 'privato', 'plan' => 'demo', 'subscription_status' => 'free']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        $promo = Promo::create([
            'tenant_id' => $tenant->id, 'title' => 'Lezioni di sport acquatici', 'slug' => 'lezioni', 'description' => 'Impara il surf.',
            'offers' => [['name' => 'Lezione di Surf', 'detail' => 'Per tutti']],
            'status' => 'published', 'always_active' => true, 'seo_title' => 'Lezioni Sport Acquatici', 'seo_description' => 'Partecipa alle lezioni di sport acquatici.',
            'image_path' => 'promos/sonia/x.jpg',
            'image_variants' => ['decor' => ['offer-0' => ['path' => 'promos/sonia/decor-offer-0.jpg', 'label' => 'Lezione di Surf', 'key' => 'beauty']]],
        ]);

        return [$tenant, $user, $promo];
    }

    public function test_editing_a_promo_refreshes_seo_and_drops_decor_of_changed_offers(): void
    {
        [$tenant, $user, $promo] = $this->setupPromo();

        $this->actingAs($user)->put(route('admin.promos.update', [$tenant, $promo]), [
            'title' => 'Lezioni scolastiche di Economia',
            'description' => 'Ripetizioni con Sonia, laureata in Economia e Commercio.',
            'offers' => [['name' => 'Ripetizioni di Economia', 'price' => '', 'detail' => 'Passo dopo passo']],
            'always_active' => 1,
        ])->assertRedirect();

        $promo->refresh();
        $this->assertSame('Lezioni scolastiche di Economia', $promo->seo_title);
        $this->assertStringContainsString('Economia e Commercio', $promo->seo_description);
        $this->assertArrayNotHasKey('offer-0', $promo->image_variants['decor'] ?? []);
    }

    public function test_untouched_offers_keep_their_decor_image(): void
    {
        [$tenant, $user, $promo] = $this->setupPromo();

        $this->actingAs($user)->put(route('admin.promos.update', [$tenant, $promo]), [
            'title' => 'Lezioni di sport acquatici',
            'description' => 'Impara il surf.',
            'offers' => [['name' => 'Lezione di Surf', 'price' => '', 'detail' => 'Per tutti']],
            'always_active' => 1,
        ])->assertRedirect();

        $this->assertArrayHasKey('offer-0', $promo->fresh()->image_variants['decor']);
    }

    public function test_public_promo_page_has_no_beauty_center_copy_for_other_businesses(): void
    {
        [$tenant, , $promo] = $this->setupPromo();

        $this->get(route('promo.show', [$tenant, $promo]))
            ->assertOk()
            ->assertDontSee('look e il benessere del corpo')
            ->assertDontSee('Ti accogliamo in')
            ->assertDontSee('passa a trovarci negli orari di apertura');
    }
}
