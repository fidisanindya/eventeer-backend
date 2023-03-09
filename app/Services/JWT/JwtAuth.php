<?php

namespace App\Services\Jwt;

use App\Services\Auth\JwtBuilder;
use App\Models\User;
use Carbon\CarbonInterface;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Auth;

class JwtAuth
{
    public function createJwtToken(User $user)
    {
        $privateKey = file_get_contents(base_path('private.pem'));
        
        $authTokenPayload = [
                "iss" => env('APP_URL'),
                "aud" => env('APP_URL'),
                "iat" => time(),
                "nbf" => time() - 10,
                "exp" => time() + (60 * 60 * 24), // 1 Days
                "sub" => $user->id,
                "data" => $user
            ];

        $token = JWT::encode($authTokenPayload, $privateKey, env("JWT_ALGO"));
        
        return $token;
    }   
}