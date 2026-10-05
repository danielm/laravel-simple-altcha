<?php

namespace Danielm\LaravelSimpleAltcha;

use AltchaOrg\Altcha\Algorithm\Argon2id;
use AltchaOrg\Altcha\Algorithm\DeriveKeyInterface;
use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Algorithm\Scrypt;
use AltchaOrg\Altcha\Altcha;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\ServiceProvider;
use Danielm\LaravelSimpleAltcha\Http\Middleware\VerifyAltcha;
use Illuminate\Routing\Router;

class AltchaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/altcha.php', 'altcha');

        $this->app->singleton(AltchaManager::class, function ($app) {
            $config = $app['config']->get('altcha');

            if (empty($config['hmac_secret'])) {
                throw new \RuntimeException(
                    'ALTCHA_HMAC_SECRET is not set. Generate one with: php -r "echo bin2hex(random_bytes(32));"'
                );
            }

            $altcha = new Altcha(
                hmacSignatureSecret: $config['hmac_secret'],
                hmacKeySignatureSecret: $config['hmac_key_secret'] ?: null,
            );

            return new AltchaManager(
                $altcha,
                $this->makeAlgorithm((string) $config['algorithm']),
                $app->make(CacheFactory::class),
                $config,
            );
        });

        $this->app->alias(AltchaManager::class, 'altcha');
    }

    public function boot(Router $router): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'altcha');

        if (config('altcha.route.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/altcha.php');
        }

        $router->aliasMiddleware('altcha', VerifyAltcha::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/altcha.php' => config_path('altcha.php'),
            ], 'altcha-config');

            $this->publishes([
                __DIR__.'/../lang' => $this->app->langPath('vendor/altcha'),
            ], 'altcha-lang');

            $this->publishes([
                __DIR__.'/../stubs/react/altcha-widget.tsx' => resource_path('js/components/altcha-widget.tsx'),
            ], 'altcha-react');
        }
    }

    private function makeAlgorithm(string $name): DeriveKeyInterface
    {
        return match (strtolower($name)) {
            'pbkdf2' => new Pbkdf2(),
            'argon2id' => new Argon2id(),
            'scrypt' => new Scrypt(),
            default => throw new \InvalidArgumentException(
                "Unsupported ALTCHA algorithm [{$name}]. Use pbkdf2, argon2id or scrypt."
            ),
        };
    }
}
