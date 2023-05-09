<?php

namespace App\Http\Controllers;

use Image;
use App\Jobs\UploadImage;
use App\Models\Certificate;
use App\Models\Community;
use App\Models\CommunityUser;
use App\Models\Follow;
use App\Models\Interest;
use App\Models\Portofolio;
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

            $certificateUser = Certificate::select('id_submission')->get();
            $data->certificate = Submission::whereIn('id_submission', $certificateUser)->get();

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

        $interest = $request->input('interest_name');

        $portofolio = $request->input('portofolio');

        $socialMedia = $request->validate([
            'instagram' => '',
            'twitter' => '',
            'youtube' => '',
            'github' => '',
            'linkedin' => '',
            'website' => '',
        ]);

        if($portofolio){
            foreach($portofolio as $porto){
                $record = Portofolio::where('id_portofolio', $porto['id_portofolio'])->first();

                if($record){
                    Portofolio::where('id_portofolio', $record->id_portofolio)->update([
                        'project_name' => $porto['project_name'],
                        'project_url' => $porto['project_url'],
                        'start_date' => $porto['start_date'],
                        'end_date' => $porto['end_date'],
                    ]);
                }else{
                    Portofolio::create([
                        'project_name' => $porto['project_name'],
                        'project_url' => $porto['project_url'],
                        'start_date' => $porto['start_date'],
                        'end_date' => $porto['end_date'],
                        'id_user' => $user['id_user']
                    ]);
                }
            }
        }

        if($interest){
            foreach($interest as $int){
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
        }

        // not completed
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
