<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Pagine pubbliche «Cosa puoi fare» e «Guida all'app»: si aprono, mostrano le cose attive e il credito dei giochi. */
class PublicGuidesTest extends TestCase
{
    use RefreshDatabase;

    public function test_features_page_lists_active_things_and_links_the_app(): void
    {
        $this->get(route('guides.features'))->assertOk()
            ->assertSee('Cosa puoi fare con inm35.it')
            ->assertSee(route('app.index'), false)
            ->assertSee('Preventivi')
            ->assertSee('In arrivo');
    }

    public function test_app_guide_explains_every_section_and_game_credit(): void
    {
        $this->get(route('guides.app'))->assertOk()
            ->assertSee('id="promo"', false)->assertSee('id="sito"', false)->assertSee('id="facebook"', false)->assertSee('id="credito"', false)
            ->assertSee('marchi trattati', false)
            ->assertSee('piramide35.com');
    }

    public function test_guides_index_links_the_new_guides(): void
    {
        $this->get(route('guides.index'))->assertOk()
            ->assertSee(route('guides.features'), false)
            ->assertSee(route('guides.app'), false);
    }
}
