<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** inm35.it scaricabile come app (PWA): manifest, icone, service worker con pagina offline, tasto «Installa l'app». */
class InstallableAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_manifest_is_valid_with_normal_and_maskable_icons(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertStringStartsWith('/app', $manifest['start_url']);
        $this->assertNotEmpty($manifest['theme_color']);

        $sizes = [];
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
            [$w, $h] = getimagesize(public_path(ltrim($icon['src'], '/')));
            $this->assertSame($icon['sizes'], $w.'x'.$h);
            $sizes[$icon['purpose']][] = $icon['sizes'];
        }
        $this->assertEqualsCanonicalizing(['192x192', '512x512'], $sizes['any']);
        $this->assertEqualsCanonicalizing(['192x192', '512x512'], $sizes['maskable']);
    }

    public function test_the_service_worker_shows_the_offline_page_without_network(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("'/offline.html'", $sw);
        $this->assertStringContainsString("request.mode === 'navigate'", $sw);
        $this->assertStringContainsString('Sei offline', file_get_contents(public_path('offline.html')));
    }

    public function test_platform_pages_link_the_manifest_and_register_the_service_worker(): void
    {
        foreach ([route('welcome'), route('admin.login'), route('pricing.show')] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('manifest.webmanifest', false)
                ->assertSee("navigator.serviceWorker.register('/sw.js'", false)
                ->assertSee('name="theme-color"', false);
        }
    }

    public function test_the_install_button_is_on_the_home_and_in_the_app_but_not_on_a_client_promo_page(): void
    {
        $tenant = Tenant::create(['name' => 'Salone Anna', 'slug' => 'salone-anna', 'type' => 'azienda', 'plan' => 'demo', 'guest_verified_at' => now(), 'subscription_status' => 'active']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant, ['role' => 'admin']);

        $this->get(route('welcome'))->assertOk()->assertSee('data-install', false)->assertSee('Aggiungi a Home');
        $this->actingAs($user)->get(route('app.home', $tenant))->assertOk()->assertSee('data-install', false)->assertSee('js/hub-pwa.js', false);

        $this->get(route('promo.archive', $tenant))->assertOk()
            ->assertDontSee('manifest.webmanifest', false)
            ->assertDontSee('data-install', false);
    }

    public function test_the_platform_bars_show_the_m35_logo_instead_of_a_letter(): void
    {
        $this->get(route('welcome'))->assertOk()
            ->assertSee('images/logo-piramide-128.png', false)
            ->assertDontSee('<span class="ui-pub__logo">H</span>', false);
    }
}
