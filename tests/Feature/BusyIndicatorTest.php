<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Rotellina con scritta mentre si aspetta: un solo componente (public/js/hub-busy.js) caricato da tutte le pagine. */
class BusyIndicatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_with_the_shared_style_loads_the_waiting_spinner(): void
    {
        foreach ([route('welcome'), route('admin.login'), route('search'), route('registration.create'), route('pricing.show')] as $url) {
            $this->get($url)->assertOk()->assertSee('js/hub-busy.js', false);
        }
    }

    public function test_the_spinner_covers_forms_filters_and_background_calls_with_italian_words(): void
    {
        $js = file_get_contents(public_path('js/hub-busy.js'));

        $this->assertStringContainsString("addEventListener('submit'", $js);
        $this->assertStringContainsString('HTMLFormElement.prototype.submit', $js);
        $this->assertStringContainsString('window.fetch', $js);
        $this->assertStringContainsString('XMLHttpRequest.prototype.send', $js);
        foreach (['Ricerca in corso…', 'Salvo…', 'Invio…', "L'IA sta scrivendo…"] as $words) {
            $this->assertStringContainsString($words, $js);
        }
        $this->assertStringContainsString('.hub-busy-pill', file_get_contents(public_path('css/hub-ui.css')));
    }
}
