<?php

namespace Tests\Feature;

use App\Models\ClassifiedAd;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ClassifiedContactRequestNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassifiedsPortalCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function seller(): array
    {
        $tenant = Tenant::create(['name' => 'Hazar', 'slug' => 'hazar', 'type' => 'privato', 'plan' => 'demo', 'subscription_status' => 'free']);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant);

        return [$tenant, $user];
    }

    private function createAd(User $user, Tenant $tenant, array $extra): void
    {
        $this->actingAs($user)->post(route('admin.classifieds.store', $tenant), $extra + [
            'zone' => 'Online', 'title' => 'Annuncio', 'description' => 'Testo',
        ])->assertRedirect();
    }

    public function test_each_category_keeps_only_its_own_fields(): void
    {
        [$tenant, $user] = $this->seller();

        $this->createAd($user, $tenant, ['category' => 'oggetti', 'features' => ['condition' => 'usato', 'rooms' => '3', 'buy_url' => 'https://x.it']]);
        $ad = ClassifiedAd::latest('id')->first();
        $this->assertSame(['condition' => 'usato'], $ad->features);
        $this->assertSame(['Condizione: Usato'], $ad->featureChips());

        $this->createAd($user, $tenant, ['category' => 'affitto', 'features' => ['rooms' => '3', 'beds' => '5', 'condition' => 'nuovo']]);
        $this->assertSame(['rooms' => 3, 'beds' => 5], ClassifiedAd::latest('id')->first()->features);
    }

    public function test_books_have_author_and_an_external_buy_link_with_safe_urls_only(): void
    {
        [$tenant, $user] = $this->seller();

        $this->createAd($user, $tenant, ['category' => 'libri', 'price' => 12, 'features' => [
            'author' => 'Hazar Guellil', 'buy_url' => 'https://www.amazon.it/dp/B0HCPWTCRX', 'rooms' => '9',
        ]]);

        $ad = ClassifiedAd::latest('id')->first();
        $this->assertSame('Libri', $ad->categoryLabel());
        $this->assertSame('https://www.amazon.it/dp/B0HCPWTCRX', $ad->buyUrl());
        $this->assertSame('Acquista su Amazon', $ad->buyLabel());
        $this->assertArrayNotHasKey('rooms', $ad->features);
        $this->assertFalse($ad->protected_sale);

        $this->createAd($user, $tenant, ['category' => 'libri', 'features' => ['buy_url' => 'javascript:alert(1)']]);
        $this->assertNull(ClassifiedAd::latest('id')->first()->buyUrl());
    }

    public function test_public_page_shows_the_external_buy_button_and_board_shows_category_chips_with_counts(): void
    {
        [$tenant, $user] = $this->seller();
        $ad = $tenant->classifiedAds()->create([
            'slug' => 'libro', 'category' => 'libri', 'title' => 'Il sogno italiano', 'zone' => 'Online', 'description' => 'Fiaba illustrata',
            'features' => ['author' => 'Hazar Guellil', 'buy_url' => 'https://www.amazon.it/dp/B0HCPWTCRX'],
            'status' => 'published', 'published_at' => now(), 'price' => 12,
        ]);

        $this->get($ad->publicUrl())->assertOk()
            ->assertSee('Acquista su Amazon')
            ->assertSee('rel="sponsored nofollow noopener"', false)
            ->assertSee('Autore: Hazar Guellil');

        $this->get(route('classifieds.board'))->assertOk()
            ->assertSee('Libri')->assertSee('📚')->assertDontSee('Annunci immobili');
    }

    public function test_messages_reach_the_ad_contact_email_without_showing_it_and_skip_guest_placeholder_addresses(): void
    {
        Notification::fake();

        $tenant = Tenant::create(['name' => 'Hazar', 'slug' => 'hazar', 'type' => 'privato', 'plan' => 'demo', 'subscription_status' => 'free']);
        $guest = User::factory()->create(['email' => 'guest-abc@guest.hub-core.local']);
        $guest->tenants()->attach($tenant);

        $ad = $tenant->classifiedAds()->create([
            'slug' => 'traduzioni', 'category' => 'servizi', 'title' => 'Traduzioni', 'zone' => 'Online', 'description' => 'Testo',
            'contact_email' => 'hazar@example.com', 'status' => 'published', 'published_at' => now(),
        ]);

        $this->get($ad->publicUrl())->assertOk()->assertDontSee('hazar@example.com');

        $this->post(route('classifieds.contact', [$tenant, $ad]), ['name' => 'Luca', 'email' => 'luca@example.com', 'message' => 'Mi serve una traduzione.'])->assertRedirect();

        Notification::assertSentOnDemand(ClassifiedContactRequestNotification::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'hazar@example.com');
        Notification::assertNotSentTo($guest, ClassifiedContactRequestNotification::class);
    }
}
