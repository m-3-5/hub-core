<?php

namespace Tests\Feature;

use App\Models\Promo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use M35\HubPayments\Models\PayableService;
use Tests\TestCase;

class CreationWizardTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug = 'beauty', bool $stripe = true, string $type = 'azienda'): Tenant
    {
        $tenant = Tenant::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'type' => $type, 'plan' => 'demo',
            'settings' => $stripe ? ['stripe' => ['secret_key' => Crypt::encryptString('sk_test_'.str_repeat('a', 24))]] : [],
        ]);
        $tenant->forceFill(['guest_verified_at' => now()])->save(); // account già verificato: può pubblicare

        return $tenant;
    }

    private function owner(Tenant $tenant): User
    {
        $user = User::factory()->create();
        $tenant->users()->attach($user->id, ['role' => 'admin']);

        return $user;
    }

    private function fakeGemini(array $json): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        Cache::put('gemini.model_catalog', ['text_models' => ['gemini-test'], 'text_best' => 'gemini-test']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => json_encode($json)]]]]],
            ]),
        ]);
    }

    private function fakeStripe(): void
    {
        Http::fake([
            'api.stripe.com/v1/products' => Http::response(['id' => 'prod_w1']),
            'api.stripe.com/v1/prices' => Http::response(['id' => 'price_w1']),
            'api.stripe.com/v1/payment_links' => Http::response(['id' => 'plink_w1', 'url' => 'https://buy.stripe.com/test_w1']),
        ]);
    }

    // ---- pagine ----

    public function test_wizard_screens_render_for_the_three_kinds_and_need_login(): void
    {
        $tenant = $this->tenant();
        $user = $this->owner($tenant);

        $this->get(route('admin.wizard.show', [$tenant, 'promo']))->assertRedirect();

        foreach (['promo', 'product', 'service'] as $kind) {
            $this->actingAs($user)->get(route('admin.wizard.show', [$tenant, $kind]))
                ->assertOk()
                ->assertSee('Come vuoi cominciare?')
                ->assertSee('Carica una foto')
                ->assertSee('Crea tu la grafica');
        }

        $this->actingAs($user)->get(route('admin.wizard.show', [$tenant, 'promo']))
            ->assertSee('La promo ha una scadenza?')->assertSee('un prezzo?');
        $this->actingAs($user)->get(route('admin.wizard.show', [$tenant, 'product']))
            ->assertSee('Quanto costa?')->assertSee('Lo metti in promo?')->assertDontSee('Quanto dura?');
        $this->actingAs($user)->get(route('admin.wizard.show', [$tenant, 'service']))
            ->assertSee('Quanto dura?')->assertSee('Lo metti in promo?');
        $this->actingAs($user)->get('/admin/tenants/beauty/new/altro')->assertNotFound();
    }

    public function test_product_and_service_wizard_need_stripe_but_promo_does_not(): void
    {
        $tenant = $this->tenant('senza', false);
        $user = $this->owner($tenant);

        $this->actingAs($user)->get(route('admin.wizard.show', [$tenant, 'product']))
            ->assertRedirect(route('admin.services.index', $tenant))->assertSessionHasErrors('stripe');
        $this->actingAs($user)->get(route('admin.wizard.show', [$tenant, 'promo']))->assertOk();
    }

    public function test_other_tenants_members_cannot_open_the_wizard(): void
    {
        $tenant = $this->tenant();
        $other = $this->tenant('altro');
        $intruder = $this->owner($other);

        $this->actingAs($intruder)->get(route('admin.wizard.show', [$tenant, 'promo']))->assertForbidden();
        $this->actingAs($intruder)->post(route('admin.wizard.suggest', $tenant), ['kind' => 'promo', 'image' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();
    }

    // ---- suggerimenti IA dalla foto ----

    public function test_photo_suggestion_returns_title_description_and_price_for_a_product(): void
    {
        $this->fakeGemini(['title' => 'Siero viso illuminante', 'description' => 'Un siero leggero.', 'price' => '24,50 €']);
        $tenant = $this->tenant();
        $user = $this->owner($tenant);

        $this->actingAs($user)->postJson(route('admin.wizard.suggest', $tenant), [
            'kind' => 'product', 'image' => UploadedFile::fake()->image('siero.jpg'),
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('title', 'Siero viso illuminante')
            ->assertJsonPath('description', 'Un siero leggero.')
            ->assertJsonPath('price', '24.50')
            ->assertJsonPath('payload', null);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'generativelanguage')
            && str_contains($r->body(), 'un prodotto in vendita'));
    }

    public function test_photo_suggestion_for_a_promo_keeps_the_reading_so_it_is_not_repeated(): void
    {
        $this->fakeGemini([
            'title' => 'Saldi autunno', 'description' => 'Sconti su tutto.',
            'offers' => [['name' => 'Piega', 'price' => '15€', 'detail' => 'con shampoo']],
        ]);
        $tenant = $this->tenant();
        $user = $this->owner($tenant);

        $response = $this->actingAs($user)->postJson(route('admin.wizard.suggest', $tenant), [
            'kind' => 'promo', 'image' => UploadedFile::fake()->image('volantino.jpg'),
        ])->assertOk()->assertJsonPath('title', 'Saldi autunno')->assertJsonPath('price', '15');

        $this->assertSame('Piega', $response->json('payload.offers.0.name'));
    }

    public function test_photo_suggestion_failure_is_friendly_and_input_is_validated(): void
    {
        config(['services.gemini.api_key' => null]);
        $tenant = $this->tenant();
        $user = $this->owner($tenant);

        $response = $this->actingAs($user)->postJson(route('admin.wizard.suggest', $tenant), [
            'kind' => 'service', 'image' => UploadedFile::fake()->image('a.jpg'),
        ])->assertOk()->assertJsonPath('ok', false);
        $this->assertStringContainsString('scrivi tu il titolo', $response->json('message'));

        $this->actingAs($user)->postJson(route('admin.wizard.suggest', $tenant), ['kind' => 'promo'])->assertStatus(422);
        $this->actingAs($user)->postJson(route('admin.wizard.suggest', $tenant), ['kind' => 'altro', 'image' => UploadedFile::fake()->image('a.jpg')])->assertStatus(422);
        $this->actingAs($user)->postJson(route('admin.wizard.suggest', $tenant), ['kind' => 'promo', 'image' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertStatus(422);
    }

    // ---- salvataggio: promo ----

    public function test_wizard_promo_with_photo_is_created_and_published_with_price_and_expiry(): void
    {
        Storage::fake('public');
        $tenant = $this->tenant();
        $user = $this->owner($tenant);
        $payload = ['title' => 'Titolo IA', 'description' => 'Testo IA', 'offers' => [['name' => 'Piega', 'price' => '10€', 'detail' => 'x']]];

        $response = $this->actingAs($user)->post(route('admin.promos.store', $tenant), [
            'wizard' => 1, 'visual_tier' => 'base', 'promo_source' => 'upload', 'skip_ai' => 1,
            'image' => UploadedFile::fake()->image('promo.jpg', 800, 600),
            'manual_title' => 'Settimana del colore', 'manual_description' => 'Tutta la settimana.',
            'always_active' => 0, 'starts_at' => now()->toDateString(), 'ends_at' => now()->addMonth()->toDateString(),
            'price' => '12,50 €', 'publish_now' => 1, 'ai_payload' => json_encode($payload),
        ]);

        $promo = Promo::sole();
        $response->assertRedirect(route('admin.promos.show', [$tenant, $promo]));
        $this->assertSame('Settimana del colore', $promo->title);
        $this->assertSame('Tutta la settimana.', $promo->description);
        $this->assertSame('published', $promo->status);
        $this->assertNotNull($promo->published_at);
        $this->assertFalse($promo->always_active);
        $this->assertTrue($promo->ends_at->isSameDay(now()->addMonth()));
        $this->assertSame('23:59', $promo->ends_at->format('H:i')); // vale fino a fine giornata
        $this->assertSame('12,50 €', $promo->offers[0]['price']);   // il prezzo scelto sostituisce quello letto
        $this->assertSame('Piega', $promo->offers[0]['name']);
        $this->assertSame('Tutta la settimana.', $promo->description);
    }

    public function test_unverified_guest_account_keeps_the_promo_as_draft_even_when_publishing(): void
    {
        Storage::fake('public');
        $tenant = $this->tenant();
        $tenant->forceFill(['guest_verified_at' => null])->save();
        $user = $this->owner($tenant);

        $this->actingAs($user)->post(route('admin.promos.store', $tenant), [
            'wizard' => 1, 'visual_tier' => 'base', 'promo_source' => 'upload', 'skip_ai' => 1,
            'image' => UploadedFile::fake()->image('promo.jpg', 800, 600),
            'manual_title' => 'Ospite', 'always_active' => 1, 'publish_now' => 1,
        ]);

        $this->assertSame('draft', Promo::sole()->status);
    }

    public function test_wizard_promo_without_price_clears_offers_and_draft_stays_unpublished(): void
    {
        Storage::fake('public');
        $tenant = $this->tenant();
        $user = $this->owner($tenant);

        $this->actingAs($user)->post(route('admin.promos.store', $tenant), [
            'wizard' => 1, 'visual_tier' => 'base', 'promo_source' => 'upload', 'skip_ai' => 1,
            'image' => UploadedFile::fake()->image('promo.jpg', 800, 600),
            'manual_title' => 'Sempre qui', 'manual_description' => 'Senza scadenza.',
            'always_active' => 1, 'price' => '', 'publish_now' => 0,
            'ai_payload' => json_encode(['title' => 'x', 'offers' => [['name' => 'Piega', 'price' => '10€']]]),
        ]);

        $promo = Promo::sole();
        $this->assertSame('draft', $promo->status);
        $this->assertTrue($promo->always_active);
        $this->assertNull($promo->ends_at);
        $this->assertSame([], $promo->offers);
    }

    public function test_tampered_ai_payload_is_ignored_safely(): void
    {
        Storage::fake('public');
        $tenant = $this->tenant();
        $user = $this->owner($tenant);

        $this->actingAs($user)->post(route('admin.promos.store', $tenant), [
            'wizard' => 1, 'visual_tier' => 'base', 'promo_source' => 'upload', 'skip_ai' => 1,
            'image' => UploadedFile::fake()->image('promo.jpg', 800, 600),
            'manual_title' => 'Titolo mio', 'always_active' => 1, 'ai_payload' => '{"non json',
        ]);

        $this->assertSame('Titolo mio', Promo::sole()->title);

        $payloadWithHtml = json_encode(['title' => 'x', 'description' => '<script>alert(1)</script>ciao']);
        $this->actingAs($user)->post(route('admin.promos.store', $tenant), [
            'wizard' => 1, 'visual_tier' => 'base', 'promo_source' => 'upload', 'skip_ai' => 1,
            'image' => UploadedFile::fake()->image('promo2.jpg', 800, 600),
            'manual_title' => 'Secondo', 'always_active' => 1, 'ai_payload' => $payloadWithHtml,
        ]);

        $this->assertStringNotContainsString('<script>', Promo::where('title', 'Secondo')->sole()->ai_metadata['description']);
    }

    // ---- salvataggio: prodotti e servizi ----

    public function test_wizard_service_saves_duration_and_promo_label_and_exposes_them(): void
    {
        Storage::fake('public');
        $this->fakeStripe();
        $tenant = $this->tenant();
        $user = $this->owner($tenant);
        $until = now()->addMonth()->toDateString();

        $this->actingAs($user)->post(route('admin.services.store', $tenant), [
            'wizard' => 1, 'title' => 'Massaggio rilassante', 'description' => 'Un’ora di relax.', 'amount' => '60.00',
            'duration_minutes' => 60, 'promo_label' => 1, 'promo_until' => $until, 'published_to_site' => 1, 'auto_cover' => 1,
        ])->assertSessionHasNoErrors();

        $service = PayableService::sole();
        $this->assertSame('service', $service->type);
        $this->assertSame(6000, $service->amount_cents);
        $this->assertSame(60, $service->durationMinutes());
        $this->assertSame('1 h', $service->durationLabel());
        $this->assertTrue($service->onPromo());
        $this->assertSame($until, $service->promoUntil()->toDateString());
        $this->assertTrue($service->published_to_site);

        $this->getJson('/api/v1/beauty/services')->assertOk()
            ->assertJsonPath('services.0.on_promo', true)
            ->assertJsonPath('services.0.promo_until', $until)
            ->assertJsonPath('services.0.duration_minutes', 60);

        $this->get("/s/beauty/{$service->slug}")->assertOk()->assertSee('In promo fino al')->assertSee('1 h');
        $this->get('/s/beauty')->assertOk()->assertSee('In promo fino al');
    }

    public function test_ai_graphic_cover_is_kept_on_the_site_but_never_sent_to_stripe_when_it_is_an_svg(): void
    {
        Storage::fake('public');
        config(['services.gemini.api_key' => 'test-key']);
        Cache::put('gemini.model_catalog', ['text_models' => ['gemini-test'], 'text_best' => 'gemini-test']);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600"><rect width="800" height="600" fill="#0f766e"/><text x="400" y="300">Crema</text></svg>';
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => $svg]]]]]]),
            'api.stripe.com/v1/products' => Http::response(['id' => 'prod_g1']),
            'api.stripe.com/v1/prices' => Http::response(['id' => 'price_g1']),
            'api.stripe.com/v1/payment_links' => Http::response(['id' => 'plink_g1', 'url' => 'https://buy.stripe.com/test_g1']),
        ]);
        $tenant = $this->tenant();
        $user = $this->owner($tenant);

        $this->actingAs($user)->post(route('admin.products.store', $tenant), [
            'wizard' => 1, 'title' => 'Crema mani', 'description' => 'Nutre.', 'amount' => '12.90', 'auto_cover' => 1, 'published_to_site' => 1,
        ])->assertSessionHasNoErrors();

        $product = PayableService::sole();
        $this->assertNotNull($product->cover_image_path);
        $this->assertTrue(Storage::disk('public')->exists($product->cover_image_path));
        $this->assertStringContainsString('crema', strtolower(Storage::disk('public')->get($product->cover_image_path)));

        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/products') && ! str_contains($r->body(), 'images'));
    }

    public function test_wizard_product_without_label_or_duration_and_expired_label_disappears(): void
    {
        Storage::fake('public');
        $this->fakeStripe();
        $tenant = $this->tenant();
        $user = $this->owner($tenant);

        $this->actingAs($user)->post(route('admin.products.store', $tenant), [
            'wizard' => 1, 'title' => 'Shampoo', 'description' => 'Delicato.', 'amount' => '12.90', 'published_to_site' => 1,
        ])->assertSessionHasNoErrors();

        $product = PayableService::sole();
        $this->assertSame('product', $product->type);
        $this->assertFalse($product->onPromo());
        $this->assertNull($product->durationMinutes());
        $this->getJson('/api/v1/beauty/products')->assertJsonPath('products.0.on_promo', false)->assertJsonPath('products.0.promo_until', null);

        // etichetta con data già passata: non risulta più in promo
        $product->update(['metadata' => ['promo_until' => now()->subDay()->toDateString()]]);
        $this->assertFalse($product->fresh()->onPromo());
        $this->get("/s/beauty/{$product->slug}")->assertDontSee('In promo fino al');
    }

    public function test_promo_label_needs_a_future_date_and_invalid_duration_is_rejected(): void
    {
        $this->fakeStripe();
        $tenant = $this->tenant();
        $user = $this->owner($tenant);
        $base = ['title' => 'Siero', 'amount' => '20.00'];

        $this->actingAs($user)->post(route('admin.products.store', $tenant), $base + ['promo_label' => 1])->assertSessionHasErrors('promo_until');
        $this->actingAs($user)->post(route('admin.products.store', $tenant), $base + ['promo_label' => 1, 'promo_until' => now()->subDay()->toDateString()])->assertSessionHasErrors('promo_until');
        $this->actingAs($user)->post(route('admin.services.store', $tenant), $base + ['duration_minutes' => 2])->assertSessionHasErrors('duration_minutes');
        $this->assertSame(0, PayableService::count());
    }

    public function test_public_catalog_page_shows_services_and_products_in_separate_sections(): void
    {
        $tenant = $this->tenant();
        foreach ([['service', 'Piega'], ['product', 'Shampoo']] as [$type, $title]) {
            PayableService::create([
                'tenant_id' => $tenant->id, 'type' => $type, 'title' => $title, 'slug' => strtolower($title),
                'amount_cents' => 1000, 'status' => 'active', 'published_to_site' => true, 'payment_url' => 'https://buy.stripe.com/x',
            ]);
        }

        $this->get('/s/beauty')->assertOk()->assertSee('id="servizi"', false)->assertSee('id="prodotti"', false)
            ->assertSee('Piega')->assertSee('Shampoo');
    }
}
