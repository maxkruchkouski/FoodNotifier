<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $this->resolveSlug($request);

        if ($slug) {
            $restaurant = Restaurant::withoutGlobalScope('owner')
                ->where('slug', $slug)
                ->first();

            if ($restaurant) {
                app()->instance('tenant', $restaurant);
            }
        }

        return $next($request);
    }

    private function resolveSlug(Request $request): ?string
    {
        // Заголовок — приоритет (для API-запросов с фронтенда)
        $headerValue = $request->header(config('tenancy.header'));
        if ($headerValue) {
            return $headerValue;
        }

        return match (config('tenancy.mode')) {
            'subdomain' => $this->extractSubdomain($request->getHost()),
            'path' => $request->segment(1),
            'query' => $request->query(config('tenancy.query_key')),
            default => null,
        };
    }

    private function extractSubdomain(string $host): ?string
    {
        $baseDomain = config('tenancy.domain');

        if (!str_ends_with($host, '.' . $baseDomain)) {
            return null;
        }

        $subdomain = str_replace('.' . $baseDomain, '', $host);

        return $subdomain === 'www' ? null : $subdomain;
    }
}
