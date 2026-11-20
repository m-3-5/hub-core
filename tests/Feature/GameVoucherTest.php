<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantUsageEntry;
use App\Models\User;
use App\Support\GameVoucher;
use App\Support\PayPerUse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Buoni vinti ai giochi di piramide35.com: firma HMAC, scadenza, una volta sola, massimo 3 al mese. */
class GameVoucherTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'segreto-di-prova-solo-per-i-test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.game_vouchers.secret' => self::SECRET, 'services.game_vouchers.monthly_limit' => 3, 'services.game_vouchers.max_cents' => 500]);
        Notification::fake();
    }

    /** @return array{0: Tenant, 1: User} */
    private function privato(string $slug = 'mario'): array
    {
        $tenant = Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'type' => 'privato', 'plan' => 'demo', 'guest_verified_at' => now()]);
        $user = User::factory()->create(['email' => $slug.'@example.com']);
        $user->tenants()->attach($tenant, ['role' => 'admin']);

        return [$tenant->fresh(), $user];
    }

    /** Lo stesso calcolo che fa piramide35.com (formato concordato). */
    private function code(int $cents = 100, string $date = '', string $random = 'K7Q2ZP'): string
    {
        $date = $date ?: now()->addMonth()->format('ymd');
        $sig = strtoupper(substr(hash_hmac('sha256', "M35G|{$cents}|{$date}|{$random}", self::SECRET), 0, 10));

        return "M35G-{$cents}-{$date}-{$random}-{$sig}";
    }

    public function test_the_agreed_format_matches_the_helper(): void
    {
        $this->assertSame($this->code(100, '261231', 'K7Q2ZP'), GameVoucher::make(100, Carbon::create(2026, 12, 31), 'K7Q2ZP'));
    }

    public function test_right_signature_adds_one_euro_with_a_clear_movement(): void
    {
        [$tenant, $user] = $this->privato();
        $code = $this->code();

        $this->actingAs($user)->post(route('voucher.redeem'), ['codice' => strtolower($code), 'attivita' => $tenant->slug])
            ->assertRedirect(route('admin.credit.show', $tenant))
            ->assertSessionHas('success');

        $this->assertSame(100, PayPerUse::creditCents($tenant->fresh()));
        $entry = TenantUsageEntry::where('tenant_id', $tenant->id)->where('reason', 'voucher')->sole();
        $this->assertSame(100, $entry->amount_cents);
        $this->assertSame('Buono gioco piramide35', $entry->description);
        $this->assertDatabaseHas('game_vouchers', ['code' => $code, 'tenant_id' => $tenant->id, 'user_id' => $user->id, 'cents' => 100]);

        $this->actingAs($user)->get(route('admin.credit.show', $tenant))->assertOk()->assertSee('Buono gioco piramide35')->assertSee('+ 1 €');
    }

    public function test_wrong_signature_or_changed_amount_is_refused(): void
    {
        [$tenant, $user] = $this->privato();
        $good = $this->code(100);

        foreach ([
            substr($good, 0, -1).(str_ends_with($good, 'A') ? 'B' : 'A'), // firma sbagliata
            str_replace('M35G-100-', 'M35G-500-', $good),                 // importo cambiato a mano
            'M35G-100-261231-K7Q2ZP',                                      // senza firma
            'ciao',
        ] as $bad) {
            $this->actingAs($user)->from('/buono')->post(route('voucher.redeem'), ['codice' => $bad])->assertSessionHasErrors('codice');
        }

        config(['services.game_vouchers.secret' => 'un-altro-segreto']);
        $this->actingAs($user)->from('/buono')->post(route('voucher.redeem'), ['codice' => $good])->assertSessionHasErrors('codice');

        $this->assertSame(0, PayPerUse::creditCents($tenant->fresh()));
        $this->assertDatabaseCount('game_vouchers', 0);
    }

    public function test_expired_voucher_is_refused_but_valid_until_end_of_its_day(): void
    {
        [$tenant, $user] = $this->privato();

        $this->actingAs($user)->from('/buono')->post(route('voucher.redeem'), ['codice' => $this->code(100, now()->subDay()->format('ymd'))])
            ->assertSessionHasErrors(['codice' => 'Questo buono è scaduto il '.now()->subDay()->format('d/m/Y').'.']);

        $this->actingAs($user)->post(route('voucher.redeem'), ['codice' => $this->code(100, now()->format('ymd'))])->assertSessionHasNoErrors();
        $this->assertSame(100, PayPerUse::creditCents($tenant->fresh()));
    }

    public function test_already_used_voucher_is_refused_even_for_someone_else(): void
    {
        [$tenant, $user] = $this->privato('mario');
        [$other, $otherUser] = $this->privato('lucia');
        $code = $this->code();

        $this->actingAs($user)->post(route('voucher.redeem'), ['codice' => $code])->assertSessionHasNoErrors();
        $this->actingAs($user)->from('/buono')->post(route('voucher.redeem'), ['codice' => $code])
            ->assertSessionHasErrors(['codice' => 'Questo buono è già stato usato.']);
        $this->actingAs($otherUser)->from('/buono')->post(route('voucher.redeem'), ['codice' => $code])
            ->assertSessionHasErrors(['codice' => 'Questo buono è già stato usato.']);

        $this->assertSame(100, PayPerUse::creditCents($tenant->fresh()));
        $this->assertSame(0, PayPerUse::creditCents($other->fresh()));
    }

    public function test_at_most_three_vouchers_a_month(): void
    {
        [$tenant, $user] = $this->privato();

        foreach (['AAAAA1', 'AAAAA2', 'AAAAA3'] as $r) {
            $this->actingAs($user)->post(route('voucher.redeem'), ['codice' => $this->code(100, '', $r)])->assertSessionHasNoErrors();
        }
        $this->actingAs($user)->from('/buono')->post(route('voucher.redeem'), ['codice' => $this->code(100, '', 'AAAAA4')])->assertSessionHasErrors('codice');
        $this->assertSame(300, PayPerUse::creditCents($tenant->fresh()));

        $this->travelTo(now()->addMonth()->startOfMonth()->addHour());
        $this->actingAs($user)->post(route('voucher.redeem'), ['codice' => $this->code(100, now()->addMonth()->format('ymd'), 'AAAAA4')])->assertSessionHasNoErrors();
        $this->assertSame(400, PayPerUse::creditCents($tenant->fresh()));
    }

    public function test_too_big_amount_or_missing_secret_is_refused(): void
    {
        [, $user] = $this->privato();

        $this->actingAs($user)->from('/buono')->post(route('voucher.redeem'), ['codice' => $this->code(5000)])->assertSessionHasErrors('codice');

        config(['services.game_vouchers.secret' => null]);
        $this->assertFalse(GameVoucher::check($this->code())['ok']);
    }

    public function test_direct_link_asks_to_log_in_and_comes_back_with_the_code(): void
    {
        $code = $this->code();

        $this->get('/buono?codice='.$code)->assertOk()->assertSee($code)->assertSee('Mandami il link per entrare');
        $this->assertSame('/buono?codice='.rawurlencode($code), session('after_login'));

        [, $user] = $this->privato();
        $this->actingAs($user)->get('/buono?codice='.$code)->assertOk()->assertSee('Buono da')->assertSee('Usa il buono');
    }

    public function test_password_login_returns_to_the_voucher_page(): void
    {
        [, $user] = $this->privato();
        $user->forceFill(['password' => 'segreta-123'])->save();
        $code = $this->code();

        $this->get('/buono?codice='.$code);
        $this->post(route('admin.login.submit'), ['email' => $user->email, 'password' => 'segreta-123'])
            ->assertRedirect('/buono?codice='.rawurlencode($code));
    }
}
