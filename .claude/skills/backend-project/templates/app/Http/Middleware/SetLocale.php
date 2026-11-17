<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $language = strtolower(substr(
            $request->header('Accept-Language', 'en'),
            0,
            2
        ));

        App::setLocale(
            in_array($language, ['ar', 'en'], true) ? $language : 'en'
        );

        return $next($request);
    }
}
