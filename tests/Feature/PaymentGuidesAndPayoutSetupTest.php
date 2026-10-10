<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NewTicketNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Guide pubbliche su come ricevere i soldi, condizioni economiche e scelta «come vuoi ricevere i soldi?». */
class PaymentGuidesAndPayoutSetupTest extends TestCase
{
    use RefreshDatabase;

    private function seller(): array
    {
        $tenant = Tenant::create(['name' => 'Beauty', 'slug' => 'beauty', 'type' => 'azienda', 'plan' => 'demo']);
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => 'admin']);

        return [$tenant, $user];
    }

    public function test_guides_index_lists_every_way_of_getting_paid(): void
    {
        $html = $this->get('/guide/pagamenti')->assertOk()->getContent();

        foreach (['Pagamenti protetti Hub Core', 'Aprire un conto Stripe', 'Aprire un conto PayPal', 'Ricevere con bonifico', 'Carte prepagate e conti online', route('terms.economic')] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }
    }

    public function test_every_guide_opens_with_official_links_and_safety_advice(): void
    {
        $this->get('/guide/pagamenti/stripe')->assertOk()
            ->assertSee('dashboard.stripe.com/register', false)->assertSee('Secret key')->assertSee('verifica in due passaggi')->assertSee('Non dare mai a nessuno');

        $this->get('/guide/pagamenti/paypal')->assertOk()
            ->assertSee('paypal.com/it/business', false)->assertSee('PayPal.me')->assertSee('Beni e servizi');

        $this->get('/guide/pagamenti/bonifico')->assertOk()->assertSee('IBAN')->assertSee('causale')->assertSee('truffa');
        $this->get('/guide/pagamenti/carte-e-conti-online')->assertOk()->assertSee('IBAN italiano')->assertSee('Conto online');
        $this->get('/guide/pagamenti/pagamenti-protetti')->assertOk()->assertSee('7 giorni')->assertSee('Collega i pagamenti protetti');
    }

    public function test_unknown_guide_is_not_found(): void
    {
        $this->get('/guide/pagamenti/inventata')->assertNotFound();
    }

    public function test_economic_terms_say_that_stripe_costs_may_differ_and_that_direct_selling_is_allowed(): void
    {
        $this->get('/condizioni-economiche')->assertOk()
            ->assertSee('quella che indichiamo o diversa', false)
            ->assertSee('1,5% + 0,25 € a pagamento')
            ->assertSee('Commissione Hub Core')
            ->assertSee('7 giorni')
            ->assertSee('Vendite dirette');
    }

    public function test_guides_and_terms_are_in_the_sitemap(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString(route('guides.index'), $xml);
        $this->assertStringContainsString(route('guides.show', 'paypal'), $xml);
        $this->assertStringContainsString(route('terms.economic'), $xml);
    }

    public function test_the_pricing_page_links_to_guides_and_terms(): void
    {
        $this->get(route('pricing.show'))->assertOk()->assertSee(route('guides.index'), false)->assertSee(route('terms.economic'), false);
    }

    public function test_starting_to_sell_without_any_payment_method_leads_to_the_choice_screen(): void
    {
        [$tenant, $user] = $this->seller();

        $this->actingAs($user)->get(route('admin.wizard.show', [$tenant, 'product']))->assertRedirect(route('admin.payout.setup', $tenant));
    }

    public function test_the_choice_screen_shows_each_option_with_its_guide(): void
    {
        [$tenant, $user] = $this->seller();

        $this->actingAs($user)->get(route('admin.payout.setup', $tenant))->assertOk()
            ->assertSee('Come vuoi ricevere i soldi?')
            ->assertSee('Pagamenti protetti Hub Core')->assertSee('Consigliato')
            ->assertSee('Il mio conto Stripe')->assertSee('PayPal')->assertSee('Bonifico bancario')
            ->assertSee(route('guides.show', 'pagamenti-protetti'), false)
            ->assertSee(route('guides.show', 'stripe'), false)
            ->assertSee(route('guides.show', 'paypal'), false)
            ->assertSee(route('guides.show', 'bonifico'), false)
            ->assertSee(route('guides.show', 'carte-e-conti-online'), false)
            ->assertSee(route('admin.connect.start', $tenant), false)
            ->assertSee(route('terms.economic'), false);
    }

    public function test_choosing_paypal_or_bank_transfer_notifies_the_team_and_keeps_the_guide_handy(): void
    {
        [$tenant, $user] = $this->seller();
        $admin = User::factory()->create(['is_super_admin' => true]);
        Notification::fake();

        $this->actingAs($user)->post(route('admin.payout.interest', $tenant), ['method' => 'paypal'])
            ->assertRedirect(route('admin.payout.setup', $tenant))->assertSessionHas('status');

        $ticket = Ticket::sole();
        $this->assertSame('payout_method', $ticket->context_type);
        $this->assertStringContainsString('PayPal', $ticket->context_label);
        Notification::assertSentTo($admin, NewTicketNotification::class);
    }

    public function test_an_unknown_payout_method_is_rejected(): void
    {
        [$tenant, $user] = $this->seller();

        $this->actingAs($user)->post(route('admin.payout.interest', $tenant), ['method' => 'contanti-in-busta'])->assertSessionHasErrors('method');

        $this->assertSame(0, Ticket::count());
    }

    public function test_another_tenants_user_cannot_use_the_choice_screen(): void
    {
        [$tenant] = $this->seller();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get(route('admin.payout.setup', $tenant))->assertForbidden();
    }

    public function test_max_points_to_the_guides_when_asked_about_getting_paid(): void
    {
        config(['services.gemini.api_key' => null]);

        $this->postJson(route('max.chat'), ['message' => 'Come apro un conto PayPal per incassare?'])
            ->assertOk()->assertJsonPath('actions.0.url', route('guides.index'));
    }
}
