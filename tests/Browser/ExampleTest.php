<?php

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ExampleTest extends DuskTestCase
{
    /**
     * A basic browser test example.
     */
    public function test_homepage_renders_in_browser(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->resize(360, 800)
                ->visit('/')
                ->assertSee('BisaBelajar')
                ->assertSee('AI-VET Platform');
        });
    }
}
