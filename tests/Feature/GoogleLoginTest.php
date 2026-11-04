<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google_login.client_id' => 'client-id', 'services.google_login.client_secret' => 'client-secret']);
    }

    private function fakeGoogle(string $email, bool $verified = true): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-google']),
            'openidconnect.googleapis.com/*' => Http::response(['email' => $email, 'email_verified' => $verified]),
        ]);
    }

    /** Avvia il flusso e restituisce lo "state" che Google rimanderebbe indietro. */
    private function start(string $uri = '/accedi/google'): string
    {
        $location = $this->get($uri)->assertRedirect()->headers->get('Location');
        parse_str(parse_url($location, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $location);
        $this->assertSame('client-id', $query['client_id']);
        $this->assertSame(route('google.callback'), $query['redirect_uri']);
        $this->assertStringNotContainsString('profile', $query['scope']);

        return $query['state'];
    }

    public function test_buttons_and_routes_exist_only_when_configured(): void
    {
        $this->get(route('admin.login'))->assertOk()->assertSee('Continua con Google');
        $this->get(route('registration.create', ['per' => 'sito', 'ref' => 'piramide35']))
            ->assertOk()->assertSee('Registrati con Google')->assertSee('/accedi/google?per=sito&amp;ref=piramide35', false);

        config(['services.google_login.client_secret' => null]);
        $this->get(route('admin.login'))->assertOk()->assertDontSee('Continua con Google');
        $this->get('/accedi/google')->assertNotFound();
        $this->get('/accedi/google/ritorno?code=x&state=y')->assertNotFound();
    }

    public function test_an_existing_user_enters_with_google(): void
    {
        $tenant = Tenant::create(['name' => 'Salone Anna', 'slug' => 'salone-anna', 'type' => 'azienda', 'plan' => 'demo', 'guest_verified_at' => now(), 'subscription_status' => 'active']);
        $user = User::factory()->create(['email' => 'Anna@Example.com']);
        $user->tenants()->attach($tenant, ['role' => 'admin']);
        $this->fakeGoogle('anna@example.com');

        $this->get('/accedi/google/ritorno?code=abc&state='.$this->start())->assertRedirect(route('app.home', $tenant));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_new_person_from_piramide35_answers_two_questions_and_lands_on_the_site_wizard(): void
    {
        $this->fakeGoogle('rocco@gmail.com');

        $state = $this->start('/accedi/google?per=sito&ref=piramide35');
        $this->get('/accedi/google/ritorno?code=abc&state='.$state)->assertRedirect(route('google.signup'));
        $this->assertGuest();

        $this->get(route('google.signup'))->assertOk()->assertSee('rocco@gmail.com')->assertDontSee('value="privato"', false);

        $this->post(route('google.signup.store'), ['type' => 'azienda', 'company_name' => 'Pizzeria Da Rocco'])->assertRedirect();

        $tenant = Tenant::where('name', 'Pizzeria Da Rocco')->sole();
        $user = User::where('email', 'rocco@gmail.com')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse($tenant->isGuestPending());
        $this->assertSame('website', $tenant->settings['first_module']);
        $this->assertSame('piramide35', $tenant->settings['source']);
        $this->assertSame('trialing', $tenant->subscription_status);
        $this->assertSame('Pizzeria Da Rocco', $user->name);

        // Dopo la registrazione si entra direttamente nella creazione del sito.
        $this->get(route('admin.login'))->assertRedirect(route('admin.sitebuilder.show', $tenant));
    }

    public function test_bad_state_or_unverified_email_is_refused(): void
    {
        $this->fakeGoogle('x@gmail.com');
        $this->start();
        $this->get('/accedi/google/ritorno?code=abc&state=sbagliato')->assertRedirect(route('admin.login'));
        $this->assertGuest();

        $this->fakeGoogle('y@gmail.com', verified: false);
        $this->get('/accedi/google/ritorno?code=abc&state='.$this->start())->assertRedirect(route('admin.login'));
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_the_privacy_page_explains_google_and_ai_and_is_linked_in_the_footer(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('Continua con Google')->assertSee('Gemini')->assertSee('M 3.5 S.R.L.');
        $this->get(route('site.start'))->assertOk()->assertSee(route('privacy'));
    }

    public function test_the_signup_page_needs_a_google_session(): void
    {
        $this->get(route('google.signup'))->assertRedirect(route('registration.create'));
        $this->post(route('google.signup.store'), ['type' => 'azienda', 'company_name' => 'X'])->assertRedirect(route('registration.create'));
        $this->assertSame(0, Tenant::count());
    }
}
