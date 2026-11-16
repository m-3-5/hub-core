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

/** Idee del mese: su misura, una al mese, mai allo staff né a chi si è disiscritto. */
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

    public function test_tips_are_tailored_and_sent_once_a_month(): void
    {
        Notification::fake();
        [$tenant, $user] = $this->client();
        $staff = User::factory()->create(['is_super_admin' => true]);
        $staff->tenants()->attach($tenant, ['role' => 'admin']);

        $tips = MarketingEmails::tips($tenant);
        $this->assertStringContainsString('sito con l', $tips[0][0]);

        $this->artisan('hub:monthly-tips')->assertSuccessful();
        Notification::assertSentTo($user, MonthlyTipsNotification::class);
        Notification::assertNotSentTo($staff, MonthlyTipsNotification::class);

        // Una sola volta al mese.
        $this->artisan('hub:monthly-tips')->assertSuccessful();
        Notification::assertSentToTimes($user, MonthlyTipsNotification::class, 1);

        // Con il sito online le idee cambiano: lingue e «fatti trovare».
        GeneratedSite::create(['tenant_id' => $tenant->id, 'status' => 'published', 'published_at' => now(), 'answers' => ['tagline' => 'x', 'services' => ['Caffè']]]);
        $titles = array_column(MarketingEmails::tips($tenant->fresh()), 0);
        $this->assertNotContains('🌐 Il tuo sito con l\'IA a 1 €', $titles);
        $this->assertContains('🌍 Il tuo sito in altre lingue', $titles);
    }

    public function test_the_email_has_an_unsubscribe_link_that_works_without_login(): void
    {
        [$tenant, $user] = $this->client();
        config(['hub.marketing.offer' => 'Primo mese di promo a metà prezzo']);

        $mail = (new MonthlyTipsNotification($tenant, MarketingEmails::tips($tenant)))->toMail($user)->render();
        $this->assertStringContainsString('Non mandatemele più', (string) $mail);
        $this->assertStringContainsString('Primo mese di promo a metà prezzo', (string) $mail);

        $url = MarketingEmails::unsubscribeUrl($tenant);
        $this->get($url)->assertOk()->assertSee('Non mandatemele più');
        $this->post($url)->assertOk()->assertSee('non ti mandiamo più');
        $this->assertTrue(MarketingEmails::optedOut($tenant->fresh()));

        Notification::fake();
        $this->artisan('hub:monthly-tips')->assertSuccessful();
        Notification::assertNothingSent();

        // Si può tornare indietro; un link senza firma non funziona.
        $this->post($url, ['resubscribe' => 1])->assertOk();
        $this->assertFalse(MarketingEmails::optedOut($tenant->fresh()));
        $this->get(route('email.unsubscribe', $tenant))->assertForbidden();
    }
}
