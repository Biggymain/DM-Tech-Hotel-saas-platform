<?php

use App\Http\Controllers\Api\V1\GroupRegistrationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\RevenueInsightController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Payment Webhooks (No Auth Required)
Route::post('v1/payments/webhook/{gateway}', [\App\Http\Controllers\Api\V1\PaymentWebhookController::class, 'handleWebhook']);

// Basic API routes
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register-hotel', [\App\Http\Controllers\Auth\HotelRegistrationController::class, 'register']);
        Route::post('register-group', [GroupRegistrationController::class, 'register']); // Public — no tenant scope
        Route::get('sla/dashboard', [SLADashboardController::class, 'activeTickets']);
        Route::get('sla/branch-overview', [SLADashboardController::class, 'branchOverview']);
        Route::get('sla/report', [SLADashboardController::class, 'performanceReport']);
        Route::post('login', [AuthController::class, 'login']); // Kept existing route
        Route::post('staff-pin', [AuthController::class, 'staffPinLogin']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']); // Kept existing route
        Route::post('reset-password', [AuthController::class, 'resetPassword']);

        // Staff Setup (PIN & Password Activation)
        Route::middleware('auth:sanctum')->post('staff/setup', [\App\Http\Controllers\Api\V1\AuthController::class, 'setupStaff']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [\App\Http\Controllers\Api\V1\AuthController::class, 'user']);
            Route::post('logout', [\App\Http\Controllers\Api\V1\AuthController::class, 'logout']);
        });
    });

    // Alignment Routes
    Route::get('theme', [\App\Http\Controllers\Api\V1\ThemeController::class, 'getTheme']);
    Route::post('guest/claim-room', [\App\Http\Controllers\Api\V1\GuestSessionController::class, 'claimRoom']);
    Route::post('guest/verify-pin', [\App\Http\Controllers\Api\V1\GuestSessionController::class, 'verifyPin']);

    // Organization Management (GROUP_ADMIN + SUPER_ADMIN only — no single-hotel tenant scope needed)
    Route::prefix('organization')->middleware('auth:sanctum')->group(function () {
        Route::get('/overview', [\App\Http\Controllers\Api\V1\OrganizationController::class, 'overview']);
        Route::get('/branches', [\App\Http\Controllers\Api\V1\OrganizationController::class, 'branches']);
        Route::post('/branches', [\App\Http\Controllers\Api\V1\OrganizationController::class, 'store']);
        Route::post('/branches/{id}/onboard', [\App\Http\Controllers\Api\V1\OrganizationController::class, 'onboardManager']);
        Route::put('/settings', [\App\Http\Controllers\Api\V1\OrganizationController::class, 'updateSettings']);

        // Website Creator Routes
        Route::group(['prefix' => 'website'], function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\GroupWebsiteController::class, 'show']);
            Route::put('/', [\App\Http\Controllers\Api\V1\GroupWebsiteController::class, 'update']);
            Route::put('/overrides/{hotel}', [\App\Http\Controllers\Api\V1\GroupWebsiteController::class, 'updateOverride']);
            Route::post('/upload-image', [\App\Http\Controllers\Api\V1\ImageUploadController::class, 'upload']);
        });
    });

    // ── HARDWARE INTEGRATION — DOOR LOCKS ────────────────────────────────────
    // Webhook: No auth — secured by HMAC-SHA256 Shared Secret inside the controller.
    Route::post('/integration/lock-events', [\App\Http\Controllers\Api\V1\LockEventController::class, 'receive'])
        ->middleware('throttle:60,1');
    // Admin view of lock events (auth required)
    Route::get('/integration/lock-events', [\App\Http\Controllers\Api\V1\LockEventController::class, 'index'])
        ->middleware('auth:sanctum');

    // Public Channel Webhooks — OTA push notifications
    Route::post('/channels/webhook/{channel}', [\App\Http\Controllers\Api\V1\OtaChannelController::class, 'webhook']);

    // Cloud Edge-Node Sync Ingestion Endpoint
    Route::get('/sync/status', [\App\Http\Controllers\Api\V1\SyncController::class, 'syncStatus']);
    Route::post('/sync/batch', [\App\Http\Controllers\Api\V1\SyncController::class, 'batchSync']);
    Route::post('/sync/ingest', [\App\Http\Controllers\Api\V1\CloudSyncController::class, 'ingest']);

    // ── PUBLIC BOOKING ENGINE ─────────────────────────────────────────────────
    // No auth required. Tenant is resolved from {hotel_slug} or Host header
    // via DomainTenantMiddleware. Rate-limited to prevent abuse.
    Route::prefix('booking')->middleware(['throttle:60,1'])->group(function () {
        $ctrl = \App\Http\Controllers\Api\V1\PublicBookingController::class;

        // ── STATIC ROUTES FIRST (must come before /{hotel_slug} wildcard) ────────
        // Group Website Routes
        Route::get('/group-website', [\App\Http\Controllers\Api\V1\PublicGroupWebsiteController::class, 'findFirst']); // returns first active group slug — used for portal redirect
        Route::get('/group/{group_slug}', [\App\Http\Controllers\Api\V1\PublicGroupWebsiteController::class, 'show']);
        Route::get('/group/{group_slug}/branch/{hotel_slug}', [\App\Http\Controllers\Api\V1\PublicGroupWebsiteController::class, 'branchDetails']);

        // ── WILDCARD ROUTES LAST ──────────────────────────────────────────────────
        Route::get('/{hotel_slug}',                  [$ctrl, 'show']);
        Route::get('/{hotel_slug}/availability',     [$ctrl, 'availability']);
        Route::post('/{hotel_slug}/reserve',         [$ctrl, 'reserve']);
        Route::post('/{hotel_slug}/confirm-payment', [$ctrl, 'confirmPayment']);
    });

    // Guest Portal (Public start, then protected by TenantMiddleware)
    Route::prefix('guest')->middleware(['throttle:10,1', \App\Http\Middleware\TenantMiddleware::class])->group(function () {
        Route::post('/session/start', [\App\Http\Controllers\Api\V1\GuestPortalController::class, 'startSession'])->withoutMiddleware(\App\Http\Middleware\TenantMiddleware::class);
        Route::post('/session/authenticate', [\App\Http\Controllers\Api\V1\GuestPortalController::class, 'authenticate']);
        Route::get('/dashboard', [\App\Http\Controllers\Api\V1\GuestPortalController::class, 'dashboard']);
        
        // Orders & Tracking
        Route::get('/orders/{order}', [\App\Http\Controllers\Api\V1\GuestOutletController::class, 'showOrder']);
        Route::post('/orders/{outlet}', [\App\Http\Controllers\Api\V1\GuestOutletController::class, 'storeOrder']);
        
        // Menu & Recommendations
        Route::get('/menus/{outlet}', [\App\Http\Controllers\Api\V1\GuestOutletController::class, 'menu']);
        Route::get('/menus/{outlet}/recommendations', [\App\Http\Controllers\Api\V1\GuestOutletController::class, 'recommendations']);
        
        // Service Requests
        Route::get('/requests', [\App\Http\Controllers\Api\V1\GuestRequestController::class, 'index']);
        Route::post('/requests', [\App\Http\Controllers\Api\V1\GuestRequestController::class, 'store']);
        Route::post('/service-requests', [\App\Http\Controllers\Api\V1\GuestRequestController::class, 'store']); // Alias for the enhancement requirement
        
        Route::post('/reservations/availability', [\App\Http\Controllers\Api\V1\GuestReservationController::class, 'searchAvailability']);
        Route::post('/reservations', [\App\Http\Controllers\Api\V1\GuestReservationController::class, 'store']);
    });

    Route::middleware(['auth:sanctum', \App\Http\Middleware\TenantMiddleware::class])->group(function () {
        Route::apiResource('departments', \App\Http\Controllers\Api\V1\DepartmentController::class)->middleware('role.verify:hotel.manage');
        Route::apiResource('hotels', \App\Http\Controllers\Controller::class)->only(['index'])->middleware('role.verify:hotel.manage');

        // PMS Routes
        Route::prefix('pms')->group(function () {
            // Availability
            Route::get('/availability', [\App\Http\Controllers\Api\V1\PMS\PmsAvailabilityController::class, 'index']);

            // Room Types
            Route::get('/room-types', [\App\Http\Controllers\Api\V1\PMS\PmsRoomTypeController::class, 'index']);
            Route::post('/room-types', [\App\Http\Controllers\Api\V1\PMS\PmsRoomTypeController::class, 'store']);
            Route::get('/room-types/{id}', [\App\Http\Controllers\Api\V1\PMS\PmsRoomTypeController::class, 'show']);
            Route::put('/room-types/{id}', [\App\Http\Controllers\Api\V1\PMS\PmsRoomTypeController::class, 'update']);
            Route::delete('/room-types/{id}', [\App\Http\Controllers\Api\V1\PMS\PmsRoomTypeController::class, 'destroy']);

            // Rooms
            Route::get('/rooms/map', [\App\Http\Controllers\Api\V1\PMS\PmsRoomController::class, 'roomMap'])->middleware('role.verify:pms.rooms.view');
            Route::get('/rooms', [\App\Http\Controllers\Api\V1\PMS\PmsRoomController::class, 'index'])->middleware('role.verify:pms.rooms.view');
            Route::post('/rooms', [\App\Http\Controllers\Api\V1\PMS\PmsRoomController::class, 'store'])->middleware('role.verify:pms.rooms.manage');
            Route::put('/rooms/{room}/status', [\App\Http\Controllers\Api\V1\PMS\PmsRoomController::class, 'updateStatus'])->middleware('role.verify:pms.rooms.manage');
            Route::put('/rooms/{room}/housekeeping', [\App\Http\Controllers\Api\V1\PMS\PmsRoomController::class, 'updateHousekeeping'])->middleware('role.verify:pms.rooms.manage');

            // Guests
            Route::get('/guests', [\App\Http\Controllers\Api\V1\PMS\PmsGuestController::class, 'index']);
            Route::post('/guests', [\App\Http\Controllers\Api\V1\PMS\PmsGuestController::class, 'store']);

            // Reservations
            Route::get('/reservations', [\App\Http\Controllers\Api\V1\PMS\PmsReservationController::class, 'index']);
            Route::post('/reservations', [\App\Http\Controllers\Api\V1\PMS\PmsReservationController::class, 'store']);
            Route::put('/reservations/{reservation}', [\App\Http\Controllers\Api\V1\PMS\PmsReservationController::class, 'update']);
            Route::delete('/reservations/{reservation}', [\App\Http\Controllers\Api\V1\PMS\PmsReservationController::class, 'destroy']);
            Route::put('/reservations/{reservation}/confirm', [\App\Http\Controllers\Api\V1\PMS\PmsReservationController::class, 'confirm']);
            Route::post('/reservations/{reservation}/check-in', [\App\Http\Controllers\Api\V1\PMS\PmsReservationController::class, 'checkIn']);
            Route::post('/reservations/{reservation}/check-out', [\App\Http\Controllers\Api\V1\PMS\PmsReservationController::class, 'checkOut']);
            Route::post('/reservations/{reservation}/extend', [\App\Http\Controllers\Api\V1\PMS\PmsReservationController::class, 'extend']);
            Route::get('/reservations/{reservation}/folio', [\App\Http\Controllers\Api\V1\PMS\PmsFolioController::class, 'showByReservation']);

            // Folios
            Route::get('/folios', [\App\Http\Controllers\Api\V1\PMS\PmsFolioController::class, 'index']);
            Route::post('/folios/{folio}/charge', [\App\Http\Controllers\Api\V1\PMS\PmsFolioController::class, 'postCharge']);
            Route::post('/folios/{folio}/payment', [\App\Http\Controllers\Api\V1\PMS\PmsFolioController::class, 'postPayment']);
        });

        // Housekeeping Tasks
        Route::prefix('housekeeping/tasks')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\HousekeepingTaskController::class, 'index'])->middleware('role.verify:housekeeping.tasks.view');
            Route::post('/{task}/assign', [\App\Http\Controllers\Api\V1\HousekeepingTaskController::class, 'assign'])->middleware('role.verify:housekeeping.tasks.manage');
            Route::post('/{task}/start', [\App\Http\Controllers\Api\V1\HousekeepingTaskController::class, 'start'])->middleware('role.verify:housekeeping.tasks.manage');
            Route::post('/{task}/complete', [\App\Http\Controllers\Api\V1\HousekeepingTaskController::class, 'complete'])->middleware('role.verify:housekeeping.tasks.manage');
        });

        // Maintenance Requests
        Route::prefix('maintenance/requests')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\MaintenanceRequestController::class, 'index'])->middleware('role.verify:maintenance.requests.view');
            Route::post('/', [\App\Http\Controllers\Api\V1\MaintenanceRequestController::class, 'store'])->middleware('role.verify:maintenance.requests.manage');
            Route::post('/{maintenanceRequest}/assign', [\App\Http\Controllers\Api\V1\MaintenanceRequestController::class, 'assign'])->middleware('role.verify:maintenance.requests.manage');
            Route::post('/{maintenanceRequest}/start', [\App\Http\Controllers\Api\V1\MaintenanceRequestController::class, 'start'])->middleware('role.verify:maintenance.requests.manage');
            Route::post('/{maintenanceRequest}/resolve', [\App\Http\Controllers\Api\V1\MaintenanceRequestController::class, 'resolve'])->middleware('role.verify:maintenance.requests.manage');
        });

        Route::get('/users', [\App\Http\Controllers\Api\V1\StaffController::class, 'index'])->middleware('role.verify:users.view');
        Route::post('/users', [\App\Http\Controllers\Api\V1\StaffController::class, 'store'])->middleware('role.verify:users.create');
        Route::post('/staff/toggle-duty', [\App\Http\Controllers\Api\V1\StaffController::class, 'toggleDuty']);
        Route::get('/roles', [\App\Http\Controllers\Api\V1\RoleController::class, 'index']);
        Route::apiResource('outlets', \App\Http\Controllers\Api\V1\OutletController::class);
        
        Route::prefix('guest-requests')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\GuestRequestController::class, 'index'])->middleware('role.verify:guest.requests.view');
            Route::get('/active-sessions', [\App\Http\Controllers\Api\V1\GuestPortalController::class, 'activeSessions'])->middleware('role.verify:guest.requests.view');
            Route::post('/{serviceRequest}/assign', [\App\Http\Controllers\Api\V1\GuestRequestController::class, 'assign'])->middleware('role.verify:guest.requests.manage');
            Route::post('/{serviceRequest}/complete', [\App\Http\Controllers\Api\V1\GuestRequestController::class, 'complete'])->middleware('role.verify:guest.requests.manage');
        });

        // POS Orders
        Route::middleware(['module.active:pos'])->prefix('orders')->group(function() {
            Route::get('/', [\App\Http\Controllers\Api\V1\OrderController::class, 'index'])->middleware('role.verify:orders.view');
            Route::post('/', [\App\Http\Controllers\Api\V1\OrderController::class, 'store'])->middleware('role.verify:orders.create');
            Route::get('/{order}', [\App\Http\Controllers\Api\V1\OrderController::class, 'show'])->middleware('role.verify:orders.view');
            Route::put('/{order}/status', [\App\Http\Controllers\Api\V1\OrderController::class, 'updateStatus'])->middleware('role.verify:orders.update');
            Route::delete('/{order}', [\App\Http\Controllers\Api\V1\OrderController::class, 'destroy'])->middleware('role.verify:orders.delete');
            Route::get('/kds', [\App\Http\Controllers\Api\V1\OrderController::class, 'kds'])->middleware('role.verify:kds.view');
        });

        // Menu Management
        Route::prefix('menu')->group(function() {
            Route::prefix('categories')->group(function() {
                Route::get('/', [\App\Http\Controllers\Api\V1\MenuCategoryController::class, 'index'])->middleware('role.verify:menu.view');
                Route::post('/', [\App\Http\Controllers\Api\V1\MenuCategoryController::class, 'store'])->middleware('role.verify:menu.create');
                Route::get('/{id}', [\App\Http\Controllers\Api\V1\MenuCategoryController::class, 'show'])->middleware('role.verify:menu.view');
                Route::put('/{id}', [\App\Http\Controllers\Api\V1\MenuCategoryController::class, 'update'])->middleware('role.verify:menu.update');
                Route::delete('/{id}', [\App\Http\Controllers\Api\V1\MenuCategoryController::class, 'destroy'])->middleware('role.verify:menu.delete');
            });

            Route::prefix('items')->group(function() {
                Route::get('/', [\App\Http\Controllers\Api\V1\MenuItemController::class, 'index'])->middleware('role.verify:menu.view');
                Route::post('/', [\App\Http\Controllers\Api\V1\MenuItemController::class, 'store'])->middleware('role.verify:menu.create');
                Route::get('/{id}', [\App\Http\Controllers\Api\V1\MenuItemController::class, 'show'])->middleware('role.verify:menu.view');
                Route::put('/{id}', [\App\Http\Controllers\Api\V1\MenuItemController::class, 'update'])->middleware('role.verify:menu.update');
                Route::delete('/{id}', [\App\Http\Controllers\Api\V1\MenuItemController::class, 'destroy'])->middleware('role.verify:menu.delete');
                Route::post('/{id}/duplicate', [\App\Http\Controllers\Api\V1\MenuItemController::class, 'duplicateToOutlet'])->middleware('role.verify:menu.create');
            });

            Route::prefix('modifiers')->group(function() {
                Route::get('/', [\App\Http\Controllers\Api\V1\ModifierController::class, 'index'])->middleware('role.verify:menu.view');
                Route::post('/', [\App\Http\Controllers\Api\V1\ModifierController::class, 'store'])->middleware('role.verify:menu.create');
                Route::get('/{id}', [\App\Http\Controllers\Api\V1\ModifierController::class, 'show'])->middleware('role.verify:menu.view');
                Route::put('/{id}', [\App\Http\Controllers\Api\V1\ModifierController::class, 'update'])->middleware('role.verify:menu.update');
                Route::delete('/{id}', [\App\Http\Controllers\Api\V1\ModifierController::class, 'destroy'])->middleware('role.verify:menu.delete');
            });
        });

        // Kitchen Display System (KDS)
        Route::middleware(['module.active:kitchen'])->prefix('kds')->group(function() {
            Route::apiResource('stations', \App\Http\Controllers\Api\V1\KitchenStationController::class)->middleware('role.verify:settings.manage');
            Route::get('/tickets', [\App\Http\Controllers\Api\V1\KitchenDisplayController::class, 'index'])->middleware('role.verify:kds.view');
            Route::get('/tickets/{id}', [\App\Http\Controllers\Api\V1\KitchenDisplayController::class, 'show'])->middleware('role.verify:kds.view');
            Route::put('/tickets/{id}/status', [\App\Http\Controllers\Api\V1\KitchenDisplayController::class, 'updateStatus'])->middleware('role.verify:kds.update');
            Route::put('/inventory/{id}/toggle', [\App\Http\Controllers\Api\V1\KitchenDisplayController::class, 'toggleAvailability'])->middleware('role.verify:kds.update');
            Route::post('/restock', [\App\Http\Controllers\Api\V1\KitchenDisplayController::class, 'requestRestock'])->middleware('role.verify:kds.update');
            Route::put('/items/{id}/status', [\App\Http\Controllers\Api\V1\KitchenDisplayController::class, 'updateItemStatus'])->middleware('role.verify:kds.update');

            // SLA Dashboard
            Route::get('/sla/active', [\App\Http\Controllers\Api\V1\SLADashboardController::class, 'activeTickets'])->middleware('role.verify:manager.view');
            Route::get('/sla/report', [\App\Http\Controllers\Api\V1\SLADashboardController::class, 'performanceReport'])->middleware('role.verify:manager.view');
        });

        // Inventory Management
        Route::prefix('inventory')->group(function() {
            // Suppliers
            Route::apiResource('suppliers', \App\Http\Controllers\Api\V1\SupplierController::class)->middleware('role.verify:inventory.manage');
            
            // Inventory Items
            Route::apiResource('items', \App\Http\Controllers\Api\V1\InventoryItemController::class)->middleware('role.verify:inventory.manage');
            
            // Transactions & Adjustments
            Route::get('transactions', [\App\Http\Controllers\Api\V1\InventoryTransactionController::class, 'index'])->middleware('role.verify:inventory.view');
            Route::post('transactions', [\App\Http\Controllers\Api\V1\InventoryTransactionController::class, 'store'])->middleware('role.verify:inventory.manage');
            Route::get('transactions/{id}', [\App\Http\Controllers\Api\V1\InventoryTransactionController::class, 'show'])->middleware('role.verify:inventory.view');
            
            // Recipes / Bills of Materials
            Route::get('menu-items/{menuItemId}/recipes', [\App\Http\Controllers\Api\V1\MenuRecipeController::class, 'index'])->middleware('role.verify:inventory.view');
            Route::post('menu-items/{menuItemId}/recipes', [\App\Http\Controllers\Api\V1\MenuRecipeController::class, 'store'])->middleware('role.verify:inventory.manage');
            Route::delete('menu-items/{menuItemId}/recipes/{ingredientId}', [\App\Http\Controllers\Api\V1\MenuRecipeController::class, 'destroy'])->middleware('role.verify:inventory.manage');

            // Purchase Orders
            Route::apiResource('purchase-orders', \App\Http\Controllers\Api\V1\PurchaseOrderController::class)->except(['update', 'destroy'])->middleware('role.verify:inventory.manage');
            Route::post('purchase-orders/{id}/receive', [\App\Http\Controllers\Api\V1\PurchaseOrderController::class, 'receive'])->middleware('role.verify:inventory.manage');
        });

        // Billing & Payments
        Route::prefix('billing')->group(function() {
            // Invoices
            Route::get('invoices', [\App\Http\Controllers\Api\V1\InvoiceController::class, 'index'])->middleware('role.verify:billing.view');
            Route::get('invoices/{id}', [\App\Http\Controllers\Api\V1\InvoiceController::class, 'show'])->middleware('role.verify:billing.view');
            Route::put('invoices/{id}', [\App\Http\Controllers\Api\V1\InvoiceController::class, 'update'])->middleware('role.verify:billing.manage');
            
            // Payment Methods
            Route::apiResource('payment-methods', \App\Http\Controllers\Api\V1\PaymentMethodController::class)->middleware('role.verify:billing.manage');
            
            // Payments
            Route::get('payments', [\App\Http\Controllers\Api\V1\PaymentController::class, 'index'])->middleware('role.verify:billing.view');
            Route::post('payments', [\App\Http\Controllers\Api\V1\PaymentController::class, 'store'])->middleware('role.verify:payments.process');
            Route::post('payments/{id}/refund', [\App\Http\Controllers\Api\V1\PaymentController::class, 'refund'])->middleware('role.verify:payments.refund');
        });

        // Payments (new integration)
        Route::prefix('payments')->group(function() {
            Route::get('transactions', [\App\Http\Controllers\Api\V1\PaymentGatewayController::class, 'index']);
            Route::get('gateways', [\App\Http\Controllers\Api\V1\PaymentGatewayController::class, 'listGateways']);
            Route::post('gateways', [\App\Http\Controllers\Api\V1\PaymentGatewayController::class, 'updateGateway']);
            Route::post('create-intent', [\App\Http\Controllers\Api\V1\PaymentGatewayController::class, 'createIntent']);
            Route::post('confirm', [\App\Http\Controllers\Api\V1\PaymentGatewayController::class, 'confirm']);
            Route::post('manual-confirm', [\App\Http\Controllers\Api\V1\PaymentGatewayController::class, 'manualConfirm']);
            Route::post('refund', [\App\Http\Controllers\Api\V1\PaymentGatewayController::class, 'refund']);
        });

        // Reports & Analytics BI
        Route::prefix('reports')->group(function() {
            Route::get('dashboard-summary', [\App\Http\Controllers\Api\V1\ReportController::class, 'dashboard'])->middleware('role.verify:reports.view');
            Route::get('daily-sales', [\App\Http\Controllers\Api\V1\ReportController::class, 'dailySales'])->middleware('role.verify:reports.view');
            Route::get('outlet-performance', [\App\Http\Controllers\Api\V1\ReportController::class, 'outletPerformance'])->middleware('role.verify:reports.view');
            Route::get('menu-performance', [\App\Http\Controllers\Api\V1\ReportController::class, 'menuPerformance'])->middleware('role.verify:reports.view');
            Route::get('payment-breakdown', [\App\Http\Controllers\Api\V1\ReportController::class, 'paymentBreakdown'])->middleware('role.verify:reports.view');
            Route::get('inventory-usage', [\App\Http\Controllers\Api\V1\ReportController::class, 'inventoryUsage'])->middleware('role.verify:reports.view');
            
            Route::prefix('export')->group(function() {
                Route::get('daily-sales', [\App\Http\Controllers\Api\V1\ReportController::class, 'exportDailySales'])->middleware('role.verify:reports.export');
                Route::get('outlet-performance', [\App\Http\Controllers\Api\V1\ReportController::class, 'exportOutletPerformance'])->middleware('role.verify:reports.export');
            });
        });

        // Notifications
        Route::prefix('notifications')->group(function() {
            Route::get('/', [\App\Http\Controllers\Api\V1\NotificationController::class, 'index'])->middleware('role.verify:notifications.view');
            Route::put('/read-all', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAllAsRead'])->middleware('role.verify:notifications.view');
            Route::put('/{id}/read', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAsRead'])->middleware('role.verify:notifications.view');
            Route::delete('/{id}', [\App\Http\Controllers\Api\V1\NotificationController::class, 'destroy'])->middleware('role.verify:notifications.manage');
        });

        // System Logs
        Route::prefix('system')->group(function() {
            Route::get('activity-logs', [\App\Http\Controllers\Api\V1\SystemLogController::class, 'activityLogs'])->middleware('role.verify:system.activity.view');
            Route::get('audit-logs', [\App\Http\Controllers\Api\V1\SystemLogController::class, 'auditLogs'])->middleware('role.verify:system.audit.view');
        });

        // Dynamic Pricing & Rate Management
        Route::prefix('pricing')->group(function () {
            // Rate Plans
            Route::get('/rate-plans', [\App\Http\Controllers\Api\V1\RatePlanController::class, 'index'])->middleware('role.verify:pricing.rate_plans.view');
            Route::post('/rate-plans', [\App\Http\Controllers\Api\V1\RatePlanController::class, 'store'])->middleware('role.verify:pricing.rate_plans.manage');
            Route::put('/rate-plans/{ratePlan}', [\App\Http\Controllers\Api\V1\RatePlanController::class, 'update'])->middleware('role.verify:pricing.rate_plans.manage');
            Route::delete('/rate-plans/{ratePlan}', [\App\Http\Controllers\Api\V1\RatePlanController::class, 'destroy'])->middleware('role.verify:pricing.rate_plans.manage');

            // Seasonal Rates
            Route::get('/seasonal-rates', [\App\Http\Controllers\Api\V1\SeasonalRateController::class, 'index'])->middleware('role.verify:pricing.rate_plans.view');
            Route::post('/seasonal-rates', [\App\Http\Controllers\Api\V1\SeasonalRateController::class, 'store'])->middleware('role.verify:pricing.seasonal_rates.manage');
            Route::put('/seasonal-rates/{seasonalRate}', [\App\Http\Controllers\Api\V1\SeasonalRateController::class, 'update'])->middleware('role.verify:pricing.seasonal_rates.manage');
            Route::delete('/seasonal-rates/{seasonalRate}', [\App\Http\Controllers\Api\V1\SeasonalRateController::class, 'destroy'])->middleware('role.verify:pricing.seasonal_rates.manage');

            // Occupancy Rules
            Route::get('/occupancy-rules', [\App\Http\Controllers\Api\V1\OccupancyRateController::class, 'index'])->middleware('role.verify:pricing.rate_plans.view');
            Route::post('/occupancy-rules', [\App\Http\Controllers\Api\V1\OccupancyRateController::class, 'store'])->middleware('role.verify:pricing.occupancy_rules.manage');
            Route::put('/occupancy-rules/{occupancyRateRule}', [\App\Http\Controllers\Api\V1\OccupancyRateController::class, 'update'])->middleware('role.verify:pricing.occupancy_rules.manage');
            Route::delete('/occupancy-rules/{occupancyRateRule}', [\App\Http\Controllers\Api\V1\OccupancyRateController::class, 'destroy'])->middleware('role.verify:pricing.occupancy_rules.manage');
        });
        // Global Admin Dashboard & Resources
        Route::prefix('admin')->group(function () {
            // Protected by active subscription
            Route::middleware('subscription.active')->group(function() {
                Route::post('/payment-gateways/test', [\App\Http\Controllers\Api\V1\PaymentGatewayController::class, 'testConnection']);
                Route::get('/dashboard/occupancy', [\App\Http\Controllers\Api\V1\DashboardController::class, 'occupancy']);
                Route::get('/dashboard/revenue', [\App\Http\Controllers\Api\V1\DashboardController::class, 'revenue']);
                Route::get('/dashboard/operations', [\App\Http\Controllers\Api\V1\DashboardController::class, 'operations']);
                
                Route::get('/orders/live', [\App\Http\Controllers\Api\V1\OrderController::class, 'live']);
                Route::get('/orders/pos', [\App\Http\Controllers\Api\V1\OrderController::class, 'posOrders']);
                
                Route::get('/housekeeping/status', [\App\Http\Controllers\Api\V1\HousekeepingTaskController::class, 'statusSummary']);
                Route::get('/service-requests', [\App\Http\Controllers\Api\V1\GuestRequestController::class, 'index']); // Alias

                // Revenue Intelligence
                Route::prefix('revenue')->group(function () {
                    Route::get('/insights', [RevenueInsightController::class, 'index']);
                    Route::get('/summary', [RevenueInsightController::class, 'summary']);
                    Route::post('/trigger', [RevenueInsightController::class, 'triggerSync']);
                    Route::put('/config', [RevenueInsightController::class, 'updateConfig']);
                });

                // OTA Channel Manager
                Route::prefix('ota')->group(function () {
                    Route::get('/channels', [\App\Http\Controllers\Api\V1\OtaChannelController::class, 'index']);
                    Route::get('/connections', [\App\Http\Controllers\Api\V1\OtaChannelController::class, 'connections']);
                    Route::post('/connect', [\App\Http\Controllers\Api\V1\OtaChannelController::class, 'connect']);
                    Route::delete('/disconnect/{channelId}', [\App\Http\Controllers\Api\V1\OtaChannelController::class, 'disconnect']);
                    Route::post('/sync', [\App\Http\Controllers\Api\V1\OtaChannelController::class, 'triggerSync']);
                    Route::post('/map/room-type', [\App\Http\Controllers\Api\V1\OtaChannelController::class, 'mapRoomType']);
                    Route::post('/map/rate-plan', [\App\Http\Controllers\Api\V1\OtaChannelController::class, 'mapRatePlan']);
                    Route::get('/sync-logs', [\App\Http\Controllers\Api\V1\OtaChannelController::class, 'syncLogs']);
                    Route::get('/reservations', [\App\Http\Controllers\Api\V1\OtaChannelController::class, 'otaReservations']);
                });
            });

            // Subscription management for hotel admins - EXEMPT from subscription.active
            Route::prefix('subscription')->group(function() {
                Route::get('/current', [\App\Http\Controllers\Api\V1\PlatformSubscriptionController::class, 'current']);
                Route::get('/plans', [\App\Http\Controllers\Api\V1\PlatformSubscriptionController::class, 'plans']);
                Route::post('/checkout', [\App\Http\Controllers\Api\V1\PlatformSubscriptionController::class, 'checkout']);
                Route::get('/invoices', [\App\Http\Controllers\Api\V1\PlatformSubscriptionController::class, 'invoices']);
            });

            // Platform owner analytics (Usually restricted to super-admin)
            Route::get('/platform/analytics', [\App\Http\Controllers\Api\V1\PlatformSubscriptionController::class, 'analytics']);
        });
    });
});

