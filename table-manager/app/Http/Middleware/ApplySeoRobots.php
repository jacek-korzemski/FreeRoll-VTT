<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplySeoRobots
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('robots.txt', 'sitemap.xml')) {
            return $response;
        }

        $indexable = config('vtt.seo') && $request->routeIs('home', 'tutorial');

        if (! $indexable) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
