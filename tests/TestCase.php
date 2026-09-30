<?php

namespace Tests;

use App\Support\EmailVerification;
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

    /**
     * Create and Edit Account take only an email that passed its code
     * (EmailVerification, 2026-09-30). A test about something else proves
     * the address the way the form's Send code and Verify would have.
     */
    protected function withVerifiedEmail(?string $email): static
    {
        if ($email) {
            $this->withSession(['account_verified_emails.' . EmailVerification::key($email) => now()->getTimestamp()]);
        }

        return $this;
    }
}
