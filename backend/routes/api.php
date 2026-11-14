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
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/status', [OrderController::class, 'checkStatus']);

// Роуты для экрана кухни (kitchen.html) и табло (board.html)
Route::get('/kitchen/orders', [OrderController::class, 'getCookingOrders']);
Route::get('/board/orders', [OrderController::class, 'getBoardOrders']);

// Действия кухни
Route::post('/kitchen/orders/start', [OrderController::class, 'startCooking']);
Route::post('/kitchen/orders/ready', [OrderController::class, 'markReady']);


/*
|--------------------------------------------------------------------------
| ЗАЩИЩЕННАЯ АДМИНКА И SAAS ПАНЕЛЬ (ПОЛНОСТЬЮ ИСПРАВЛЕНО)
|--------------------------------------------------------------------------
*/

// Публичный вход в админку
Route::post('/admin/login', [AdminController::class, 'login']);

// Закрытые роуты админки (требуют Sanctum токен)
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {

    // Выход и профиль пользователя
    Route::post('/logout', [AdminController::class, 'logout']);
    Route::get('/user', function (\Illuminate\Http\Request $request) {
        return $request->user();
    });

    // Управление пользователями / SaaS партнерами (строго через существующий AdminController)
    Route::get('/owners', [AdminController::class, 'getOwners']);
    Route::post('/users', [AdminController::class, 'createUser']); // Сюда шлем форму создания клиента SaaS

    // Управление ресторанами (строго через существующий AdminController)
    Route::get('/restaurants', [AdminController::class, 'getRestaurants']);
    Route::post('/restaurants', [AdminController::class, 'createRestaurant']); // Сюда шлем форму создания ресторана
    Route::post('/restaurants/toggle', [AdminController::class, 'toggleTariff']);
    Route::delete('/restaurants/{id}', [AdminController::class, 'deleteRestaurant']);

    // Аналитика, KPI и дополнительные метрики
    Route::get('/analytics', [AnalyticsController::class, 'index']);
    Route::get('/analytics/kpi', [AnalyticsController::class, 'getKpiMetrics']);
    Route::get('/analytics/chart', [AnalyticsController::class, 'getChartData']);
    Route::get('/qr', [AdminController::class, 'generateQr']);
});
