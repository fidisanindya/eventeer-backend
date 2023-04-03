<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Get Token From Header Authorization
            $token = $request->header('Authorization');

            if (!$token) {
                throw new Exception('Authorization header not found');
            }

            $publicKey = env('JWT_PUBLIC_KEY');;

            // Delete Character "Bearer "
            $jwt = str_replace('Bearer ', '', $token);
            
            // Decode JWT Token with Public Key
            $payload = JWT::decode($jwt, new Key($publicKey, env('JWT_ALGO')));

            $request->attributes->add(['user_id' => $payload->sub]);
            
            return $next($request);
        } catch (Exception $e) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
    }
}
