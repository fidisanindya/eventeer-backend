<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Jobs\ForgotQueue;
use App\Models\EmailQueue;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Mail\ForgotPasswordMail;
use App\Http\Controllers\Controller;
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

        // Send Email Attempt
        if ($checkEmail){
            $lastAttempt = ForgotActivity::where('email', $checkEmail->email)->latest()->first();
            
            if ($lastAttempt != null){
                if (strtotime(Carbon::now()->toDateTimeString()) - strtotime($lastAttempt->created_at) > env('EMAIL_ATTEMPT_TIMEOUT')){
                    // After 6 hours, reset the attempt
                    ForgotActivity::where('email', $checkEmail->email)->delete();
                }
            }

            $attempt = ForgotActivity::where('email', $checkEmail->email)->count();

            if($attempt >= env('EMAIL_ATTEMPT_TRY')) {
                return response()->json([
                    'code'      => 429,
                    'status'    => 'failed',
                    'result'    => 'Too many attempts',
                ], 429);
            } else {
                // Create new log attempt
                ForgotActivity::create([
                    'id_user'   => $checkEmail->id_user,
                    'email'     => $checkEmail->email,
                ]);
            }
        }

        // Send Email
        if($checkEmail != null){
            $token = md5(Str::random(12));

            $details = [
                'email'     => $checkEmail->email,
                'full_name' => $checkEmail->full_name,
            ];

            if($request->hit_from == 'web'){
                $details['link_to'] = env('LINK_EMAIL_WEB').'/forgot-password/reset-password?token='.$token;
            } else if($request->hit_from == 'mobile') {
                $details['link_to'] = env('LINK_EMAIL_MOBILE').'/forgot-password/reset-password?token='.$token;
            } else {
                return response()->json([
                    'code'      => 404,
                    'status'    => 'failed',
                    'result'    => 'hit_from body request not available',
                ], 404);
            }
            
            ForgotQueue::dispatch($details);
            
            $html = (new ForgotPasswordMail($details))->render();
            $logQueue = [
                'to'            => $checkEmail->email,
                'cc'            => '',
                'bcc'           => '',
                'message'       => $html,
                'status'        => 'sent',
                'date'          => date('Y-m-d H:i:s'),
                'headers'       => '',
                'attachment'    => '0',
                'subject'       => 'Reset Password',
                'is_broadcast'  => 0,
                'id_event'      => null,
                'id_broadcast'  => 0,
            ];

            EmailQueue::create($logQueue);

            User::where('id_user', $checkEmail->id_user)
                ->update([
                    'forgotten_password'        => $token,
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
}
