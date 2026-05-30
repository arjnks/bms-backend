<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Admin\OverviewController;
use App\Http\Controllers\Api\V1\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Api\V1\Admin\BillController as AdminBillController;
use App\Http\Controllers\Api\V1\Admin\UserApprovalController;
use App\Http\Controllers\Api\V1\Admin\ReminderRuleController;
use App\Http\Controllers\Api\V1\Admin\ReportController;
use App\Http\Controllers\Api\V1\Customer\BillController as CustomerBillController;
use App\Http\Controllers\Api\V1\Customer\PreferenceController as CustomerPreferenceController;
use App\Http\Controllers\Api\V1\Customer\ExternalBillController;
use App\Http\Controllers\Api\V1\Admin\SyncController;

Route::prefix('v1')->group(function () {
    // Auth Routes
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    
    Route::get('/cleanup-dummies', function() {
        $dummyUsers = \App\Models\User::where('role', 'customer')->where('email', 'not like', 'customer_%')->pluck('id');
        \App\Models\Bill::whereIn('user_id', $dummyUsers)->delete();
        \App\Models\ReminderLog::whereIn('user_id', $dummyUsers)->delete();
        $count = \App\Models\User::whereIn('id', $dummyUsers)->delete();
        return ['deleted' => $count];
    });

    // Signed route for downloading generated manual bills
    Route::get('/customer/bills/{id}/stream', [CustomerBillController::class, 'stream'])
        ->name('bills.download.stream')
        ->middleware('signed');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Admin Routes
        Route::prefix('admin')->middleware('role:admin')->group(function () {
            Route::get('/overview', [OverviewController::class, 'index']);
            
            // Customers
            Route::get('/customers', [AdminCustomerController::class, 'index']);
            Route::post('/customers', [AdminCustomerController::class, 'store']);
            Route::get('/customers/{id}', [AdminCustomerController::class, 'show']);
            Route::patch('/customers/{id}', [AdminCustomerController::class, 'update']);
            Route::delete('/customers/{id}', [AdminCustomerController::class, 'destroy']);
            Route::post('/customers/{id}/remind', [AdminCustomerController::class, 'remind']);
            Route::post('/remind/bulk', [AdminCustomerController::class, 'bulkRemind']);
            
            // Customer External Bills
            Route::get('/customers/{id}/external-bills', [AdminCustomerController::class, 'externalBills']);
            Route::get('/customers/{id}/external-bills/{billno}', [AdminCustomerController::class, 'externalBillDetails']);
            Route::get('/customers/{id}/external-bills/{billno}/download', [AdminCustomerController::class, 'downloadExternalBill']);
            
            // Security Logs
            Route::get('/login-logs', [\App\Http\Controllers\Api\V1\Admin\LoginLogController::class, 'index']);
            
            // Bills
            Route::get('/bills', [AdminBillController::class, 'index']);
            Route::post('/bills', [AdminBillController::class, 'store']);
            Route::post('/bills/{id}/mark-paid', [AdminBillController::class, 'markPaid']);
            Route::post('/bills/{id}/verify-payment', [AdminBillController::class, 'verifyPayment']);
            Route::post('/bills/{id}/reject-payment', [AdminBillController::class, 'rejectPayment']);
            
            // Users
            Route::get('/users/pending', [UserApprovalController::class, 'pending']);
            Route::post('/users/{id}/approve', [UserApprovalController::class, 'approve']);
            Route::post('/users/{id}/reject', [UserApprovalController::class, 'reject']);
            
            // Reminder Rules
            Route::get('/reminder-rules', [ReminderRuleController::class, 'index']);
            Route::post('/reminder-rules', [ReminderRuleController::class, 'store']);
            Route::patch('/reminder-rules/{id}', [ReminderRuleController::class, 'update']);
            Route::delete('/reminder-rules/{id}', [ReminderRuleController::class, 'destroy']);
            
            // Reports
            Route::get('/reports/aging', [ReportController::class, 'aging']);
            Route::get('/reports/collections', [ReportController::class, 'collections']);

            // Sync
            Route::get('/sync/status', [SyncController::class, 'status']);
            Route::post('/sync/customers', [SyncController::class, 'syncCustomers']);
        });

        // Customer Routes
        Route::prefix('customer')->middleware('role:customer,marketing_company')->group(function () {
            Route::get('/bills', [CustomerBillController::class, 'index']);
            Route::get('/bills/{id}', [CustomerBillController::class, 'show']);
            Route::get('/bills/{id}/download', [CustomerBillController::class, 'download']);
            Route::post('/bills/{id}/submit-payment', [CustomerBillController::class, 'submitPayment']);
            // Customer self-service preferences
            Route::get('/preferences', [CustomerPreferenceController::class, 'show']);
            Route::patch('/preferences', [CustomerPreferenceController::class, 'update']);
            // Live bills from billing system
            Route::get('/live-bills', [ExternalBillController::class, 'index']);
            Route::get('/live-bills/{billno}', [ExternalBillController::class, 'show']);
            Route::get('/live-bills/{billno}/download', [ExternalBillController::class, 'download']);
            // Keep old routes for backward compat
            Route::get('/external-bills', [ExternalBillController::class, 'index']);
            Route::get('/external-bills/{billno}', [ExternalBillController::class, 'show']);
            Route::get('/external-bills/{billno}/download', [ExternalBillController::class, 'download']);
        });
    });
});

Route::get('/setup-seed', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        return response()->json(['message' => 'Database seeded successfully. You can now login.']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});

Route::get('/debug-logs', function() { return file_exists(storage_path('logs/laravel.log')) ? file_get_contents(storage_path('logs/laravel.log')) : 'No log'; });

Route::get('/dump-bill/{id}', function($id) { $b = \App\Models\Bill::find($id); return $b ? $b : ['error' => 'not found']; });
Route::get('/cleanup-dummies', function() {
    $dummyUsers = \App\Models\User::where('role', 'customer')->where('email', 'not like', 'customer_%')->pluck('id');
    \App\Models\Bill::whereIn('user_id', $dummyUsers)->delete();
    \App\Models\ReminderLog::whereIn('user_id', $dummyUsers)->delete();
    $count = \App\Models\User::whereIn('id', $dummyUsers)->delete();
    return ['deleted' => $count];
});
