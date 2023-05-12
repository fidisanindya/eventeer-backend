<?php

namespace App\Http\Controllers;

use Image;
use App\Jobs\UploadImage;
use App\Models\Certificate;
use App\Models\Community;
use App\Models\CommunityUser;
use App\Models\Company;
use App\Models\Follow;
use App\Models\Interest;
use App\Models\Portofolio;
use App\Models\Profession;
use App\Models\Submission;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function get_profile($id){
        $data = User::where('id_user', $id)->first();
        if($data){
            // Social Media
            $socialMedia = UserProfile::select('key_name', 'value')->whereIn('key_name', ['instagram', 'twitter', 'youtube', 'github', 'linkedin', 'website'])->where('id_user', $data->id_user)->get();
            foreach ($socialMedia as $sm){
                $data->{$sm->key_name} = $sm->value;
            }
            
             // Interest
            $interestUser = UserProfile::select('value')->where([['id_user', '=', $data->id_user], ['key_name', '=', 'id_interest']])->get();
            $interestData = Interest::select('interest_name')->whereIn('id_interest', $interestUser)->get();

            $data->tag = $interestData;

            // Following and Followers
            $data->follower = Follow::where('id_user', $data->id_user)->count();
            $data->following = Follow::where('followed_by', $data->id_user)->count();

            // Community 
            $communityUser = CommunityUser::select('id_community')->where('id_user', $data->id_user)->get();
            $data->community = Community::select('title', 'start_date', 'end_date')->whereIn('id_community', $communityUser)->get();

            // Portofolio
            $data->portofolio = Portofolio::select('project_name', 'project_url', 'start_date', 'end_date')->where('id_user', $data->id_user)->get();

            $submissionUser = Submission::select('id_submission')->where('id_user', $data->id_user)->get();
            $data->certificate = Certificate::whereIn('id_submission', $submissionUser)->get();

            return response()->json([
                'code' => 200,
                'status' => 'success',
                'result' => $data,
            ]);
        }

        return response()->json([
            'code' => 404,
            'status' => 'user not found',
        ], 404);
    }

    public function edit_profile_picture(Request $request){
        $request->validate([
            'id_user' => 'required',
            'profile_picture' => 'required'
        ]);

        $image = Image::make($request->profile_picture)->resize(400, null, function ($constraint) {
            $constraint->aspectRatio();
        });

        $filename = $request->id_user . '_profile_picture.' . $request->profile_picture->getClientOriginalExtension();

        $data = $image->encode($request->profile_picture->getClientOriginalExtension())->__toString();

        Storage::put('public/picture_queue/' . $filename, $data);

        UploadImage::dispatch($filename);

        $key = "userfiles/images/profile_picture/" . $filename;

        $imageUrl = config('filesystems.disks.s3.bucketurl') . "/" . $key;
        
        $query = User::where('id_user', $request->id_user)->update([
            'profile_picture' => $imageUrl
        ]);

        if($query){
            return response()->json([
                'code' => 200,
                'status' => 'success edit profile picture',
            ]);
        }
    }

    public function edit_banner(Request $request){
        $request->validate([
            'id_user' => 'required',
            'banner' => 'required'
        ]);

        $image = Image::make($request->banner)->resize(600, null, function ($constraint) {
            $constraint->aspectRatio();
        });

        $filename = $request->id_user . '_banner.' . $request->banner->getClientOriginalExtension();

        $data = $image->encode($request->banner->getClientOriginalExtension())->__toString();

        Storage::put('public/picture_queue/' . $filename, $data);

        UploadImage::dispatch($filename);

        $key = "userfiles/images/profile_picture/" . $filename;

        $imageUrl = config('filesystems.disks.s3.bucketurl') . "/" . $key;
        
        $query = User::where('id_user', $request->id_user)->update([
            'cover_image' => $imageUrl
        ]);

        if($query){
            return response()->json([
                'code' => 200,
                'status' => 'success edit banner picture',
            ]);
        }
    }

    public function edit_profile(Request $request){
        $user = $request->validate([
            'id_user' => 'required',
            'full_name' => 'required',
            'bio' => '',
            'city' => 'required',
            'gender' => 'required',
            'company' => 'required',
            'profession' => 'required',
        ]);

        $interestDelete = $request->input('interest_delete');

        $interestAdd = $request->input('interest_add');

        $portofolioAdd = $request->input('portofolio_add');

        $portofolioEdit = $request->input('portofolio_edit');

        $portofolioDelete = $request->input('portofolio_delete');

        $socialMedia = $request->validate([
            'instagram' => '',
            'twitter' => '',
            'youtube' => '',
            'github' => '',
            'linkedin' => '',
            'website' => '',
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

        if($portofolioAdd){
            foreach($portofolioAdd as $pa){
                Portofolio::create([
                    'project_name' => $pa['project_name'],
                    'project_url' => $pa['project_url'],
                    'start_date' => $pa['start_date'],
                    'end_date' => $pa['end_date'],
                    'id_user' => $user['id_user']
                ]);
            }
        }

        if($portofolioEdit){
            foreach($portofolioEdit as $pe){
                $record = Portofolio::where('id_portofolio', $pe['id_portofolio'])->first();
    
                if($record){
                    Portofolio::where('id_portofolio', $record->id_portofolio)->update([
                        'project_name' => $pe['project_name'],
                        'project_url' => $pe['project_url'],
                        'start_date' => $pe['start_date'],
                        'end_date' => $pe['end_date'],
                    ]);
                }
            }
        }

        if($portofolioDelete){
            Portofolio::whereIn('id_portofolio', $portofolioDelete)->delete();
        }

        if($interestAdd){
            foreach($interestAdd as $int){
                $interest = Interest::select('id_interest')->where('interest_name', $int)->first();

                if($interest != null){
                    UserProfile::create([
                        'id_user' => $user['id_user'],
                        'key_name' => 'id_interest',
                        'value' => $interest->id_interest
                    ]);
                }else{
                    Interest::create([
                        'interest_name' => $int,
                        'created_by' => $user['id_user'],
                    ]);

                    $newInterest = Interest::select('id_interest')->where('interest_name', $int)->first();
                    
                    UserProfile::create([
                        'id_user' => $user['id_user'],
                        'key_name' => 'id_interest',
                        'value' => $newInterest->id_interest
                    ]);
                }
            }
        }

        if($interestDelete){
            UserProfile::where([['id_user', '=', $user['id_user']], ['key_name', '=', 'id_interest']])->whereIn('value', $interestDelete)->delete();
        }

        if($socialMedia){
            foreach($socialMedia as $keyName => $value){
                $record = UserProfile::where([['id_user', '=', $user['id_user']], ['key_name', '=', $keyName]])->first();
                if($record){
                    UserProfile::where([['id_user', '=', $user['id_user']], ['key_name', '=', $keyName]])->update([
                        'value' => $value
                    ]);
                }else{
                    UserProfile::create([
                        'id_user' => $user['id_user'],
                        'key_name' => $keyName,
                        'value' => $value
                    ]);
                }
            }
        }

        User::where('id_user', $user['id_user'])->update([
            'full_name' => $user['full_name'],
            'bio' => $user['bio'],
            'id_city' => $user['city'],
            'gender' => $user['gender'],
            'id_company' => $company->id_company,
            'id_job' => $profession->id_profession
        ]);

        return response()->json([
            'code' => 200,
            'status' => 'success edit profile'
        ]);
    }

    public function add_portofolio(Request $request){
        $request->validate([
            'portofolio' => '',
            'id_user' => 'required',
        ]);

        foreach($request->portofolio as $porto){
            Portofolio::create([
                'project_name' => $porto['project_name'],
                'project_url' => $porto['project_url'],
                'start_date' => $porto['start_date'],
                'end_date' => $porto['end_date'],
                'id_user' => $request->id_user
            ]);
        }
     
        return response()->json([
            'code' => 200,
            'status' => 'success'
        ]);
    }

    public function edit_portofolio(Request $request){
        $request->validate([
            'portofolio' => '',
        ]);

        foreach($request->portofolio as $porto){
            $record = Portofolio::where('id_portofolio', $porto['id_portofolio'])->first();

            if($record){
                Portofolio::where('id_portofolio', $record->id_portofolio)->update([
                    'project_name' => $porto['project_name'],
                    'project_url' => $porto['project_url'],
                    'start_date' => $porto['start_date'],
                    'end_date' => $porto['end_date'],
                ]);
            }
        }
     
        return response()->json([
            'code' => 200,
            'status' => 'success'
        ]);
    }

    public function delete_portofolio(Request $request){
        $request->validate([
            'id_portofolio' => 'required'
        ]);

        $query = Portofolio::whereIn('id_portofolio', $request->id_portofolio)->delete();

        if ($query){
            return response()->json([
                'code' => 200,
                'status' => 'success'
            ]); 
        }
    }
}
