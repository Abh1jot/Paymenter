<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\DiscordSuite\Http\Controllers\DiscordInteractionController;
use Paymenter\Extensions\Others\DiscordSuite\Http\Middleware\VerifyDiscordSignature;

Route::group(['prefix' => 'api/discord-suite'], function () {
    Route::post('/interactions', [DiscordInteractionController::class, 'handle'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, \App\Http\Middleware\VerifyCsrfToken::class])
        ->middleware(VerifyDiscordSignature::class)
        ->name('discord-suite.interactions');
});
