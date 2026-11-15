<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Niente più tasto «condividi» fluttuante: una sezione «Condividi» ferma sopra il footer. */
class FloatingShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_floating_share_button_anywhere(): void
    {
        $this->assertFileDoesNotExist(public_path('js/floating-share.js'));

        $tenant = Tenant::create(['name' => 'Sonia', 'slug' => 'sonia', 'type' => 'privato', 'plan' => 'demo', 'subscription_status' => 'free']);
        $ad = $tenant->classifiedAds()->create(['slug' => 'lezioni', 'category' => 'servizi', 'title' => 'Lezioni', 'zone' => 'Online', 'description' => 'x', 'status' => 'published', 'published_at' => now()]);

        foreach ([route('welcome'), route('pricing.show'), route('classifieds.board'), $ad->publicUrl(), route('site.start'), route('guides.index')] as $url) {
            $html = $this->get($url)->assertOk()->assertDontSee('floating-share', false)->getContent();
            // una sola sezione «Condividi», sopra il footer
            $this->assertSame(1, substr_count($html, 'share-row share-section'), $url);
        }
    }

    public function test_pages_without_their_own_share_section_get_one_above_the_footer(): void
    {
        $html = $this->get(route('site.start'))->assertOk()->getContent();

        $this->assertStringContainsString('Condividi questa pagina', $html);
        $this->assertStringContainsString('https://wa.me/?text=', $html);
        if (str_contains($html, '<footer')) {
            $this->assertLessThan(strripos($html, '<footer'), strpos($html, 'share-row share-section'));
        }
    }

    public function test_share_buttons_sit_in_their_own_section_at_the_bottom_of_the_page(): void
    {
        $tenant = Tenant::create(['name' => 'Sonia', 'slug' => 'sonia', 'type' => 'privato', 'plan' => 'demo', 'subscription_status' => 'free']);

        $this->get(route('classifieds.tenant-board', $tenant))->assertOk()
            ->assertSeeInOrder(['class="cats"', 'class="filters"', 'share-row share-section'], false);
        $this->get(route('promo.archive', $tenant))->assertOk()
            ->assertSeeInOrder(['Archivio', 'share-row share-section'], false);
        $this->get(route('welcome'))->assertOk()
            ->assertSeeInOrder(['share-row share-section', 'ui-foot'], false);
    }

    public function test_private_noindex_admin_and_non_html_responses_are_left_alone(): void
    {
        $this->get(route('admin.login'))->assertOk()->assertDontSee('share-section', false);
        $this->get(route('search', ['q' => 'ab']))->assertOk()->assertDontSee('share-section', false);
        $this->get(route('sitemap'))->assertOk()->assertDontSee('share-row', false);

        $tenant = Tenant::create(['name' => 'Sonia', 'slug' => 'sonia', 'type' => 'privato', 'plan' => 'demo', 'subscription_status' => 'free']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        $this->actingAs($user)->get(route('app.home', $tenant))->assertOk()->assertDontSee('share-section', false);
    }
}
