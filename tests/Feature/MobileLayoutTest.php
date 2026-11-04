<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/** Pagine da telefono: niente fuori dallo schermo (tabelle che diventano schede, regole condivise in hub-ui.css). */
class MobileLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): array
    {
        $tenant = Tenant::create(['name' => 'Salone Anna', 'slug' => 'salone-anna', 'type' => 'azienda', 'plan' => 'demo', 'guest_verified_at' => now(), 'subscription_status' => 'active']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);

        return [$tenant, $user];
    }

    public function test_the_shared_stylesheet_keeps_everything_inside_a_phone_screen(): void
    {
        $css = file_get_contents(public_path('css/hub-ui.css'));

        $this->assertStringContainsString('box-sizing: border-box', $css);
        $this->assertStringContainsString('overflow-x: clip', $css);
        $this->assertStringContainsString('min-height: 48px', $css);
        $this->assertStringContainsString('.rtable td.ract', $css);
    }

    public function test_payment_links_become_cards_with_buttons_that_wrap_on_the_phone(): void
    {
        [$tenant, $user] = $this->tenant();
        $this->actingAs($user);
        view()->share('errors', new ViewErrorBag);

        $link = ['id' => 'plink_1', 'url' => 'https://buy.stripe.com/test', 'active' => true,
            'line_items' => ['data' => [['price' => ['unit_amount' => 2500, 'currency' => 'eur', 'product' => ['name' => 'Taglio', 'images' => []]]]]]];

        $html = view('hub-payments::admin.services.payment-links', [
            'tenant' => $tenant, 'paymentLinks' => [$link], 'linkedServices' => collect(),
        ])->render();

        $this->assertStringContainsString('class="rtable"', $html);
        $this->assertStringContainsString('class="ract"', $html);
        $this->assertStringContainsString('data-label="Prezzo"', $html);
        $this->assertStringContainsString('Importa e pubblica', $html);
    }

    public function test_the_modules_table_of_the_subscription_page_becomes_cards_on_the_phone(): void
    {
        [$tenant, $user] = $this->tenant();

        $this->actingAs($user)->get(route('admin.billing.show', $tenant))->assertOk()
            ->assertSee('rtable mod-table', false)
            ->assertSee('data-label="Stato"', false);
    }
}
