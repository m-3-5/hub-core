<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MonthlyTipsNotification;
use App\Support\MarketingEmails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use M35\HubSitebuilder\Models\GeneratedSite;
use Tests\TestCase;

/** Un'idea a settimana: a ciclo su tutte le funzioni, testi che cambiano, mai allo staff né a chi si è disiscritto. */
class MonthlyTipsTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $slug = 'bar-sport'): array
    {
        $tenant = Tenant::create(['name' => 'Bar Sport', 'slug' => $slug, 'type' => 'azienda', 'plan' => 'demo', 'guest_verified_at' => now()]);
        $user = User::factory()->create(['email' => $slug.'@example.com']);
        $user->tenants()->attach($tenant, ['role' => 'admin']);

        return [$tenant, $user];
    }

    public function test_one_email_a_week_cycling_through_topics_with_changing_texts(): void
    {
        Notification::fake();
        [$tenant, $user] = $this->client();
        $staff = User::factory()->create(['is_super_admin' => true]);
        $staff->tenants()->attach($tenant, ['role' => 'admin']);

        $first = MarketingEmails::tips($tenant);
        $this->assertCount(2, $first);
        $this->assertSame('site', $first[0][3]);

        $this->artisan('hub:weekly-tips')->assertSuccessful();
        Notification::assertSentTo($user, MonthlyTipsNotification::class);
        Notification::assertNotSentTo($staff, MonthlyTipsNotification::class);

        // Non due volte nella stessa settimana.
        $this->artisan('hub:weekly-tips')->assertSuccessful();
        Notification::assertSentToTimes($user, MonthlyTipsNotification::class, 1);

        // La settimana dopo: argomento diverso.
        $this->travel(7)->days();
        $second = MarketingEmails::tips($tenant->fresh());
        $this->assertNotSame($first[0][3], $second[0][3]);

        // Dopo un giro intero l'argomento torna, con un testo diverso.
        $seen = [];
        $tenant = $tenant->fresh();
        for ($i = 0; $i < 30; $i++) {
            $tips = MarketingEmails::tips($tenant);
            $seen[$tips[0][3]][] = $tips[0][0];
            MarketingEmails::remember($tenant, [$tips[0]]);
            $tenant = $tenant->fresh();
        }
        $this->assertGreaterThan(1, count(array_unique($seen['site'])));

        // Con il sito online non si propone più il sito, ma le lingue sì.
        GeneratedSite::create(['tenant_id' => $tenant->id, 'status' => 'published', 'published_at' => now(), 'answers' => ['tagline' => 'x', 'services' => ['Caffè']]]);
        $keys = array_keys(array_filter(MarketingEmails::catalog($tenant->fresh()), fn ($t) => $t[2]));
        $this->assertNotContains('site', $keys);
        $this->assertContains('langs', $keys);
    }

    public function test_the_email_has_an_unsubscribe_link_that_works_without_login(): void
    {
        [$tenant, $user] = $this->client();
        config(['hub.marketing.offer' => 'Primo mese di promo a metà prezzo']);

        $mail = (new MonthlyTipsNotification($tenant, MarketingEmails::tips($tenant)))->toMail($user);
        $this->assertStringEndsWith('— Bar Sport', $mail->subject);
        $html = (string) $mail->render();
        $this->assertStringContainsString('Non mandatemele più', $html);
        $this->assertStringContainsString('Lo sapevi?', $html);
        $this->assertStringContainsString('Primo mese di promo a metà prezzo', $html);

        $url = MarketingEmails::unsubscribeUrl($tenant);
        $this->get($url)->assertOk()->assertSee('Non mandatemele più');
        $this->post($url)->assertOk()->assertSee('non ti mandiamo più');
        $this->assertTrue(MarketingEmails::optedOut($tenant->fresh()));

        Notification::fake();
        $this->artisan('hub:weekly-tips')->assertSuccessful();
        Notification::assertNothingSent();

        // Si può tornare indietro; un link senza firma non funziona.
        $this->post($url, ['resubscribe' => 1])->assertOk();
        $this->assertFalse(MarketingEmails::optedOut($tenant->fresh()));
        $this->get(route('email.unsubscribe', $tenant))->assertForbidden();
    }

    public function test_a_single_tenant_test_send_does_not_count_and_the_old_name_still_works(): void
    {
        Notification::fake();
        [$tenant, $user] = $this->client();
        [, $other] = $this->client('pizzeria');

        $this->artisan('hub:monthly-tips', ['--tenant' => 'bar-sport'])->assertSuccessful();
        Notification::assertSentTo($user, MonthlyTipsNotification::class);
        Notification::assertNotSentTo($other, MonthlyTipsNotification::class);
        $this->assertArrayNotHasKey('last_tips_at', $tenant->fresh()->settings['marketing'] ?? []);
    }

    public function test_who_already_has_a_website_does_not_get_the_one_euro_site_idea(): void
    {
        [$tenant] = $this->client();
        $tenant->update(['website' => 'https://www.barsport.it']);

        $this->assertNotContains('site', array_column(MarketingEmails::tips($tenant->fresh()), 3));
    }
}
