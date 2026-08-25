<?php

namespace Tests\Browser;

use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class TutorWorkspaceBrowserTest extends DuskTestCase
{
    public function test_tutor_can_toggle_between_card_and_table_views_in_browser(): void
    {
        $tutor = User::where('email', 'tutor@gmail.com')->first() ?? User::factory()->tutor()->create([
            'email' => 'tutor@gmail.com',
            'name' => 'Tutor',
        ]);

        $this->browse(function (Browser $browser) use ($tutor) {
            $browser->resize(1280, 800)
                ->loginAs($tutor)
                ->visit('/tutor/courses')
                ->assertSee('Tutor workspace')
                ->assertSee('Courses you own')
                ->click('@table-view-btn')
                ->waitForText('KURSUS')
                ->assertSee('VISIBILITAS')
                ->assertSee('TANGGAL DIBUAT')
                ->click('@cards-view-btn')
                ->waitForText('Edit');
        });
    }

    public function test_tutor_sees_clean_top_navbar_with_no_duplicates(): void
    {
        $tutor = User::where('email', 'tutor@gmail.com')->first();

        $this->browse(function (Browser $browser) use ($tutor) {
            $browser->loginAs($tutor)
                ->visit('/tutor/courses')
                ->assertSee('BisaBelajar')
                ->assertSee('Tutor workspace')
                ->assertSee('Courses');
        });
    }
}
