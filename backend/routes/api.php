use App\Http\Controllers\AdminController;
use App\Http\Controllers\AnalyticsController;

// Управление пользователями и ресторанами (SaaS)
Route::post('/admin/users', [AdminController::class, 'createUser']);
Route::post('/admin/restaurants', [AdminController::class, 'createRestaurant']);
Route::post('/admin/restaurants/toggle', [AdminController::class, 'toggleTariff']);
Route::delete('/admin/restaurants/{id}', [AdminController::class, 'deleteRestaurant']);
Route::get('/admin/owners', [AdminController::class, 'getOwners']);
Route::get('/admin/restaurants', [AdminController::class, 'getRestaurants']);

// Аналитика, Метрики и KPI поваров
Route::get('/admin/analytics/kpi', [AnalyticsController::class, 'getKpiMetrics']);
Route::get('/admin/analytics/chart', [AnalyticsController::class, 'getChartData']);
Route::get('/admin/qr', [AdminController::class, 'generateQr']);
