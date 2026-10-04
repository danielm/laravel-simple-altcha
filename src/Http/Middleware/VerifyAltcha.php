<?php

namespace Danielm\LaravelSimpleAltcha\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Danielm\LaravelSimpleAltcha\AltchaManager;

/**
 * Usage: Route::post('/contact', ...)->middleware('altcha');
 * On failure a ValidationException is thrown for the altcha field, so Inertia
 * and JSON clients get the usual 422 / redirect-with-errors behaviour.
 */
class VerifyAltcha
{
    public function __construct(private readonly AltchaManager $altcha)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $field = config('altcha.field', 'altcha');
        $result = $this->altcha->verify($request->input($field));

        if (! $result->verified) {
            throw ValidationException::withMessages([
                $field => trans($result->messageKey()),
            ]);
        }

        return $next($request);
    }
}
