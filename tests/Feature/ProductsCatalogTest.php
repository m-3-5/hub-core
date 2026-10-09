<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantModuleCharge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use M35\HubPayments\Models\PayableService;
use Tests\TestCase;

class ProductsCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug, bool $stripe = true): Tenant
    {
        return Tenant::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'type' => 'azienda',
            'plan' => 'demo',
            'settings' => $stripe ? ['stripe' => ['secret_key' => Crypt::encryptString('sk_test_'.str_repeat('a', 24))]] : [],
        ]);
    }

    private function item(Tenant $tenant, string $type, string $title, array $extra = []): PayableService
    {
        return PayableService::create(array_merge([
            'tenant_id' => $tenant->id,
            'type' => $type,
            'title' => $title,
            'slug' => PayableService::uniqueSlugForTenant($tenant->id, $title),
            'amount_cents' => 1500,
            'currency' => 'eur',
            'payment_url' => 'https://buy.stripe.com/test_'.md5($title),
            'status' => 'active',
            'published_to_site' => true,
        ], $extra));
    }

    private function owner(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => 'admin']);

        return $user;
    }

    public function test_products_api_lists_only_published_active_products_of_that_tenant(): void
    {
        $beauty = $this->tenant('beauty');
        $other = $this->tenant('altro');

        $this->item($beauty, 'product', 'Shampoo');
        $this->item($beauty, 'product', 'Maschera bozza', ['published_to_site' => false]);
        $this->item($beauty, 'product', 'Vecchio', ['status' => 'archived']);
        $this->item($beauty, 'service', 'Piega');
        $this->item($other, 'product', 'Prodotto altrui');

        $response = $this->getJson('/api/v1/beauty/products')->assertOk();

        $response->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.title', 'Shampoo')
            ->assertJsonMissingPath('services');

        $this->getJson('/api/v1/beauty/services')->assertOk()
            ->assertJsonCount(1, 'services')
            ->assertJsonPath('services.0.title', 'Piega');
    }

    public function test_creating_a_product_uses_stripe_and_never_consumes_the_service_quota(): void
    {
        Http::fake([
            'api.stripe.com/v1/products' => Http::response(['id' => 'prod_1']),
            'api.stripe.com/v1/prices' => Http::response(['id' => 'price_1']),
            'api.stripe.com/v1/payment_links' => Http::response(['id' => 'plink_1', 'url' => 'https://buy.stripe.com/test_x']),
        ]);

        $tenant = $this->tenant('beauty');
        $tenant->forceFill(['settings' => array_merge($tenant->settings, ['services_included_quota' => 0])])->save();
        $user = $this->owner($tenant);

        $this->actingAs($user)->post(route('admin.products.store', $tenant), [
            'title' => 'Siero capelli',
            'amount' => '24.90',
            'published_to_site' => 1,
        ])->assertRedirect();

        $product = PayableService::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('product', $product->type);
        $this->assertSame(2490, $product->amount_cents);
        $this->assertSame('price_1', $product->stripe_price_id);
        $this->assertSame(0, TenantModuleCharge::count(), 'i prodotti non generano extra a pagamento');
    }

    public function test_services_and_products_do_not_mix_in_the_panel_and_tenants_are_isolated(): void
    {
        $beauty = $this->tenant('beauty');
        $other = $this->tenant('altro');
        $service = $this->item($beauty, 'service', 'Piega');
        $product = $this->item($beauty, 'product', 'Shampoo');
        $foreign = $this->item($other, 'product', 'Altrui');
        $user = $this->owner($beauty);

        $this->actingAs($user)->get(route('admin.products.index', $beauty))
            ->assertOk()->assertSee('Shampoo')->assertDontSee('Piega')->assertDontSee('Altrui');

        $this->actingAs($user)->get(route('admin.services.index', $beauty))
            ->assertOk()->assertSee('Piega')->assertDontSee('Shampoo');

        $this->actingAs($user)->get(route('admin.products.show', [$beauty, $service]))->assertNotFound();
        $this->actingAs($user)->get(route('admin.services.show', [$beauty, $product]))->assertNotFound();
        $this->actingAs($user)->get(route('admin.products.show', [$other, $foreign]))->assertForbidden();
    }
}
