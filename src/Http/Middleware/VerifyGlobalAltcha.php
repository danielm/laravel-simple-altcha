<?php

namespace Danielm\LaravelSimpleAltcha\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Danielm\LaravelSimpleAltcha\AltchaManager;

/**
 * This middleware can be used globally, and will ony skip verification
 * on defined routes (Bypass)
 */
class VerifyGlobalAltcha
{
    public function __construct(private readonly AltchaManager $altcha)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $field = config('altcha.field', 'altcha');
        $routes = config('altcha.verify_routes', []);

        if (empty($routes) || !$request->routeIs($routes)) {
            return $next($request);
        }

        $result = $this->altcha->verify($request->input($field));

        if (! $result->verified) {
            throw ValidationException::withMessages([
                $field => trans($result->messageKey()),
            ]);
        }

        return $next($request);
    }
}
