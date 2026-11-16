<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use M35\HubSitebuilder\Services\SiteCopyWriter;
use Tests\TestCase;

/** Se Gemini risponde con un testo non valido, i testi del sito si chiedono di nuovo da soli. */
class SiteCopyWriterRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_invalid_answer_is_retried_automatically(): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        Cache::put('gemini.model_catalog', ['text_models' => ['gemini-test'], 'text_best' => 'gemini-test']);
        $tenant = Tenant::create(['name' => 'Bar Sport', 'slug' => 'bar-sport', 'type' => 'azienda', 'plan' => 'demo']);
        $reply = fn (string $text) => ['candidates' => [['content' => ['parts' => [['text' => $text]]]]]];

        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push($reply('{"tagline": "Caffè e cornetti'))  // risposta tagliata a metà
            ->push($reply(json_encode(['tagline' => 'Il caffè buono del paese', 'services' => [['name' => 'Colazioni', 'line' => 'Cornetti caldi', 'icon' => '☕']]])))]);

        $copy = app(SiteCopyWriter::class)->write($tenant, 'Bar con colazioni e aperitivi');

        $this->assertSame('Il caffè buono del paese', $copy['tagline']);
        Http::assertSentCount(2);
    }
}
