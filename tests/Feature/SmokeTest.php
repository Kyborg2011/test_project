<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_is_rendered_with_frontend_assets(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('vendor/bootstrap/bootstrap.min.css', false)
            ->assertSee('vendor/jquery/jquery.min.js', false)
            ->assertSee('name="csrf-token"', false);
    }

    public function test_health_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_tests_run_against_the_mysql_testing_database(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('testing', DB::connection()->getDatabaseName());
    }
}
