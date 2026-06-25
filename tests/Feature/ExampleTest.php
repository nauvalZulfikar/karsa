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
        // App ini redirect root '/' ke panel admin (login). Sehat = redirect, bukan 200.
        $this->get('/')->assertRedirect();
    }
}
