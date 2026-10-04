<?php

namespace Danielm\LaravelSimpleAltcha;

use AltchaOrg\Altcha\Algorithm\DeriveKeyInterface;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Illuminate\Contracts\Cache\Factory as CacheFactory;

class AltchaManager
{
    /**
     * @param array<string, mixed> $config the "altcha" config array
     */
    public function __construct(
        private readonly Altcha $altcha,
        private readonly DeriveKeyInterface $algorithm,
        private readonly CacheFactory $cache,
        private readonly array $config,
    ) {
    }

    /**
     * Create a signed challenge. $overrides takes the same keys as config('altcha.challenge').
     *
     * @param array<string, mixed> $overrides
     */
    public function createChallenge(array $overrides = []): Challenge
    {
        $c = array_merge($this->config['challenge'] ?? [], $overrides);
        $expiresIn = $c['expires_in'] ?? null;

        return $this->altcha->createChallenge(new CreateChallengeOptions(
            algorithm: $this->algorithm,
            cost: (int) ($c['cost'] ?? 10000),
            memoryCost: isset($c['memory_cost']) ? (int) $c['memory_cost'] : null,
            parallelism: isset($c['parallelism']) ? (int) $c['parallelism'] : null,
            expiresAt: $expiresIn !== null ? time() + (int) $expiresIn : null,
            data: $c['data'] ?? null,
        ));
    }

    /**
     * Verify the base64 payload posted by the widget. Never throws on bad
     * client input; inspect VerificationResult::$reason instead.
     */
    public function verify(mixed $payload): VerificationResult
    {
        if ($this->isTestingBypass($payload)) {
            return VerificationResult::passed();
        }

        if (! is_string($payload) || $payload === '') {
            return VerificationResult::failed(VerificationResult::MISSING);
        }

        try {
            $options = new VerifySolutionOptions(payload: $payload, algorithm: $this->algorithm);
            $result = $this->altcha->verifySolution($options);
        } catch (\InvalidArgumentException|\JsonException|\TypeError|\ValueError) {
            return VerificationResult::failed(VerificationResult::MALFORMED);
        }

        if (! $result->verified) {
            return VerificationResult::failed(
                $result->expired ? VerificationResult::EXPIRED : VerificationResult::INVALID
            );
        }

        if (($this->config['replay']['enabled'] ?? true) && ! $this->markUsed($options)) {
            return VerificationResult::failed(VerificationResult::REPLAYED);
        }

        return VerificationResult::passed();
    }

    /**
     * Atomically record the challenge as used. Keyed on the challenge's HMAC
     * signature (which covers nonce, salt and all parameters), not on the raw
     * payload string, so re-encoding the same payload can't dodge the check.
     */
    private function markUsed(VerifySolutionOptions $options): bool
    {
        $replay = $this->config['replay'] ?? [];
        $signature = (string) $options->payload->challenge->signature;

        $ttl = $replay['ttl'] ?? (($this->config['challenge']['expires_in'] ?? 3600) + 60);
        $key = ($replay['prefix'] ?? 'altcha:used:').hash('sha256', $signature);

        return $this->cache->store($replay['store'] ?? null)->add($key, true, max(1, (int) $ttl));
    }

    private function isTestingBypass(mixed $payload): bool
    {
        $bypass = $this->config['testing_bypass'] ?? null;

        return is_string($bypass)
            && $bypass !== ''
            && is_string($payload)
            && function_exists('app')
            && app()->environment('testing')
            && hash_equals($bypass, $payload);
    }
}
