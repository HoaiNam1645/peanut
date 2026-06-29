<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuyLabelController;
use App\Http\Controllers\BuyLabelWebhookController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MetadataController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderFulfillStatusController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\PartnerAppController;
use App\Http\Controllers\PartnerStoreController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StockAuditLogController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\TierController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/user', [Controller::class, 'index'])->name('user');

// Broadcasting authentication
Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
    $user = $request->user('jwt');

    if (! $user) {
        return response()->json(['error' => 'Unauthenticated'], 401);
    }

    $request->setUserResolver(function () use ($user) {
        return $user;
    });

    return \Illuminate\Support\Facades\Broadcast::auth($request);
})->middleware('jwt.auth');

// Telegram/Dropbox token management (Public endpoints with key authentication)
Route::group(['prefix' => 'telegram'], function () {
    Route::any('/getToken', [TelegramController::class, 'getToken']);
    Route::any('/resetToken', [TelegramController::class, 'resetToken']);
    // Webhook nhận callback từ inline button (verify qua ?secret=XXX)
    Route::post('/webhook', [\App\Http\Controllers\TelegramWebhookController::class, 'handle']);
});

Route::group(['prefix' => 'auth'], function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me'])->middleware('jwt.auth');
});

Route::group(['prefix' => 'users', 'middleware' => 'jwt.auth'], function () {
    Route::get('/roles', [UserController::class, 'getRoles'])->middleware('permission:users.view'); // Must be before /{id}
    Route::post('', [UserController::class, 'register'])->middleware('permission:users.create');
    Route::get('/', [UserController::class, 'index'])->middleware('permission:users.view');
    Route::get('/{id}', [UserController::class, 'show'])->middleware('permission:users.view');
    Route::put('/{id}', [UserController::class, 'update'])->middleware('permission:users.update');
    Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('permission:users.delete');
    Route::post('/{id}/add-fund', [\App\Http\Controllers\TransactionController::class, 'addFundToUser'])->middleware('permission:transactions.add_fund');
});

// Orders updateLabel
Route::post('/update-label', [OrderController::class, 'updateLabel']);
// Orders API
Route::post('/orders/create', [OrderController::class, 'createOrder']);
Route::post('/orders/seller-cancel', [\App\Http\Controllers\SellerCancelOrderController::class, 'sellerCancel']);

// Tumbler Orders API (public endpoints)
Route::post('/orders/tumbler/label-ship', [OrderController::class, 'createOrderTumblerLabelShip']);
Route::post('/orders/tumbler/seller-ship', [OrderController::class, 'createOrderTumblerSellerShip']);
Route::post('/orders/export-urls', [OrderController::class, 'exportOrderUrls']); // Export URLs for specific orders

// Order detail - supports both JWT auth and admin api_key
Route::get('/orders/{id}', [OrderController::class, 'getOrderById'])->where('id', '[0-9]+')->middleware('optional.api.key');
// Process order
Route::get('/orders/process-order', [OrderController::class, 'getProcessOrderStatus']);

Route::group(['prefix' => 'orders', 'middleware' => 'jwt.auth'], function () {
    Route::get('/', [OrderController::class, 'getOrders'])->middleware('permission:orders.view');
    Route::get('/ids', [OrderController::class, 'getOrderIds'])->middleware('permission:orders.export_ids');
    Route::put('/update', [OrderController::class, 'updateOrder'])->middleware('permission:orders.update');
    Route::get('/fulfill-statuses', [OrderFulfillStatusController::class, 'getFulfillStatuses'])->middleware('permission:orders.view');
    Route::get('/embroidery-types', [OrderController::class, 'getEmbroideryTypes']);
    Route::put('/change-fulfill-status', [OrderFulfillStatusController::class, 'changeFulfillStatus'])->middleware('permission:orders.change_status');
    Route::put('/batch-change-fulfill-status', [OrderFulfillStatusController::class, 'batchChangeFulfillStatus'])->middleware('permission:orders.change_status');
    Route::post('/cancel-refund-shipping', [\App\Http\Controllers\CancelRefundShippingController::class, 'cancelWithShippingRefund'])->middleware('permission:orders.cancel');
    Route::put('/change-status-items', [OrderItemController::class, 'changeItemStatus'])->middleware('permission:orders.change_status');
    Route::put('/qc-reject', [OrderItemController::class, 'qcRejectItem'])->middleware('permission:orders.qc_reject');
    Route::post('/post-label', [OrderController::class, 'postLabel'])->middleware('permission:orders.update');
    Route::get('/update-label', [OrderController::class, 'updateLabel'])->middleware('permission:orders.update');
    // NOTE: /orders/track/{orderId} moved OUT of this auth group → public view (see below).
    Route::get('/videos', [OrderController::class, 'getAllVideos'])->middleware('permission:orders.view_videos');
    Route::get('/videos/{orderItemId}', [OrderController::class, 'getVideos'])->middleware('permission:orders.view_videos');
    Route::get('/{id}/timeline', [OrderController::class, 'getOrderTimeline'])->middleware('permission:orders.view.detail');
    Route::get('/{id}/qr-codes', [OrderController::class, 'getOrderQRCodes'])->middleware('permission:orders.view.detail');
    Route::put('/remake/file', [OrderController::class, 'remakeFile'])->middleware('permission:orders.remake');
    Route::put('/remake/qr', [OrderController::class, 'remakeQr'])->middleware('permission:orders.remake');
    Route::post('/batch-remake-qr', [OrderController::class, 'batchRemakeQr'])->middleware('permission:orders.batch_remake');
    Route::post('/batch-remake-des', [OrderController::class, 'batchRemakeDes'])->middleware('permission:orders.batch_remake');
    Route::post('/batch-convert-label', [OrderController::class, 'batchConvertLabel'])->middleware('permission:orders.batch_remake');
    Route::post('/batch-recalculate-pricing', [OrderController::class, 'batchRecalculatePricing'])->middleware('permission:orders.update');
    Route::post('/upload-video', [OrderController::class, 'uploadVideo'])->middleware('permission:orders.upload_video');
    Route::post('/upload/file', [\App\Http\Controllers\UploadController::class, 'uploadFile'])->middleware('permission:orders.update');

    // Admin-adjustable daily order creation limit (base floor + today's extra)
    Route::get('/config/daily-limit', [OrderController::class, 'getDailyLimit'])->middleware('permission:orders.view');
    Route::put('/config/daily-limit', [OrderController::class, 'updateDailyLimit'])->middleware('permission:orders.update');
});

// Proxy endpoint for printing shipping labels (avoid CORS)
Route::get('/proxy/shipping-label', [OrderController::class, 'proxyShippingLabel']);
// Proxy endpoint for downloading QR codes and images (avoid CORS)
Route::get('/proxy/download', [OrderController::class, 'proxyDownload']);

// Public order tracking (VIEW ONLY — no auth, so workshop can scan QR & view without login).
// Stage mutations (PUT /orders/change-status-items) still require auth + permission.
Route::get('/orders/track/{orderId}', [OrderController::class, 'trackOrder']);

// Fulfillment Priorities API
Route::group(['prefix' => 'fulfillment-priorities', 'middleware' => 'jwt.auth'], function () {
    Route::get('/', [\App\Http\Controllers\FulfillmentPriorityController::class, 'getAll'])->middleware('permission:fulfillment.view');
    Route::get('/tier', [\App\Http\Controllers\FulfillmentPriorityController::class, 'getForTier'])->middleware('permission:fulfillment.view');
    Route::get('/price', [\App\Http\Controllers\FulfillmentPriorityController::class, 'getPrice'])->middleware('permission:fulfillment.view');
    Route::put('/', [\App\Http\Controllers\FulfillmentPriorityController::class, 'update'])->middleware('permission:fulfillment.manage');
});

// Tier-specific fulfillment priority management (inside /tiers routes pattern)
Route::group(['prefix' => 'tiers', 'middleware' => ['jwt.auth', 'permission:tiers.manage']], function () {
    Route::get('/{tierId}/priority-fee', [\App\Http\Controllers\FulfillmentPriorityController::class, 'getByTierId']);
    Route::post('/{tierId}/priority-fee', [\App\Http\Controllers\FulfillmentPriorityController::class, 'add']);
    Route::put('/{tierId}/priority-fee/{id}', [\App\Http\Controllers\FulfillmentPriorityController::class, 'updateSingle']);
    Route::delete('/{tierId}/priority-fee/{id}', [\App\Http\Controllers\FulfillmentPriorityController::class, 'delete']);
});

// Stores API (requires authentication)
Route::group(['prefix' => 'stores', 'middleware' => 'jwt.auth'], function () {
    Route::get('/', [StoreController::class, 'index'])->middleware('permission:stores.view');
    Route::get('/list', [StoreController::class, 'getStoresList'])->middleware('permission:stores.view');
    Route::get('/users', [StoreController::class, 'getUsers'])->middleware('permission:stores.view');
    Route::post('/', [StoreController::class, 'store'])->middleware('permission:stores.create');
    Route::put('/{id}', [StoreController::class, 'update'])->middleware('permission:stores.update');
    Route::get('/{id}', [StoreController::class, 'show'])->middleware('permission:stores.view');
});

Route::group(['prefix' => 'partner-apps', 'middleware' => 'jwt.auth'], function () {
    Route::get('/', [PartnerAppController::class, 'index']);
    Route::post('/', [PartnerAppController::class, 'store'])->middleware('permission:permissions.manage');
    Route::put('/{id}', [PartnerAppController::class, 'update'])->middleware('permission:permissions.manage');
});

Route::group(['prefix' => 'partner-stores', 'middleware' => 'jwt.auth'], function () {
    Route::get('/', [PartnerStoreController::class, 'index'])->middleware('permission:stores.view');
    Route::get('/users', [PartnerStoreController::class, 'users'])->middleware('permission:stores.view');
    Route::post('/', [PartnerStoreController::class, 'store'])->middleware('permission:stores.create');
    Route::put('/{id}', [PartnerStoreController::class, 'update'])->middleware('permission:stores.update');
});

Route::get('/styles', [ProductController::class, 'getStyles']);
Route::get('/colors', [ProductController::class, 'getColors']);
Route::get('/sizes', [ProductController::class, 'getSizes']);
Route::get('/brands', [ProductController::class, 'getBrands']);
Route::get('/products-simple', [ProductController::class, 'getProductsSimple']);
Route::get('/all-variants', [ProductController::class, 'getAllVariantsSimple']);
Route::get('/embroidery-types', [TierController::class, 'getEmbroideryTypes']);

// Public catalog (no auth) — powers the standalone storefront. These reuse the
// existing catalog query methods, which do NOT scope by user/seller.
Route::get('/catalog/products', [ProductController::class, 'getProductsWithVariants']);
Route::get('/catalog/products/{id}', [ProductController::class, 'getProductDetail'])->where('id', '[0-9]+');
Route::get('/catalog/filter-options', [ProductController::class, 'getFilterOptions']);

// Print Product APIs (for Tumbler, etc.)
Route::get('/print/styles', [ProductController::class, 'getPrintStyles']);
Route::get('/print/colors', [ProductController::class, 'getPrintColors']);
Route::get('/print/sizes', [ProductController::class, 'getPrintSizes']);
Route::get('/print/all-variants', [ProductController::class, 'getAllPrintVariantsSimple']);

// Metadata APIs
Route::get('/metadata/embroidery-types', [MetadataController::class, 'getEmbroideryTypes']);
Route::get('/metadata/fulfillment-priorities', [MetadataController::class, 'getFulfillmentPriorities']);
Route::get('/metadata/shipping-methods', [MetadataController::class, 'getShippingMethods']);

// Products API (requires authentication)
Route::group(['prefix' => 'products', 'middleware' => 'jwt.auth'], function () {
    Route::get('/', [ProductController::class, 'index'])->middleware('permission:products.view');
    Route::get('/print', [ProductController::class, 'getPrintProducts'])->middleware('permission:products.view');
    Route::post('/', [ProductController::class, 'store'])->middleware('permission:products.create');
    Route::get('/filter-options', [ProductController::class, 'getFilterOptions'])->middleware('permission:products.view');
    Route::get('/metadata', [ProductController::class, 'getMetadata'])->middleware('permission:products.view');
    Route::get('/variants', [ProductController::class, 'getVariants'])->middleware('permission:products.variants');
    Route::put('/variants/{id}', [ProductController::class, 'updateVariant'])->middleware('permission:products.variants');
    Route::put('/variants/{variantId}/pricing', [ProductController::class, 'updateVariantPricing'])->middleware('permission:products.variants');
    Route::get('/variants/{variantId}', [ProductController::class, 'getVariantById'])->middleware('permission:products.variants');
    Route::get('/with-variants', [ProductController::class, 'getProductsWithVariants'])->middleware('permission:products.view');
    Route::post('/updatestock', [ProductController::class, 'updateStock'])->middleware('permission:stock.update');
    // Import CSV - phải đặt trước /{id}
    Route::get('/import/template', [ProductController::class, 'downloadImportTemplate'])->middleware('permission:products.import');
    Route::get('/import/export', [ProductController::class, 'exportImportData'])->middleware('permission:products.import');
    Route::post('/import/preview', [ProductController::class, 'previewImport'])->middleware('permission:products.import');
    Route::post('/import', [ProductController::class, 'importCSV'])->middleware('permission:products.import');
    // Dynamic routes - phải đặt cuối
    Route::put('/{id}', [ProductController::class, 'updateProduct'])->middleware('permission:products.update');
    Route::get('/{id}', [ProductController::class, 'getProductDetail'])->middleware('permission:products.view');
    Route::delete('/{id}', [ProductController::class, 'deleteProduct'])->middleware('permission:products.update');
});

// Stock Management API (requires authentication and staff role)
Route::group(['prefix' => 'stock', 'middleware' => ['jwt.auth']], function () {
    Route::get('/summary', [StockController::class, 'summary'])->middleware('permission:stock.view');
    Route::get('/filter-options', [StockController::class, 'filterOptions'])->middleware('permission:stock.view');
    Route::get('/', [StockController::class, 'index'])->middleware('permission:stock.view');
    Route::put('/variants/{id}', [StockController::class, 'updateVariant'])->middleware('permission:stock.update');
    Route::get('/variants/{id}/history', [StockController::class, 'history'])->middleware('permission:stock.audit_logs');
    Route::post('/bulk-update', [StockController::class, 'bulkUpdate'])->middleware('permission:stock.update');
    Route::post('/imports', [StockController::class, 'importStock'])->middleware('permission:stock.import');
    Route::get('/exports', [StockController::class, 'exportStock'])->middleware('permission:stock.export');
});

// Stock Dashboard API
Route::group(['prefix' => 'stock/dashboard', 'middleware' => ['jwt.auth', 'permission:stock.view']], function () {
    Route::get('/summary', [\App\Http\Controllers\StockDashboardController::class, 'getSummary']);
    Route::get('/analytics', [\App\Http\Controllers\StockDashboardController::class, 'getAnalytics']);
    Route::get('/activities', [\App\Http\Controllers\StockDashboardController::class, 'getActivities']);
});

// Dashboard API
Route::group(['prefix' => 'dashboard', 'middleware' => ['jwt.auth', 'permission:dashboard.view']], function () {
    Route::get('/statistics', [DashboardController::class, 'getStatistics']);
});

// Stock Audit Logs
Route::group(['prefix' => 'stock/audit-logs', 'middleware' => ['jwt.auth', 'permission:stock.audit_logs']], function () {
    Route::get('/', [StockAuditLogController::class, 'getAuditLogs']);
    Route::get('/filter-options', [StockAuditLogController::class, 'getFilterOptions']);
    Route::get('/check-variant', [StockAuditLogController::class, 'checkVariantProductions']);
});

// Stock Shortage Report (Order-centric)
Route::group(['prefix' => 'stock/shortage', 'middleware' => ['jwt.auth', 'permission:stock.shortage']], function () {
    Route::get('/', [\App\Http\Controllers\ShortageReportController::class, 'index']);
    Route::get('/by-variant', [\App\Http\Controllers\ShortageReportController::class, 'indexByVariant']);
    Route::get('/filter-options', [\App\Http\Controllers\ShortageReportController::class, 'getFilterOptions']);
    Route::get('/export', [\App\Http\Controllers\ShortageReportController::class, 'export']);
    Route::get('/orders/{orderId}', [\App\Http\Controllers\ShortageReportController::class, 'getShortageVariants']);
});

// Reports API
Route::group(['prefix' => 'reports', 'middleware' => 'jwt.auth'], function () {
    Route::get('/staff', [\App\Http\Controllers\StaffReportController::class, 'index'])->middleware('permission:reports.staff');
    Route::get('/staff-list', [\App\Http\Controllers\StaffReportController::class, 'getStaffList'])->middleware('permission:reports.staff');
});

// Transactions/Wallet API (requires authentication)
Route::group(['prefix' => 'transactions', 'middleware' => 'jwt.auth'], function () {
    Route::get('/', [TransactionController::class, 'index'])->middleware('permission:transactions.view');
    Route::post('/add-fund', [TransactionController::class, 'addFund'])->middleware('permission:transactions.add_fund');
    Route::get('/export', [TransactionController::class, 'export'])->middleware('permission:transactions.export');
    Route::get('/sellers', [TransactionController::class, 'getSellers'])->middleware('permission:transactions.view');

    // Admin fund request approval routes
    Route::get('/pending', [TransactionController::class, 'getPendingFundRequests'])->middleware('permission:transactions.approve_funds');
    Route::post('/{id}/approve', [TransactionController::class, 'approveFundRequest'])->middleware('permission:transactions.approve_funds');
    Route::post('/{id}/reject', [TransactionController::class, 'rejectFundRequest'])->middleware('permission:transactions.approve_funds');
});

// Support Tickets API (requires authentication)
Route::group(['prefix' => 'tickets', 'middleware' => 'jwt.auth'], function () {
    Route::get('/', [SupportController::class, 'index'])->middleware('permission:tickets.view');
    Route::post('/', [SupportController::class, 'store'])->middleware('permission:tickets.create');
    Route::get('/sellers', [SupportController::class, 'getSellers'])->middleware('permission:tickets.view');
    Route::get('/supports', [SupportController::class, 'getSupports'])->middleware('permission:tickets.view');
    Route::get('/{id}', [SupportController::class, 'show'])->middleware('permission:tickets.view');
    Route::put('/{id}/status', [SupportController::class, 'updateStatus'])->middleware('permission:tickets.update_status');
    Route::post('/{id}/messages', [SupportController::class, 'sendMessage'])->middleware('permission:tickets.send_message');
});

// ShipDVX buy-label webhook (PUBLIC — provider callback; secret-verified inside controller)
Route::post('/buy-label/webhook', [BuyLabelWebhookController::class, 'handle']);

// Buy Label API (requires authentication)
Route::group(['prefix' => 'buy-label', 'middleware' => ['jwt.auth', 'permission:orders.buy_label']], function () {
    Route::post('/single', [BuyLabelController::class, 'buyLabelShipEngine']);
    Route::post('/batch', [BuyLabelController::class, 'buyAllLabel']);
    Route::post('/check-eligible', [BuyLabelController::class, 'checkEligibleOrders']);
    Route::post('/preview-prices', [BuyLabelController::class, 'previewPrices']);
    Route::get('/provider-orders', [BuyLabelController::class, 'providerOrders']);
});

// Tiers API (requires authentication)
Route::group(['prefix' => 'tiers', 'middleware' => 'jwt.auth'], function () {
    Route::get('/', [TierController::class, 'index'])->middleware('permission:tiers.view');
    Route::get('/options', [TierController::class, 'getTierOptions'])->middleware('permission:tiers.view');
    Route::post('/', [TierController::class, 'store'])->middleware('permission:tiers.manage');
    Route::put('/{id}', [TierController::class, 'update'])->middleware('permission:tiers.manage');
    Route::delete('/{id}', [TierController::class, 'destroy'])->middleware('permission:tiers.manage');

    // Extra Fee routes
    Route::post('/{tierId}/extra-fee', [TierController::class, 'addExtraFee'])->middleware('permission:tiers.manage');
    Route::put('/{tierId}/extra-fee/{id}', [TierController::class, 'updateExtraFee'])->middleware('permission:tiers.manage');
    Route::delete('/{tierId}/extra-fee/{id}', [TierController::class, 'deleteExtraFee'])->middleware('permission:tiers.manage');

    // Refund Fee routes
    Route::post('/{tierId}/refund-fee', [TierController::class, 'addRefundFee'])->middleware('permission:tiers.manage');
    Route::put('/{tierId}/refund-fee/{id}', [TierController::class, 'updateRefundFee'])->middleware('permission:tiers.manage');
    Route::delete('/{tierId}/refund-fee/{id}', [TierController::class, 'deleteRefundFee'])->middleware('permission:tiers.manage');

    // Embroidery Fee routes
    Route::post('/{tierId}/embroidery-fee', [TierController::class, 'addEmbroideryFee'])->middleware('permission:tiers.manage');
    Route::put('/{tierId}/embroidery-fee/{id}', [TierController::class, 'updateEmbroideryFee'])->middleware('permission:tiers.manage');
    Route::delete('/{tierId}/embroidery-fee/{id}', [TierController::class, 'deleteEmbroideryFee'])->middleware('permission:tiers.manage');
});

Route::group(['prefix' => 'attendances', 'middleware' => 'jwt.auth'], function () {
    Route::get('/', [AttendanceController::class, 'index'])->middleware('permission:attendance.view');
    Route::get('/logs/{userId}', [AttendanceController::class, 'getLogs'])->middleware('permission:attendance.view');
    Route::post('/logs/{userId}/complete', [AttendanceController::class, 'completeMissingLog'])->middleware('permission:attendance.import');
    Route::post('/import', [AttendanceController::class, 'import'])->middleware('permission:attendance.import');
});

Route::group(['prefix' => 'payroll', 'middleware' => 'jwt.auth'], function () {
    Route::get('/tiers', [App\Http\Controllers\PayrollController::class, 'getTiers'])->middleware('permission:payroll.view');
    Route::post('/tiers', [App\Http\Controllers\PayrollController::class, 'createTier'])->middleware('permission:payroll.manage');
    Route::put('/tiers/{id}', [App\Http\Controllers\PayrollController::class, 'updateTier'])->middleware('permission:payroll.manage');
    Route::delete('/tiers/{id}', [App\Http\Controllers\PayrollController::class, 'deleteTier'])->middleware('permission:payroll.manage');

    Route::get('/employees/{id}/salary-history', [App\Http\Controllers\PayrollController::class, 'getSalaryHistory'])->middleware('permission:payroll.view');
    Route::get('/employees/{id}/current-salary', [App\Http\Controllers\PayrollController::class, 'getCurrentSalary'])->middleware('permission:payroll.view');
    Route::get('/employees/{id}/salary-log', [App\Http\Controllers\PayrollController::class, 'getSalaryLog'])->middleware('permission:payroll.view');
    Route::post('/employees/{id}/salary', [App\Http\Controllers\PayrollController::class, 'createSalary'])->middleware('permission:payroll.manage');
    Route::put('/employees/{id}/salary', [App\Http\Controllers\PayrollController::class, 'updateSalary'])->middleware('permission:payroll.manage');

    Route::get('/adjustments', [App\Http\Controllers\PayrollController::class, 'getAdjustments'])->middleware('permission:payroll.view');
    Route::post('/adjustments', [App\Http\Controllers\PayrollController::class, 'createAdjustment'])->middleware('permission:payroll.manage');
    Route::put('/adjustments/{id}', [App\Http\Controllers\PayrollController::class, 'updateAdjustment'])->middleware('permission:payroll.manage');
    Route::delete('/adjustments/{id}', [App\Http\Controllers\PayrollController::class, 'deleteAdjustment'])->middleware('permission:payroll.manage');

    Route::get('/report', [App\Http\Controllers\PayrollController::class, 'getPayrollReport'])->middleware('permission:payroll.view');

    Route::post('/net-salary', [App\Http\Controllers\PayrollController::class, 'updateNetSalary'])->middleware('permission:payroll.manage');
});

// Permissions API (requires admin/HR or explicit permission)
Route::group(['prefix' => 'permissions', 'middleware' => 'jwt.auth'], function () {
    Route::get('/', [\App\Http\Controllers\PermissionController::class, 'getAll'])->middleware('permission:permissions.view');
    Route::get('/matrix', [\App\Http\Controllers\PermissionController::class, 'getRolesWithPermissions'])->middleware('permission:permissions.view');
    Route::post('/', [\App\Http\Controllers\PermissionController::class, 'store'])->middleware('permission:permissions.manage');
    Route::put('/{id}', [\App\Http\Controllers\PermissionController::class, 'update'])->middleware('permission:permissions.manage');
    Route::delete('/{id}', [\App\Http\Controllers\PermissionController::class, 'destroy'])->middleware('permission:permissions.manage');
    Route::put('/roles/{roleId}', [\App\Http\Controllers\PermissionController::class, 'updateRolePermissions'])->middleware('permission:permissions.manage');
    Route::post('/roles', [\App\Http\Controllers\PermissionController::class, 'storeRole'])->middleware('permission:permissions.manage');
    Route::put('/roles/{id}/info', [\App\Http\Controllers\PermissionController::class, 'updateRole'])->middleware('permission:permissions.manage');
    Route::delete('/roles/{id}', [\App\Http\Controllers\PermissionController::class, 'destroyRole'])->middleware('permission:permissions.manage');
    Route::post('/seed', [\App\Http\Controllers\PermissionController::class, 'seedFromRoutes'])->middleware('permission:permissions.manage');
});
