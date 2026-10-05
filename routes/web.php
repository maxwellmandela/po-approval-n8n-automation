<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProcurementRequestController;
use App\Http\Controllers\ProcurementReviewController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/requests', [ProcurementRequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/create', [ProcurementRequestController::class, 'create'])->name('requests.create');
    Route::post('/requests', [ProcurementRequestController::class, 'store'])->name('requests.store');
    Route::get('/requests/{procurementRequest}', [ProcurementRequestController::class, 'show'])->name('requests.show');
    Route::post('/requests/{procurementRequest}/submit', [ProcurementRequestController::class, 'submit'])->name('requests.submit');
    Route::post('/requests/clarifications/{clarification}/respond', [ProcurementRequestController::class, 'respondToClarification'])->name('requests.clarification.respond');

    Route::get('/review-queue', [ProcurementReviewController::class, 'queue'])->name('review.queue');
    Route::get('/review/{procurementRequest}', [ProcurementReviewController::class, 'show'])->name('review.show');
    Route::post('/review/{procurementRequest}/approve', [ProcurementReviewController::class, 'approve'])->name('review.approve');
    Route::post('/review/{procurementRequest}/clarify', [ProcurementReviewController::class, 'clarify'])->name('review.clarify');
    Route::post('/review/{procurementRequest}/reject', [ProcurementReviewController::class, 'reject'])->name('review.reject');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
