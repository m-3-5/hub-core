<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\ContentModerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use M35\HubSitebuilder\Models\ArchivedSite;
use M35\HubSitebuilder\Models\GeneratedSite;
use M35\HubSitebuilder\Notifications\SiteEditLinkNotification;
use M35\HubSitebuilder\Notifications\SiteExpiringNotification;
use M35\HubSitebuilder\Notifications\SiteRemovedNotification;
use M35\HubSitebuilder\Services\SiteBuilderHeroGenerator;
use Tests\TestCase;

class SiteBuilderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function allow(bool $allowed = true, bool $unavailable = false, string $reason = ''): void
    {
        $this->mock(ContentModerator::class, fn ($m) => $m->shouldReceive('check')->andReturn([
            'allowed' => $allowed, 'unavailable' => $unavailable, 'category' => $allowed ? 'ok' : 'truffa', 'reason' => $reason,
        ]));
        $this->mock(SiteBuilderHeroGenerator::class, fn ($m) => $m->shouldReceive('generate')->andReturn(null));
    }

    /** @return array{0: Tenant, 1: User} */
    private function owner(array $tenant = [], string $email = 'anna@example.com'): array
    {
        $t = Tenant::create(array_merge(['name' => 'Salone Anna', 'slug' => 'salone-anna', 'type' => 'azienda', 'plan' => 'demo', 'guest_verified_at' => now(), 'subscription_status' => 'active'], $tenant));
        $u = User::factory()->create(['email' => $email]);
        $u->tenants()->attach($t, ['role' => 'admin']);

        return [$t, $u];
    }

    private function payload(array $extra = []): array
    {
        return array_merge(['tagline' => 'Il tuo taglio, come lo sogni', 'services' => 'Taglio, Colore, Piega', 'cta_label' => 'Chiamaci', 'extra' => null], $extra);
    }

    public function test_a_serious_site_is_published_and_the_secret_link_goes_to_the_real_email_only(): void
    {
        Notification::fake();
        $this->allow();
        [$tenant, $user] = $this->owner();
        $guest = User::factory()->create(['email' => 'guest-1@guest.hub-core.local']);
        $guest->tenants()->attach($tenant, ['role' => 'admin']);

        $this->actingAs($user)->post(route('admin.sitebuilder.generate', $tenant), $this->payload())->assertRedirect();

        $site = GeneratedSite::sole();
        $this->assertTrue($site->isLive());
        $this->assertNotNull($site->last_edited_at);
        Notification::assertSentTo($user, SiteEditLinkNotification::class);
        Notification::assertNotSentTo($guest, SiteEditLinkNotification::class);
        $this->get(route('site.public.show', $tenant))->assertOk()->assertSee('Il tuo taglio, come lo sogni');
    }

    public function test_spam_prohibited_or_unchecked_content_is_never_published(): void
    {
        Notification::fake();
        [$tenant, $user] = $this->owner();

        // Refused by the check
        $this->allow(false, false, 'Sembra una truffa.');
        $this->actingAs($user)->post(route('admin.sitebuilder.generate', $tenant), $this->payload())->assertSessionHasErrors('tagline');
        $this->assertSame(0, GeneratedSite::count());

        // Check not reachable: fail closed
        $this->allow(false, true, 'Controllo non disponibile.');
        $this->actingAs($user)->post(route('admin.sitebuilder.generate', $tenant), $this->payload())->assertSessionHasErrors('tagline');
        $this->assertSame(0, GeneratedSite::count());

        // Two links: refused without even asking the AI
        $this->mock(ContentModerator::class, fn ($m) => $m->shouldReceive('check')->never());
        $this->actingAs($user)->post(route('admin.sitebuilder.generate', $tenant), $this->payload(['extra' => 'vai su http://a.example e www.b.example']))->assertSessionHasErrors('tagline');
        $this->assertSame(0, GeneratedSite::count());
        Notification::assertNothingSent();
    }

    public function test_secret_link_edits_without_login_is_moderated_and_expires_or_is_replaced(): void
    {
        Notification::fake();
        $this->allow();
        [$tenant, $user] = $this->owner();
        $this->actingAs($user)->post(route('admin.sitebuilder.generate', $tenant), $this->payload())->assertRedirect();

        $token = null;
        Notification::assertSentTo($user, SiteEditLinkNotification::class, function ($n) use (&$token) {
            $token = (string) basename(parse_url($n->toMail(new User)->actionUrl, PHP_URL_PATH));

            return true;
        });
        auth()->logout();

        $this->get(route('site.edit.show', $token))->assertOk()->assertSee('Modifica il tuo sito')->assertSee('Il tuo taglio, come lo sogni')
            ->assertSee('noindex', false);

        $this->post(route('site.edit.update', $token), $this->payload(['tagline' => 'Nuova frase']))->assertRedirect(route('site.edit.show', $token));
        $this->assertSame('Nuova frase', GeneratedSite::sole()->answers['tagline']);

        // The check applies to edits too
        $this->allow(false, false, 'Contenuto non adatto.');
        $this->post(route('site.edit.update', $token), $this->payload(['tagline' => 'Truffa garantita']))->assertSessionHasErrors('tagline');
        $this->assertSame('Nuova frase', GeneratedSite::sole()->answers['tagline']);

        // Wrong token and expired token
        $this->get(route('site.edit.show', str_repeat('x', 48)))->assertNotFound();
        GeneratedSite::sole()->forceFill(['edit_link_sent_at' => now()->subDays(GeneratedSite::EDIT_LINK_DAYS + 1)])->save();
        $this->get(route('site.edit.show', $token))->assertNotFound();

        // A new link replaces the old one
        $this->allow();
        $this->actingAs($user)->post(route('admin.sitebuilder.edit-link', $tenant))->assertSessionHas('success');
        $new = null;
        Notification::assertSentToTimes($user, SiteEditLinkNotification::class, 2);
        $this->assertNotNull(GeneratedSite::sole()->edit_token_hash);
        $this->assertNotSame(hash('sha256', $token), GeneratedSite::sole()->edit_token_hash);
        $this->get(route('site.edit.show', $token))->assertNotFound();
    }

    public function test_without_a_real_email_no_link_is_sent_and_the_site_stays_hidden(): void
    {
        Notification::fake();
        $this->allow();
        [$tenant, $user] = $this->owner(['guest_verified_at' => null], 'guest-9@guest.hub-core.local');

        $this->actingAs($user)->post(route('admin.sitebuilder.generate', $tenant), $this->payload())->assertRedirect();

        Notification::assertNothingSent();
        $this->actingAs($user)->post(route('admin.sitebuilder.edit-link', $tenant))->assertSessionHasErrors('tagline');

        auth()->logout();
        $this->get(route('site.public.show', $tenant))->assertNotFound();

        $this->actingAs($user)->get(route('site.public.show', $tenant))->assertOk(); // anteprima del titolare
    }

    public function test_lifecycle_warns_then_archives_and_tells_the_owner(): void
    {
        Notification::fake();
        $this->allow();
        [$tenant, $user] = $this->owner(['subscription_status' => 'trialing', 'trial_ends_at' => now()->subDay()]);
        $this->actingAs($user)->post(route('admin.sitebuilder.generate', $tenant), $this->payload())->assertRedirect();
        auth()->logout();

        // Anteprima: non cambia nulla
        $this->artisan('hub:sites-lifecycle')->assertSuccessful();
        $this->assertNull(GeneratedSite::sole()->warned_at);
        Notification::assertNotSentTo($user, SiteExpiringNotification::class);

        $this->artisan('hub:sites-lifecycle', ['--apply' => true])->assertSuccessful();
        $site = GeneratedSite::sole();
        $this->assertNotNull($site->warned_at);
        $this->assertTrue($site->delete_after->between(now()->addDays(13), now()->addDays(15)));
        Notification::assertSentTo($user, SiteExpiringNotification::class);

        // Ancora in tolleranza: resta online
        $this->artisan('hub:sites-lifecycle', ['--apply' => true])->assertSuccessful();
        $this->assertSame(1, GeneratedSite::count());

        // Scaduta la tolleranza: archivio riservato e avviso di rimozione
        $site->forceFill(['delete_after' => now()->subHour()])->save();
        $this->artisan('hub:sites-lifecycle', ['--apply' => true])->assertSuccessful();

        $this->assertSame(0, GeneratedSite::count());
        $archived = ArchivedSite::sole();
        $this->assertSame('salone-anna', $archived->tenant_slug);
        $this->assertSame('anna@example.com', $archived->owner_email);
        $this->assertSame('trial_scaduto', $archived->reason);
        $this->assertSame('Il tuo taglio, come lo sogni', $archived->answers['tagline']);
        Notification::assertSentTo($user, SiteRemovedNotification::class);
        $this->get(route('site.public.show', $tenant))->assertNotFound();

        // Solo l'amministratore vede l'archivio
        $this->actingAs($user)->get(route('admin.site-archive.index'))->assertForbidden();
        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin)->get(route('admin.site-archive.index'))->assertOk()->assertSee('Salone Anna')->assertSee('Il tuo taglio, come lo sogni');
    }

    public function test_paying_or_protected_or_recovered_sites_are_not_removed(): void
    {
        Notification::fake();
        $this->allow();
        [$paying] = $this->owner(['slug' => 'a', 'subscription_status' => 'active']);
        [$kept, $keptUser] = $this->owner(['slug' => 'b', 'subscription_status' => 'canceled', 'settings' => ['keep_site' => true]], 'b@example.com');
        [$late, $lateUser] = $this->owner(['slug' => 'c', 'subscription_status' => 'past_due'], 'c@example.com');

        foreach ([$paying, $kept, $late] as $t) {
            GeneratedSite::create(['tenant_id' => $t->id, 'status' => 'published', 'answers' => $this->payload(), 'published_at' => now()->subYear(), 'last_edited_at' => now()->subYear()]);
        }

        $this->artisan('hub:sites-lifecycle', ['--apply' => true])->assertSuccessful();

        $this->assertNull($paying->generatedSite->warned_at);
        $this->assertNull($kept->generatedSite->warned_at);
        $this->assertNotNull($late->generatedSite->warned_at);
        Notification::assertNotSentTo($keptUser, SiteExpiringNotification::class);
        Notification::assertSentTo($lateUser, SiteExpiringNotification::class);

        // Il cliente regolarizza: l'avviso decade
        $late->update(['subscription_status' => 'active']);
        $this->artisan('hub:sites-lifecycle', ['--apply' => true])->assertSuccessful();
        $this->assertNull($late->generatedSite->fresh()->warned_at);
        $this->assertNull($late->generatedSite->fresh()->delete_after);
    }

    public function test_inactive_free_sites_are_warned_and_unverified_ones_are_archived_silently(): void
    {
        Notification::fake();
        [$free, $freeUser] = $this->owner(['slug' => 'privato', 'type' => 'privato', 'subscription_status' => 'free'], 'p@example.com');
        GeneratedSite::create(['tenant_id' => $free->id, 'status' => 'published', 'answers' => $this->payload(), 'published_at' => now()->subDays(200), 'last_edited_at' => now()->subDays(200)]);

        [$ghost] = $this->owner(['slug' => 'fantasma', 'guest_verified_at' => null, 'subscription_status' => 'trialing', 'trial_ends_at' => now()->addDay()], 'guest-2@guest.hub-core.local');
        $old = GeneratedSite::create(['tenant_id' => $ghost->id, 'status' => 'published', 'answers' => $this->payload()]);
        $old->forceFill(['created_at' => now()->subDays(45)])->save();

        $this->artisan('hub:sites-lifecycle', ['--apply' => true])->assertSuccessful();

        $this->assertNotNull($free->generatedSite->fresh()->warned_at);
        Notification::assertSentTo($freeUser, SiteExpiringNotification::class);
        $this->assertSame('mai_verificato', ArchivedSite::sole()->reason);
        Notification::assertNotSentTo($freeUser, SiteRemovedNotification::class);
    }

    public function test_landing_shows_the_presence_points_and_only_the_clients_who_agreed(): void
    {
        Tenant::create(['name' => 'O.M.A. Passarelli', 'slug' => 'oma', 'type' => 'azienda', 'plan' => 'demo', 'website' => 'https://oma.example', 'guest_verified_at' => now(), 'settings' => ['directory' => ['show' => true, 'city' => 'Castrovillari']]]);
        Tenant::create(['name' => 'Privato Segreto', 'slug' => 'segreto', 'type' => 'azienda', 'plan' => 'demo', 'website' => 'https://segreto.example', 'guest_verified_at' => now()]);
        Tenant::create(['name' => 'Non Verificato', 'slug' => 'nv', 'type' => 'azienda', 'plan' => 'demo', 'website' => 'https://nv.example', 'settings' => ['directory' => ['show' => true]]]);
        Tenant::create(['name' => 'Sito Http', 'slug' => 'http', 'type' => 'azienda', 'plan' => 'demo', 'website' => 'http://http.example', 'guest_verified_at' => now(), 'settings' => ['directory' => ['show' => true]]]);

        $this->get(route('landing.web'))->assertOk()
            ->assertSee('Castiglione delle Stiviere')->assertSee('Castrovillari')->assertSee('Senise')
            ->assertSee('I siti dei nostri clienti')->assertSee('O.M.A. Passarelli')->assertSee('https://oma.example', false)
            ->assertDontSee('Privato Segreto')->assertDontSee('Non Verificato')->assertDontSee('Sito Http')
            ->assertDontSee('Ora siamo a');
    }

    public function test_admin_can_choose_who_appears_and_who_keeps_the_site(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente', 'slug' => 'cliente', 'type' => 'azienda', 'plan' => 'demo', 'website' => 'https://c.example', 'guest_verified_at' => now()]);
        $admin = User::factory()->create(['is_super_admin' => true]);
        $normal = User::factory()->create();
        $normal->tenants()->attach($tenant, ['role' => 'admin']);

        $this->actingAs($normal)->post(route('admin.tenants.directory', $tenant), ['show' => 1])->assertForbidden();

        $this->actingAs($admin)->post(route('admin.tenants.directory', $tenant), ['show' => 1, 'city' => 'Rossano', 'keep_site' => 1])->assertRedirect();
        $this->assertTrue($tenant->fresh()->settings['directory']['show']);
        $this->assertSame('Rossano', $tenant->fresh()->settings['directory']['city']);
        $this->assertTrue($tenant->fresh()->settings['keep_site']);
    }
}
