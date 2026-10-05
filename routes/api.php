<?php

use App\Http\Controllers\Api\IntegrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/webhooks/procurement', [IntegrationController::class, 'webhook'])
        ->name('api.v1.procurement.webhook');

    Route::post('/approver/email-decision', [IntegrationController::class, 'emailDecision'])
        ->middleware('throttle:60,1')
        ->name('api.v1.approver.email-decision');

    Route::post('/requester/email-clarification-response', [IntegrationController::class, 'clarificationResponse'])
        ->middleware('throttle:60,1')
        ->name('api.v1.requester.email-clarification-response');

    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    })->name('api.v1.health');
});
