<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Jwt\JwtAuth;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request, JwtAuth $jwtAuth){
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string'
        ]);

        $user = User::where('email', $credentials['email'])->first();
        if (Hash::check($credentials['password'], $user->password)) {
            // Create JWT Token
            $token = $jwtAuth->createJwtToken($user);
            
            return response()->json([
                'status' => 'success',
                'user' => $user,
                'authorization' => [
                    'token' => $token,
                    'type' => 'bearer',
                ]
            ]);

        }

        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized',
        ], 401);
    }
}
