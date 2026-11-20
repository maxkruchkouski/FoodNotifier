<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, ['superadmin', 'admin', 'owner'])) {
            return response()->json(['message' => 'Доступ запрещён'], 403);
        }

        return $next($request);
    }
}
