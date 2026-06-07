<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DailyEntryController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AppSettingController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::get('health', fn() => response()->json(['success' => true]));

    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::apiResource('users', UserController::class)->except(['create', 'edit']);
        Route::apiResource('customers', CustomerController::class)->except(['create', 'edit']);
        Route::apiResource('expense-categories', ExpenseCategoryController::class)->except(['create', 'edit']);
        Route::apiResource('expenses', ExpenseController::class)->except(['create', 'edit']);
        Route::apiResource('loans', LoanController::class)->except(['create', 'edit']);
        Route::get('daily-entries', [DailyEntryController::class, 'index']);
        Route::get('daily-entries/by-loan', [DailyEntryController::class, 'byLoan']);
        Route::get('daily-entries/summary', [DailyEntryController::class, 'summary']);
        Route::post('daily-entries/bulk', [DailyEntryController::class, 'bulk']);
        Route::put('daily-entries/{id}', [DailyEntryController::class, 'update']);
        Route::delete('daily-entries/{id}', [DailyEntryController::class, 'destroy']);
        Route::get('ledger', [LedgerController::class, 'index']);
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('reports/collections', [ReportsController::class, 'collections']);
        Route::get('reports/loans', [ReportsController::class, 'loans']);
        Route::get('settings', [AppSettingController::class, 'index']);
        Route::put('settings', [AppSettingController::class, 'update']);
    });

    Route::middleware(['auth:sanctum', 'role:super_admin'])->group(function () {
        Route::apiResource('books', BookController::class)->only(['index', 'store', 'update']);
    });
});
