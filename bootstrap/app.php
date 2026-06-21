<?php

// Compatibility fix for mb_split() removed in PHP 8.0
if (!function_exists('mb_split')) {
    function mb_split($pattern, $string, $limit = -1) {
        // mb_split was essentially preg_split with UTF-8 support
        // In PHP 8.0+, we can just use preg_split with the /u flag
        if (strpos($pattern, '/') === false) {
            $pattern = '/' . preg_quote($pattern, '/') . '/u';
        } else if (strpos($pattern, '/u') === false && substr($pattern, -1) === '/') {
            $pattern = substr($pattern, 0, -1) . '/u';
        }
        return preg_split($pattern, $string, $limit);
    }
}

use October\Rain\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // web: __DIR__.'/../routes/web.php',
        // commands: __DIR__.'/../routes/console.php',
        // health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
