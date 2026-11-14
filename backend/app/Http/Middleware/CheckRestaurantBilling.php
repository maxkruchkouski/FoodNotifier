<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRestaurantBilling
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'error' => 'tenant_not_resolved',
                'message' => 'Ресторан не найден',
            ], 404);
        }

        if (!$tenant->is_active) {
            return response()->json([
                'success' => false,
                'error' => 'billing_blocked',
                'message' => 'Доступ заблокирован. Оплатите тариф.',
            ], 403);
        }

        return $next($request);
    }
}
