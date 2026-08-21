<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Others\CancelUpgrade\Controllers\CancelUpgradeController;

Route::group(['middleware' => ['web', 'auth']], function () {
    Route::post('/cancel-upgrade/{service}', [CancelUpgradeController::class, 'cancel'])
        ->name('services.cancel-upgrade')
        ->middleware('can:view,service');
});
