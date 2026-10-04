<?php

namespace Danielm\LaravelSimpleAltcha\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Danielm\LaravelSimpleAltcha\AltchaManager;

class ChallengeController
{
    public function __invoke(AltchaManager $altcha): JsonResponse
    {
        return response()
            ->json($altcha->createChallenge()->toArray())
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
