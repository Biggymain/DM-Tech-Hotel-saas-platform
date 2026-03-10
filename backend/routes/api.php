<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Basic API routes
Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('register-hotel', [\App\Http\Controllers\Auth\HotelRegistrationController::class, 'register']);
        // Auth routes will be defined here
        Route::get('me', function(Request $request) {
            return $request->user();
        })->middleware('auth:sanctum');
    });

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::apiResource('departments', \App\Http\Controllers\DepartmentController::class)->middleware('role.verify:hotel.manage');
        Route::apiResource('hotels', \App\Http\Controllers\Controller::class)->only(['index'])->middleware('role.verify:hotel.manage');
        Route::apiResource('users', \App\Http\Controllers\Controller::class)->only(['index']);
        Route::apiResource('roles', \App\Http\Controllers\Controller::class)->only(['index']);
        
        // Placeholder protected routes as requested
        Route::prefix('rooms')->group(function() {
            Route::middleware('role.verify:rooms.manage')->group(function() {
                Route::get('/', function() { return response()->json(['message' => 'Rooms accessed']); });
            });
        });
        Route::prefix('reservations')->group(function() {
            Route::middleware('role.verify:reservations.manage')->group(function() {
                Route::get('/', function() { return response()->json(['message' => 'Reservations accessed']); });
            });
        });
        // POS Orders
        Route::prefix('orders')->group(function() {
            Route::get('/', [\App\Http\Controllers\OrderController::class, 'index'])->middleware('role.verify:orders.view');
            Route::post('/', [\App\Http\Controllers\OrderController::class, 'store'])->middleware('role.verify:orders.create');
            Route::get('/{id}', [\App\Http\Controllers\OrderController::class, 'show'])->middleware('role.verify:orders.view');
            Route::put('/{id}/status', [\App\Http\Controllers\OrderController::class, 'updateStatus'])->middleware('role.verify:orders.update');
            Route::delete('/{id}', [\App\Http\Controllers\OrderController::class, 'destroy'])->middleware('role.verify:orders.delete');
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
        Route::prefix('kds')->group(function() {
            Route::get('/tickets', [\App\Http\Controllers\Api\V1\KitchenDisplayController::class, 'index'])->middleware('role.verify:kds.view');
            Route::get('/tickets/{id}', [\App\Http\Controllers\Api\V1\KitchenDisplayController::class, 'show'])->middleware('role.verify:kds.view');
            Route::put('/tickets/{id}/status', [\App\Http\Controllers\Api\V1\KitchenDisplayController::class, 'updateStatus'])->middleware('role.verify:kds.update');
            Route::put('/items/{id}/status', [\App\Http\Controllers\Api\V1\KitchenDisplayController::class, 'updateItemStatus'])->middleware('role.verify:kds.update');
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

        Route::prefix('finance')->group(function() {
            Route::middleware('role.verify:finance.manage')->group(function() {
                Route::get('/', function() { return response()->json(['message' => 'Finance accessed']); });
            });
        });
    });
});

// Load Module Routes
$modules = ['HotelManagement', 'Rooms', 'Reservations', 'Restaurant', 'POS', 'Inventory', 'Finance', 'Notifications', 'Analytics'];
foreach ($modules as $module) {
    $moduleRoutePath = base_path("modules/{$module}/Routes/api.php");
    if (file_exists($moduleRoutePath)) {
        Route::prefix('v1/' . strtolower($module))
            ->middleware('api')
            ->group($moduleRoutePath);
    }
}
