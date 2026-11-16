<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use M35\HubPayments\Models\PayableService;
use M35\HubPayments\Support\TenantBankTransfer;
use M35\HubPayments\Support\TenantStripeConfig;
use Tests\TestCase;

/**
 * Prodotti e servizi si creano anche senza Stripe: restano «in vetrina» (Chiedi info).
 * «Acquista» compare solo se l'attività può incassare; col bonifico il cliente vede IBAN e causale.
 */
class ShowcaseAndBankTransferTest extends TestCase
{
    use RefreshDatabase;

    private const IBAN = 'IT60X0542811101000000123456';

    /** @return array{0: Tenant, 1: User} */
    private function seller(array $settings = [], ?string $phone = null): array
    {
        $tenant = Tenant::create(['name' => 'Ferramenta Rossi', 'slug' => 'ferramenta-rossi', 'type' => 'azienda', 'plan' => 'demo', 'guest_verified_at' => now(), 'settings' => $settings, 'phone' => $phone]);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);

        return [$tenant, $user];
    }

    private function createProduct(Tenant $tenant, User $user)
    {
        return $this->actingAs($user)->post(route('admin.products.store', $tenant), [
            'title' => 'Trapano a batteria', 'description' => 'Con due batterie.', 'amount' => '89.90', 'published_to_site' => 1,
        ]);
    }

    public function test_a_product_is_created_without_stripe_and_stays_in_the_shop_window(): void
    {
        Http::fake();
        [$tenant, $user] = $this->seller(['whatsapp' => '393401112222']);

        $this->createProduct($tenant, $user)->assertRedirect()->assertSessionHas('status');

        $product = PayableService::sole();
        $this->assertNull($product->payment_url);
        $this->assertSame(8990, $product->amount_cents);
        Http::assertNothingSent();

        $this->get(route('admin.products.index', $tenant))->assertOk()->assertSee('Vetrina');
        $this->get(route('admin.products.show', [$tenant, $product]))->assertOk()->assertSee('Ricevi pagamenti online');

        $this->get(route('services.public.show', [$tenant, $product]))->assertOk()
            ->assertSee('Chiedi info')->assertSee('https://wa.me/393401112222', false)
            ->assertDontSee('Acquista ora')->assertDontSee('Pagamento sicuro tramite Stripe');
        $this->get(route('services.public.archive', $tenant))->assertOk()->assertDontSee('Acquista online, pagamento sicuro');
    }

    public function test_without_whatsapp_the_ask_button_calls_the_business(): void
    {
        [$tenant, $user] = $this->seller([], '0983 123456');
        $this->createProduct($tenant, $user);

        $this->get(route('services.public.show', [$tenant, PayableService::sole()]))->assertSee('href="tel:0983123456"', false);
    }

    public function test_with_bank_details_the_customer_sees_iban_amount_and_reason(): void
    {
        [$tenant, $user] = $this->seller();
        $this->actingAs($user)->post(route('admin.payout.bank', $tenant), ['bank_holder' => 'Mario Rossi', 'bank_iban' => 'it60 x054 2811 1010 0000 0123 456'])
            ->assertRedirect(route('admin.payout.setup', $tenant));
        $this->assertSame(self::IBAN, TenantBankTransfer::details($tenant->fresh())['iban']);

        $this->createProduct($tenant->fresh(), $user);

        $this->get(route('services.public.show', [$tenant, PayableService::sole()]))->assertOk()
            ->assertSee('Paga con bonifico')->assertSee('Mario Rossi')->assertSee('IT60 X054 2811 1010 0000 0123 456')
            ->assertSee('89,90')->assertSee('Trapano a batteria')
            ->assertDontSee('Acquista ora');
        $this->get(route('admin.products.index', $tenant))->assertDontSee('sono in vetrina');
    }

    public function test_a_wrong_iban_is_refused_and_the_iban_can_be_removed(): void
    {
        [$tenant, $user] = $this->seller();
        $this->actingAs($user)->post(route('admin.payout.bank', $tenant), ['bank_holder' => 'Mario', 'bank_iban' => 'IT60X0542811101000000123457'])
            ->assertSessionHasErrors('bank_iban');
        $this->assertFalse(TenantBankTransfer::isSet($tenant->fresh()));

        $this->post(route('admin.payout.bank', $tenant), ['bank_holder' => 'Mario', 'bank_iban' => self::IBAN]);
        $this->get(route('admin.payout.setup', $tenant))->assertOk()->assertSee('Togli IBAN');
        $this->delete(route('admin.payout.bank.forget', $tenant))->assertRedirect();
        $this->assertFalse(TenantBankTransfer::isSet($tenant->fresh()));
    }

    public function test_with_stripe_the_buy_button_is_there_and_a_showcase_item_gets_its_link_on_save(): void
    {
        Http::fake([
            'api.stripe.com/v1/products*' => Http::response(['id' => 'prod_1']),
            'api.stripe.com/v1/prices' => Http::response(['id' => 'price_1']),
            'api.stripe.com/v1/payment_links' => Http::response(['id' => 'plink_1', 'url' => 'https://buy.stripe.com/test_1']),
        ]);
        [$tenant, $user] = $this->seller();
        $this->createProduct($tenant, $user);
        $product = PayableService::sole();

        // ora collega il suo Stripe e salva il prodotto: nasce il link di pagamento
        TenantStripeConfig::store($tenant->fresh(), 'sk_test_'.str_repeat('x', 24));
        $this->put(route('admin.products.update', [$tenant, $product]), ['title' => 'Trapano a batteria', 'amount' => '89.90', 'published_to_site' => 1])
            ->assertRedirect();

        $this->assertSame('https://buy.stripe.com/test_1', $product->fresh()->payment_url);
        $this->get(route('services.public.show', [$tenant, $product]))->assertSee('Acquista ora')->assertDontSee('Chiedi info');
    }
}
