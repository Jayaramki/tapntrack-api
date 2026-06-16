<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DailyEntryController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\LineController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\AppSettingController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', fn() => response()->json(['success' => true]));

    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::get('security-question', [AuthController::class, 'getSecurityQuestion']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);

        Route::middleware('auth:api')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
        });
    });

    Route::middleware(['auth:api'])->group(function () {
        Route::get('users', [UserController::class, 'index']);
        Route::post('users', [UserController::class, 'store']);
        Route::get('users/{id}', [UserController::class, 'show']);
        Route::put('users/{id}', [UserController::class, 'update']);
        Route::patch('users/{id}/toggle-status', [UserController::class, 'toggleStatus']);
        Route::delete('users/{id}', [UserController::class, 'destroy']);

        Route::get('customers', [CustomerController::class, 'index']);
        Route::post('customers', [CustomerController::class, 'store']);
        Route::get('customers/{id}', [CustomerController::class, 'show']);
        Route::put('customers/{id}', [CustomerController::class, 'update']);
        Route::patch('customers/{id}/toggle-status', [CustomerController::class, 'toggleStatus']);
        Route::get('expense-categories', [ExpenseCategoryController::class, 'index']);
        Route::post('expense-categories', [ExpenseCategoryController::class, 'store']);
        Route::put('expense-categories/{id}', [ExpenseCategoryController::class, 'update']);
        Route::delete('expense-categories/{id}', [ExpenseCategoryController::class, 'destroy']);

        Route::get('expenses', [ExpenseController::class, 'index']);
        Route::post('expenses', [ExpenseController::class, 'store']);
        Route::get('expenses/{id}', [ExpenseController::class, 'show']);
        Route::put('expenses/{id}', [ExpenseController::class, 'update']);
        Route::patch('expenses/{id}/toggle-status', [ExpenseController::class, 'toggleStatus']);

        Route::get('lines', [LineController::class, 'index']);
        Route::post('lines', [LineController::class, 'store']);
        Route::put('lines/{id}', [LineController::class, 'update']);
        Route::delete('lines/{id}', [LineController::class, 'destroy']);
        Route::get('loans', [LoanController::class, 'index']);
        Route::get('loans/deleted', [LoanController::class, 'deleted']);
        Route::get('loans/archived', [LoanController::class, 'archived']);
        Route::get('loans/pending', [LoanController::class, 'pending']);
        Route::get('loans/{id}', [LoanController::class, 'show']);
        Route::post('loans', [LoanController::class, 'store']);
        Route::put('loans/{id}', [LoanController::class, 'update']);
        Route::delete('loans/{id}', [LoanController::class, 'destroy']);
        Route::patch('loans/{id}/restore', [LoanController::class, 'restore']);
        Route::patch('loans/{id}/archive', [LoanController::class, 'archive']);
        Route::patch('loans/{id}/unarchive', [LoanController::class, 'unarchive']);
        Route::delete('loans/{id}/permanent', [LoanController::class, 'permanentDelete']);
        Route::delete('loans/{id}/force', [LoanController::class, 'hardDelete']);
        Route::get('daily-entries', [DailyEntryController::class, 'index']);
        Route::get('daily-entries/by-loan', [DailyEntryController::class, 'byLoan']);
        Route::get('daily-entries/summary', [DailyEntryController::class, 'summary']);
        Route::post('daily-entries/bulk', [DailyEntryController::class, 'bulk']);
        Route::post('daily-entries', [DailyEntryController::class, 'store']);
        Route::put('daily-entries/{id}', [DailyEntryController::class, 'update']);
        Route::delete('daily-entries/{id}', [DailyEntryController::class, 'destroy']);
        Route::get('ledger', [LedgerController::class, 'index']);
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('reports/collections', [ReportsController::class, 'collections']);
        Route::get('reports/loans', [ReportsController::class, 'loans']);
        Route::get('settings', [AppSettingController::class, 'index']);
        Route::put('settings', [AppSettingController::class, 'update']);
    });

    Route::middleware(['auth:api', 'role:super_admin'])->group(function () {
        Route::get('books', [BookController::class, 'index']);
        Route::post('books', [BookController::class, 'store']);
        Route::get('books/{id}', [BookController::class, 'show']);
        Route::put('books/{id}', [BookController::class, 'update']);
        Route::patch('books/{id}/toggle-active', [BookController::class, 'toggleActive']);
        Route::delete('books/{id}', [BookController::class, 'destroy']);
    });
});
