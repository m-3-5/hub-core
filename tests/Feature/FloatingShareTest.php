<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FloatingShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_get_the_floating_share_script(): void
    {
        $tenant = Tenant::create(['name' => 'Sonia', 'slug' => 'sonia', 'type' => 'privato', 'plan' => 'demo', 'subscription_status' => 'free']);
        $ad = $tenant->classifiedAds()->create(['slug' => 'lezioni', 'category' => 'servizi', 'title' => 'Lezioni', 'zone' => 'Online', 'description' => 'x', 'status' => 'published', 'published_at' => now()]);

        foreach ([route('welcome'), route('pricing.show'), route('classifieds.board'), $ad->publicUrl()] as $url) {
            $this->get($url)->assertOk()->assertSee('js/floating-share.js', false);
        }
    }

    public function test_private_noindex_admin_and_non_html_responses_are_left_alone(): void
    {
        $this->get(route('admin.login'))->assertOk()->assertDontSee('floating-share.js', false);
        $this->get(route('search', ['q' => 'ab']))->assertOk()->assertDontSee('floating-share.js', false);
        $this->get(route('sitemap'))->assertOk()->assertDontSee('floating-share', false);

        $tenant = Tenant::create(['name' => 'Sonia', 'slug' => 'sonia', 'type' => 'privato', 'plan' => 'demo', 'subscription_status' => 'free']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        $this->actingAs($user)->get(route('app.home', $tenant))->assertOk()->assertDontSee('floating-share.js', false);
    }

    public function test_script_file_exists_and_is_discreet_by_design(): void
    {
        $js = file_get_contents(public_path('js/floating-share.js'));

        $this->assertStringContainsString('IDLE_MS', $js);        // compare dopo una pausa
        $this->assertStringContainsString('mousemove', $js);      // si apre avvicinando il mouse
        $this->assertStringContainsString('noindex', $js);        // non compare nelle pagine private
        $this->assertStringContainsString('fs-closed', $js);      // si può nascondere per la sessione
        $this->assertStringContainsString('prefers-reduced-motion', $js);
    }
}
