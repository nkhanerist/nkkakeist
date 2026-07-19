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
        $supported = config('localization.supported', ['ja', 'en']);
        $locale = $request->session()->get('locale');

        if (! is_string($locale) || ! in_array($locale, $supported, true)) {
            $locale = config('localization.default', 'ja');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
