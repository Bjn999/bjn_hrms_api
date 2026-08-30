<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language');

        if (!$locale) {
            $locale = $request->input('lang', 'ar');
        }

        // Extract primary language code (e.g. 'en-US' -> 'en', 'ar-EG' -> 'ar')
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }

        if (str_contains($locale, ',')) {
            $locale = explode(',', $locale)[0];
        }

        if (in_array(strtolower($locale), ['ar', 'en'])) {
            app()->setLocale(strtolower($locale));
        } else {
            app()->setLocale('ar');
        }

        return $next($request);
    }
}
