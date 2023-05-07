<?php

namespace App\Http\Controllers;

use Image;
use App\Jobs\UploadImage;
use App\Models\Community;
use App\Models\CommunityUser;
use App\Models\Follow;
use App\Models\Interest;
use App\Models\Portofolio;
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
            $follower = Follow::where('id_user', $data->id_user)->count();
            $following = Follow::where('followed_by', $data->id_user)->count();

            $data->follower = $follower;
            $data->following = $following;

            // Community 
            $communityUser = CommunityUser::select('id_community')->where('id_user', $data->id_user)->get();
            $community = Community::select('title', 'start_date', 'end_date')->whereIn('id_community', $communityUser)->get();
            
            $data->community = $community;

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

    public function add_portofolio(Request $request){
        $request->validate([
            'project_name' => 'required',
            'project_url',
            'start_date_month' => 'required',
            'start_date_year' => 'required',
            'end_date_month' => 'required',
            'end_date_year' => 'required',
            'id_user' => 'required'
        ]);

        if($request->start_date_month < 10){
            $startDateMonth = '0' . $request->start_date_month;
        }else{
            $startDateMonth = $request->start_date_month;
        }

        $startDate = $request->start_date_year . '-' . $startDateMonth . '-01';

        if($request->end_date_month < 10){
            $endDateMonth = '0' . $request->end_date_month;
        }else{
            $endDateMonth = $request->end_date_month;
        }

        $endDate = $request->end_date_year . '-' . $endDateMonth . '-31';

        $query = Portofolio::create([
            'project_name' => $request->project_name,
            'project_url' => $request->project_url,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'id_user' => $request->id_user
        ]);

        if($query){
            return response()->json([
                'code' => 200,
                'status' => 'success'
            ]);
        }
    }
}
