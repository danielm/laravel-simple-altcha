<?php

namespace Danielm\LaravelSimpleAltcha\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Danielm\LaravelSimpleAltcha\AltchaManager;

/**
 * Usage: $request->validate(['altcha' => [new ValidAltcha]]);
 * Implicit, so a missing/empty field also fails (no need for 'required').
 */
class ValidAltcha implements ValidationRule
{
    public bool $implicit = true;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $result = app(AltchaManager::class)->verify($value);

        if (! $result->verified) {
            $fail($result->messageKey())->translate();
        }
    }
}
