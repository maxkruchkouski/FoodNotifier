<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        $user = $request->user();

        if (!$tenant) {
            return response()->json([
                'success' => false,
                'error' => 'tenant_not_resolved',
                'message' => 'Ресторан не найден',
            ], 404);
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'unauthenticated',
            ], 401);
        }

        if ($user->is_superadmin) {
            return $next($request);
        }

        $hasAccess = $user->restaurants()
            ->where('restaurants.id', $tenant->id)
            ->exists();

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'error' => 'forbidden',
                'message' => 'Нет доступа к этому ресторану',
            ], 403);
        }

        return $next($request);
    }
}
