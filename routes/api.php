<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\KitchenController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PlatformSettingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SettlementController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\UserController;
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
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
});

// Platform branding (public, no auth)
Route::get('platform/branding', [PlatformSettingController::class, 'branding']);

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
        });

        // Settlements
        Route::get('settlements', [SettlementController::class, 'index']);
        Route::get('settlements/{id}', [SettlementController::class, 'show']);

        // Users (restaurant staff management)
        Route::middleware('role:restaurant_admin')->group(function () {
            Route::apiResource('users', UserController::class);
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

        // Settlements
        Route::get('all-settlements', [SettlementController::class, 'allSettlements']);
        Route::post('settlements/{id}/payment', [SettlementController::class, 'addPayment']);

        // Platform Settings / Branding
        Route::prefix('settings')->group(function () {
            Route::get('/', [PlatformSettingController::class, 'index']);
            Route::post('/', [PlatformSettingController::class, 'update']);
        });
    });


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
    $target = storage_path('app/public');
    $link = public_path('storage');

    // Remove existing link/directory if it exists
    if (is_link($link)) {
        unlink($link);
    } elseif (is_dir($link)) {
        rmdir($link);
    }

    // Try symlink first, fall back to manual copy approach for cPanel
    try {
        symlink($target, $link);
        return response()->json(['message' => 'Storage symlink created', 'target' => $target, 'link' => $link]);
    } catch (\Exception $e) {
        // cPanel fallback: create directory and copy files
        if (!is_dir($link)) {
            mkdir($link, 0755, true);
        }

        // Recursively copy storage/app/public to public/storage
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($target, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $dest = $link . DIRECTORY_SEPARATOR . $iterator->getSubPathname();
            if ($item->isDir()) {
                if (!is_dir($dest)) mkdir($dest, 0755, true);
            } else {
                copy($item, $dest);
            }
        }

        return response()->json([
            'message' => 'Storage files copied (symlink not supported)',
            'target' => $target,
            'link' => $link,
            'note' => 'Files were copied instead of symlinked. Re-run after uploading new files.',
        ]);
    }
});

Route::get('/migrate', function () {
    Artisan::call('migrate', ['--force' => true]);
    return response()->json(['message' => 'Migrations run']);
});
