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
Route::get('/kitchen/orders', [OrderController::class, 'getCookingOrders']);

// ИСПРАВЛЕНО: Роуты переведены на плоскую структуру без {id} в URL, так как ID передается в теле запроса (body)
Route::post('/kitchen/orders/start', [OrderController::class, 'startCooking']);
// ИСПРАВЛЕНО: Метод переименован в markReady, как в новом коде OrderController
Route::post('/kitchen/orders/ready', [OrderController::class, 'markReady']);



// Для обработки нажатия «В РАБОТУ» (фронтенд шлет POST на /api/orders/{id}/start)
Route::post('/orders/{id}/start', [OrderController::class, 'startCooking']);

// На случай, если фронтенд передает ID внутри тела (FormData) на единый урл:
Route::post('/kitchen/orders/start', [OrderController::class, 'startCooking']);

// Роуты для экрана кухни (kitchen.html) и табло (board.html)
Route::get('/kitchen/orders', [OrderController::class, 'getCookingOrders']);

Route::get('/board/orders', [OrderController::class, 'getBoardOrders']);


// Действия кухни
Route::post('/kitchen/orders/start', [OrderController::class, 'startCooking']);
Route::post('/kitchen/orders/ready', [OrderController::class, 'markReady']);


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


    //tut
    Route::middleware('auth:sanctum')->group(function () {
        // Получение профиля текущего пользователя (для блока внизу сайдбара)
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        // Раздел «Рестораны» и «Метрики» (доступно и админу, и owner'у в пределах его scope)
        Route::apiResource('restaurants', RestaurantController::class);
        Route::get('/metrics/kitchen-performance', [MetricsController::class, 'kitchenPerformance']);

        // Раздел «Клиенты SaaS» (Доступно СТРОГО суперадмину)
        Route::middleware('can:manage-saas')->group(function () {
            Route::get('/saas/owners', [SaaSClientController::class, 'index']);
            Route::post('/saas/owners', [SaaSClientController::class, 'store']);
        });
    });

});
