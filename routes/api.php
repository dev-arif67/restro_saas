<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\KitchenController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\PlatformSettingController;
use App\Http\Controllers\Api\PosOrderController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettlementController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VatReportController;
use App\Http\Controllers\Api\VoucherController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes (No Authentication)
|--------------------------------------------------------------------------
*/

// Authentication
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Platform branding (public, no auth)
Route::get('platform/branding', [PlatformSettingController::class, 'branding']);

// Public subscription plans (for registration page)
Route::get('plans', [PlanController::class, 'index']);

// Contact / Enquiry (public)
Route::post('contact', [ContactController::class, 'store']);

// Customer-facing (public, no auth required)
Route::prefix('customer')->group(function () {
    Route::get('restaurant/{tenant}', [CustomerController::class, 'restaurant']);
    Route::get('restaurant/{tenant}/menu', [CustomerController::class, 'menu']);
    Route::get('restaurant/{tenant}/table/{table}', [CustomerController::class, 'table']);

    // Place order (with WiFi validation)
    Route::post('restaurant/{tenant}/order', [OrderController::class, 'store'])
        ->middleware('wifi.validate');

    // Voucher validation
    Route::post('voucher/validate', [VoucherController::class, 'validate']);

    // Track order
    Route::get('order/track/{orderNumber}', [OrderController::class, 'trackOrder']);

    // Invoice (public)
    Route::get('order/{orderNumber}/invoice', [OrderController::class, 'invoice']);
});

// SSLCommerz Payment Routes (public, callbacks from gateway)
Route::prefix('payment/sslcommerz')->group(function () {
    Route::post('initiate', [PaymentController::class, 'initiate']);
    Route::post('success', [PaymentController::class, 'handleSuccess']);
    Route::post('fail', [PaymentController::class, 'handleFail']);
    Route::post('cancel', [PaymentController::class, 'handleCancel']);
    Route::post('ipn', [PaymentController::class, 'ipn']);
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:api'])->group(function () {

    // Auth management
    Route::prefix('auth')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);

    /*
    |----------------------------------------------------------------------
    | Tenant-scoped Routes (requires active tenant + subscription)
    |----------------------------------------------------------------------
    */
    Route::middleware(['tenant', 'subscription'])->group(function () {

        // Menu Items
        Route::apiResource('menu-items', MenuItemController::class);
        Route::patch('menu-items/{id}/toggle', [MenuItemController::class, 'toggleAvailability']);
        Route::post('menu-items/{id}/restore', [MenuItemController::class, 'restore']);

        // Categories
        Route::apiResource('categories', CategoryController::class);

        // Tables
        Route::apiResource('tables', TableController::class);
        Route::post('tables/transfer', [TableController::class, 'transfer']);
        Route::get('tables/{id}/qr', [TableController::class, 'generateQrCode']);
        Route::get('tables/parcel-qr', [TableController::class, 'generateParcelQr']);

        // Vouchers
        Route::apiResource('vouchers', VoucherController::class);

        // Orders (restaurant-side management)
        Route::apiResource('orders', OrderController::class)->only(['index', 'show']);
        Route::patch('orders/{id}/status', [OrderController::class, 'updateStatus']);
        Route::post('orders/{id}/cancel', [OrderController::class, 'cancel']);
        Route::post('orders/{id}/mark-paid', [OrderController::class, 'markPaid']);

        // POS Terminal (staff + admin order creation)
        Route::middleware('role:restaurant_admin,staff')->group(function () {
            Route::post('pos/orders', [PosOrderController::class, 'store']);
        });

        // Kitchen
        Route::prefix('kitchen')->group(function () {
            Route::get('orders', [KitchenController::class, 'activeOrders']);
            Route::get('orders/{status}', [KitchenController::class, 'ordersByStatus']);
            Route::post('orders/{id}/advance', [KitchenController::class, 'advanceOrder']);
            Route::get('stats', [KitchenController::class, 'stats']);
        });

        // Reports
        Route::prefix('reports')->group(function () {
            Route::get('sales', [ReportController::class, 'salesReport']);
            Route::get('vouchers', [ReportController::class, 'voucherReport']);
            Route::get('tables', [ReportController::class, 'tablePerformance']);
            Route::get('trends', [ReportController::class, 'trendReport']);
            Route::get('top-items', [ReportController::class, 'topSellingItems']);
            Route::get('revenue-comparison', [ReportController::class, 'revenueComparison']);
            Route::get('settlements', [ReportController::class, 'settlementReport']);

            // VAT Reports
            Route::get('vat/daily', [VatReportController::class, 'dailyZReport']);
            Route::get('vat/monthly', [VatReportController::class, 'monthlyVatReport']);
        });

        // Settlements
        Route::get('settlements', [SettlementController::class, 'index']);
        Route::get('settlements/{id}', [SettlementController::class, 'show']);

        // Users: restaurant admin can manage own tenant's staff/kitchen users
        Route::middleware('role:restaurant_admin')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::post('users', [UserController::class, 'store']);
            Route::get('users/{id}', [UserController::class, 'show']);
            Route::put('users/{id}', [UserController::class, 'update']);
            Route::delete('users/{id}', [UserController::class, 'destroy']);
        });

        // Restaurant Branding (restaurant admin)
        Route::middleware('role:restaurant_admin')->prefix('branding')->group(function () {
            Route::get('/', [BrandingController::class, 'show']);
            Route::post('/', [BrandingController::class, 'update']);
        });

        // Subscription status
        Route::get('subscription/current', [SubscriptionController::class, 'currentSubscription']);
        Route::post('subscription/pay', [SubscriptionController::class, 'initiatePayment']);
        Route::post('subscription/callback', [SubscriptionController::class, 'paymentCallback']);
    });

    /*
    |----------------------------------------------------------------------
    | Super Admin Routes
    |----------------------------------------------------------------------
    */
    Route::middleware('role:super_admin')->prefix('admin')->group(function () {

        // Tenant management
        Route::apiResource('tenants', TenantController::class);
        Route::get('tenants-dashboard', [TenantController::class, 'dashboard']);

        // Subscriptions
        Route::apiResource('subscriptions', SubscriptionController::class)->only(['index', 'store', 'show']);
        Route::post('subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel']);

        // User management (super admin only)
        Route::apiResource('users', UserController::class);

        // Settlements
        Route::get('all-settlements', [SettlementController::class, 'allSettlements']);
        Route::post('settlements/{id}/payment', [SettlementController::class, 'addPayment']);

        // Admin Settlements (enhanced)
        Route::prefix('settlements')->group(function () {
            Route::get('/', [\App\Http\Controllers\AdminSettlementController::class, 'index']);
            Route::get('stats', [\App\Http\Controllers\AdminSettlementController::class, 'stats']);
            Route::get('export', [\App\Http\Controllers\AdminSettlementController::class, 'export']);
            Route::get('{settlement}', [\App\Http\Controllers\AdminSettlementController::class, 'show']);
            Route::post('{settlement}/payment', [\App\Http\Controllers\AdminSettlementController::class, 'recordPayment']);
        });

        // Platform Settings / Branding
        Route::prefix('settings')->group(function () {
            Route::get('/', [PlatformSettingController::class, 'index']);
            Route::post('/', [PlatformSettingController::class, 'update']);
        });

        // Contact Enquiries (super admin)
        Route::prefix('enquiries')->group(function () {
            Route::get('/', [\App\Http\Controllers\EnquiryController::class, 'index']);
            Route::get('{enquiry}', [\App\Http\Controllers\EnquiryController::class, 'show']);
            Route::patch('{enquiry}/read', [\App\Http\Controllers\EnquiryController::class, 'markRead']);
            Route::patch('{enquiry}/status', [\App\Http\Controllers\EnquiryController::class, 'updateStatus']);
            Route::post('{enquiry}/reply', [\App\Http\Controllers\EnquiryController::class, 'reply']);
            Route::delete('{enquiry}', [\App\Http\Controllers\EnquiryController::class, 'destroy']);
        });

        // Advanced Tenant Management
        Route::get('tenants/{id}/stats', [TenantController::class, 'stats']);
        Route::post('tenants/{id}/impersonate', [TenantController::class, 'impersonate']);
        Route::post('tenants/{id}/send-email', [TenantController::class, 'sendEmail']);
        Route::post('tenants/bulk-action', [TenantController::class, 'bulkAction']);
        Route::get('tenants/export', [TenantController::class, 'export']);

        // Advanced Subscription Management
        Route::get('subscriptions/expiring-soon', [SubscriptionController::class, 'expiringSoon']);
        Route::post('subscriptions/{id}/extend', [SubscriptionController::class, 'extend']);
        Route::post('subscriptions/{tenantId}/renew', [SubscriptionController::class, 'renewManual']);

        // Subscription Plans
        Route::apiResource('plans', PlanController::class);

        // Announcements
        Route::apiResource('announcements', AnnouncementController::class);
        Route::post('announcements/{id}/send', [AnnouncementController::class, 'send']);

        // System Management
        Route::prefix('system')->group(function () {
            Route::get('health', [SystemController::class, 'health']);
            Route::get('info', [SystemController::class, 'info']);
            Route::get('queue-stats', [SystemController::class, 'queueStats']);
            Route::post('retry-failed-jobs', [SystemController::class, 'retryFailedJobs']);
            Route::post('clear-cache', [SystemController::class, 'clearCache']);
            Route::get('logs', [SystemController::class, 'logs']);
        });

        // Audit Logs
        Route::prefix('audit-logs')->group(function () {
            Route::get('/', [AuditLogController::class, 'index']);
            Route::get('actions', [AuditLogController::class, 'actions']);
            Route::get('stats', [AuditLogController::class, 'stats']);
            Route::get('export', [AuditLogController::class, 'export']);
            Route::get('{id}', [AuditLogController::class, 'show']);
        });
    });

    // Active Announcements (for tenant dashboard)
    Route::get('announcements/active', [AnnouncementController::class, 'active']);
    Route::post('announcements/{id}/read', [AnnouncementController::class, 'markAsRead']);

});
Route::get('/clear-cache', function () {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    return response()->json(['message' => 'Cache cleared']);
});
Route::get('/optimize:clear', function () {
    Artisan::call('optimize:clear');
    return response()->json(['message' => 'Optimization cache cleared']);
});

Route::get('/storage-link', function () {
    Artisan::call('storage:link');
    return response()->json(['message' => 'Storage linked']);
});

Route::get('/migrate', function () {
    Artisan::call('migrate', ['--force' => true]);
    return response()->json(['message' => 'Migrations run']);
});
