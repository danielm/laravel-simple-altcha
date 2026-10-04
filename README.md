# Laravel ALTCHA

![tests](https://github.com/danielm/laravel-simple-altcha/actions/workflows/tests.yml/badge.svg)

[ALTCHA](https://altcha.org) proof-of-work captcha for Laravel, built on
[`altcha-org/altcha`](https://github.com/altcha-org/altcha-lib-php) v2. Includes:

- a challenge endpoint (`GET /altcha/challenge`)
- a `ValidAltcha` validation rule and an `altcha` route middleware
- replay protection (a solved payload works once)
- a React / Inertia component published as a stub

## Install

```bash
composer require danielm/laravel-simple-altcha
npm install altcha
```

`.env`:

```dotenv
# php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
ALTCHA_HMAC_SECRET=
```

All supported variables are documented in [`.env.example`](.env.example).

Optional publishing:

```bash
php artisan vendor:publish --tag=altcha-config   # config/altcha.php
php artisan vendor:publish --tag=altcha-lang     # translations
php artisan vendor:publish --tag=altcha-react    # React component + JSX types
```

The `altcha-react` tag copies:

- `resources/js/components/altcha-widget.tsx`
- `resources/js/types/altcha.d.ts`

## Backend

Validation rule (implicit, so a missing field also fails):

```php
use Danielm\LaravelSimpleAltcha\Rules\ValidAltcha;

$request->validate([
    'email'  => ['required', 'email'],
    'altcha' => [new ValidAltcha],
]);
```

Or middleware:

```php
Route::post('/contact', ContactController::class)->middleware('altcha');
```

Or directly:

```php
$result = app(\Danielm\LaravelSimpleAltcha\AltchaManager::class)->verify($request->input('altcha'));
$result->verified;  // bool
$result->reason;    // missing | malformed | expired | invalid | replayed
```

## Frontend (Inertia + React)

```tsx
import { useRef } from 'react';
import { useForm } from '@inertiajs/react';
import { AltchaWidget, type AltchaHandle } from '@/components/altcha-widget';

export default function Contact() {
    const altcha = useRef<AltchaHandle>(null);
    const { data, setData, post, processing, errors } = useForm({ email: '', altcha: '' });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/contact', {
            // payloads are single-use, so always get a fresh one afterwards
            onFinish: () => altcha.current?.reset(),
        });
    };

    return (
        <form onSubmit={submit}>
            <input value={data.email} onChange={(e) => setData('email', e.target.value)} />
            <AltchaWidget ref={altcha} onChange={(payload) => setData('altcha', payload)} />
            {errors.altcha && <p>{errors.altcha}</p>}
            <button disabled={processing || !data.altcha}>Send</button>
        </form>
    );
}
```

`challengeUrl` defaults to `/altcha/challenge`. If you change `altcha.route.path`,
pass the new URL. Extra props are forwarded to `<altcha-widget>` as attributes.

## Configuration notes

- **Algorithm**: `pbkdf2` (default, no extensions), `argon2id` (`ext-sodium`),
  `scrypt` (`ext-scrypt`). `cost` means different things per algorithm. Your
  widget build must support the one you choose.
- **Replay protection** uses `Cache::add()`. Use redis/memcached/database in
  production; the `file` driver is not atomic. Keys are derived from the
  challenge's HMAC signature, so re-encoding a payload does not bypass it.
- **Challenge route** is intentionally outside the `web` group (no session or
  cookies) and throttled by default. Adjust `altcha.route.middleware`.
- **Widget requires HTTPS** (localhost is fine for development).

## Testing in your app

Set `ALTCHA_TESTING_BYPASS=let-me-in` in `.env.testing`, then post
`['altcha' => 'let-me-in']`. The bypass only works when the app environment
is `testing`.

## Package tests

```bash
composer install
composer test
```
