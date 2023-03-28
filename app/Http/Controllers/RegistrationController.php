<?php

namespace App\Http\Controllers;

use App\Mail\EmailVerification;
use App\Models\City;
use App\Models\Interest;
use App\Models\Profession;
use App\Models\User;
use App\Models\UserProfile;
use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class RegistrationController extends Controller
{
    public function registration(Request $request){
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'confirm_password' => 'required',
        ]);

        $checkEmail = User::where('email', $request->email)->first();

        if($checkEmail != null){
            return response()->json([
                'code' => 409,
                'status' => 'Email already exists',
                'result' => null
            ],409);
        }

        if($request->password == $request->confirm_password){
            $passwordHash = Hash::make($request->password);

            $activation_code = hash('SHA1', time());

            User::create([
                'email' => $request->email,
                'password' => $passwordHash,
                'activation_code' => $activation_code
            ]);

            $user = User::where('email', $request->email)->first();

            Mail::to($request->email)->send(new EmailVerification($user));

            UserProfile::create([
                'id_user' => $user->id_user,
                'key_name' => 'registration_step',
                'value' => 1,
            ]);

            return response()->json([
                'code' => 200,
                'status' => 'Registration Successfull',
                'result' => $user
            ],200);

        }
        
        return response()->json([
            'code' => 401,
            'status' => 'Confirm password not match',
            'result' => null
        ], 401);
    }

    public function resend_verification_link(Request $request){
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        if($user){        
            $activation_code = hash('SHA1', time());

            User::where('email', $request->email)->update([
                'activation_code' => $activation_code
            ]);

            Mail::to($user->email)->send(new EmailVerification($user));
            
            return response()->json([
                'code' => 200,
                'status' => 'Success Resend Link',
            ], 200);
        }

        return response()->json([
            'code' => 401,
            'status' => 'Failed Resend Link',
        ], 401);
    }

    public function verification_email(Request $request){
        $request->validate([
            'email' => 'required|email',
            'activation_code' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if($user->activation_code == $request->activation_code){
            $query = User::where('email', $request->email)->update([
                'email_status' => 'verified',
            ]);

            if($query){
                User::where('email', $request->email)->update([
                    'activation_code' => null
                ]);
                
                $userRes = User::where('email', $request->email)->first();
                
                UserProfile::where([['id_user', '=', $userRes->id_user], ['key_name', '=', 'registration_step']])->update([
                    'value' => 2,
                ]);

                return response()->json([
                    'code' => 200,
                    'status' => 'Verification Succesfull',
                    'result' => $userRes
                ], 200);

            }else{
                return response()->json([
                    'code' => 401,
                    'status' => 'Verification Failed',
                ], 401);
            }
        }

        return response()->json([
            'code' => 401,
            'status' => 'activation code not match',
        ], 401);
    }

    public function get_interest(){
        $interest = Interest::where('created_by', 1)->get();

        if($interest){
            return response()->json([
                'code' => 200,
                'status' => 'success',
                'result' => $interest
            ], 200);
        }

        return response()->json([
            'code' => 401,
            'status' => 'interest not found',
        ], 401); 
    }

    public function choose_interest(Request $request){
        $request->validate([
            'id_user' => 'required',
            'interest_name' => 'required',
        ]);

        foreach($request->interest_name  as $int){
            $interest = Interest::select('id_interest')->where('interest_name', $int)->first();

            UserProfile::create([
                'id_user' => $request->id_user,
                'key_name' => 'id_interest',
                'value' => $interest->id_interest
            ]);
        }

        UserProfile::where([['id_user', '=', $request->id_user], ['key_name', '=', 'registration_step']])->update([
            'value' => 3,
        ]);

        return response()->json([
            'code' => 200,
            'status' => 'success',
        ], 200);
    }

    public function get_location(){
        $location = City::all();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $location
        ], 200);
    }

    public function submit_profile(Request $request){
        $dataValidate = $request->validate([
            'id_user' => 'required',
            'profile_picture' => 'image',
            'full_name' => 'required|string',
            'city' => 'required',
            'gender' => 'required',
        ]);

        if($request->profile_picture){
            $filename = $request->id_user . '_profile_picture.' . $request->profile_picture->getClientOriginalExtension();

            $credentials = new Credentials($_ENV['AWS_ACCESS_KEY_ID'], $_ENV['AWS_SECRET_ACCESS_KEY']);

            $s3 = new S3Client([
                'version' => 'latest',
                'region' => 'auto',
                'endpoint' => "https://" . config('filesystems.disks.s3.account') . "." . "r2.cloudflarestorage.com",
                'credentials' => $credentials
            ]);

            $key = "userfiles/images/profile_picture/" . $filename;
            
            $s3->putObject([
                'Bucket' => config('filesystems.disks.s3.bucket'),
                'Key' => $key,
                'Body' => file_get_contents($request->profile_picture),
                'ACL'    => 'public-read',
            ]);

            $imageUrl = config('filesystems.disks.s3.bucketurl') . "/" . $key;

            if($dataValidate){
                User::where('id_user', $request->id_user)->update([
                    'full_name' => $request->full_name,
                    'id_city' => $request->city,
                    'gender' => $request->gender,
                    'profile_picture' => $imageUrl
                ]);
    
                $user = User::where('id_user', $request->id_user)->first();
    
                UserProfile::where([['id_user', '=', $user->id_user], ['key_name', '=', 'registration_step']])->update([
                    'value' => 4,
                ]);
    
                return response()->json([
                    'code' => 200,
                    'status' => 'success',
                    'result' => $user
                ], 200);
            }
        }else{
            if($dataValidate){
                User::where('id_user', $request->id_user)->update([
                    'full_name' => $request->full_name,
                    'id_city' => $request->city,
                    'gender' => $request->gender,
                ]);
    
                $user = User::where('id_user', $request->id_user)->first();
    
                UserProfile::where([['id_user', '=', $user->id_user], ['key_name', '=', 'registration_step']])->update([
                    'value' => 4,
                ]);
    
                return response()->json([
                    'code' => 200,
                    'status' => 'success',
                    'result' => $user
                ], 200);
            }
        }

        return response()->json([
            'code' => 401,
            'status' => 'Validation Failed',
        ], 401);
    }

    public function get_profession(){
        $profession = Profession::where('created_by', 1)->get();

        if($profession){
            return response()->json([
                'code' => 200,
                'status' => 'success',
                'result' => $profession
            ], 200);
        }

        return response()->json([
            'code' => 401,
            'status' => 'profession not found',
        ], 401); 
    }

    public function submit_profession(Request $request){
        $request->validate([
            'id_user' => 'required|numeric',
            'company' => 'required|string',
            'profession' => 'required',
        ]);

        UserProfile::create([
            'id_user' => $request->id_user,
            'key_name' => 'company',
            'value' => $request->company,
        ]);

        $profession = Profession::where('job_title', $request->profession)->first();

        UserProfile::create([
            'id_user' => $request->id_user,
            'key_name' => 'id_job',
            'value' => $profession->id_job,
        ]);

        UserProfile::where([['id_user', '=', $request->id_user], ['key_name', '=', 'registration_step']])->update([
            'value' => 5,
        ]);

        return response()->json([
            'code' => 200,
            'status' => 'success add profession and company',
        ], 200);
    }

    public function get_profile_user(Request $request){
        $token = $request->header('Authorization');

        $publicKey = file_get_contents(base_path('public.pem'));

        $jwt = str_replace('Bearer ', '', $token);
        $payload = JWT::decode($jwt, new Key($publicKey, env('JWT_ALGO')));

        $id = $payload->data->id_user;

        $user = User::where('id_user', $id)->get();

        if($user){
            return response()->json([
                'code' => 200,
                'status' => 'success',
                'result' => $user
            ], 200);
        }

        return response()->json([
            'code' => 401,
            'status' => 'user not found',
        ], 401); 
    }

    public function get_profile_user_id(Request $request){
        $request->validate([
            'id_user' => 'required|string',
        ]);

        $user = User::where('id_user', $request->id_user)->first();

        if($user){
            return response()->json([
                'code' => 200,
                'status' => 'success',
                'result' => $user
            ], 200);
        }

        return response()->json([
            'code' => 401,
            'status' => 'user not found',
        ], 401); 
    }
}