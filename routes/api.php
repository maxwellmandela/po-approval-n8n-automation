<?php

use App\Http\Controllers\Api\IntegrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/webhooks/procurement', [IntegrationController::class, 'webhook'])
        ->name('api.v1.procurement.webhook');

    Route::get('/health', function () {
        return response()->json(['status' => 'ok']);
    })->name('api.v1.health');
});
