<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProjectFoundationTest extends TestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_the_health_check_endpoint_succeeds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_filament_is_not_installed(): void
    {
        $this->assertFalse(
            class_exists(\Filament\FilamentServiceProvider::class),
            'Filament must not be installed for the M1 baseline.',
        );
    }
}
