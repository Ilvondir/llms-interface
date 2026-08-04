<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_application_redirects_guests_from_home(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('dashboard'));
    }
}
