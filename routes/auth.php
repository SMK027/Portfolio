<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    // Bots : connexion par code d'application
    Route::get('login/bot', [\App\Http\Controllers\Auth\BotLoginController::class, 'create'])->name('login.bot');
    Route::post('login/bot', [\App\Http\Controllers\Auth\BotLoginController::class, 'store']);

    // L'inscription publique est désactivée : les comptes sont créés depuis l'administration.
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Second facteur (mot de passe déjà vérifié, session pas encore ouverte)
    Route::controller(\App\Http\Controllers\Auth\TwoFactorChallengeController::class)->prefix('double-authentification')->group(function () {
        Route::get('/', 'create')->name('two-factor.challenge');
        Route::post('/', 'store')->middleware('throttle:10,1');
        Route::post('/cle/options', 'keyOptions')->middleware('throttle:10,1')->name('two-factor.key-options');
        Route::post('/cle', 'verifyKey')->middleware('throttle:10,1')->name('two-factor.key');
    });

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
