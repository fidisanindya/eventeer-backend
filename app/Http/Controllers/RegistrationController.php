<?php

namespace App\Http\Controllers;

use App\Jobs\UploadImage;
use Image;
use Carbon\Carbon;
use App\Models\City;
use App\Models\User;
use Aws\S3\S3Client;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\Interest;
use App\Models\EmailQueue;
use App\Models\Profession;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use App\Jobs\VerificationQueue;
use App\Mail\EmailVerification;
use App\Models\Company;
use App\Models\EmailVerifActivity;
use Aws\Credentials\Credentials;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Services\JwtAuth;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class RegistrationController extends Controller
{
    public function registration(Request $request, JwtAuth $jwtAuth){
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $checkEmail = User::where('email', $request->email)->first();

        if($checkEmail != null){
            return response()->json([
                'code' => 409,
                'status' => 'Email already exists',
                'result' => null
            ],409);
        }

        $passwordHash = Hash::make($request->password);

        $activation_code = hash('SHA1', time());

        User::create([
            'email' => $request->email,
            'password' => $passwordHash,
            'activation_code' => $activation_code
        ]);

        $user = User::where('email', $request->email)->first();
        // Create new log attempt
        EmailVerifActivity::create([
            'id_user'   => $user->id_user,
            'email'     => $user->email,
        ]);
        $details = [
            'email'     => $user->email,
        ];

        if($request->hit_from == 'web') {
            $details['link_to'] = env('LINK_EMAIL_WEB').'/register?activation_code='.$activation_code;
        } elseif ($request->hit_from == 'mobile'){
            $details['link_to'] = env('LINK_EMAIL_MOBILE').'/register?activation_code='.$activation_code;
        } else {
            return response()->json([
                'code'      => 404,
                'status'    => 'failed',
                'result'    => 'hit_from body request not available',
            ], 404);
        } 

        VerificationQueue::dispatch($details);

        $html = (new EmailVerification($details))->render();
        $this->logQueue($user->email, $html, 'Email Verification');

        UserProfile::create([
            'id_user' => $user->id_user,
            'key_name' => 'registration_step',
            'value' => 1,
        ]);

        $token = $jwtAuth->createJwtToken($user);

        $user->token = $token;
        
        return response()->json([
            'code' => 200,
            'status' => 'Registration Successfull',
            'result' => $user
        ],200);
    }

    public function resend_verification_link(Request $request, JwtAuth $jwtAuth){
        $request->validate([
            'email' => 'required|email'
        ]);

        $user = User::where('email', $request->email)->first();

        $token = $jwtAuth->createJwtToken($user);

        // Email Attempt
        if ($user){
            $lastAttempt = EmailVerifActivity::where('email', $user->email)->where('created_at', '>', Carbon::now()->subMinutes(5))->count();

            if ($lastAttempt >= env('EMAIL_ATTEMPT_TRY')) {
                return response()->json([
                    'code'      => 429,
                    'status'    => 'failed',
                    'result'    => 'Too many attempts',
                ], 429);
            }
            
            EmailVerifActivity::create([
                'id_user'   => $user->id_user,
                'email'     => $user->email,
            ]);
        }

        // Send Email
        if($user){        
            $activation_code = hash('SHA1', time());

            User::where('email', $request->email)->update([
                'activation_code' => $activation_code
            ]);
            
            $details = [
                'email'     => $user->email,
            ];

            if($request->hit_from == 'web') {
                $details['link_to'] = env('LINK_EMAIL_WEB').'/register?activation_code='.$activation_code . '&token=' . $token;
            } elseif ($request->hit_from == 'mobile'){
                $details['link_to'] = env('LINK_EMAIL_MOBILE').'/register?activation_code='.$activation_code . '&token=' . $token;
            } else {
                return response()->json([
                    'code'      => 404,
                    'status'    => 'failed',
                    'result'    => 'hit_from body request not available',
                ], 404);
            } 

            VerificationQueue::dispatch($details);

            $html = (new EmailVerification($details))->render();
            $this->logQueue($user->email, $html, 'Email Verification');

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
            'activation_code' => 'required'
        ]);

        $user = User::where('activation_code', $request->activation_code)->first();

        if($user){
            User::where('activation_code', $request->activation_code)->update([
                'email_status' => 'verified',
                'activation_code' => null
            ]);
                
            $userRes = User::where('email', $user->email)->first();
                
            UserProfile::where([['id_user', '=', $userRes->id_user], ['key_name', '=', 'registration_step']])->update([
                'value' => 2,
            ]);

            $registration_step = UserProfile::select('value')->where([['id_user', '=', $userRes->id_user], ['key_name', '=', 'registration_step']])->first();

            $userRes->registration_step = (int)$registration_step->value;

            return response()->json([
                'code' => 200,
                'status' => 'Verification Succesfull',
                'result' => $userRes
            ], 200);
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

            if($interest != null){
                UserProfile::create([
                    'id_user' => $request->id_user,
                    'key_name' => 'id_interest',
                    'value' => $interest->id_interest
                ]);
            }else{
                Interest::create([
                    'interest_name' => $int,
                    'created_by' => $request->id_user,
                ]);

                $newInterest = Interest::select('id_interest')->where('interest_name', $int)->first();
                
                UserProfile::create([
                    'id_user' => $request->id_user,
                    'key_name' => 'id_interest',
                    'value' => $newInterest->id_interest
                ]);
            }
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
            'full_name' => 'required|string',
            'bio' => '',
            'city' => 'required',
            'gender' => 'required',
        ]);

        $maxSize = Validator::make($request->all(), [
            'profile_picture' => 'image|max:10000',
        ]);

        if ($maxSize->fails()) {
            return response_json(422, 'failed', $maxSize->messages());
        }

        if($request->profile_picture){
            $image = Image::make($request->profile_picture)->resize(400, null, function ($constraint) {
                $constraint->aspectRatio();
            });

            $filename = $request->id_user . '_profile_picture.' . $request->profile_picture->getClientOriginalExtension();

            $data = $image->encode($request->profile_picture->getClientOriginalExtension())->__toString();

            Storage::put('public/picture_queue/' . $filename, $data);

            UploadImage::dispatch($filename);

            $key = "userfiles/images/profile_picture/" . $filename;

            $imageUrl = config('filesystems.disks.s3.bucketurl') . "/" . $key;

            if($dataValidate){
                User::where('id_user', $request->id_user)->update([
                    'full_name' => $request->full_name,
                    'id_city' => $request->city,
                    'bio' => $request->bio,
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

        $company = Company::where('company_name', $request->company)->first();

        if($company == null){
            Company::create([
                'company_name' => $request->company,
                'created_by' => $request->id_user
            ]);

            $company = Company::where('company_name', $request->company)->first();
        }

        $profession = Profession::where('job_title', $request->profession)->first();

        if($profession == null){
            Profession::create([
                'job_title' => $request->profession,
                'created_by' => $request->id_user
            ]);

            $profession = Profession::select('id_job')->where('job_title', $request->profession)->first();
        }

        User::where('id_user', $request->id_user)->update([
            'id_job' => $profession->id_job,
            'id_company' => $company->id_company
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

    public function get_user($id){
        $user = User::where('id_user', $id)->first();

        if($user){
            $registration_step = UserProfile::select('value')->where([['id_user', '=', $id], ['key_name', '=' , 'registration_step']])->first();

            $user->registration_step = $registration_step->value;

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

    public function store_interest(Request $request){
        $request->validate([
            'interest_name' => 'required',
        ]);

        $token = $request->header('Authorization');

        $publicKey = file_get_contents(base_path('public.pem'));

        $jwt = str_replace('Bearer ', '', $token);
        $payload = JWT::decode($jwt, new Key($publicKey, env('JWT_ALGO')));

        $id = $payload->data->id_user;

        Interest::create([
            'interest_name' => $request->interest_name,
            'created_by' => 1
        ]);

        $data = Interest::where([['interest_name', '=', $request->interest_name], ['created_by', '=', 1]])->first();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $data
        ], 200);
    }

    public function registration_v2(Request $request, JwtAuth $jwtAuth){
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $checkEmail = User::where('email', $request->email)->first();

        if($checkEmail != null){
            return response()->json([
                'code' => 409,
                'status' => 'Email already exists',
                'result' => null
            ],409);
        }

        $passwordHash = Hash::make($request->password);

        $activation_code = hash('SHA1', time());

        User::create([
            'email' => $request->email,
            'password' => $passwordHash,
            'activation_code' => $activation_code
        ]);

        $user = User::where('email', $request->email)->first();

        $token = $jwtAuth->createJwtToken($user);

        $user->token = $token;
        // Create new log attempt
        EmailVerifActivity::create([
            'id_user'   => $user->id_user,
            'email'     => $user->email,
        ]);
        $details = [
            'email'     => $user->email,
        ];

        if($request->hit_from == 'web') {
            $details['link_to'] = env('LINK_EMAIL_WEB').'/register?activation_code=' . $activation_code . '&token=' . $token;
        } elseif ($request->hit_from == 'mobile'){
            $details['link_to'] = env('LINK_EMAIL_MOBILE').'/register?activation_code='.$activation_code . '&token=' . $token;
        } else {
            return response()->json([
                'code'      => 404,
                'status'    => 'failed',
                'result'    => 'hit_from body request not available',
            ], 404);
        } 

        VerificationQueue::dispatch($details);

        $html = (new EmailVerification($details))->render();
        $this->logQueue($user->email, $html, 'Email Verification');

        $query = UserProfile::create([
            'id_user' => $user->id_user,
            'key_name' => 'registration_step',
            'value' => 1,
        ]);

        $user->registration_step = $query->value;
        
        return response()->json([
            'code' => 200,
            'status' => 'Registration Successfull',
            'result' => $user
        ],200);
    }

    private function logQueue($to, $message, $subject, $cc='', $bcc='', $headers='', $attachment='0', $is_broadcast=0, $id_event=null, $id_broadcast=0) {
        $logQueue = [
            'to'            => $to,
            'cc'            => $cc,
            'bcc'           => $bcc,
            'message'       => $message,
            'status'        => 'sent',
            'date'          => date('Y-m-d H:i:s'),
            'headers'       => $headers,
            'attachment'    => $attachment,
            'subject'       => $subject,
            'is_broadcast'  => $is_broadcast,
            'id_event'      => $id_event,
            'id_broadcast'  => $id_broadcast,
        ];

        EmailQueue::create($logQueue);
    }
}
