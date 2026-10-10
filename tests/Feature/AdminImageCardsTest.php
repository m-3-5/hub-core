<?php

namespace Tests\Feature;

use App\Models\ClassifiedAd;
use App\Models\Promo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Liste admin a schede con immagine: matita per modificare, X per eliminare. */
class AdminImageCardsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): array
    {
        $tenant = Tenant::create(['name' => 'Beauty', 'slug' => 'beauty', 'type' => 'azienda', 'plan' => 'demo']);
        $tenant->forceFill(['guest_verified_at' => now()])->save();
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => 'admin']);

        return [$tenant, $user];
    }

    public function test_promo_list_shows_image_cards_with_edit_and_delete(): void
    {
        [$tenant, $user] = $this->owner();
        $active = $tenant->promos()->create(['title' => 'Saldi estate', 'slug' => 'saldi', 'status' => 'published', 'always_active' => true, 'published_at' => now(), 'image_path' => 'promos/saldi.jpg']);
        $draft = $tenant->promos()->create(['title' => 'Bozza autunno', 'slug' => 'bozza', 'status' => 'draft']);

        $html = $this->actingAs($user)->get(route('admin.promos.index', $tenant))->assertOk()->getContent();

        $this->assertStringContainsString('class="mgrid"', $html);
        $this->assertStringContainsString('promos/saldi.jpg', $html);
        $this->assertStringContainsString(route('admin.promos.edit', [$tenant, $active]), $html);
        $this->assertStringContainsString(route('admin.promos.destroy', [$tenant, $active]), $html);
        $this->assertStringContainsString(route('admin.promos.edit', [$tenant, $draft]), $html);
        $this->assertStringContainsString('Nessuna immagine', $html);
        $this->assertStringContainsString('Nuova promo', $html);
    }

    public function test_deleting_a_promo_from_its_card_returns_to_the_list(): void
    {
        [$tenant, $user] = $this->owner();
        $promo = $tenant->promos()->create(['title' => 'Da togliere', 'slug' => 'da-togliere', 'status' => 'draft']);

        $this->actingAs($user)->delete(route('admin.promos.destroy', [$tenant, $promo]))
            ->assertRedirect(route('admin.promos.index', $tenant))
            ->assertSessionHas('success', 'Promo eliminata.');

        $this->assertDatabaseMissing('promos', ['id' => $promo->id]);
    }

    public function test_classified_list_shows_cards_with_publish_edit_and_delete(): void
    {
        [$tenant, $user] = $this->owner();
        $ad = ClassifiedAd::create([
            'tenant_id' => $tenant->id, 'slug' => 'bilocale', 'title' => 'Bilocale al lago', 'zone' => 'Sirmione', 'category' => 'affitto',
            'price' => 450, 'price_unit' => 'mese', 'status' => 'draft', 'description' => 'Luminoso',
        ]);

        $html = $this->actingAs($user)->get(route('admin.classifieds.index', $tenant))->assertOk()->getContent();

        $this->assertStringContainsString('Bilocale al lago', $html);
        $this->assertStringContainsString(route('admin.classifieds.edit', [$tenant, $ad]), $html);
        $this->assertStringContainsString(route('admin.classifieds.destroy', [$tenant, $ad]), $html);
        $this->assertStringContainsString(route('admin.classifieds.publish', [$tenant, $ad]), $html);
    }
}
