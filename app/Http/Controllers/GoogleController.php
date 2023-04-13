<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserProfile;
use App\Services\JwtAuth;
use Exception;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleController extends Controller
{
    public function redirectToGoogle()
    {
        try {
            return Socialite::driver('google')->redirect();
        } catch (InvalidStateException $e) {
            return Socialite::driver('google')->stateless()->redirect();
        }
    }

    public function handleGoogleCallback(JwtAuth $jwtAuth)
    {
        try {
            $user = Socialite::driver('google')->user();
        } catch (InvalidStateException $e) {
            $user = Socialite::driver('google')->stateless()->user();
        }

        $checkUser = User::where('email', $user->email)->first();

        if($checkUser && !$checkUser->sso_id){
            return response()->json([
                'code' => 409,
                'status' => 'email already registered not using google',
            ],409);
        }

        $findUser = User::where([['sso_id', '=', $user->id]])->first();
        
        if($findUser){
            $token = $jwtAuth->createJwtToken($findUser);
        
            $findUser->token = $token;

            $registrationStep = UserProfile::select('value')->where([['id_user', '=', $findUser->id_user], ['key_name', '=', 'registration_step']])->first();

            $findUser->registration_step = $registrationStep->value;

            return response()->json([
                'code' => 200,
                'status' => 'success',
                'result' => $findUser
            ],200);
        }else{
            User::create([
                'email' => $user->email,
                'sso_id' => $user->id,
                'email_status' => 'verified',
                'password' => Hash::make(Str::random(15)),
                'full_name' => $user->name,
                'profile_picture' => $user->avatar,
                'registered_via' => 'google'
            ]);

            $newUser = User::where('email', $user->email)->first();

            UserProfile::create([
                'id_user' => $newUser->id_user,
                'key_name' => 'registration_step',
                'value' => 2,
            ]);

            $registrationStep = UserProfile::select('value')->where([['id_user', '=', $newUser->id_user], ['key_name', '=', 'registration_step']])->first();

            $token = $jwtAuth->createJwtToken($newUser);
        
            $newUser->token = $token;
            $newUser->registration_step = $registrationStep->value;

            return response()->json([
                'code' => 200,
                'status' => 'success',
                'result' => $newUser
            ],200);
        }
    }

    public function handleCallbackClient(Request $request, JwtAuth $jwtAuth)
    {
        $checkUser = User::where('email', $request->email)->first();

        if($checkUser && !$checkUser->sso_id){
            return response()->json([
                'code' => 409,
                'status' => 'email already registered not using google',
            ],409);
        }

        User::create([
            'email' => $request->email,
            'sso_id' => $request->id,
            'email_status' => 'verified',
            'password' => Hash::make(Str::random(15)),
            'full_name' => $request->name,
            'profile_picture' => $request->avatar,
            'registered_via' => 'google'
        ]);

        $newUser = User::where('email', $request->email)->first();

        UserProfile::create([
            'id_user' => $newUser->id_user,
            'key_name' => 'registration_step',
            'value' => 2,
        ]);

        $registrationStep = UserProfile::select('value')->where([['id_user', '=', $newUser->id_user], ['key_name', '=', 'registration_step']])->first();

        $token = $jwtAuth->createJwtToken($newUser);

        $newUser->token = $token;
        $newUser->registration_step = $registrationStep->value;

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $newUser
        ],200);
    }
}
