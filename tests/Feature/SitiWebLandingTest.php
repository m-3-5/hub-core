<?php

namespace Tests\Feature;

use App\Models\ClassifiedAd;
use App\Models\SiteLead;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SiteLeadNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use M35\HubPayments\Models\PayableService;
use Tests\TestCase;

class SitiWebLandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'landing.offer_ends' => now()->addDays(20)->toDateString(),
            'landing.phone' => null,
            'landing.whatsapp' => null,
            'landing.leads_email' => 'leads@example.test',
        ]);
    }

    private function tenant(string $slug = 'beauty'): Tenant
    {
        return Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'type' => 'azienda', 'plan' => 'demo']);
    }

    /** Dati validi: il modulo si invia dopo almeno 3 secondi dal caricamento. */
    private function payload(array $extra = []): array
    {
        return array_merge([
            'name' => 'Maria Rossi', 'phone' => '333 1234567', 'email' => 'maria@example.com',
            'package' => 'aziendale', 'message' => 'Ho un negozio a Schiavonea.', 'consent' => '1',
            'rendered_at' => time() - 30, 'company' => '',
        ], $extra);
    }

    // ---- pagina ----

    public function test_landing_shows_local_seo_three_plans_and_the_limited_offer(): void
    {
        $response = $this->get('/siti-web-corigliano-rossano')->assertOk();

        $response->assertSee('Siti web e app a Corigliano-Rossano', false)
            ->assertSee('Ora siamo a Fabrizio')
            ->assertSee('Offerta di lancio fino al')
            ->assertSee('Vetrina')->assertSee('Aziendale')->assertSee('Professionale su misura')
            ->assertSee('290')->assertSee('590')->assertSee('990')       // prezzi in offerta
            ->assertSee('490')->assertSee('890')->assertSee('1.490')      // prezzi di listino barrati
            ->assertSee('id="contatti"', false)
            ->assertSee('<link rel="canonical" href="'.route('landing.web').'">', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"priceValidUntil"', false);
    }

    public function test_structured_data_for_google_is_valid_json_with_offers_and_faq(): void
    {
        $html = $this->get('/siti-web-corigliano-rossano')->assertOk()->getContent();

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $data = json_decode($m[1] ?? '', true);

        $this->assertIsArray($data, 'Il JSON-LD deve essere JSON valido');
        $this->assertSame('https://schema.org', $data['@context']);
        $types = collect($data['@graph'])->pluck('@type')->all();
        $this->assertSame(['ProfessionalService', 'FAQPage'], $types);

        $offers = $data['@graph'][0]['hasOfferCatalog']['itemListElement'];
        $this->assertSame(['290', '590', '990'], array_column($offers, 'price'));
        $this->assertSame('EUR', $offers[0]['priceCurrency']);
        $this->assertSame('Corigliano-Rossano', $data['@graph'][0]['address']['addressLocality']);
        $this->assertStringNotContainsString('<?php', $html);
    }

    public function test_after_the_offer_ends_the_page_shows_regular_prices_without_fake_urgency(): void
    {
        config(['landing.offer_ends' => now()->subDay()->toDateString()]);

        $response = $this->get('/siti-web-corigliano-rossano')->assertOk();

        $response->assertDontSee('Offerta di lancio fino al')->assertDontSee('id="offerBox"', false)->assertDontSee('priceValidUntil', false)
            ->assertSee('490')->assertSee('890');
        $this->assertStringNotContainsString('class="off"', $response->getContent());
    }

    public function test_direct_contact_buttons_only_appear_when_a_number_is_configured(): void
    {
        $this->get('/siti-web-corigliano-rossano')->assertDontSee('wa.me', false)->assertDontSee('tel:', false);

        config(['landing.whatsapp' => '393401234567', 'landing.phone' => '+39 0983 123456']);
        $this->get('/siti-web-corigliano-rossano')->assertSee('https://wa.me/393401234567', false)->assertSee('tel:+390983123456', false);
    }

    // ---- modulo ----

    public function test_a_valid_request_is_saved_and_emailed(): void
    {
        Notification::fake();

        $this->post(route('landing.web.lead'), $this->payload())
            ->assertRedirect(route('landing.web').'#contatti')->assertSessionHas('lead_sent');

        $lead = SiteLead::sole();
        $this->assertSame('Maria Rossi', $lead->name);
        $this->assertSame('333 1234567', $lead->phone);
        $this->assertSame('aziendale', $lead->package);
        $this->assertSame('Aziendale', $lead->packageLabel());
        $this->assertSame('new', $lead->status);
        $this->assertSame('393331234567', $lead->dialNumber());
        $this->assertNotSame('127.0.0.1', $lead->ip_hash);

        Notification::assertSentOnDemand(SiteLeadNotification::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'leads@example.test');
    }

    public function test_the_notification_email_contains_the_contact_details(): void
    {
        $lead = SiteLead::create(['name' => 'Luca', 'phone' => '+39 340 5551234', 'email' => 'luca@example.com', 'package' => 'app', 'message' => 'Mi serve un\'app per le prenotazioni']);
        $mail = (new SiteLeadNotification($lead))->toMail(new \stdClass);
        $text = collect([$mail->subject, ...$mail->introLines])->implode("\n");

        $this->assertStringContainsString('Luca', $text);
        $this->assertStringContainsString('+39 340 5551234', $text);
        $this->assertStringContainsString('App su misura', $text);
        $this->assertStringContainsString('prenotazioni', $text);
        $this->assertSame('https://wa.me/393405551234', $mail->actionUrl);
    }

    public function test_missing_or_wrong_fields_send_the_visitor_back_to_the_form(): void
    {
        Notification::fake();

        $this->from(route('landing.web'))->post(route('landing.web.lead'), $this->payload(['name' => '', 'phone' => 'abc', 'consent' => null]))
            ->assertRedirect(route('landing.web').'#contatti')->assertSessionHasErrors(['name', 'phone', 'consent']);
        $this->post(route('landing.web.lead'), $this->payload(['package' => 'inventato']))->assertSessionHasErrors('package');
        $this->post(route('landing.web.lead'), $this->payload(['email' => 'non-una-mail']))->assertSessionHasErrors('email');

        $this->assertSame(0, SiteLead::count());
        Notification::assertNothingSent();
    }

    public function test_spam_bots_get_a_fake_success_and_nothing_is_stored(): void
    {
        Notification::fake();

        // campo trappola compilato
        $this->post(route('landing.web.lead'), $this->payload(['company' => 'Spam SRL']))->assertSessionHas('lead_sent');
        // invio istantaneo (< 3 secondi)
        $this->post(route('landing.web.lead'), $this->payload(['rendered_at' => time()]))->assertSessionHas('lead_sent');

        $this->assertSame(0, SiteLead::count());
        Notification::assertNothingSent();
    }

    public function test_requests_are_rate_limited_per_visitor(): void
    {
        Notification::fake();

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('landing.web.lead'), $this->payload())->assertRedirect();
        }

        $this->post(route('landing.web.lead'), $this->payload())->assertStatus(429);
    }

    public function test_a_mail_failure_does_not_lose_the_request(): void
    {
        config(['mail.default' => 'array']);
        Notification::shouldReceive('route')->andThrow(new \RuntimeException('smtp giù'));

        $this->post(route('landing.web.lead'), $this->payload())->assertSessionHas('lead_sent');

        $this->assertSame(1, SiteLead::count());
    }

    // ---- Google: sitemap, robots, noindex ----

    public function test_sitemap_lists_public_pages_and_content_but_not_drafts_hidden_items_or_quotes(): void
    {
        $tenant = $this->tenant();

        $tenant->promos()->create(['title' => 'Saldi', 'slug' => 'saldi', 'status' => 'published', 'always_active' => true, 'published_at' => now()]);
        $tenant->promos()->create(['title' => 'Bozza', 'slug' => 'bozza', 'status' => 'draft']);
        foreach ([['service', 'piega', true, 'active'], ['product', 'shampoo', true, 'active'], ['service', 'nascosto', false, 'active'],
            ['quote', 'preventivo', true, 'active'], ['product', 'vecchio', true, 'archived']] as [$type, $slug, $published, $status]) {
            PayableService::create(['tenant_id' => $tenant->id, 'type' => $type, 'title' => $slug, 'slug' => $slug, 'amount_cents' => 1000, 'status' => $status, 'published_to_site' => $published]);
        }
        ClassifiedAd::create(['tenant_id' => $tenant->id, 'slug' => 'casa-mare', 'title' => 'Casa', 'zone' => 'Sibari', 'description' => 'x', 'status' => 'published', 'published_at' => now()]);
        ClassifiedAd::create(['tenant_id' => $tenant->id, 'slug' => 'casa-bozza', 'title' => 'Bozza', 'zone' => 'Sibari', 'description' => 'x', 'status' => 'draft']);

        $response = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        $xml = $response->getContent();

        foreach ([route('welcome'), route('landing.web'), route('pricing.show'), route('promo.hub-archive'), route('classifieds.board'),
            url('/p/beauty/saldi'), url('/s/beauty'), url('/s/beauty/piega'), url('/s/beauty/shampoo'), url('/a/beauty/casa-mare')] as $loc) {
            $this->assertStringContainsString('<loc>'.$loc.'</loc>', $xml, $loc);
        }

        foreach (['/p/beauty/bozza', '/s/beauty/nascosto', '/s/beauty/preventivo', '/s/beauty/vecchio', '/a/beauty/casa-bozza', '/admin', '/api'] as $path) {
            $this->assertStringNotContainsString($path.'<', $xml, $path);
        }

        $this->assertNotFalse(simplexml_load_string($xml), 'La sitemap deve essere XML valido');
    }

    public function test_robots_points_to_the_sitemap_and_blocks_private_areas(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Sitemap: https://inm35.it/sitemap.xml', $robots);

        foreach (['/admin', '/app', '/auth', '/api'] as $path) {
            $this->assertStringContainsString('Disallow: '.$path, $robots);
        }

        $this->assertStringNotContainsString('Disallow: /s/', $robots);
        $this->assertStringNotContainsString('Disallow: /p/', $robots);
        $this->assertStringNotContainsString('Disallow: /siti-web', $robots);
    }

    public function test_private_pages_are_noindex_and_public_ones_have_canonical(): void
    {
        $this->get('/admin/login')->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/siti-web-corigliano-rossano')->assertDontSee('noindex', false);
        $this->get('/prezzi')->assertSee('<link rel="canonical"', false);
        $this->get('/annunci')->assertSee('<link rel="canonical"', false)->assertSee('og:title', false);
    }

    public function test_home_links_to_the_landing_so_google_can_reach_it(): void
    {
        $this->get('/')->assertOk()->assertSee(route('landing.web'), false);
    }

    // ---- pannello ----

    public function test_only_super_admins_see_and_manage_the_requests(): void
    {
        $lead = SiteLead::create(['name' => 'Anna', 'phone' => '3331112222', 'package' => 'vetrina']);
        $tenant = $this->tenant();
        $member = User::factory()->create();
        $tenant->users()->attach($member->id, ['role' => 'admin']);
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->get(route('admin.leads.index'))->assertRedirect();
        $this->actingAs($member)->get(route('admin.leads.index'))->assertForbidden();
        $this->actingAs($member)->post(route('admin.leads.toggle', $lead))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.leads.index'))->assertOk()->assertSee('Anna')->assertSee('Vetrina')->assertSee('wa.me/393331112222', false);
        $this->actingAs($admin)->post(route('admin.leads.toggle', $lead))->assertRedirect();
        $this->assertSame('contacted', $lead->fresh()->status);
        $this->actingAs($admin)->post(route('admin.leads.toggle', $lead))->assertRedirect();
        $this->assertSame('new', $lead->fresh()->status);
    }
}
