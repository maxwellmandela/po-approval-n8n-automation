<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_guest_homepage_has_procurement_branding(): void
    {
        $baseUrl = rtrim(config('app.url'), '/');

        $response = $this->withHeaders([
            'X-Forwarded-Host' => parse_url($baseUrl, PHP_URL_HOST),
            'X-Forwarded-Proto' => parse_url($baseUrl, PHP_URL_SCHEME),
        ])->get('/');
        $html = $response->getContent();

        $response->assertOk()
            ->assertSee('Procurement Hub')
            ->assertSee('Request, review, and approve purchase requests')
            ->assertSee('Start a request');

        $this->assertSame(1, substr_count($html, '<!DOCTYPE html>'));
        $this->assertSame(1, substr_count($html, '<head>'));
        $this->assertStringContainsString('<title>Procurement Hub</title>', $html);
        $this->assertStringContainsString($baseUrl.'/build/assets/', $html);
        $this->assertStringContainsString($baseUrl.'/login', $html);
    }
}
