<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof Customer) {
            abort(403, 'This action is unauthorized.');
        }

        return $next($request);
    }
}
