<?php

namespace Tests\Feature;

use App\Models\PendingRegistration;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ContentModerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use M35\HubSitebuilder\Models\GeneratedSite;
use M35\HubSitebuilder\Services\SiteBuilderHeroGenerator;
use Tests\TestCase;

/** Percorso «Crea il tuo sito con l'IA»: da /crea-sito (anche da piramide35.com) alla pagina pubblica dell'attività. */
class SiteWithAiJourneyTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGemini(array|string $reply, int $status = 200): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        Cache::put('gemini.model_catalog', ['text_models' => ['gemini-test'], 'text_best' => 'gemini-test']);
        $text = is_array($reply) ? json_encode($reply) : $reply;
        Http::fake(['generativelanguage.googleapis.com/*' => $status === 200
            ? Http::response(['candidates' => [['content' => ['parts' => [['text' => $text]]]]]])
            : Http::response(['error' => ['message' => 'overloaded']], $status)]);
    }

    private function tenantWithOwner(): array
    {
        $tenant = Tenant::create(['name' => 'Salone Anna', 'slug' => 'salone-anna', 'type' => 'azienda', 'plan' => 'demo',
            'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDays(30), 'guest_verified_at' => now(),
            'settings' => ['first_module' => 'website']]);
        $user = User::factory()->create(['email' => 'anna@example.com']);
        $user->tenants()->attach($tenant, ['role' => 'admin']);

        return [$tenant, $user];
    }

    public function test_the_public_page_leads_to_the_site_registration_keeping_the_source(): void
    {
        $this->get('/crea-sito?ref=piramide35')
            ->assertOk()
            ->assertSee('Il tuo sito, scritto dall', false)
            ->assertSee(route('registration.create', ['per' => 'sito', 'ref' => 'piramide35']));

        // Un ref strano non finisce nei link.
        $this->get('/crea-sito?ref=%3Cscript%3E')->assertOk()->assertDontSee('%3Cscript', false);

        $this->get(route('registration.create', ['per' => 'sito', 'ref' => 'piramide35']))
            ->assertOk()
            ->assertSee('Per chi è il sito?')
            ->assertSee('name="source" value="piramide35"', false)
            ->assertSee('value="website"', false)
            ->assertDontSee('data-type="privato"', false);
    }

    public function test_an_email_already_registered_gets_a_clear_italian_message(): void
    {
        app()->setLocale('it');
        User::factory()->create(['email' => 'max@example.com']);

        $this->from(route('registration.create', ['per' => 'sito']))->post(route('registration.store'), [
            'type' => 'azienda', 'first_module' => 'website', 'company_name' => 'M 3.5', 'contact_name' => 'Max', 'email' => 'max@example.com',
        ])->assertSessionHasErrors(['email' => 'Questa email ha già un account: accedi con «Accedi» (o «Password dimenticata?»).']);

        $this->assertSame('Compila il nome dell\'attività.', __('validation.required', ['attribute' => __('validation.attributes.company_name')]));
    }

    public function test_someone_already_inside_goes_straight_to_the_site_creation(): void
    {
        [$tenant, $user] = $this->tenantWithOwner();

        $this->actingAs($user)->get('/crea-sito')->assertOk()->assertSee(route('admin.sitebuilder.show', $tenant), false);
    }

    public function test_registration_for_a_site_confirms_the_email_and_lands_on_the_site_wizard(): void
    {
        Notification::fake();

        $this->post(route('registration.store'), [
            'type' => 'azienda', 'first_module' => 'website', 'source' => 'piramide35',
            'company_name' => 'Pizzeria Da Rocco', 'contact_name' => 'Rocco', 'email' => 'rocco@example.com',
        ])->assertRedirect(route('welcome'));

        $pending = PendingRegistration::sole();
        $this->assertSame('website', $pending->first_module);
        $this->assertSame('piramide35', $pending->source);

        $this->get(route('registration.confirm', $pending->token))->assertRedirect();

        $tenant = Tenant::where('name', 'Pizzeria Da Rocco')->sole();
        $this->assertFalse($tenant->isGuestPending(), 'chi conferma l\'email non è più un ospite');
        $this->assertSame('website', $tenant->settings['first_module']);
        $this->assertSame('piramide35', $tenant->settings['source']);
        $this->assertTrue($tenant->hasPremiumFeatures());

        // Imposta la password: entra subito e va alla creazione del sito.
        $user = User::where('email', 'rocco@example.com')->sole();
        $token = Password::broker()->createToken($user);

        $this->post(route('admin.password.update'), [
            'token' => $token, 'email' => 'rocco@example.com', 'password' => 'una-password-lunga', 'password_confirmation' => 'una-password-lunga',
        ])->assertRedirect(route('admin.sitebuilder.show', $tenant));

        $this->assertAuthenticatedAs($user);
        $this->get(route('admin.sitebuilder.show', $tenant))->assertOk()->assertSee('Scrivi il sito con l');
    }

    public function test_the_ai_writes_the_site_texts_from_a_short_description(): void
    {
        [$tenant, $user] = $this->tenantWithOwner();
        $this->fakeGemini([
            'tagline' => 'Il tuo taglio, come lo sogni',
            'services' => [
                ['name' => 'Taglio', 'line' => 'Su misura per te', 'icon' => '✂️'],
                ['name' => 'Colore, riflessi', 'line' => 'Toni naturali', 'icon' => 'abc'],
                'Sposa <b>vip</b>',
            ],
            'strengths' => [['title' => 'Su appuntamento', 'text' => 'Niente attese', 'icon' => '📅'], ['title' => '<script>x</script>', 'text' => 'y', 'icon' => '⭐']],
            'faq' => [['q' => 'Serve prenotare?', 'a' => 'Sì, scrivici su WhatsApp.']],
            'closing' => 'Prenota il tuo momento',
            'style' => 'elegante',
            'about' => 'Siamo un salone di Rossano.',
            'cta_label' => 'Prenota ora',
        ]);

        $this->actingAs($user)->postJson(route('admin.sitebuilder.suggest', $tenant), ['description' => 'Parrucchiera per donna a Rossano', 'city' => 'Rossano'])
            ->assertOk()
            ->assertJson(['ok' => true, 'tagline' => 'Il tuo taglio, come lo sogni', 'cta_label' => 'Prenota ora', 'about' => 'Siamo un salone di Rossano.'])
            ->assertJsonPath('services', ['Taglio', 'Colore riflessi', 'Sposa vip'])
            ->assertJsonPath('extras.service_details.0', ['name' => 'Taglio', 'line' => 'Su misura per te', 'icon' => '✂️'])
            ->assertJsonPath('extras.service_details.1.icon', '✓')
            ->assertJsonPath('extras.strengths.0.title', 'Su appuntamento')
            ->assertJsonPath('extras.strengths.1.title', 'x')
            ->assertJsonPath('extras.faq.0.q', 'Serve prenotare?')
            ->assertJsonPath('extras.closing', 'Prenota il tuo momento')
            ->assertJsonPath('extras.style', 'elegante');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'gemini-test:generateContent') && str_contains(json_encode($r->data()), 'Salone Anna'));
    }

    public function test_when_the_ai_is_down_the_wizard_says_so_in_json(): void
    {
        [$tenant, $user] = $this->tenantWithOwner();
        $this->fakeGemini('', 503);

        $this->actingAs($user)->postJson(route('admin.sitebuilder.suggest', $tenant), ['description' => 'Parrucchiera per donna a Rossano'])
            ->assertOk()->assertJson(['ok' => false])->assertJsonPath('message', fn ($m) => str_contains($m, 'scrivi tu'));

        $this->actingAs($user)->postJson(route('admin.sitebuilder.suggest', $tenant), ['description' => 'corto'])
            ->assertStatus(422)->assertJson(['ok' => false]);
    }

    public function test_the_created_site_is_public_with_about_and_whatsapp(): void
    {
        Notification::fake();
        [$tenant, $user] = $this->tenantWithOwner();
        $this->mock(ContentModerator::class, fn ($m) => $m->shouldReceive('check')->andReturn(['allowed' => true, 'unavailable' => false, 'category' => 'ok', 'reason' => '']));
        $this->mock(SiteBuilderHeroGenerator::class, fn ($m) => $m->shouldReceive('generate')->andReturn(null));

        $extras = json_encode([
            'service_details' => [['name' => 'Taglio', 'line' => 'Su misura per te', 'icon' => '✂️']],
            'strengths' => [['title' => 'Su appuntamento', 'text' => 'Niente attese', 'icon' => '📅']],
            'faq' => [['q' => 'Serve prenotare?', 'a' => 'Sì, scrivici su WhatsApp.']],
            'closing' => 'Prenota il tuo momento',
            'style' => 'amichevole',
        ]);

        $this->actingAs($user)->post(route('admin.sitebuilder.generate', $tenant), [
            'tagline' => 'Il tuo taglio, come lo sogni', 'services' => 'Taglio, Colore', 'cta_label' => 'Scrivici su WhatsApp',
            'extra' => 'Siamo un salone di Rossano.', 'phone' => '333 123 4567', 'extras' => $extras,
        ])->assertRedirect(route('admin.sitebuilder.show', $tenant));

        $this->assertSame('333 123 4567', $tenant->fresh()->phone);
        $site = GeneratedSite::sole();
        $this->assertTrue($site->isLive());

        auth()->logout();
        $this->get(route('site.public.show', $tenant))
            ->assertOk()
            ->assertSee('Il tuo taglio, come lo sogni')
            ->assertSee('Chi siamo')
            ->assertSee('Perché sceglierci')
            ->assertSee('Su appuntamento')
            ->assertSee('Su misura per te')
            ->assertSee('Serve prenotare?')
            ->assertSee('Prenota il tuo momento')
            ->assertSee('Quicksand') // stile «amichevole» proposto dall'IA
            ->assertSee(route('privacy'))
            ->assertSee(route('site.start', ['ref' => 'sito-salone-anna']), false)
            ->assertSee('https://wa.me/393331234567', false);

        // Il link segreto modifica i testi principali ma non cancella quelli in più.
        $token = $site->fresh()->issueEditToken();
        $this->post(route('site.edit.update', $token), ['tagline' => 'Nuova frase', 'services' => 'Taglio', 'cta_label' => 'Chiamaci'])->assertRedirect();
        $this->assertSame('Prenota il tuo momento', $site->fresh()->answers['extras']['closing']);

        $this->actingAs($user)->get(route('admin.sitebuilder.show', $tenant))->assertOk()->assertSee('Il tuo sito è online');
    }

    public function test_photos_logo_hours_socials_vat_and_reviews_make_the_page_richer(): void
    {
        Notification::fake();
        Storage::fake('public');
        [$tenant, $user] = $this->tenantWithOwner();
        $seen = [];
        $this->mock(ContentModerator::class, function ($m) use (&$seen) {
            $m->shouldReceive('check')->andReturnUsing(function ($text, $images = null) use (&$seen) {
                $seen[] = [$text, $images];

                return ['allowed' => true, 'unavailable' => false, 'category' => 'ok', 'reason' => ''];
            });
        });
        $this->mock(SiteBuilderHeroGenerator::class, fn ($m) => $m->shouldReceive('generate')->andReturn(null));

        $payload = [
            'tagline' => 'Il tuo taglio', 'services' => 'Taglio', 'cta_label' => 'Chiamaci', 'phone' => '333 123 4567', 'more' => '1',
            'address' => 'Via Roma 1, Rossano', 'hours' => 'Lun-Sab 9-19',
            'facebook' => 'facebook.com/saloneanna', 'instagram' => '@saloneanna', 'vat' => 'IT 01234567890',
            'reviews' => [['text' => 'Bravissime!', 'name' => 'Maria R.'], ['text' => '', 'name' => '']],
            'logo' => \Illuminate\Http\UploadedFile::fake()->image('logo.png', 200, 200),
            'photos' => [\Illuminate\Http\UploadedFile::fake()->image('a.jpg', 1200, 900), \Illuminate\Http\UploadedFile::fake()->image('b.jpg', 1200, 900)],
        ];

        $this->actingAs($user)->post(route('admin.sitebuilder.generate', $tenant), $payload)->assertSessionHasNoErrors();

        $answers = GeneratedSite::sole()->answers;
        $this->assertCount(2, $answers['media']['photos']);
        $this->assertNotNull($answers['media']['logo']);
        $this->assertSame('https://www.instagram.com/saloneanna', $answers['info']['instagram']);
        $this->assertSame('https://facebook.com/saloneanna', $answers['info']['facebook']);
        $this->assertSame('01234567890', $answers['info']['vat']);
        $this->assertCount(1, $answers['info']['reviews']);
        $this->assertSame('Via Roma 1, Rossano', $tenant->fresh()->address);
        // Logo e foto controllati dall'IA insieme ai testi, già rimpiccioliti.
        $this->assertCount(3, $seen[0][1]);
        $this->assertStringContainsString('Bravissime!', $seen[0][0]);
        Storage::disk('public')->assertExists($answers['media']['photos']);

        auth()->logout();
        $this->get(route('site.public.show', $tenant))->assertOk()
            ->assertSee('Dai un\'occhiata', false)->assertSee('Parola ai clienti')->assertSee('Bravissime!')
            ->assertSee('Lun-Sab 9-19')->assertSee('Mostra la mappa')->assertSee('P.IVA 01234567890')
            ->assertSee('https://www.instagram.com/saloneanna', false)->assertSee($answers['media']['logo'], false);

        // Foto nuove sostituiscono le vecchie, che spariscono dal disco.
        $old = $answers['media']['photos'];
        $this->actingAs($user)->post(route('admin.sitebuilder.generate', $tenant), [...$payload, 'logo' => null, 'photos' => [\Illuminate\Http\UploadedFile::fake()->image('c.jpg')]])->assertSessionHasNoErrors();
        $this->assertCount(1, GeneratedSite::sole()->answers['media']['photos']);
        $this->assertNotNull(GeneratedSite::sole()->answers['media']['logo']);
        Storage::disk('public')->assertMissing($old);

        // Se il controllo rifiuta, le foto appena caricate non restano sul disco.
        $this->mock(ContentModerator::class, fn ($m) => $m->shouldReceive('check')->andReturn(['allowed' => false, 'unavailable' => false, 'category' => 'altro', 'reason' => 'Foto non adatta.']));
        $before = count(Storage::disk('public')->allFiles('sites/salone-anna/media'));
        $this->actingAs($user)->post(route('admin.sitebuilder.generate', $tenant), [...$payload, 'logo' => null, 'photos' => [\Illuminate\Http\UploadedFile::fake()->image('d.jpg')]])->assertSessionHasErrors('tagline');
        $this->assertCount($before, Storage::disk('public')->allFiles('sites/salone-anna/media'));

        // Valori sbagliati: messaggi chiari.
        $this->actingAs($user)->post(route('admin.sitebuilder.generate', $tenant), [...$payload, 'logo' => null, 'photos' => null, 'vat' => '123', 'facebook' => 'ciao'])
            ->assertSessionHasErrors(['vat' => 'La partita IVA ha 11 cifre.', 'facebook']);
    }

    public function test_weekly_hours_promos_shop_and_chosen_style_appear_in_the_site(): void
    {
        Notification::fake();
        [$tenant, $user] = $this->tenantWithOwner();
        $this->mock(ContentModerator::class, fn ($m) => $m->shouldReceive('check')->andReturn(['allowed' => true, 'unavailable' => false, 'category' => 'ok', 'reason' => '']));
        $this->mock(SiteBuilderHeroGenerator::class, fn ($m) => $m->shouldReceive('generate')->andReturn(null));

        \App\Models\Promo::create(['tenant_id' => $tenant->id, 'title' => 'Sconto autunno', 'slug' => 'sconto-autunno', 'status' => 'published', 'always_active' => true, 'published_at' => now()]);
        \App\Models\Promo::create(['tenant_id' => $tenant->id, 'title' => 'Bozza segreta', 'slug' => 'bozza', 'status' => 'draft', 'always_active' => true]);
        \M35\HubPayments\Models\PayableService::create(['tenant_id' => $tenant->id, 'type' => 'service', 'title' => 'Piega', 'slug' => 'piega', 'amount_cents' => 2500, 'currency' => 'eur', 'status' => 'active', 'published_to_site' => true]);
        \M35\HubPayments\Models\PayableService::create(['tenant_id' => $tenant->id, 'type' => 'product', 'title' => 'Nascosto', 'slug' => 'nascosto', 'amount_cents' => 900, 'currency' => 'eur', 'status' => 'active', 'published_to_site' => false]);

        $this->actingAs($user)->post(route('admin.sitebuilder.generate', $tenant), [
            'tagline' => 'Il tuo taglio', 'services' => 'Taglio', 'cta_label' => 'Chiamaci', 'phone' => '333 123 4567', 'more' => '1',
            'days' => [1, 2, 3, 4, 5, 6], 'open1' => '09:00', 'close1' => '13:00', 'open2' => '16:00', 'close2' => '20:00', 'hours' => 'Domenica su prenotazione',
            'extras' => json_encode(['style' => 'classico']),
        ])->assertSessionHasNoErrors();

        $info = GeneratedSite::sole()->answers['info'];
        $this->assertSame(['days' => [1, 2, 3, 4, 5, 6], 'ranges' => [['09:00', '13:00'], ['16:00', '20:00']]], $info['schedule']);
        $this->assertSame('Lun-Sab 9:00-13:00 e 16:00-20:00', \M35\HubSitebuilder\Services\SiteContent::scheduleText($info['schedule']));
        $this->assertSame('Lun, Mer 0:30-2:00', \M35\HubSitebuilder\Services\SiteContent::scheduleText(['days' => [1, 3], 'ranges' => [['00:30', '02:00']]]));

        auth()->logout();
        $this->get(route('site.public.show', $tenant))->assertOk()
            ->assertSee('Lun-Sab 9:00-13:00 e 16:00-20:00 · Domenica su prenotazione')
            ->assertSee('data-open-now', false)->assertSee('data-schedule', false)
            ->assertSee('Offerte del momento')->assertSee('Sconto autunno')->assertDontSee('Bozza segreta')
            ->assertSee(route('promo.show', [$tenant, 'sconto-autunno']), false)
            ->assertSee('Acquista online')->assertSee('Piega')->assertSee('25,00 €')->assertDontSee('Nascosto')
            ->assertSee('Playfair'); // stile «classico» scelto nel riepilogo

        // Giorni senza orari: messaggio chiaro solo lato pagina; lato server lo schedule resta vuoto.
        $this->assertNull(\M35\HubSitebuilder\Services\SiteContent::schedule(['days' => [1], 'open1' => null, 'close1' => null]));
    }

    public function test_model_discovery_inside_a_request_only_lists_models_without_probing(): void
    {
        config(['services.gemini.api_key' => 'test-key', 'services.gemini.model' => 'gemini-flash-lite-latest', 'services.gemini.fallback_models' => ['gemini-ritirato']]);
        Cache::forget('gemini.model_catalog');
        Http::fake(['generativelanguage.googleapis.com/v1beta/models*' => Http::response(['models' => [
            ['name' => 'models/gemini-2.5-flash'], ['name' => 'models/gemini-flash-lite-latest'], ['name' => 'models/gemini-2.5-flash-image'],
        ]])]);

        $resolver = app(\App\Services\GeminiModelResolver::class);
        $resolver->ensureDiscovered();

        $this->assertSame(['gemini-flash-lite-latest', 'gemini-2.5-flash'], $resolver->textModels());
        $this->assertSame(['gemini-2.5-flash-image'], $resolver->imageModels());
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'generateContent'));
    }

    public function test_the_hero_artwork_uses_the_shared_gemini_client(): void
    {
        Storage::fake('public');
        [$tenant] = $this->tenantWithOwner();
        $this->fakeGemini('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" fill="#f0f"/></svg>');

        $hero = app(SiteBuilderHeroGenerator::class)->generate($tenant, 'sites/test-hero');

        $this->assertNotNull($hero);
        $this->assertStringEndsWith('.svg', $hero['svg_path']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'gemini-test:generateContent'));
    }
}
