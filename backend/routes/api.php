<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CloudSyncController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\GroupRegistrationController;
use App\Http\Controllers\Api\V1\GroupWebsiteController;
use App\Http\Controllers\Api\V1\GuestOutletController;
use App\Http\Controllers\Api\V1\GuestPortalController;
use App\Http\Controllers\Api\V1\GuestRequestController;
use App\Http\Controllers\Api\V1\GuestReservationController;
use App\Http\Controllers\Api\V1\GuestSessionController;
use App\Http\Controllers\Api\V1\HousekeepingTaskController;
use App\Http\Controllers\Api\V1\ImageUploadController;
use App\Http\Controllers\Api\V1\InventoryItemController;
use App\Http\Controllers\Api\V1\InventoryTransactionController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\KitchenDisplayController;
use App\Http\Controllers\Api\V1\KitchenStationController;
use App\Http\Controllers\Api\V1\LockEventController;
use App\Http\Controllers\Api\V1\MaintenanceRequestController;
use App\Http\Controllers\Api\V1\MenuCategoryController;
use App\Http\Controllers\Api\V1\MenuItemController;
use App\Http\Controllers\Api\V1\MenuRecipeController;
use App\Http\Controllers\Api\V1\ModifierController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OccupancyRateController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\StaffPinController;
use App\Http\Controllers\Api\V1\OtaChannelController;
use App\Http\Controllers\Api\V1\OutletController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentGatewayController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\PlatformSubscriptionController;
use App\Http\Controllers\Api\V1\PMS\PmsAvailabilityController;
use App\Http\Controllers\Api\V1\PMS\PmsFolioController;
use App\Http\Controllers\Api\V1\PMS\PmsGuestController;
use App\Http\Controllers\Api\V1\PMS\PmsReservationController;
use App\Http\Controllers\Api\V1\PMS\PmsRoomController;
use App\Http\Controllers\Api\V1\PMS\PmsRoomTypeController;
use App\Http\Controllers\Api\V1\PublicBookingController;
use App\Http\Controllers\Api\V1\PublicGroupWebsiteController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\RatePlanController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RevenueInsightController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SeasonalRateController;
use App\Http\Controllers\Api\V1\SLADashboardController;
use App\Http\Controllers\Api\V1\StaffController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\SystemLogController;
use App\Http\Controllers\Api\V1\ThemeController;
use App\Http\Controllers\Auth\HotelRegistrationController;
use App\Http\Controllers\Api\V1\HardwareController;
use App\Http\Controllers\Api\V1\LeisureController;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Payment Webhooks (No Auth Required)
Route::post('v1/payments/webhook/{gateway}', [PaymentWebhookController::class, 'handleWebhook']);

// Basic API routes
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register-hotel', [HotelRegistrationController::class, 'register']);
        Route::post('register-group', [GroupRegistrationController::class, 'register']); // Public — no tenant scope
        Route::get('sla/dashboard', [SLADashboardController::class, 'activeTickets']);
        Route::get('sla/branch-overview', [SLADashboardController::class, 'branchOverview']);
        Route::get('sla/report', [SLADashboardController::class, 'performanceReport']);
        Route::post('login', [AuthController::class, 'login']); // Kept existing route
        Route::post('staff-pin', [AuthController::class, 'staffPinLogin']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']); // Kept existing route
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
        Route::post('activate-branch', [\App\Http\Controllers\Api\V1\LicensingController::class, 'activate']); 

        // Staff Setup (PIN & Password Activation)
        Route::middleware('auth:sanctum')->post('staff/setup', [AuthController::class, 'setupStaff']);

        // ── SUPPORT STAFF SIGNUP ────────────────────────────────────────────────
        Route::post('support-signup', [\App\Http\Controllers\Api\V1\SupportStaffController::class, 'signup']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'user']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('staff/set-pin', [StaffPinController::class, 'setPin']);
        });
    });

    // Alignment Routes
    Route::get('theme', [ThemeController::class, 'getTheme']);
    Route::post('guest/claim-room', [GuestSessionController::class, 'claimRoom']);
    Route::post('guest/verify-pin', [GuestSessionController::class, 'verifyPin']);

    // Organization Management (GROUP_ADMIN + SUPER_ADMIN only — no single-hotel tenant scope needed)
    Route::prefix('owner')->middleware('auth:sanctum')->group(function () {
        Route::get('/analytics/master-summary', [\App\Http\Controllers\Api\V1\OwnerAnalyticsController::class, 'masterSummary']);
        Route::get('/analytics/export', [\App\Http\Controllers\Api\V1\OwnerAnalyticsController::class, 'exportMasterSummary']);
    });

    Route::prefix('organization')->middleware('auth:sanctum')->group(function () {
        Route::get('/overview', [OrganizationController::class, 'overview']);
        Route::get('/branches', [OrganizationController::class, 'branches']);
        Route::post('/branches', [OrganizationController::class, 'store']);
        Route::post('/branches/{id}/onboard', [OrganizationController::class, 'onboardManager']);
        Route::put('/settings', [OrganizationController::class, 'updateSettings']);

        // Website Creator Routes
        Route::group(['prefix' => 'website'], function () {
            Route::get('/', [GroupWebsiteController::class, 'show']);
            Route::put('/', [GroupWebsiteController::class, 'update']);
            Route::put('/overrides/{hotel}', [GroupWebsiteController::class, 'updateOverride']);
            Route::post('/upload-image', [ImageUploadController::class, 'upload']);
        });
    });

    // ── HARDWARE INTEGRATION — DOOR LOCKS ────────────────────────────────────
    // Webhook: No auth — secured by HMAC-SHA256 Shared Secret inside the controller.
    Route::post('/integration/lock-events', [LockEventController::class, 'receive'])
        ->middleware('throttle:60,1');
    // Admin view of lock events (auth required)
    Route::get('/integration/lock-events', [LockEventController::class, 'index'])
        ->middleware('auth:sanctum');

    // Public Channel Webhooks — OTA push notifications
    Route::post('/channels/{channel}/webhook', [OtaChannelController::class, 'webhook']);

    // Cloud Edge-Node Sync Ingestion Endpoint
    Route::get('/sync/status', [SyncController::class, 'syncStatus']);
    Route::post('/sync/batch', [SyncController::class, 'batchSync']);
    Route::post('/sync/ingest', [CloudSyncController::class, 'ingest']);

    // ── LEISURE HUB HARDWARE BRIDGE ──────────────────────────────────────────
    // GET /api/v1/hardware/verify/{code}
    Route::get('/hardware/verify/{code}', [HardwareController::class, 'verify']);
    Route::get('/hardware-id', [HardwareController::class, 'hardwareId']);

    // ── PUBLIC BOOKING ENGINE ─────────────────────────────────────────────────
    // No auth required. Tenant is resolved from {hotel_slug} or Host header
    // via DomainTenantMiddleware. Rate-limited to prevent abuse.
    Route::prefix('booking')->middleware(['throttle:60,1'])->group(function () {
        $ctrl =  PublicBookingController::class;

        // ── STATIC ROUTES FIRST (must come before /{hotel_slug} wildcard) ────────
        // Group Website Routes
        Route::get('/group-website', [PublicGroupWebsiteController::class, 'findFirst']); // returns first active group slug — used for portal redirect
        Route::get('/group/{group_slug}', [PublicGroupWebsiteController::class, 'show']);
        Route::get('/group/{group_slug}/branch/{hotel_slug}', [PublicGroupWebsiteController::class, 'branchDetails']);

        // ── WILDCARD ROUTES LAST ──────────────────────────────────────────────────
        Route::get('/{hotel_slug}',                 [$ctrl, 'show']);
        Route::get('/{hotel_slug}/availability',    [$ctrl, 'availability']);
        Route::post('/{hotel_slug}/reserve',        [$ctrl, 'reserve']);
        Route::post('/{hotel_slug}/confirm-payment', [$ctrl, 'confirmPayment']);
    });

    // Guest Portal (Public start, then protected by TenantMiddleware)
    Route::prefix('guest')->middleware(['throttle:10,1', \App\Http\Middleware\TenantMiddleware::class])->group(function () {
        Route::post('/session/start', [GuestPortalController::class, 'startSession'])->withoutMiddleware(\App\Http\Middleware\TenantMiddleware::class);
        Route::post('/session/authenticate', [GuestPortalController::class, 'authenticate']);
        Route::get('/dashboard', [GuestPortalController::class, 'dashboard']);
        
        // Orders & Tracking
        Route::get('/orders/{order}', [GuestOutletController::class, 'showOrder']);
        Route::post('/orders/{outlet}', [GuestOutletController::class, 'storeOrder']);
        
        // Menu & Recommendations
        Route::get('/menus/{outlet}', [GuestOutletController::class, 'menu']);
        Route::get('/menu/{outlet}', [GuestOutletController::class, 'menu']); // Alias
        Route::get('/menus/{outlet}/recommendations', [GuestOutletController::class, 'recommendations']);
        Route::get('/menu/{outlet}/recommendations', [GuestOutletController::class, 'recommendations']); // Alias
        
        // Service Requests
        Route::get('/requests', [GuestRequestController::class, 'index']);
        Route::post('/requests', [GuestRequestController::class, 'store']);
        Route::post('/service-requests', [GuestRequestController::class, 'store']); // Alias for the enhancement requirement
        Route::post('/service-request', [GuestRequestController::class, 'store']); // Alias
        
        Route::post('/reservations/availability', [GuestReservationController::class, 'searchAvailability']);
        Route::post('/reservations', [GuestReservationController::class, 'store']);
    });

    Route::middleware(['auth:sanctum', \App\Http\Middleware\TenantMiddleware::class, 'feature.guard'])->group(function () {
        Route::apiResource('departments', DepartmentController::class)->middleware('role.verify:hotel.manage');
        Route::apiResource('hotels', Controller::class)->only(['index'])->middleware('role.verify:hotel.manage');

        // PMS Routes
        Route::prefix('pms')->middleware('feature:pms')->group(function () {
            // Availability
            Route::get('/availability', [PmsAvailabilityController::class, 'index']);

            // Room Types
            Route::get('/room-types', [PmsRoomTypeController::class, 'index']);
            Route::post('/room-types', [PmsRoomTypeController::class, 'store']);
            Route::get('/room-types/{id}', [PmsRoomTypeController::class, 'show']);
            Route::put('/room-types/{id}', [PmsRoomTypeController::class, 'update']);
            Route::delete('/room-types/{id}', [PmsRoomTypeController::class, 'destroy']);

            // Rooms
            Route::get('/rooms/map', [PmsRoomController::class, 'roomMap'])->middleware('role.verify:pms.rooms.view');
            Route::get('/rooms', [PmsRoomController::class, 'index'])->middleware('role.verify:pms.rooms.view');
            Route::post('/rooms', [PmsRoomController::class, 'store'])
                ->middleware(['role.verify:pms.rooms.manage', 'feature:rooms.create']);
            Route::put('/rooms/{room}/status', [PmsRoomController::class, 'updateStatus'])->middleware('role.verify:pms.rooms.manage');
            Route::put('/rooms/{room}/housekeeping', [PmsRoomController::class, 'updateHousekeeping'])->middleware('role.verify:pms.rooms.manage');

            // Guests
            Route::get('/guests', [PmsGuestController::class, 'index']);
            Route::post('/guests', [PmsGuestController::class, 'store']);

            // Reservations
            Route::get('/reservations', [PmsReservationController::class, 'index']);
            Route::post('/reservations', [PmsReservationController::class, 'store']);
            Route::put('/reservations/{reservation}', [PmsReservationController::class, 'update']);
            Route::delete('/reservations/{reservation}', [PmsReservationController::class, 'destroy']);
            Route::put('/reservations/{reservation}/confirm', [PmsReservationController::class, 'confirm']);
            Route::post('/reservations/{reservation}/check-in', [PmsReservationController::class, 'checkIn']);
            Route::post('/reservations/{reservation}/check-out', [PmsReservationController::class, 'checkOut']);
            Route::post('/reservations/{reservation}/extend', [PmsReservationController::class, 'extend']);
            Route::get('/reservations/{reservation}/folio', [PmsFolioController::class, 'showByReservation']);

            // Folios
            Route::get('/folios', [PmsFolioController::class, 'index']);
            Route::post('/folios/{folio}/charge', [PmsFolioController::class, 'postCharge']);
            Route::post('/folios/{folio}/payment', [PmsFolioController::class, 'postPayment']);
        });

        // Housekeeping Tasks
        Route::prefix('housekeeping/tasks')->group(function () {
            Route::get('/', [HousekeepingTaskController::class, 'index'])->middleware('role.verify:housekeeping.tasks.view');
            Route::post('/{task}/assign', [HousekeepingTaskController::class, 'assign'])->middleware('role.verify:housekeeping.tasks.manage');
            Route::post('/{task}/start', [HousekeepingTaskController::class, 'start'])->middleware('role.verify:housekeeping.tasks.manage');
            Route::post('/{task}/complete', [HousekeepingTaskController::class, 'complete'])->middleware('role.verify:housekeeping.tasks.manage');
        });

        // Maintenance Requests
        Route::prefix('maintenance/requests')->group(function () {
            Route::get('/', [MaintenanceRequestController::class, 'index'])->middleware('role.verify:maintenance.requests.view');
            Route::post('/', [MaintenanceRequestController::class, 'store'])->middleware('role.verify:maintenance.requests.manage');
            Route::post('/{maintenanceRequest}/assign', [MaintenanceRequestController::class, 'assign'])->middleware('role.verify:maintenance.requests.manage');
            Route::post('/{maintenanceRequest}/start', [MaintenanceRequestController::class, 'start'])->middleware('role.verify:maintenance.requests.manage');
            Route::post('/{maintenanceRequest}/resolve', [MaintenanceRequestController::class, 'resolve'])->middleware('role.verify:maintenance.requests.manage');
        });

        Route::get('/users', [StaffController::class, 'index'])->middleware('role.verify:users.view');
        Route::post('/users', [StaffController::class, 'store'])->middleware('role.verify:users.create');
        Route::post('/staff/toggle-duty', [StaffController::class, 'toggleDuty']);
        Route::get('/roles', [RoleController::class, 'index']);
        Route::apiResource('outlets', OutletController::class);
        
        Route::prefix('guest-requests')->group(function () {
            Route::get('/', [GuestRequestController::class, 'index'])->middleware('role.verify:guest.requests.view');
            Route::get('/active-sessions', [GuestPortalController::class, 'activeSessions'])->middleware('role.verify:guest.requests.view');
            Route::post('/{serviceRequest}/assign', [GuestRequestController::class, 'assign'])->middleware('role.verify:guest.requests.manage');
            Route::post('/{serviceRequest}/complete', [GuestRequestController::class, 'complete'])->middleware('role.verify:guest.requests.manage');
        });

        // POS Orders
        Route::middleware(['module.active:pos'])->prefix('orders')->group(function() {
            Route::get('/', [OrderController::class, 'index'])->middleware('role.verify:orders.view');
            Route::get('/velocity', [OrderController::class, 'velocityMetrics'])->middleware('role.verify:orders.view');
            Route::post('/', [OrderController::class, 'store'])->middleware('role.verify:pos.manage');
            Route::post('/activate-session', [OrderController::class, 'activateSession'])->middleware('role.verify:pos.manage');
            Route::post('/transfer-items', [OrderController::class, 'transferItems'])->middleware('role.verify:orders.update');
            Route::get('/{order}', [OrderController::class, 'show'])->middleware('role.verify:orders.view');
            Route::put('/{order}/status', [OrderController::class, 'updateStatus'])->middleware('role.verify:orders.update');
            Route::post('/{order}/claim', [OrderController::class, 'claim'])->middleware('role.verify:orders.update');
            Route::post('/{order}/void', [OrderController::class, 'void'])->middleware('role.verify:orders.update');
            Route::delete('/{order}', [OrderController::class, 'destroy'])->middleware('role.verify:orders.delete');
            Route::get('/kds', [OrderController::class, 'kds'])->middleware('role.verify:kds.view');
        });
        
        // Alignment Alias for Phase 6.3 - Waitress Handshake
        Route::prefix('pos')->group(function() {
            Route::post('/activate-session', [OrderController::class, 'activateSession'])->middleware('role.verify:pos.manage');
        });

        // Menu Management
        Route::prefix('menu')->group(function() {
            Route::prefix('categories')->group(function() {
                Route::get('/', [MenuCategoryController::class, 'index'])->middleware('role.verify:menu.view');
                Route::post('/', [MenuCategoryController::class, 'store'])->middleware('role.verify:menu.create');
                Route::get('/{id}', [MenuCategoryController::class, 'show'])->middleware('role.verify:menu.view');
                Route::put('/{id}', [MenuCategoryController::class, 'update'])->middleware('role.verify:menu.update');
                Route::delete('/{id}', [MenuCategoryController::class, 'destroy'])->middleware('role.verify:menu.delete');
            });

            Route::prefix('items')->group(function() {
                Route::get('/', [MenuItemController::class, 'index'])->middleware('role.verify:menu.view');
                Route::post('/', [MenuItemController::class, 'store'])->middleware('role.verify:menu.create');
                Route::get('/{id}', [MenuItemController::class, 'show'])->middleware('role.verify:menu.view');
                Route::put('/{id}', [MenuItemController::class, 'update'])->middleware('role.verify:menu.update');
                Route::delete('/{id}', [MenuItemController::class, 'destroy'])->middleware('role.verify:menu.delete');
                Route::post('/{id}/duplicate', [MenuItemController::class, 'duplicateToOutlet'])->middleware('role.verify:menu.create');
            });

            Route::prefix('modifiers')->group(function() {
                Route::get('/', [ModifierController::class, 'index'])->middleware('role.verify:menu.view');
                Route::post('/', [ModifierController::class, 'store'])->middleware('role.verify:menu.create');
                Route::get('/{id}', [ModifierController::class, 'show'])->middleware('role.verify:menu.view');
                Route::put('/{id}', [ModifierController::class, 'update'])->middleware('role.verify:menu.update');
                Route::delete('/{id}', [ModifierController::class, 'destroy'])->middleware('role.verify:menu.delete');
            });
        });

        // Kitchen Display System (KDS)
        Route::middleware(['module.active:kitchen'])->prefix('kds')->group(function() {
            Route::apiResource('stations', KitchenStationController::class)->middleware('role.verify:settings.manage');
            Route::get('/tickets', [KitchenDisplayController::class, 'index'])->middleware('role.verify:kds.view');
            Route::get('/tickets/{id}', [KitchenDisplayController::class, 'show'])->middleware('role.verify:kds.view');
            Route::put('/tickets/{id}/status', [KitchenDisplayController::class, 'updateStatus'])->middleware('role.verify:kds.update');
            Route::post('/tickets/{id}/print', [KitchenDisplayController::class, 'printTicket'])->middleware('role.verify:kds.update');
            Route::put('/inventory/{id}/toggle', [KitchenDisplayController::class, 'toggleAvailability'])->middleware('role.verify:kds.update');
            Route::post('/restock', [KitchenDisplayController::class, 'requestRestock'])->middleware('role.verify:kds.update');
            Route::put('/items/{id}/status', [KitchenDisplayController::class, 'updateItemStatus'])->middleware('role.verify:kds.update');

            // SLA Dashboard
            Route::get('/sla/active', [SLADashboardController::class, 'activeTickets'])->middleware('role.verify:manager.view');
            Route::get('/sla/report', [SLADashboardController::class, 'performanceReport'])->middleware('role.verify:manager.view');
        });

        // Inventory Management
        Route::prefix('inventory')->group(function() {
            // Suppliers
            Route::apiResource('suppliers', SupplierController::class)->middleware('role.verify:inventory.manage');
            
            // Inventory Items
            Route::apiResource('items', InventoryItemController::class)->middleware('role.verify:inventory.manage');
            
            // Transactions & Adjustments
            Route::get('transactions', [InventoryTransactionController::class, 'index'])->middleware('role.verify:inventory.view');
            Route::post('transactions', [InventoryTransactionController::class, 'store'])->middleware('role.verify:inventory.manage');
            Route::get('transactions/{id}', [InventoryTransactionController::class, 'show'])->middleware('role.verify:inventory.view');
            
            // Recipes / Bills of Materials
            Route::get('menu-items/{menuItemId}/recipes', [MenuRecipeController::class, 'index'])->middleware('role.verify:inventory.view');
            Route::post('menu-items/{menuItemId}/recipes', [MenuRecipeController::class, 'store'])->middleware('role.verify:inventory.manage');
            Route::delete('menu-items/{menuItemId}/recipes/{ingredientId}', [MenuRecipeController::class, 'destroy'])->middleware('role.verify:inventory.manage');

            // Purchase Orders
            Route::apiResource('purchase-orders', PurchaseOrderController::class)->except(['update', 'destroy'])->middleware('role.verify:inventory.manage');
            Route::post('purchase-orders/{id}/receive', [PurchaseOrderController::class, 'receive'])->middleware('role.verify:inventory.manage');

            // Stock Transfers (Chain of Custody)
            Route::get('transfers', [StockTransferController::class, 'index'])->middleware('role.verify:inventory.view');
            Route::post('transfers/request', [StockTransferController::class, 'request'])->middleware('role.verify:inventory.manage');
            Route::post('transfers/{transfer}/dispatch', [StockTransferController::class, 'dispatch'])->middleware('role.verify:inventory.manage');
            Route::post('transfers/{transfer}/receive', [StockTransferController::class, 'receive'])
                ->middleware(['role.verify:inventory.manage', 'feature.guard']);
        });

        // Billing & Payments
        Route::prefix('billing')->group(function() {
            // Invoices
            Route::get('invoices', [InvoiceController::class, 'index'])->middleware('role.verify:billing.view');
            Route::get('invoices/{id}', [InvoiceController::class, 'show'])->middleware('role.verify:billing.view');
            Route::put('invoices/{id}', [InvoiceController::class, 'update'])->middleware('role.verify:billing.manage');
            
            // Payment Methods
            Route::apiResource('payment-methods', PaymentMethodController::class)->middleware('role.verify:billing.manage');
            
            // Payments
            Route::get('payments', [PaymentController::class, 'index'])->middleware('role.verify:billing.view');
            Route::post('payments', [PaymentController::class, 'store'])->middleware('role.verify:payments.process');
            Route::post('payments/{id}/refund', [PaymentController::class, 'refund'])->middleware('role.verify:payments.refund');
        });

        // Payments (new integration)
        Route::prefix('payments')->group(function() {
            Route::get('transactions', [PaymentGatewayController::class, 'index']);
            Route::get('gateways', [PaymentGatewayController::class, 'listGateways']);
            Route::post('gateways', [PaymentGatewayController::class, 'updateGateway']);
            Route::post('create-intent', [PaymentGatewayController::class, 'createIntent']);
            Route::post('confirm', [PaymentGatewayController::class, 'confirm']);
            Route::post('manual-confirm', [PaymentGatewayController::class, 'manualConfirm']);
            Route::post('refund', [PaymentGatewayController::class, 'refund']);
        });

        // Reports & Analytics BI
        Route::prefix('reports')->group(function() {
            Route::get('dashboard-summary', [ReportController::class, 'dashboard'])->middleware('role.verify:reports.view');
            Route::get('daily-sales', [ReportController::class, 'dailySales'])->middleware('role.verify:reports.view');
            Route::get('outlet-performance', [ReportController::class, 'outletPerformance'])->middleware('role.verify:reports.view');
            Route::get('menu-performance', [ReportController::class, 'menuPerformance'])->middleware('role.verify:reports.view');
            Route::get('payment-breakdown', [ReportController::class, 'paymentBreakdown'])->middleware('role.verify:reports.view');
            Route::get('inventory-usage', [ReportController::class, 'inventoryUsage'])->middleware('role.verify:reports.view');
            
            Route::prefix('export')->group(function() {
                Route::get('daily-sales', [ReportController::class, 'exportDailySales'])->middleware('role.verify:reports.export');
                Route::get('outlet-performance', [ReportController::class, 'exportOutletPerformance'])->middleware('role.verify:reports.export');
            });
        });

        // Financial Analytics (Phase 6.1)
        Route::prefix('analytics')->middleware('feature:financial_analytics')->group(function () {
            Route::get('/revenue-summary', [\App\Http\Controllers\Api\V1\AnalyticsController::class, 'revenueSummary']);
        });

        // Notifications
        Route::prefix('notifications')->group(function() {
            Route::get('/', [NotificationController::class, 'index'])->middleware('role.verify:notifications.view');
            Route::put('/read-all', [NotificationController::class, 'markAllAsRead'])->middleware('role.verify:notifications.view');
            Route::put('/{id}/read', [NotificationController::class, 'markAsRead'])->middleware('role.verify:notifications.view');
            Route::delete('/{id}', [NotificationController::class, 'destroy'])->middleware('role.verify:notifications.manage');
        });

        // System Logs
        Route::prefix('system')->group(function() {
            Route::get('activity-logs', [SystemLogController::class, 'activityLogs'])->middleware('role.verify:system.activity.view');
            Route::get('audit-logs', [SystemLogController::class, 'auditLogs'])->middleware('role.verify:system.audit.view');
        });

        // Dynamic Pricing & Rate Management
        Route::prefix('pricing')->group(function () {
            // Rate Plans
            Route::get('/rate-plans', [RatePlanController::class, 'index'])->middleware('role.verify:pricing.rate_plans.view');
            Route::post('/rate-plans', [RatePlanController::class, 'store'])->middleware('role.verify:pricing.rate_plans.manage');
            Route::put('/rate-plans/{ratePlan}', [RatePlanController::class, 'update'])->middleware('role.verify:pricing.rate_plans.manage');
            Route::delete('/rate-plans/{ratePlan}', [RatePlanController::class, 'destroy'])->middleware('role.verify:pricing.rate_plans.manage');

            // Seasonal Rates
            Route::get('/seasonal-rates', [SeasonalRateController::class, 'index'])->middleware('role.verify:pricing.rate_plans.view');
            Route::post('/seasonal-rates', [SeasonalRateController::class, 'store'])->middleware('role.verify:pricing.seasonal_rates.manage');
            Route::put('/seasonal-rates/{seasonalRate}', [SeasonalRateController::class, 'update'])->middleware('role.verify:pricing.seasonal_rates.manage');
            Route::delete('/seasonal-rates/{seasonalRate}', [SeasonalRateController::class, 'destroy'])->middleware('role.verify:pricing.seasonal_rates.manage');

            // Occupancy Rules
            Route::get('/occupancy-rules', [OccupancyRateController::class, 'index'])->middleware('role.verify:pricing.rate_plans.view');
            Route::post('/occupancy-rules', [OccupancyRateController::class, 'store'])->middleware('role.verify:pricing.occupancy_rules.manage');
            Route::put('/occupancy-rules/{occupancyRateRule}', [OccupancyRateController::class, 'update'])->middleware('role.verify:pricing.occupancy_rules.manage');
            Route::delete('/occupancy-rules/{occupancyRateRule}', [OccupancyRateController::class, 'destroy'])->middleware('role.verify:pricing.occupancy_rules.manage');
        });
        // Global Admin Dashboard & Resources
        Route::prefix('admin')->group(function () {
            // Protected by active subscription
            Route::middleware('subscription.active')->group(function() {
                Route::post('/payment-gateways/test', [PaymentGatewayController::class, 'testConnection']);
                Route::get('/dashboard/occupancy', [DashboardController::class, 'occupancy']);
                Route::get('/dashboard/revenue', [DashboardController::class, 'revenue']);
                Route::get('/dashboard/operations', [DashboardController::class, 'operations']);
                
                Route::get('/orders/live', [OrderController::class, 'live']);
                Route::get('/orders/pos', [OrderController::class, 'posOrders']);
                
                Route::get('/housekeeping/status', [HousekeepingTaskController::class, 'statusSummary']);
                Route::get('/service-requests', [GuestRequestController::class, 'index']); // Alias

                // Revenue Intelligence
                Route::prefix('revenue')->group(function () {
                    Route::get('/insights', [RevenueInsightController::class, 'index']);
                    Route::get('/summary', [RevenueInsightController::class, 'summary']);
                    Route::post('/trigger', [RevenueInsightController::class, 'triggerSync']);
                    Route::put('/config', [RevenueInsightController::class, 'updateConfig']);
                });

                // OTA Channel Manager
                Route::prefix('ota')->group(function () {
                    Route::get('/channels', [OtaChannelController::class, 'index']);
                    Route::get('/connections', [OtaChannelController::class, 'connections']);
                    Route::post('/connect', [OtaChannelController::class, 'connect']);
                    Route::delete('/disconnect/{channelId}', [OtaChannelController::class, 'disconnect']);
                    Route::post('/sync', [OtaChannelController::class, 'triggerSync']);
                    Route::post('/map/room-type', [OtaChannelController::class, 'mapRoomType']);
                    Route::post('/map/rate-plan', [OtaChannelController::class, 'mapRatePlan']);
                    Route::get('/sync-logs', [OtaChannelController::class, 'syncLogs']);
                    Route::get('/reservations', [OtaChannelController::class, 'otaReservations']);
                });
            });

            // Subscription management for hotel admins - EXEMPT from subscription.active
            Route::prefix('subscription')->group(function() {
                Route::get('/current', [PlatformSubscriptionController::class, 'current']);
                Route::get('/plans', [PlatformSubscriptionController::class, 'plans']);
                Route::post('/checkout', [PlatformSubscriptionController::class, 'checkout']);
                Route::get('/invoices', [PlatformSubscriptionController::class, 'invoices']);
            });

            // Platform Analytics (accessible to any authenticated hotel admin)
            Route::get('/platform/analytics', [\App\Http\Controllers\Api\V1\OwnerAnalyticsController::class, 'platformAnalytics']);

            // Leisure Hub (Port 3003)
            Route::prefix('leisure')->group(function() {
                Route::get('/metrics', [DashboardController::class, 'leisureMetrics'])->middleware('role.verify:manager.view');
                Route::get('/audit', [LeisureController::class, 'audit'])->middleware('role.verify:manager.view');
                Route::post('/provision', [LeisureController::class, 'provision'])->middleware('role.verify:hotel.manage');
                Route::post('/reset-credential', [LeisureController::class, 'resetCredential'])->middleware('role.verify:pos.manage');
                Route::post('/daily-pin', [LeisureController::class, 'generatePin']);
                Route::apiResource('memberships', LeisureController::class)->only(['index', 'store', 'show']);
            });

        });
    });

    // Super Admin Billing & Platform Lifecycle (Exempt from Tenant Scope)
    Route::middleware(['auth:sanctum'])->group(function() {
        if (request()->user() && !request()->user()->is_super_admin) {
            // Optional: abort if accessed by non-admins if not protected by another layer
        }
        Route::patch('/super-admin/plans', [\App\Http\Controllers\Api\V1\SuperAdminBillingController::class, 'updatePlans']);
        Route::get('/super-admin/billing/health', [\App\Http\Controllers\Api\V1\SuperAdminBillingController::class, 'health']);
    });
});

Route::post('v1/monnify/webhook', [\App\Http\Controllers\Api\V1\MonnifyWebhookController::class, 'handle']);

