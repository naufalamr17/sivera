<?php

use App\Http\Controllers\Api\V1\EventController;
use App\Http\Middleware\VerifySiveraSignature;
use Illuminate\Support\Facades\Route;

Route::post('/v1/events', [EventController::class, 'store'])
    ->middleware(VerifySiveraSignature::class)
    ->name('api.events.store');
