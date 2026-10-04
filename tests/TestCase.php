<?php

namespace Danielm\LaravelSimpleAltcha\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Danielm\LaravelSimpleAltcha\AltchaServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [AltchaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('altcha.hmac_secret', 'test-secret');
        $app['config']->set('altcha.challenge.cost', 10); // keep solving fast in tests
        $app['config']->set('cache.default', 'array');
    }
}
