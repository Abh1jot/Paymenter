<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\DiscordSuite\Http\Controllers\DiscordLinkedRolesController;
use Paymenter\Extensions\Others\DiscordSuite\Http\Controllers\DiscordOAuthController;
use Paymenter\Extensions\Others\DiscordSuite\Livewire\Account\DiscordSettings;

Route::group(['middleware' => ['web', 'auth']], function () {
    Route::get('/discord-suite/oauth/redirect', [DiscordOAuthController::class, 'redirect'])->name('discord-suite.oauth.redirect');
    Route::get('/discord-suite/oauth/callback', [DiscordOAuthController::class, 'callback'])->name('discord-suite.oauth.callback');
    Route::post('/discord-suite/unlink', [DiscordOAuthController::class, 'unlink'])->name('discord-suite.unlink');

    Route::get('/discord-suite/linked-roles/oauth', [DiscordLinkedRolesController::class, 'redirect'])->name('discord-suite.linked-roles.redirect');
    Route::get('/discord-suite/linked-roles/callback', [DiscordLinkedRolesController::class, 'callback'])->name('discord-suite.linked-roles.callback');

    Route::get('/account/discord', DiscordSettings::class)->name('discord-suite.account.settings');
});
