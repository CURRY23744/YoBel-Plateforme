<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_root_redirects_to_the_dashboard(): void
    {
        $this->get('/')
            ->assertRedirect('/dashboard');
    }

    public function test_general_register_pages_are_available(): void
    {
        $this->get('/decisions')->assertOk()->assertSee('data-register-type="decisions"', false);
        $this->get('/meeting-minutes')->assertOk()->assertSee('data-register-type="meeting-minutes"', false);
    }

    public function test_frontend_assets_are_available_without_a_vite_build(): void
    {
        $cssPath = public_path('css/app.css');
        $jsPath = public_path('js/app.js');

        $this->assertFileExists($cssPath);
        $this->assertFileExists($jsPath);
        $this->assertStringNotContainsString("import './bootstrap';", file_get_contents($jsPath));
    }
}
