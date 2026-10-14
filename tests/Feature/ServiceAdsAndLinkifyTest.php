<?php

namespace Tests\Feature;

use App\Models\ClassifiedAd;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Linkify;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceAdsAndLinkifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_linkify_makes_only_web_addresses_clickable_and_escapes_html(): void
    {
        $html = Linkify::html('Acquista su https://www.amazon.it/dp/B0HCPWTCRX. <script>alert(1)</script> javascript:alert(1)');

        $this->assertStringContainsString('<a href="https://www.amazon.it/dp/B0HCPWTCRX" target="_blank" rel="nofollow ugc noopener">amazon.it ↗</a>.', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('href="javascript', $html);
    }

    public function test_a_translation_service_ad_can_be_created_with_hourly_price_and_has_no_protected_purchase(): void
    {
        $tenant = Tenant::create(['name' => 'Hazar', 'slug' => 'hazar', 'type' => 'privato', 'plan' => 'demo', 'subscription_status' => 'free']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        $this->actingAs($user)->post(route('admin.classifieds.store', $tenant), [
            'category' => 'servizi', 'title' => 'Traduzioni in francese e inglese', 'zone' => 'Online',
            'price' => 25, 'price_unit' => 'ora', 'protected_sale' => 1, 'description' => 'Traduzioni da italiano e arabo.',
        ])->assertRedirect();

        $ad = ClassifiedAd::sole();
        $this->assertSame('servizi', $ad->category);
        $this->assertSame('Servizi e lezioni', $ad->categoryLabel());
        $this->assertSame("€ 25 all'ora", $ad->priceLabel());
        $this->assertFalse($ad->protected_sale);
    }
}
