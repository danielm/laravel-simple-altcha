<?php

return [

    /*
    |--------------------------------------------------------------------------
    | HMAC secret
    |--------------------------------------------------------------------------
    | Signs the challenges so clients can't forge them. Required.
    | Generate one with: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    */
    'hmac_secret' => env('ALTCHA_HMAC_SECRET'),

    /*
    | Optional. Enables the library's "fast verification path" (skips
    | re-deriving the key). Only used for challenges created with a counter,
    | which this package does not do by default, so you can leave it null.
    */
    'hmac_key_secret' => env('ALTCHA_HMAC_KEY_SECRET'),

    /*
    | Key derivation algorithm: pbkdf2 | argon2id | scrypt
    | argon2id needs ext-sodium, scrypt needs ext-scrypt.
    | Your widget build must support the algorithm you pick.
    */
    'algorithm' => env('ALTCHA_ALGORITHM', 'pbkdf2'),

    /*
    | Name of the request field that carries the payload.
    */
    'field' => 'altcha',

    'challenge' => [
        // Iterations / time cost. Meaning depends on the algorithm
        // (pbkdf2: iterations, e.g. 10000; argon2id: time cost, e.g. 2-3).
        'cost' => (int) env('ALTCHA_COST', 10000),

        // Memory cost (argon2id: KiB, scrypt: r). null = library default.
        'memory_cost' => null,

        // Parallelism (scrypt). null = library default.
        'parallelism' => null,

        // Seconds until a challenge expires. null = never (not recommended).
        'expires_in' => (int) env('ALTCHA_EXPIRES_IN', 120),

        // Optional custom metadata embedded in (and signed with) the challenge.
        'data' => null,
    ],

    /*
    | Challenge endpoint. The widget fetches a fresh challenge from here.
    | Deliberately not in the "web" group (no session/cookies needed).
    */
    'route' => [
        'enabled' => true,
        'path' => 'altcha/challenge',
        'name' => 'altcha.challenge',
        'middleware' => ['throttle:60,1'],
    ],

    /*
    | Replay protection. A solved payload is only a signed blob, so without
    | this it could be resubmitted until the challenge expires. Uses the cache;
    | pick a store that supports atomic add() (redis, memcached, database).
    */
    'replay' => [
        'enabled' => true,
        'store' => env('ALTCHA_CACHE_STORE'), // null = default cache store
        'ttl' => null,                        // null = challenge.expires_in + 60s
        'prefix' => 'altcha:used:',
    ],

    /*
    | If set, a payload equal to this value passes verification, but ONLY when
    | the app environment is "testing". Handy in feature tests.
    */
    'testing_bypass' => env('ALTCHA_TESTING_BYPASS'),

    /*
      Optional. Comma separated route names on wich the VerifyGlobalAltcha 
      middleware will verify if enabled.
    */
    'verify_routes' => env('ALTCHA_VERIFY_ROUTES', []), 
];
