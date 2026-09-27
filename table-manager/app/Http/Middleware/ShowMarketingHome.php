<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ShowMarketingHome
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('vtt.seo')) {
            return response()->view('marketing.home');
        }

        return $next($request);
    }
}
