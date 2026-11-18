<?php

namespace Tests\Feature;

use App\Models\Promo;
use App\Models\Tenant;
use App\Models\TenantUsageEntry;
use App\Models\User;
use App\Notifications\CreditOverdraftNotification;
use App\Notifications\UsageReceiptNotification;
use App\Support\PayPerUse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Credito prepagato: 15 € in regalo la prima volta, tutto a 1 € si scala da lì; poi ricarica (min 5 €),
 * carta salvata o fido fino a 5 € senza fermarsi. Ricevuta per email al cliente e a Max a ogni acquisto e ricarica.
 */
class PrepaidCreditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.hub_billing.pay_per_use.enabled' => true, 'services.hub_billing.secret_key' => 'sk_test_hub',
            'services.hub_billing.pay_per_use.welcome_credit_cents' => 1500,
            'services.hub_billing.pay_per_use.overdraft_cents' => 500,
            'services.hub_billing.pay_per_use.topup_min_cents' => 500,
            'mail.leads_monitor_email' => 'max@example.com',
        ]);
        Notification::fake();
    }

    /** @return array{0: Tenant, 1: User} */
    private function business(): array
    {
        $tenant = Tenant::create(['name' => 'Ferramenta Rossi', 'slug' => 'ferramenta-rossi', 'type' => 'azienda', 'plan' => 'demo', 'guest_verified_at' => now()]);
        $user = User::factory()->create(['email' => 'rossi@example.com']);
        $user->tenants()->attach($tenant, ['role' => 'admin']);

        return [$tenant->fresh(), $user];
    }

    private function publish(Tenant $tenant, User $user, string $title)
    {
        $promo = $tenant->promos()->create(['title' => $title, 'slug' => \Illuminate\Support\Str::slug($title).'-'.uniqid(), 'status' => 'draft']);

        $this->actingAs($user)->post(route('admin.promos.publish', [$tenant, $promo]));

        return $promo->fresh();
    }

    public function test_everyone_gets_15_euro_once_and_publishing_uses_it_with_a_receipt_to_client_and_max(): void
    {
        [$tenant, $user] = $this->business();

        $this->assertSame(1500, PayPerUse::creditCents($tenant));
        $this->assertSame(1500, PayPerUse::creditCents($tenant->fresh())); // una volta sola

        $promo = $this->publish($tenant, $user, 'Sconto trapani');

        $this->assertSame('published', $promo->status);
        $this->assertSame(1400, PayPerUse::creditCents($tenant->fresh()));
        $this->assertSame(0, PayPerUse::pendingCents($tenant)); // niente da addebitare sulla carta
        $this->assertSame('credit', TenantUsageEntry::where('reason', 'use')->sole()->status);

        Notification::assertSentTo($user, UsageReceiptNotification::class, function ($n) use ($user) {
            $mail = $n->toMail($user);

            return str_contains($mail->subject, 'Ricevuta n.') && str_contains(implode(' ', $mail->introLines), 'non fiscale') === false
                && str_contains(implode(' ', $mail->outroLines), 'non fiscale');
        });
        Notification::assertSentTo(new AnonymousNotifiable, UsageReceiptNotification::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'max@example.com');

        $this->get(route('admin.credit.show', $tenant))->assertOk()->assertSee('+ 15 €')->assertSee('− 1 €')->assertDontSee('(carta)');
    }

    public function test_when_credit_ends_work_goes_on_with_the_overdraft_and_both_are_told(): void
    {
        [$tenant, $user] = $this->business();
        $settings = $tenant->settings ?? [];
        $settings['ppu_credit'] = ['cents' => 100, 'welcome_at' => now()->toIso8601String()];
        $tenant->forceFill(['settings' => $settings])->save();

        $this->assertSame('published', $this->publish($tenant, $user, 'Prima')->status);   // ultimo euro di credito
        $this->assertSame('published', $this->publish($tenant, $user, 'Seconda')->status); // fido
        Notification::assertSentTo($user, CreditOverdraftNotification::class);
        Notification::assertSentTo(new AnonymousNotifiable, CreditOverdraftNotification::class);
        $this->assertSame(-100, PayPerUse::creditCents($tenant->fresh()));

        // Fino a -5 €: poi si chiede la ricarica.
        foreach (range(1, 4) as $i) {
            $this->publish($tenant, $user, 'Altra '.$i);
        }
        $this->assertSame(-480, PayPerUse::creditCents($tenant->fresh())); // dal 6° del mese 0,80 €
        $this->assertSame('draft', $this->publish($tenant, $user, 'Troppe')->status);

        $this->get(route('admin.credit.show', $tenant))->assertOk()->assertSee('stai usando il fido')->assertSee('Ricarica il credito');
    }

    public function test_top_up_with_card_settles_the_overdraft_and_counts_once(): void
    {
        [$tenant, $user] = $this->business();
        $settings = $tenant->settings ?? [];
        $settings['ppu_credit'] = ['cents' => -300, 'welcome_at' => now()->toIso8601String()];
        $tenant->forceFill(['settings' => $settings])->save();
        TenantUsageEntry::create(['tenant_id' => $tenant->id, 'amount_cents' => 100, 'reason' => 'use', 'status' => 'owed', 'item_type' => 'promo', 'item_id' => 1, 'description' => 'Promo «x»']);

        Http::fake(['api.stripe.com/v1/checkout/sessions/cs_1*' => Http::response([
            'id' => 'cs_1', 'mode' => 'payment', 'payment_status' => 'paid', 'amount_total' => 1000, 'metadata' => ['tenant_id' => (string) $tenant->id, 'ppu_topup' => '1'],
        ])]);

        $this->actingAs($user)->get(route('admin.credit.topup.return', [$tenant, 'session_id' => 'cs_1']))->assertRedirect(route('admin.credit.show', $tenant));
        $this->get(route('admin.credit.topup.return', [$tenant, 'session_id' => 'cs_1'])); // ritorno ripetuto (o webhook): non conta due volte

        $this->assertSame(700, PayPerUse::creditCents($tenant->fresh()));
        $this->assertSame(1, TenantUsageEntry::where('reason', 'topup')->count());
        $this->assertSame(0, TenantUsageEntry::where('status', 'owed')->count());
        Notification::assertSentTo($user, UsageReceiptNotification::class);
    }

    public function test_top_up_minimum_is_five_euro_and_only_staff_marks_bank_transfers(): void
    {
        [$tenant, $user] = $this->business();

        $this->actingAs($user)->post(route('admin.credit.topup', $tenant), ['amount' => 3])->assertSessionHasErrors('amount');
        $this->post(route('admin.credit.manual', $tenant), ['amount' => 20, 'method' => 'bonifico'])->assertForbidden();

        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin)->post(route('admin.credit.manual', $tenant), ['amount' => 20, 'method' => 'bonifico'])->assertRedirect();
        $this->assertSame(3500, PayPerUse::creditCents($tenant->fresh()));
    }
}
