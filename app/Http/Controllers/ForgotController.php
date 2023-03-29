<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Jobs\EmailQueue as JobsEmailQueue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Models\EmailQueue as ModelEmailQueue;

class ForgotController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    public function post_send_forgot_email(Request $request){
        $email = $request->validate([
            'email' => 'required|email:dns',
        ]);
        
        $checkEmail = User::where('email', $email)->first();

        if($checkEmail != null){
            $token = md5(Str::random(12));

            $details = [
                'title'     => 'Reset Password Akun Eventeer',
                'email'     => $checkEmail->email,
                'full_name' => $checkEmail->full_name,
                'token'     => $token,
            ];
            
            JobsEmailQueue::dispatch($details);
            
            $logQueue = [
                'to'            => $checkEmail->email,
                'cc'            => '',
                'bcc'           => '',
                'message'       => null,
                'status'        => 'sent',
                'date'          => date('Y-m-d H:i:s'),
                'headers'       => null,
                'attachment'    => '0',
                'subject'       => 'Reset Password',
                'is_broadcast'  => 0,
                'id_event'      => null,
                'id_broadcast'  => 0,
            ];

            ModelEmailQueue::create($logQueue);

            User::where('id_user', $checkEmail->id_user)
                ->update([
                    'forgotten_password'        => $token,
                    'forgotten_password_time'   => strtotime(Carbon::now()->toDateTimeString()),
                ]);

            $result = [];
            $result['message'] = 'Email sent successfully';
            $result['email'] = $checkEmail->email;

            return response()->json([
                'code'      => 200,
                'status'    => 'success',
                'result'    => $result,
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
        $token = $request->validate([
            'token' => 'required'
        ]);

        $dataUser = User::where('forgotten_password', $token['token'])->first();

        if ($dataUser != null) {
            $generatedTime = $dataUser->forgotten_password_time;
            $checkTime = strtotime(Carbon::now()->toDateTimeString()) - $generatedTime;
            if ($checkTime <= 300) {
                return response()->json([
                    'code'      => 200,
                    'status'    => 'success',
                    'result'    => $dataUser,
                ], 200);
            } else {
                return response()->json([
                    'code'      => 401,
                    'status'    => 'failed',
                    'result'    => 'Token Timeout',
                ], 401);
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
        $validatedData = $request->validate([
            'token'             => 'required',
            'password'          => 'required|min:8|same:confirm_password',
            'confirm_password'  => 'required|min:8',
        ]);

        $dataUser = User::where('forgotten_password', $validatedData['token'])->first();

        if ($dataUser != null) {
            $password = bcrypt($validatedData['password']);
    
            User::where('id_user', $dataUser->id_user)
                ->update(['password' => $password]);

            return response()->json([
                'code'      => 200,
                'status'    => 'success',
                'result'    => [
                    'message'   => 'Password updated successfully',
                    'user'      => $dataUser,
                ],
            ], 200);
        } else {
            return response()->json([
                'code'      => 404,
                'status'    => 'failed',
                'result'    => 'User not found',
            ], 404);
        }
    }
}
