<?php

namespace Danielm\LaravelSimpleAltcha\Tests;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Danielm\LaravelSimpleAltcha\AltchaManager;
use Danielm\LaravelSimpleAltcha\Rules\ValidAltcha;
use Danielm\LaravelSimpleAltcha\VerificationResult;

class AltchaTest extends TestCase
{
    /** Solve a challenge the way the browser widget would, returning the base64 payload. */
    private function solvedPayload(?array $challengeArray = null): string
    {
        $challenge = Challenge::fromArray($challengeArray ?? $this->getJson('/altcha/challenge')->json());
        $solution = (new Altcha('test-secret'))->solveChallenge(new SolveChallengeOptions(
            algorithm: new Pbkdf2(),
            challenge: $challenge,
        ));

        $this->assertNotNull($solution, 'challenge should be solvable');

        return (new Payload($challenge, $solution))->toBase64();
    }

    public function test_challenge_endpoint_returns_signed_challenge(): void
    {
        $this->getJson('/altcha/challenge')
            ->assertOk()
            ->assertJsonStructure(['parameters' => ['algorithm', 'nonce', 'salt', 'cost'], 'signature'])
            ->assertHeader('Cache-Control');
    }

    public function test_valid_payload_verifies_once_then_is_rejected_as_replay(): void
    {
        $payload = $this->solvedPayload();
        $manager = app(AltchaManager::class);

        $this->assertTrue($manager->verify($payload)->verified);

        $second = $manager->verify($payload);
        $this->assertFalse($second->verified);
        $this->assertSame(VerificationResult::REPLAYED, $second->reason);
    }

    public function test_missing_and_malformed_payloads_fail_without_throwing(): void
    {
        $manager = app(AltchaManager::class);

        $this->assertSame(VerificationResult::MISSING, $manager->verify(null)->reason);
        $this->assertSame(VerificationResult::MISSING, $manager->verify('')->reason);
        $this->assertSame(VerificationResult::MALFORMED, $manager->verify('not-base64-json')->reason);
    }

    public function test_tampered_challenge_is_rejected(): void
    {
        $challenge = $this->getJson('/altcha/challenge')->json();
        $payload = $this->solvedPayload($challenge);

        $decoded = json_decode(base64_decode($payload), true);
        $decoded['challenge']['parameters']['cost'] = 1; // forge easier params
        $forged = base64_encode(json_encode($decoded));

        $result = app(AltchaManager::class)->verify($forged);

        $this->assertFalse($result->verified);
        $this->assertSame(VerificationResult::INVALID, $result->reason);
    }

    public function test_expired_challenge_is_rejected(): void
    {
        config()->set('altcha.challenge.expires_in', -10);
        $this->app->forgetInstance(AltchaManager::class);

        $result = app(AltchaManager::class)->verify($this->solvedPayload());

        $this->assertSame(VerificationResult::EXPIRED, $result->reason);
    }

    public function test_validation_rule(): void
    {
        $ok = Validator::make(['altcha' => $this->solvedPayload()], ['altcha' => [new ValidAltcha()]]);
        $this->assertTrue($ok->passes());

        $missing = Validator::make([], ['altcha' => [new ValidAltcha()]]);
        $this->assertTrue($missing->fails());
        $this->assertArrayHasKey('altcha', $missing->errors()->toArray());
    }

    public function test_middleware_blocks_and_allows(): void
    {
        Route::post('/_protected', fn () => response()->json(['ok' => true]))->middleware('altcha');

        $this->postJson('/_protected', [])->assertStatus(422)->assertJsonValidationErrors('altcha');

        $this->postJson('/_protected', ['altcha' => $this->solvedPayload()])->assertOk();
    }

    public function test_testing_bypass(): void
    {
        config()->set('altcha.testing_bypass', 'let-me-in');
        $this->app->forgetInstance(AltchaManager::class);

        $this->assertTrue(app(AltchaManager::class)->verify('let-me-in')->verified);
        $this->assertFalse(app(AltchaManager::class)->verify('nope')->verified);
    }
}
