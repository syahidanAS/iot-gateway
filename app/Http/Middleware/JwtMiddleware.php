<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['message' => 'Token not provided'], 401);
        }

        $token = str_replace('Bearer ', '', $authHeader);

        $decoded = verify_jwt($token);

        if (!$decoded) {
            return response()->json(['message' => 'Invalid token'], 401);
        }

        // inject user_id ke request
        $request->merge(['user_id' => $decoded->sub]);

        return $next($request);
    }
}
