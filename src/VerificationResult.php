<?php

namespace Danielm\LaravelSimpleAltcha;

final class VerificationResult
{
    public const MISSING = 'missing';
    public const MALFORMED = 'malformed';
    public const EXPIRED = 'expired';
    public const INVALID = 'invalid';
    public const REPLAYED = 'replayed';

    private function __construct(
        public readonly bool $verified,
        public readonly ?string $reason = null,
    ) {
    }

    public static function passed(): self
    {
        return new self(true);
    }

    public static function failed(string $reason): self
    {
        return new self(false, $reason);
    }

    /**
     * Translation key for the failure reason, e.g. "altcha::validation.expired".
     */
    public function messageKey(): string
    {
        return 'altcha::validation.'.($this->reason ?? self::INVALID);
    }
}
