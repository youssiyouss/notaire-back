<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\App;

class LanguageInterceptorMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for a 'lang' parameter in the request (e.g., in the query string or headers)
        $language = $request->header('Accept-Language') ?? $request->query('lang', 'fr');

        // Validate and set the language (fallback to 'en' if invalid)
        if (in_array($language, ['fr', 'ar'])) { // Adjust to your supported languages
            App::setLocale($language);
        } else {
            App::setLocale('fr'); // Default language
        }

        return $next($request);
    }
}
