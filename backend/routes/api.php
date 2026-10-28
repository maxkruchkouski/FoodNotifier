<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OrderController;

Route::prefix('v1')->group(function () {
    // Взаимодействие с админкой и аналитикой
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::post('/restaurants', [DashboardController::class, 'storeRestaurant']);
    Route::patch('/restaurants/{id}/toggle', [DashboardController::class, 'toggleActive']);
    Route::delete('/restaurants/{id}', [DashboardController::class, 'destroyRestaurant']);
    Route::post('/analytics/track', [DashboardController::class, 'track']);

    // Доступ к кухонному оборудованию и заказам гостя с проверкой подписки
    Route::middleware(['billing'])->group(function () {
        Route::post('/orders', [OrderController::class, 'store']);
        Route::get('/orders/{id}/status', [OrderController::class, 'checkStatus']);
        Route::get('/kitchen/orders', [OrderController::class, 'getCookingOrders']);
        Route::patch('/kitchen/orders/{id}/start', [OrderController::class, 'startCooking']);
        Route::patch('/kitchen/orders/{id}/ready', [OrderController::class, 'markReady']);
        Route::get('/board/orders', [OrderController::class, 'getBoardOrders']);
    });
});

// ДОБАВЛЯЕМ СЮДА НАШ ТЕСТОВЫЙ РОУТ ДЛЯ ПРОВЕРКИ СВЯЗИ С ФРОНТЕНДОМ:
Route::get('/test', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Laravel API на порту 8000 работает!'
    ]);
});
