<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

class EnforceCsrfToken extends PreventRequestForgery
{
    protected function hasValidOrigin($request)
    {
        // Laravel 13 normally accepts Sec-Fetch-Site: same-origin without a token.
        // This application's stricter policy requires the official XSRF token too.
        return false;
    }
}
