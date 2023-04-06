<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;

class JwtAuth
{
    public function createJwtToken($user)
    {
        $privateKey = env('JWT_PRIVATE_KEY');
        
        $authTokenPayload = [
                "iss" => env('APP_URL'),
                "aud" => env('APP_URL'),
                "iat" => time(),
                "nbf" => time() - 10,
                "exp" => time() + (60 * 60 * 24), // 1 hari
                "sub" => $user->id,
                "data" => $user
            ];

        $token = JWT::encode($authTokenPayload, $privateKey, env("JWT_ALGO"));
        
        return $token;
    }   
}