<?php

namespace Tests\Feature;

use App\Models\ClassifiedAd;
use App\Models\Promo;
use App\Models\Tenant;
use App\Services\IndexNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use M35\HubPayments\Models\PayableService;
use Tests\TestCase;

class SeoSearchIndexNowTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::create(['name' => 'Sonia', 'slug' => 'sonia', 'type' => 'privato', 'plan' => 'demo', 'subscription_status' => 'free']);
    }

    public function test_search_finds_published_promos_offers_and_ads_and_nothing_private(): void
    {
        $t = $this->tenant();
        Promo::create(['tenant_id' => $t->id, 'title' => 'Sconto economia', 'slug' => 'sconto', 'status' => 'published', 'always_active' => true, 'description' => 'x']);
        Promo::create(['tenant_id' => $t->id, 'title' => 'Bozza economia segreta', 'slug' => 'bozza', 'status' => 'draft', 'description' => 'x']);
        PayableService::create(['tenant_id' => $t->id, 'type' => 'service', 'title' => 'Ripetizioni di economia', 'slug' => 'rip', 'amount_cents' => 2500, 'status' => 'active', 'published_to_site' => true]);
        PayableService::create(['tenant_id' => $t->id, 'type' => 'quote', 'title' => 'Preventivo economia', 'slug' => 'prev', 'amount_cents' => 100, 'status' => 'active', 'published_to_site' => true]);
        $t->classifiedAds()->create(['slug' => 'lezioni', 'category' => 'servizi', 'title' => 'Lezioni di Economia', 'zone' => 'Online', 'description' => 'x', 'status' => 'published', 'published_at' => now()]);
        $t->classifiedAds()->create(['slug' => 'draft', 'category' => 'servizi', 'title' => 'Economia in bozza', 'zone' => 'Online', 'description' => 'x', 'status' => 'draft']);

        $this->get(route('search', ['q' => 'economia']))->assertOk()
            ->assertSee('Sconto economia')->assertSee('Ripetizioni di economia')->assertSee('Lezioni di Economia')
            ->assertDontSee('Bozza economia segreta')->assertDontSee('Preventivo economia')->assertDontSee('Economia in bozza')
            ->assertSee('noindex, follow', false);

        $this->get(route('search', ['q' => 'x']))->assertOk()->assertSee('almeno due lettere');
        $this->get(route('search', ['q' => 'zzzzqq']))->assertOk()->assertSee('Nessun risultato');
    }

    public function test_opensearch_and_site_search_action_are_exposed(): void
    {
        $this->get(route('opensearch'))->assertOk()->assertSee('{searchTerms}', false);
        $this->get(route('welcome'))->assertOk()->assertSee('SearchAction', false)->assertSee('rel="search"', false);
    }

    public function test_ad_structured_data_type_follows_the_category(): void
    {
        $t = $this->tenant();
        $make = fn (string $cat, array $f = []) => $t->classifiedAds()->create([
            'slug' => 'a-'.$cat, 'category' => $cat, 'title' => 'Titolo '.$cat, 'zone' => 'Online', 'description' => 'x',
            'features' => $f, 'price' => 10, 'status' => 'published', 'published_at' => now(),
        ]);

        $book = $make('libri', ['author' => 'Hazar Guellil', 'buy_url' => 'https://www.amazon.it/dp/X']);
        $this->get($book->publicUrl())->assertOk()->assertSee('"@type":"Book"', false)->assertSee('"name":"Hazar Guellil"', false)->assertSee('https://www.amazon.it/dp/X', false);

        $this->get($make('servizi')->publicUrl())->assertSee('"@type":"Service"', false);
        $this->get($make('oggetti', ['condition' => 'usato'])->publicUrl())->assertSee('"@type":"Product"', false)->assertSee('UsedCondition', false);
        $this->get($make('affitto')->publicUrl())->assertSee('"@type":"RealEstateListing"', false);
    }

    public function test_indexnow_key_file_and_ping(): void
    {
        $this->get('/indexnow-key.txt')->assertOk()->assertSee(IndexNow::key());

        Http::fake(['api.indexnow.org/*' => Http::response('', 200)]);
        $this->assertTrue(IndexNow::ping(['https://inm35.it/a/sonia/x', 'https://inm35.it/a/sonia/x']));

        Http::assertSent(fn ($r) => $r->url() === 'https://api.indexnow.org/indexnow'
            && $r['key'] === IndexNow::key()
            && $r['keyLocation'] === IndexNow::keyUrl()
            && $r['urlList'] === ['https://inm35.it/a/sonia/x']);
    }

    public function test_publishing_refreshes_the_sitemap_immediately(): void
    {
        $t = $this->tenant();
        Cache::put('sitemap.xml', 'vecchia', 3600);

        $t->classifiedAds()->create(['slug' => 'nuovo', 'category' => 'oggetti', 'title' => 'Nuovo', 'zone' => 'Online', 'description' => 'x', 'status' => 'published', 'published_at' => now()]);

        $this->assertNull(Cache::get('sitemap.xml'));
    }
}
