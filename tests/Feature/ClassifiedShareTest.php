<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassifiedShareTest extends TestCase
{
    use RefreshDatabase;

    private function ad(array $extra = [])
    {
        $tenant = Tenant::create(['name' => 'Sonia', 'slug' => 'sonia', 'type' => 'privato', 'plan' => 'demo', 'subscription_status' => 'free']);

        return $tenant->classifiedAds()->create($extra + [
            'slug' => 'lezioni', 'category' => 'servizi', 'title' => 'Lezioni di Economia', 'zone' => 'Online',
            'description' => 'Ripetizioni', 'status' => 'published', 'published_at' => now(),
        ]);
    }

    public function test_ad_page_has_share_buttons_and_complete_social_preview_tags(): void
    {
        $ad = $this->ad(['images' => ['ads/sonia/foto.jpg']]);

        $this->get($ad->publicUrl())->assertOk()
            ->assertSee('data-native-share', false)
            ->assertSee('facebook.com/sharer/sharer.php?u='.urlencode($ad->publicUrl()), false)
            ->assertSee('wa.me/?text=', false)
            ->assertSee('data-copy="'.$ad->publicUrl().'"', false)
            ->assertSee('<meta property="og:url" content="'.$ad->publicUrl().'">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('<meta property="og:image" content="', false);
    }

    public function test_ad_without_photo_still_has_an_image_for_the_preview(): void
    {
        $ad = $this->ad();

        $html = $this->get($ad->publicUrl())->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<meta property="og:image" content="https?://[^"]+og-hub-core\.png">~', $html);
    }
}
