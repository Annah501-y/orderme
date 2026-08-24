<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success'=>false,
        'message'=>'unaunthenticated.'
        ],401);
        }
        if (! in_array($user->role->value, $roles, true)) {
            return response()->json(['success'=>false,
            'message'=> 'you are not authorized to perform this action.'],403);
        }
        return $next($request);     
}

  
}
