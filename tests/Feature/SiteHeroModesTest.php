<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use M35\HubSitebuilder\Models\GeneratedSite;
use M35\HubSitebuilder\Services\SiteBuilderHeroGenerator;
use M35\HubSitebuilder\Services\SiteContent;
use Tests\TestCase;

/** Parte in alto dei siti: disegno a tema dell'IA per tutti; senza disegno sceglie da sola (foto calma, foto in cornice, colori del sito). */
class SiteHeroModesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function photo(string $path, int $w, int $h, bool $busy): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 90, 120, 160));
        if ($busy) { // insegne, scritte, vetrine: tanti blocchi chiari e scuri
            for ($y = 0; $y < $h; $y += 20) {
                for ($x = 0; $x < $w; $x += 20) {
                    imagefilledrectangle($img, $x, $y, $x + 19, $y + 19, random_int(0, 1) ? 0xF5F5F5 : 0x101010);
                }
            }
        }
        ob_start();
        imagejpeg($img, null, 90);
        Storage::disk('public')->put($path, ob_get_clean());

        return $path;
    }

    private function site(array $photos = [], ?string $art = null, ?string $logo = null): Tenant
    {
        $tenant = Tenant::create(['name' => 'Ufficio Pratiche', 'slug' => 'ufficio-pratiche', 'type' => 'azienda', 'plan' => 'demo', 'guest_verified_at' => now(), 'phone' => '0973 123456', 'address' => 'Via Roma 1, 85038 Senise PZ']);
        GeneratedSite::create(['tenant_id' => $tenant->id, 'status' => 'published', 'published_at' => now(), 'hero_svg_path' => $art, 'answers' => [
            'tagline' => 'Pratiche auto senza code', 'services' => ['Bollo', 'Patenti'], 'extra' => 'Siamo un ufficio di Senise che sbriga le pratiche auto per te ogni giorno con cura e attenzione ai dettagli, senza code agli sportelli e con tutte le informazioni chiare fin dal primo giorno.',
            'cta_label' => 'Chiamaci', 'media' => ['logo' => $logo, 'photos' => $photos],
        ]]);

        return $tenant;
    }

    public function test_busy_shop_front_goes_in_a_frame_and_a_calm_photo_is_the_background(): void
    {
        $busy = $this->photo('sites/x/busy.jpg', 1600, 780, true);
        $calm = $this->photo('sites/x/calm.jpg', 1600, 780, false);

        $this->assertGreaterThan(18, SiteContent::photoBusy(Storage::disk('public')->path($busy)));
        $this->assertLessThan(18, SiteContent::photoBusy(Storage::disk('public')->path($calm)));

        $tenant = $this->site([$busy]);
        $this->get(route('site.public.show', $tenant))->assertOk()->assertSee('hm-split', false)->assertDontSee('class="hero-bg"', false);

        $tenant->generatedSite->update(['answers' => array_merge($tenant->generatedSite->answers, ['media' => ['logo' => null, 'photos' => [$calm]]])]);
        $this->get(route('site.public.show', $tenant))->assertSee('hm-bg', false)->assertSee('class="hero-bg"', false);
    }

    public function test_the_ai_drawing_wins_for_everyone_and_photos_stay_below(): void
    {
        Storage::disk('public')->put('sites/ufficio-pratiche/x/art-abc.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 640"><circle cx="400" cy="320" r="200" fill="#1e4fa3"/></svg>');
        $photo = $this->photo('sites/x/busy.jpg', 1600, 780, true);
        $tenant = $this->site([$photo, $photo], 'sites/ufficio-pratiche/x/art-abc.svg');

        $this->get(route('site.public.show', $tenant))->assertOk()
            ->assertSee('hm-art', false)->assertSee('art-abc.svg', false)
            ->assertSee('about-art photo', false); // la foto vera resta in «Chi siamo»
    }

    public function test_without_photos_logo_is_used_for_sharing_favicon_and_about(): void
    {
        Storage::disk('public')->put('sites/x/logo.png', 'png');
        $tenant = $this->site([], null, 'sites/x/logo.png');

        $html = $this->get(route('site.public.show', $tenant))->assertOk()->getContent();
        $this->assertStringContainsString('hm-brand', $html);
        $this->assertMatchesRegularExpression('#<meta property="og:image" content="[^"]*logo\.png">#', $html);
        $this->assertMatchesRegularExpression('#<link rel="icon" href="[^"]*logo\.png">#', $html);
        $this->assertStringContainsString('about-art logo', $html);
        $this->assertStringNotContainsString('og-hub-core.png', $html);
    }

    public function test_description_is_cut_on_a_word(): void
    {
        $tenant = $this->site();
        $html = $this->get(route('site.public.show', $tenant))->getContent();
        preg_match('#<meta name="description" content="([^"]*)">#', $html, $m);

        $this->assertLessThanOrEqual(160, mb_strlen(html_entity_decode($m[1])));
        $this->assertStringEndsWith('…', html_entity_decode($m[1]));
        $this->assertStringNotContainsString(' …', html_entity_decode($m[1]));
    }

    public function test_brands_are_cleaned_and_shown_under_the_services(): void
    {
        $answers = SiteContent::answers(['tagline' => 'Impianti', 'services' => 'Videocitofonia, Domotica', 'brands' => 'Bticino, <b>Urmet</b>, , Bticino']);
        $this->assertSame(['Bticino', 'Urmet'], $answers['brands']);
        $this->assertArrayNotHasKey('brands', SiteContent::answers(['tagline' => 'Impianti', 'services' => 'Domotica']));

        $tenant = $this->site();
        $this->get(route('site.public.show', $tenant))->assertDontSee('Marchi trattati');

        $tenant->generatedSite->update(['answers' => array_merge($tenant->generatedSite->answers, ['brands' => ['Bticino', 'Urmet']])]);
        $this->get(route('site.public.show', $tenant))->assertSee('Marchi trattati')->assertSee('Urmet');
    }

    public function test_owner_can_ask_for_a_new_drawing_for_free(): void
    {
        $tenant = $this->site();
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);
        Storage::disk('public')->put('sites/ufficio-pratiche/y/art-new.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        $this->mock(SiteBuilderHeroGenerator::class, fn ($m) => $m->shouldReceive('illustration')->once()->andReturn('sites/ufficio-pratiche/y/art-new.svg'));

        $this->actingAs($user)->post(route('admin.sitebuilder.art', $tenant))->assertRedirect()->assertSessionHas('success');
        $this->assertSame('sites/ufficio-pratiche/y/art-new.svg', $tenant->generatedSite->fresh()->hero_svg_path);
    }

    public function test_owner_can_put_the_site_offline_and_back_online(): void
    {
        $tenant = $this->site();
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);

        $this->actingAs($user)->post(route('admin.sitebuilder.visibility', $tenant))->assertRedirect();
        $this->assertTrue($tenant->generatedSite->fresh()->isOffline());
        $this->get(route('site.public.show', $tenant))->assertOk(); // il titolare lo vede in anteprima
        $this->get(route('admin.sitebuilder.show', $tenant))->assertSee('Il tuo sito è offline')->assertSee('Rimetti online');

        auth()->logout();
        $this->get(route('site.public.show', $tenant))->assertNotFound();

        $this->actingAs($user)->post(route('admin.sitebuilder.visibility', $tenant));
        auth()->logout();
        $this->get(route('site.public.show', $tenant))->assertOk();
    }

    public function test_a_broken_drawing_is_asked_again_once(): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        $tenant = $this->site();
        $this->mock(\App\Services\GeminiTextClient::class, fn ($m) => $m->shouldReceive('generate')->twice()->andReturn(
            '<svg xmlns="http://www.w3.org/2000/svg"><circle r="5"', // tagliato a metà
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 640"><circle cx="400" cy="320" r="200" fill="#1d4e89"/></svg>',
        ));

        $path = app(SiteBuilderHeroGenerator::class)->illustration($tenant, ['tagline' => 'Barbiere'], 'sites/ufficio-pratiche/z');

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }
}
