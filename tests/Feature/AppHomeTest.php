<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Home dell'app pulita: «Sito web a 1 €» in cima, tutti i servizi con solo icona e nome; senza account si prova da ospite. */
class AppHomeTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $type = 'azienda'): array
    {
        $tenant = Tenant::create(['name' => 'Ferramenta Rossi', 'slug' => 'ferramenta-rossi', 'type' => $type, 'plan' => 'demo', 'guest_verified_at' => now(), 'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(20)]);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);

        return [$tenant, $user];
    }

    public function test_a_business_home_shows_the_one_euro_site_first_and_every_service_without_descriptions(): void
    {
        [$tenant, $user] = $this->tenant();

        $html = $this->actingAs($user)->get(route('app.home', $tenant))->assertOk()
            ->assertSee('Sito web a 1 €')
            ->assertSee(route('admin.sitebuilder.show', $tenant), false)
            ->assertSee('class="ah-grid"', false)
            ->assertSee('Crea promo')->assertSee('Preventivi')->assertSee('Annunci')
            ->getContent();

        // niente spiegazioni dei moduli nella home
        foreach (config('hub.modules') as $module) {
            $this->assertStringNotContainsString($module['description'], $html, $module['key']);
        }
        $this->assertLessThan(strpos($html, 'class="ah-grid"'), strpos($html, 'Sito web a 1 €'));
    }

    public function test_a_private_account_has_no_website_banner(): void
    {
        [$tenant, $user] = $this->tenant('privato');

        $this->actingAs($user)->get(route('app.home', $tenant))->assertOk()
            ->assertDontSee('Sito web a 1 €')
            ->assertSee('Annunci');
    }

    public function test_the_app_without_an_account_shows_all_services_and_lets_you_start_as_a_guest(): void
    {
        $this->get(route('app.index'))->assertOk()
            ->assertSee('Sito web a 1 €')
            ->assertSee(route('site.start'), false)
            ->assertSee('data-guest-start="promo"', false)
            ->assertSee(route('guest.start'), false)
            ->assertSee('Accedi')->assertSee('Registrati');
    }

    public function test_starting_as_a_guest_from_a_tile_goes_straight_to_that_service(): void
    {
        $this->post(route('guest.start'), ['type' => 'azienda', 'company_name' => 'Pizzeria Da Gino', 'vai' => 'classifieds'])
            ->assertRedirect(route('admin.classifieds.index', 'pizzeria-da-gino'));

        $this->assertAuthenticated();
    }

    public function test_an_unknown_service_is_refused_and_a_logged_in_user_still_sees_their_businesses(): void
    {
        $this->post(route('guest.start'), ['type' => 'azienda', 'company_name' => 'X', 'vai' => 'billing'])->assertSessionHasErrors('vai');

        [, $user] = $this->tenant();
        $this->actingAs($user)->get(route('app.index'))->assertOk()->assertSee('Ferramenta Rossi')->assertDontSee('data-guest-start', false);
    }
}
