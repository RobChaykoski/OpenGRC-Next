<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Passport\Passport;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Use committed test keys for OAuth, and skip file-permission checks.
     *
     * Passport otherwise reads storage/oauth-*.key, which does not exist in CI
     * and is not readable with correct permissions when the repository is bind
     * mounted from Windows or macOS into a container. The keys below are test
     * fixtures, not secrets.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $privateKey = base_path('tests/Fixtures/oauth/oauth-private.key');
        $publicKey = base_path('tests/Fixtures/oauth/oauth-public.key');

        if (is_file($privateKey) && is_file($publicKey)) {
            config([
                'passport.private_key' => file_get_contents($privateKey),
                'passport.public_key' => file_get_contents($publicKey),
            ]);

            Passport::$validateKeyPermissions = false;
        }
    }
}
