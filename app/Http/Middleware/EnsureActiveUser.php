<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_active) {
            auth()->logout();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Account is inactive.'], 403);
            }

            return redirect()->route('login');
        }

        return $next($request);
    }
}
