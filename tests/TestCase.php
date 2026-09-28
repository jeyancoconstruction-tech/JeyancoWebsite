<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The kiosk's GPS check is off unless a test is about it — those turn it
     * on themselves. Most tests clock workers at a kiosk with no GPS at all.
     */
    protected function setUp(): void
    {
        parent::setUp();
        config(['kiosk.enforce_location' => false]);
    }
}
