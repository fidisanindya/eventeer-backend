<?php

namespace App\Http\Controllers;

use App\Models\LoginActivity;
use App\Models\User;
use App\Services\Jwt\JwtAuth;
use DateTime;
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

        if ($user) {
            $userLoginActivities = $user->login_activities()->limit(5)->orderBy('created_at', 'desc')->get();
            $latestLoginActivity = $userLoginActivities->first();
            $blockLoginAttempts = 5;
            $isAttemptsBlocked = false;
            $isSuccessLoginDetected = false;

            if (count($userLoginActivities) == $blockLoginAttempts) {
                $endActivity = $userLoginActivities->last();
                $latestActivityTime = new DateTime($latestLoginActivity->created_at);
                $endActivityTime = new DateTime($endActivity->created_at);
                $datetimeNow = new DateTime();

                $timestampInterval = $latestActivityTime->getTimestamp() - $endActivityTime->getTimestamp();
                $blockLoginInterval = 60 * 10; // 10 minutes interval login attempt before user blocked from login
                $isLoginAttemptReached = $timestampInterval <= $blockLoginInterval;

                $blockTime = 60 * 15; // 15 minutes block user from login
                $blockTimeRemaining = $datetimeNow->getTimestamp() - $latestActivityTime->getTimestamp();
                $isUserBlockedFromLogin = $blockTimeRemaining <= $blockTime;

                if ($isLoginAttemptReached && $isUserBlockedFromLogin) {
                    $isAttemptsBlocked = true;
                }
            }

            foreach ($userLoginActivities as $loginActivity) {
                if (boolval($loginActivity->is_successful)) {
                    $isSuccessLoginDetected = true;
                }
            }

            if ($isAttemptsBlocked && !$isSuccessLoginDetected) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Too many attempts',
                ], 401);
            }
        }
        
        if (Hash::check($credentials['password'], $user->password)) {
            // Create JWT Token
            $this->saveLoginActivity($request, $user, true);
            $token = $jwtAuth->createJwtToken($user);
            
            $user->token = $token;

            $response = [
                'code' => app('Illuminate\Http\Response')->status(),
                'status' => 'success',
                'result' => $user,
            ];

            return response($response, 200);

        }else if ($user) {
            $this->saveLoginActivity($request, $user, false);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Unauthorized',
        ], 401);
    }

    private function saveLoginActivity(Request $request, $user, bool $isSuccessful)
    {
        if ($user) {
            LoginActivity::create([
                'id_user' => $user->id_user,
                'user_agent' => $request->header('User-Agent'),
                'ipv4_address' => $request->ip(),
                'is_successful' => $isSuccessful
            ]);
        }
    }
}