<?php

use Illuminate\Support\Facades\Route;
use Danielm\LaravelSimpleAltcha\Http\Controllers\ChallengeController;

Route::get(config('altcha.route.path', 'altcha/challenge'), ChallengeController::class)
    ->middleware(config('altcha.route.middleware', []))
    ->name(config('altcha.route.name', 'altcha.challenge'));
