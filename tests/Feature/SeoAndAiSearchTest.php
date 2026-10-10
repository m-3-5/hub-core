<?php

namespace Tests\Feature;

use App\Models\ClassifiedAd;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use M35\HubPayments\Models\PayableService;
use Tests\TestCase;

class SeoAndAiSearchTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug = 'beauty'): Tenant
    {
        return Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'type' => 'azienda', 'plan' => 'demo', 'website' => 'https://'.$slug.'.example']);
    }

    /** @return array<string, mixed> primo blocco JSON-LD della pagina, già decodificato */
    private function jsonLd(string $html): array
    {
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $data = json_decode($m[1] ?? '', true);

        $this->assertIsArray($data, 'La pagina deve avere un JSON-LD valido');
        $this->assertSame('https://schema.org', $data['@context']);
        $this->assertStringNotContainsString('<?php', $html);

        return $data;
    }

    public function test_home_describes_the_company_the_website_and_the_app_for_google_and_ai(): void
    {
        $data = $this->jsonLd($this->get('/')->assertOk()->getContent());
        $types = collect($data['@graph'])->pluck('@type')->all();

        $this->assertSame(['Organization', 'WebSite', 'SoftwareApplication'], $types);
        $this->assertSame('M 3.5 S.R.L.', $data['@graph'][0]['name']);
        $this->assertContains('https://www.facebook.com/groups/1851508502319600', $data['@graph'][0]['sameAs']);
    }

    public function test_tenant_pages_list_their_content_in_structured_data(): void
    {
        $tenant = $this->tenant();
        $tenant->promos()->create(['title' => 'Saldi', 'slug' => 'saldi', 'status' => 'published', 'always_active' => true, 'published_at' => now()]);
        PayableService::create(['tenant_id' => $tenant->id, 'type' => 'service', 'title' => 'Piega', 'slug' => 'piega', 'amount_cents' => 3000, 'status' => 'active', 'published_to_site' => true, 'payment_url' => 'https://buy.stripe.com/x']);
        PayableService::create(['tenant_id' => $tenant->id, 'type' => 'product', 'title' => 'Shampoo', 'slug' => 'shampoo', 'amount_cents' => 1500, 'status' => 'active', 'published_to_site' => true, 'payment_url' => 'https://buy.stripe.com/y']);

        $promos = $this->jsonLd($this->get('/p/beauty')->assertOk()->getContent());
        $this->assertSame('Saldi', $promos['@graph'][0]['mainEntity']['itemListElement'][0]['name']);

        $catalog = $this->jsonLd($this->get('/s/beauty')->assertOk()->getContent());
        $this->assertSame('LocalBusiness', $catalog['@graph'][0]['@type']);
        $this->assertSame(['Piega', 'Shampoo'], array_column($catalog['@graph'][1]['mainEntity']['itemListElement'], 'name'));

        $hub = $this->jsonLd($this->get('/promo')->assertOk()->getContent());
        $this->assertSame('Saldi', $hub['@graph'][0]['mainEntity']['itemListElement'][0]['name']);
    }

    public function test_classified_ads_expose_listing_data_with_price(): void
    {
        $tenant = $this->tenant();
        ClassifiedAd::create([
            'tenant_id' => $tenant->id, 'slug' => 'bilocale-mare', 'title' => 'Bilocale vicino al lago', 'zone' => 'Castiglione delle Stiviere',
            'description' => 'Bilocale luminoso <b>arredato</b>.', 'price' => 450, 'price_unit' => 'mese', 'category' => 'affitto',
            'status' => 'published', 'published_at' => now(),
        ]);

        $board = $this->jsonLd($this->get('/annunci')->assertOk()->getContent());
        $this->assertSame('Bilocale vicino al lago', $board['@graph'][0]['mainEntity']['itemListElement'][0]['name']);

        $ad = $this->jsonLd($this->get('/a/beauty/bilocale-mare')->assertOk()->getContent());
        $listing = $ad['@graph'][0];
        $this->assertSame('RealEstateListing', $listing['@type']);
        $this->assertSame('450.00', $listing['offers']['price']);
        $this->assertSame('EUR', $listing['offers']['priceCurrency']);
        $this->assertStringNotContainsString('<b>', $listing['description']);
    }

    public function test_llms_txt_presents_the_site_for_ai_search(): void
    {
        $tenant = $this->tenant();
        $tenant->promos()->create(['title' => 'Saldi', 'slug' => 'saldi', 'status' => 'published', 'always_active' => true, 'published_at' => now()]);

        $response = $this->get('/llms.txt')->assertOk();
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));
        $text = $response->getContent();

        foreach (['# M 3.5', 'Corigliano-Rossano', 'Fabrizio', 'Vetrina', 'IVA esclusa', route('landing.web'), route('pricing.show'),
            'https://wa.me/393487564418', 'https://www.facebook.com/groups/1851508502319600', url('/p/beauty/saldi'), route('sitemap')] as $needle) {
            $this->assertStringContainsString($needle, $text, $needle);
        }

        $this->assertStringNotContainsString('/admin', $text);
    }

    public function test_landing_has_the_whatsapp_number_and_the_facebook_group(): void
    {
        $html = $this->get('/siti-web-corigliano-rossano')->assertOk()->getContent();

        $this->assertStringContainsString('https://wa.me/393487564418', $html);
        $this->assertStringContainsString('https://www.facebook.com/groups/1851508502319600', $html);
        $this->assertStringContainsString('Entra nel gruppo', $html);

        $this->get('/')->assertSee('facebook.com/groups/1851508502319600', false);
    }

    public function test_indexnow_key_is_published_and_submission_lists_public_pages(): void
    {
        $key = $this->get('/indexnow-key.txt')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $key);

        \Illuminate\Support\Facades\Http::fake(['api.indexnow.org/*' => \Illuminate\Support\Facades\Http::response('', 202)]);
        $this->artisan('hub:indexnow')->assertSuccessful();

        \Illuminate\Support\Facades\Http::assertSent(fn ($request) => $request['key'] === $key
            && $request['keyLocation'] === route('indexnow.key')
            && in_array(route('landing.web'), $request['urlList'], true));
    }

    public function test_group_name_can_be_changed_without_touching_code(): void
    {
        config(['landing.facebook_group.name' => 'Community Sibaritide']);

        $this->get('/siti-web-corigliano-rossano')->assertSee('Community Sibaritide');
        $this->get('/llms.txt')->assertSee('Community Sibaritide: https://www.facebook.com/groups/1851508502319600', false);
    }
}
