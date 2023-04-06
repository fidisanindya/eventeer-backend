<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\JwtAuth;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(JwtAuth $jwtAuth)
    {
        $user = Socialite::driver('google')->user();

        $checkUser = User::where('email', $user->email)->first();

        if($checkUser){
            return response()->json([
                'code' => 409,
                'status' => 'email already registered not using google',
            ],409);
        }

        $findUser = User::where([['id_social', '=', $user->id], ['email', '=', $user->email]])->first();
        
        if($findUser){
            $token = $jwtAuth->createJwtToken($findUser);
        
            $findUser->token = $token;

            return response()->json([
                'code' => 200,
                'status' => 'success',
                'result' => $findUser
            ],200);
        }else{
            User::create([
                'email' => $user->email,
                'id_social' => $user->id,
                'email_status' => 'verified',
                'password' => Hash::make(Str::random(15)),
                'full_name' => $user->name,
                'profile_picture' => $user->avatar,
                'registered_via' => 'google'
            ]);

            $newUser = User::where('email', $user->email)->first();

            $token = $jwtAuth->createJwtToken($newUser);
        
            $newUser->token = $token;

            return response()->json([
                'code' => 200,
                'status' => 'success',
                'result' => $newUser
            ],200);
        }
    }
}
