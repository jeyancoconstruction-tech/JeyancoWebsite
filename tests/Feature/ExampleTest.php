<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * "/" is not a landing page — the site opens straight onto the sign-in
     * screen. This shipped as Laravel's stock test asserting a 200 from the
     * starter welcome page, which this app never had a route for.
     */
    public function test_the_root_url_sends_visitors_to_the_login_screen(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }
}
