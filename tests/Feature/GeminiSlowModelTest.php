<?php

namespace Tests\Feature;

use App\Services\GeminiTextClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Se un modello di Gemini è lento (tempo scaduto) si passa al successivo invece di fermarsi. */
class GeminiSlowModelTest extends TestCase
{
    public function test_a_timed_out_model_falls_back_to_the_next_one(): void
    {
        config(['services.gemini.api_key' => 'test-key']);
        Cache::put('gemini.model_catalog', ['text_models' => ['modello-lento', 'modello-veloce'], 'text_best' => 'modello-lento']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'modello-lento')) {
                throw new ConnectionException('cURL error 28: Operation timed out');
            }

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => '{"ok":true}']]]]]]);
        });

        $this->assertSame('{"ok":true}', app(GeminiTextClient::class)->generate('ciao', json: true, timeout: 5));
    }
}
