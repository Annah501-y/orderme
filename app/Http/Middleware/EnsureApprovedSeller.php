<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovedSeller
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! $user instanceof User
            || (! $user->hasRole('admin') && ! $user->isApprovedSeller())
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Seller access is available after your seller account is approved.',
            ], 403);
        }

        return $next($request);
    }
}
