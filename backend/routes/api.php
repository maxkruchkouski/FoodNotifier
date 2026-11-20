<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ПУБЛИЧНЫЕ РОУТЫ (без тенанта)
|--------------------------------------------------------------------------
*/

Route::get('/restaurants/list', [OrderController::class, 'getActiveRestaurants']);

Route::post('/admin/login', [AdminController::class, 'login']);

/*
|--------------------------------------------------------------------------
| ГОСТЕВЫЕ + КУХНЯ + ТАБЛО (тенант обязателен)
|--------------------------------------------------------------------------
*/

Route::middleware(['resolve.tenant', 'check.billing'])->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/status', [OrderController::class, 'checkStatus']);

    Route::get('/kitchen/orders', [OrderController::class, 'getCookingOrders']);
    Route::get('/board/orders', [OrderController::class, 'getBoardOrders']);
    Route::post('/kitchen/orders/start', [OrderController::class, 'startCooking']);
    Route::post('/kitchen/orders/ready', [OrderController::class, 'markReady']);
});

/*
|--------------------------------------------------------------------------
| АНАЛИТИКА (тенант обязателен, но без billing — чтобы owner видел KPI даже при блокировке)
|--------------------------------------------------------------------------
*/

Route::middleware(['resolve.tenant'])->group(function () {
    Route::get('/analytics/kpi', [AnalyticsController::class, 'getKpiMetrics']);
    Route::get('/analytics/chart', [AnalyticsController::class, 'getChartData']);
});

/*
|--------------------------------------------------------------------------
| АДМИНКА / ДАШБОРД (Sanctum + тенант + доступ)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::post('/logout', [AdminController::class, 'logout']);
    Route::get('/user', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        return response()->json([
            'id'            => $user->id,
            'email'         => $user->email,
            'role'          => $user->role,
            'is_superadmin' => $user->is_superadmin,
            'restaurant'    => $user->restaurant ? [
                'id'   => $user->restaurant->id,
                'slug' => $user->restaurant->slug,
                'name' => $user->restaurant->name,
                'position' => $user->position,

            ] : null,
        ]);
    });

    // Глобальные (суперадмин)
    Route::get('/owners', [AdminController::class, 'getOwners']);
    Route::post('/users', [AdminController::class, 'createUser']);
    Route::get('/qr', [AdminController::class, 'generateQr']);

    // Рестораны (скоуп owner применится в модели)
    Route::get('/restaurants', [AdminController::class, 'getRestaurants']);
    Route::post('/restaurants', [AdminController::class, 'createRestaurant']);
    Route::post('/restaurants/toggle', [AdminController::class, 'toggleTariff']);
    Route::delete('/restaurants/{id}', [AdminController::class, 'deleteRestaurant']);

    // KPI для дашборда — с проверкой доступа к конкретному тенанту
    Route::middleware(['resolve.tenant', 'ensure.tenant.access'])->group(function () {
        Route::get('/analytics', [AnalyticsController::class, 'getKpiMetrics']);
        Route::get('/analytics/orders-series', [AnalyticsController::class, 'getOrdersSeries']);
    });
});
