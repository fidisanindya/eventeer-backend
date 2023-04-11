<?php

namespace App\Http\Controllers;

use DateTime;
use App\Models\User;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Models\LoginActivity;
use App\Models\UserProfile;
use App\Services\JwtAuth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use stdClass;

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

    public function sso_login_post(Request $request, JwtAuth $jwtAuth){
        $validator = Validator::make($request->all(), [
            'nik'       => 'required',
            'password'  => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'      => 422,
                'status'    => 'failed',
                'result'    => $validator->messages(),
            ], 422);
        }

        $client = new Client();

        $response = $client->post('https://apifactory.telkom.co.id:8243/hcm/auth/v1/token', [
            'json' => [
                'username' => $request->nik,
                'password' => $request->password,
            ],
        ]);

        $responseBody = json_decode($response->getBody(), true);

        if ($responseBody['status'] == 'success') {
            $response = $client->get('https://apifactory.telkom.co.id:8243/hcm/pwb/v1/profile/' . $responseBody['data']['auth'], [
                'headers' => [
                    'X-Authorization' => 'Bearer ' . $responseBody['data']['jwt']['token']
                ]
            ]);

            $responseBody = json_decode($response->getBody(), true);
            $checkUser = User::where('sso_id', $request->nik)->first();
            
            if ($checkUser == null){
                // Registration
                date_default_timezone_set('Asia/Jakarta');
                $dataUser = [
                    'full_name' => $responseBody['data']['dataPosisi']['NAMA'],
                    'email' => $responseBody['data']['dataPosisi']['EMAIL'],
                    'sso_id' => $request->nik,
                    'password' => Hash::make($request->nik),
                    'email_status' => "verified",
                    'registered_via' => "telkom",
                    'access' => "user",
                ];
                
                $create = User::create($dataUser);
                $id_user = $create->id;

                $data_step_reg       = [
                    'id_user' => $id_user,
                    'key_name' => 'registration_step',
                    'value' => 3,
                ];

                $step = UserProfile::where('id_user', $id_user)->first();

                if(!$step){
                    UserProfile::create($data_step_reg);
                } else{
                    UserProfile::where('id_user_profile', $step->id_user_profile)->update($data_step_reg);
                }
            }
        
            $user = User::where('sso_id', $request->nik)->first();
            $reg_step = UserProfile::where('id_user', $user->id_user)->where('key_name', 'registration_step')->latest()->first();
            // Generate JWT
            $token = $jwtAuth->createJwtToken($user);
            $result = new stdClass;
            $result->token = $token;
            $result->registration_step = $reg_step->value;
            
            // Save Login Activity
            $this->saveLoginActivity($request, $user, true);
            return response()->json([
                'code'      => 200,
                'status'    => 'success',
                'result'    => $result,
            ], 200);
        } else {
            $user = User::where('sso_id', $request->nik)->first();
            if($user){
                $lastFailedAttempt = LoginActivity::where('id_user', $user->id_user)->where('is_successful', false)->where('created_at', '>', Carbon::now()->subMinutes(1))->count();
                if($lastFailedAttempt >= 5) {
                    return response()->json([
                        'code'      => 429,
                        'status'    => 'failed',
                        'result'    => 'Too many attempts',
                    ], 429);
                }
                $this->saveLoginActivity($request, $user, false);
            }
            return response()->json([
                'code'      => 404,
                'status'    => 'failed',
                'result'    => 'NIK not found',
            ], 404);
        }
    }
}