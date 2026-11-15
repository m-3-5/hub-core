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

    public function test_app_pages_show_only_functions_and_send_costs_to_a_separate_page(): void
    {
        [$tenant, $user] = $this->tenant();
        $tenant->update(['trial_ends_at' => now()->addDays(3)]);
        $this->actingAs($user);

        foreach (['app.home', 'admin.promos.index', 'admin.promos.create', 'admin.services.index', 'admin.products.index', 'admin.quotes.index', 'admin.orders.index', 'admin.classifieds.index'] as $route) {
            $response = $this->get(route($route, $tenant))->assertOk();
            foreach (['per continuare', 'Pacchetto mensile', 'promo incluse', 'Demo inclusa', 'Trattenute di Stripe', 'senza costi extra', 'una volta sola', 'A carico dell\'azienda'] as $text) {
                $response->assertDontSee($text, false);
            }
        }

        $this->get(route('app.home', $tenant))->assertSee('Prova: 3 giorni')->assertSee('class="ui-trial"', false);
        $this->get(route('admin.services.index', $tenant))->assertSee('Costi e condizioni')->assertSee(route('terms.economic'), false);
    }

    public function test_a_guest_sees_one_button_to_register_and_comes_back_where_they_were(): void
    {
        $this->post(route('guest.start'), ['type' => 'azienda', 'company_name' => 'Pizzeria Da Gino']);
        $tenant = Tenant::where('slug', 'pizzeria-da-gino')->sole();
        $back = '/admin/tenants/pizzeria-da-gino/sitebuilder';

        $this->get(route('app.home', $tenant))->assertOk()
            ->assertSee('Vuoi andare online?')
            ->assertSee(route('guest.claim'), false);

        $this->get(route('guest.claim', ['torna' => $back]))->assertOk()->assertSee('Email o cellulare');
        $this->post(route('guest.claim.store'), ['contact' => '+39 340 000 1111', 'password' => 'password-di-prova', 'consent' => 1])
            ->assertRedirect($back);

        $this->assertNotNull($tenant->fresh()->guest_verified_at);
        $this->get(route('app.home', $tenant))->assertDontSee('Vuoi andare online?');
        // niente rimandi verso altri siti
        $this->get(route('guest.claim', ['torna' => '//evil.example']))->assertRedirect(route('app.index'));
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
