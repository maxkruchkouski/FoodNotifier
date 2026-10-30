<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\OrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ПУБЛИЧНЫЕ РОУТЫ ДЛЯ КЛИЕНТОВ, КУХНИ И ТАБЛО
|--------------------------------------------------------------------------
*/
// Получить список активных ресторанов для выбора гостем
Route::get('/restaurants/list', [OrderController::class, 'getActiveRestaurants']);


// Роуты для заказов (клиентский интерфейс index.html)
// ИСПРАВЛЕНО: 'createOrder' -> 'store', 'status' -> 'checkStatus'
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/status', [OrderController::class, 'checkStatus']);

// Роуты для экрана кухни (kitchen.html) и табло (board.html)
// ИСПРАВЛЕНО: 'kitchenIndex' -> 'getCookingOrders'
Route::get('/kitchen/orders', [OrderController::class, 'getCookingOrders']);

// Обработка статуса (для старта готовки / готовности)
Route::post('/orders/{id}/status', [OrderController::class, 'startCooking']);


/*
|--------------------------------------------------------------------------
| ЗАЩИЩЕННАЯ АДМИНКА И SAAS ПАНЕЛЬ
|--------------------------------------------------------------------------
*/

// Публичный вход в админку
Route::post('/admin/login', [AdminController::class, 'login']);

// Закрытые роуты админки (требуют Sanctum токен)
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {

    Route::post('/logout', [AdminController::class, 'logout']);
    Route::get('/analytics', [AnalyticsController::class, 'index']);

    // Управление пользователями и ресторанами (SaaS)
    Route::post('/users', [AdminController::class, 'createUser']);
    Route::get('/owners', [AdminController::class, 'getOwners']);

    Route::get('/restaurants', [AdminController::class, 'getRestaurants']);
    Route::post('/restaurants', [AdminController::class, 'createRestaurant']);
    Route::post('/restaurants/toggle', [AdminController::class, 'toggleTariff']);
    Route::delete('/restaurants/{id}', [AdminController::class, 'deleteRestaurant']);

    // Аналитика и метрики
    Route::get('/analytics/kpi', [AnalyticsController::class, 'getKpiMetrics']);
    Route::get('/analytics/chart', [AnalyticsController::class, 'getChartData']);
    Route::get('/qr', [AdminController::class, 'generateQr']);
});
