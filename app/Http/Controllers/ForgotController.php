<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Jobs\ForgotQueue;
use App\Jobs\ForgotQueueMobile;
use App\Models\EmailQueue;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Mail\ForgotPasswordMail;
use App\Http\Controllers\Controller;
use App\Mail\ForgotPasswordMailMobile;
use App\Models\ForgotActivity;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ForgotController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    public function post_send_forgot_email(Request $request){
        // Validate input
        $validator = Validator::make($request->all(), [
            'email'     => 'required|email',
            'hit_from'  => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'      => 422,
                'status'    => 'failed',
                'result'    => $validator->messages(),
            ], 422);
        }

        $checkEmail = User::where('email', $request->email)->first();

        // Email Attempt
        if ($checkEmail){
            $lastAttempt = ForgotActivity::where('email', $checkEmail->email)->where('created_at', '>', Carbon::now()->subMinutes(2))->count();

            if ($lastAttempt >= env('EMAIL_ATTEMPT_TRY')) {
                return response()->json([
                    'code'      => 429,
                    'status'    => 'failed',
                    'result'    => 'Too many attempts',
                ], 429);
            }
            
            if ($lastAttempt == 0) {
                ForgotActivity::where('email', $checkEmail->email)->delete();
            }
        
            ForgotActivity::create([
                'id_user'   => $checkEmail->id_user,
                'email'     => $checkEmail->email,
            ]);
        }

        // Send Email
        if($checkEmail != null){
            $token_web = md5(Str::random(12));
            $token_mobile = mt_rand(100000, 999999);

            $details = [
                'email'     => $checkEmail->email,
                'full_name' => $checkEmail->full_name,
            ];

            if($request->hit_from == 'web'){
                $details['link_to'] = env('LINK_EMAIL_WEB').'/forgot-password/reset-password?token='.$token_web;
            } else if($request->hit_from == 'mobile') {
                $details['otp'] = $token_mobile;
            } else {
                return response()->json([
                    'code'      => 404,
                    'status'    => 'failed',
                    'result'    => 'hit_from body request not available',
                ], 404);
            }
            
            // Log the queue from helper
            if($request->hit_from == 'web') {
                ForgotQueue::dispatch($details);
                $html_web = (new ForgotPasswordMail($details))->render();
                logQueue($checkEmail->email, $html_web, 'Reset Password');
            } else {
                ForgotQueueMobile::dispatch($details);
                $html_mobile = (new ForgotPasswordMailMobile($details))->render();
                logQueue($checkEmail->email, $html_mobile, 'Reset Password');
            }
            
            User::where('id_user', $checkEmail->id_user)
                ->update([
                    'forgotten_password'        => ($request->hit_from == 'web') ? $token_web : $token_mobile,
                    'forgotten_password_time'   => strtotime(Carbon::now()->toDateTimeString()),
                ]);

            return response()->json([
                'code'      => 200,
                'status'    => 'success',
                'result'    => [
                    'message'   => 'Email sent successfully',
                    'email' => $checkEmail->email
                ],
            ], 200);
        }else{
            return response()->json([
                'code'      => 404,
                'status'    => 'failed',
                'result'    => 'Email not found',
            ], 404);
        }
    }

    public function post_check_code(Request $request){
        $validator = Validator::make($request->all(), [
            'token' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'      => 422,
                'status'    => 'failed',
                'result'    => $validator->messages(),
            ], 422);
        }

        $dataUser = User::where('forgotten_password', $request->token)->first();

        if ($dataUser != null) {
            $generatedTime = $dataUser->forgotten_password_time;
            $checkTime = strtotime(Carbon::now()->toDateTimeString()) - $generatedTime;
            if ($checkTime <= 300) {
                return response()->json([
                    'code'      => 200,
                    'status'    => 'success',
                    'result'    => 'Token Valid',
                ], 200);
            } else {
                return response()->json([
                    'code'      => 403,
                    'status'    => 'failed',
                    'result'    => 'Token Timeout',
                ], 403);
            }
        } else {
            return response()->json([
                'code'      => 404,
                'status'    => 'failed',
                'result'    => 'User not found',
            ], 404);
        }
    }

    public function post_reset_password(Request $request){
        $validator = Validator::make($request->all(), [
            'token'             => 'required',
            'password'          => 'required|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'      => 422,
                'status'    => 'failed',
                'result'    => $validator->messages(),
            ], 422);
        }

        $dataUser = User::where('forgotten_password', $request->token)->first();

        if ($dataUser != null) {
            $lastPassword = $dataUser->password;
            
            if(Hash::check($request->password, $lastPassword)){
                return response()->json([
                    'code'      => 422,
                    'status'    => 'failed',
                    'result'    => 'Password has the same value as last password',
                ], 422);
            } else {
                $password = Hash::make($request->password);
        
                User::where('id_user', $dataUser->id_user)
                    ->update([
                        'password' => $password,
                        'forgotten_password' => null,
                        'forgotten_password_time'   => null,
                    ]);
    
                return response()->json([
                    'code'      => 200,
                    'status'    => 'success',
                    'result'    => 'Password updated successfully',
                ], 200);
            }
        } else {
            return response()->json([
                'code'      => 404,
                'status'    => 'failed',
                'result'    => 'User not found',
            ], 404);
        }
    }

    public function checkOtpCode(Request $request)
    {
        $user = User::where('email', $request->email)->first();
        
        if ($user) {
            if ($request->code == $user->forgotten_password) {
                $generated_time = $user->forgotten_password_time;
                $check_time = strtotime(Carbon::now()->toDateTimeString()) - $generated_time;

                if ($check_time <= 180) {
                    return response()->json(['code' => 200, 'message' => 'Success'], 200);
                } else {
                    return response()->json(['code' => 403, 'message' => 'Code Expired'], 403);
                }
            } else {
                return response()->json(['code' => 403, 'message' => 'Invalid Code'], 403);
            }
        } else {
            return response()->json(['code' => 404, 'message' => 'User Not Found'], 404);
        }
    }
}
