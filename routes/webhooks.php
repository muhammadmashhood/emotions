<?php

use App\Http\Controllers\TypeformWebhookController;
use Illuminate\Support\Facades\Route;

// No session, no CSRF: registered outside the "web" group in bootstrap/app.php.
Route::post('webhooks/typeform/{formUuid}', TypeformWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.typeform');
