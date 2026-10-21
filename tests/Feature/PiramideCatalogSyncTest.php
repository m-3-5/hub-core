<?php

namespace Tests\Feature;

use App\Models\Promo;
use App\Models\PromoSource;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** piramide:sync: porta su inm35.it solo le voci scelte di piramide35.com, senza doppioni. */
class PiramideCatalogSyncTest extends TestCase
{
    use RefreshDatabase;

    private const FEED = 'https://piramide35.com/feed/inm35.json';

    private array $voci;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config(['hub.piramide_feed' => self::FEED, 'hub.piramide_selezione' => ['senise', 'matera']]);

        $this->voci = [
            ['id' => 'senise', 'tipo' => 'paese', 'titolo' => 'Siti web a Senise', 'breve' => 'Siti web a Senise (PZ).', 'url' => 'https://piramide35.com/siti-web/senise', 'immagine' => 'https://piramide35.com/images/hero/senise.webp?v=1'],
            ['id' => 'matera', 'tipo' => 'paese', 'titolo' => 'Siti web a Matera', 'breve' => 'Siti web a Matera.', 'url' => 'https://piramide35.com/siti-web/matera', 'immagine' => 'https://piramide35.com/images/hero/matera.webp?v=1'],
            ['id' => 'roma', 'tipo' => 'paese', 'titolo' => 'Siti web a Roma', 'breve' => 'Siti web a Roma.', 'url' => 'https://piramide35.com/siti-web/roma', 'immagine' => 'https://piramide35.com/images/hero/roma.webp?v=1'],
        ];
        $this->fakeSito();
    }

    private function fakeSito(): void
    {
        Http::fake([
            self::FEED => fn () => Http::response(['voci' => $this->voci]),
            'piramide35.com/images/*' => Http::response(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='), 200, ['Content-Type' => 'image/png']),
        ]);
    }

    private function tenant(): Tenant
    {
        return Tenant::create(['name' => 'Piramide 35', 'slug' => 'piramide35', 'type' => 'azienda', 'plan' => 'dedicated', 'website' => 'https://piramide35.com', 'guest_verified_at' => now()]);
    }

    public function test_porta_solo_le_voci_scelte_come_promo_pubblicate_con_link_alla_pagina(): void
    {
        $tenant = $this->tenant();

        $this->artisan('piramide:sync')->assertSuccessful();

        $this->assertSame(['matera', 'senise'], $tenant->promos()->get()->map(fn ($p) => $p->ai_metadata['source_id'])->sort()->values()->all());
        $promo = Promo::where('title', 'Siti web a Senise')->firstOrFail();
        $this->assertSame('published', $promo->status);
        $this->assertTrue($promo->always_active);
        $this->assertSame('https://piramide35.com/siti-web/senise', $promo->cta_url);
        Storage::disk('public')->assertExists($promo->image_path);
        $this->get(route('promo.show', [$tenant, $promo]))->assertOk()->assertSee('Siti web a Senise');
    }

    public function test_rilanciarlo_non_crea_doppioni_e_una_promo_eliminata_non_torna(): void
    {
        $tenant = $this->tenant();
        $this->artisan('piramide:sync')->assertSuccessful();
        $this->artisan('piramide:sync')->assertSuccessful();
        $this->assertSame(2, $tenant->promos()->count());

        Promo::where('title', 'Siti web a Matera')->firstOrFail()->delete();
        $this->artisan('piramide:sync')->assertSuccessful();

        $this->assertSame(1, $tenant->promos()->count());
        $this->assertSame(2, PromoSource::count());
    }

    public function test_una_pagina_tolta_dal_sito_torna_in_bozza_e_il_testo_nuovo_si_aggiorna(): void
    {
        $tenant = $this->tenant();
        $this->artisan('piramide:sync')->assertSuccessful();

        $this->voci = [array_merge($this->voci[0], ['titolo' => 'Siti web a Senise e dintorni'])];
        $this->artisan('piramide:sync')->assertSuccessful();

        $this->assertSame('Siti web a Senise e dintorni', $tenant->promos()->where('slug', 'like', 'siti-web-a-senise%')->first()->title);
        $this->assertSame('draft', Promo::where('title', 'Siti web a Matera')->first()->status);
    }

    public function test_con_l_opzione_dry_run_non_scrive_niente(): void
    {
        $tenant = $this->tenant();

        $this->artisan('piramide:sync --dry-run')->expectsOutputToContain('Nuove: 2')->assertSuccessful();

        $this->assertSame(0, $tenant->promos()->count());
    }

    public function test_non_scarica_foto_da_altri_siti(): void
    {
        $tenant = $this->tenant();
        $this->voci[0]['immagine'] = 'https://altro.example/foto.png';

        $this->artisan('piramide:sync')->assertFailed();

        $this->assertNull($tenant->promos()->where('title', 'Siti web a Senise')->first());
        $this->assertNotNull($tenant->promos()->where('title', 'Siti web a Matera')->first());
    }
}
