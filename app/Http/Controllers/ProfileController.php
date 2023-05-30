<?php

namespace App\Http\Controllers;

use Image;
use stdClass;
use Carbon\Carbon;
use App\Models\City;
use App\Models\User;
use App\Models\Event;
use App\Models\React;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\Follow;
use App\Models\Company;
use App\Models\Interest;
use App\Models\Timeline;
use App\Jobs\UploadImage;
use App\Models\Community;
use App\Models\Portofolio;
use App\Models\Profession;
use App\Models\Submission;
use App\Models\Certificate;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use App\Models\CommunityUser;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function get_profile($id){
        $data = User::where('id_user', $id)->first();
        $data->makeHidden('id_city', 'id_company', 'id_job');
        if($data){
            // City
            $city = City::select('id_city', 'city_name')->where('id_city', $data->id_city)->first();
            $data->city = $city;

            // Company
            $company = Company::select('id_company', 'company_name')->where('id_company', $data->id_company)->first();
            $data->company = $company;

            // Profession
            $profession = Profession::select('id_job', 'job_title')->where('id_job', $data->id_job)->first();
            $data->profession = $profession;

            // Social Media
            $socialMedia = UserProfile::select('key_name', 'value')->whereIn('key_name', ['instagram', 'twitter', 'youtube', 'github', 'linkedin', 'website'])->where('id_user', $data->id_user)->get();
            foreach ($socialMedia as $sm){
                $data->{$sm->key_name} = $sm->value;
            }
            
             // Interest
            $interestUser = UserProfile::select('value')->where([['id_user', '=', $data->id_user], ['key_name', '=', 'id_interest']])->get();
            $interestData = Interest::select('id_interest', 'interest_name')->whereIn('id_interest', $interestUser)->get();

            $data->tag = $interestData;

            // Following and Followers
            $data->follower = Follow::where('id_user', $data->id_user)->count();
            $data->following = Follow::where('followed_by', $data->id_user)->count();

            // Community 
            $communityUser = CommunityUser::select('id_community')->where('id_user', $data->id_user)->get();
            $data->community = Community::select('title', 'start_date', 'end_date')->whereIn('id_community', $communityUser)->get();

            // Portofolio
            $data->portofolio = Portofolio::select('id_portofolio', 'project_name', 'project_url', 'start_date', 'end_date')->where('id_user', $data->id_user)->get();

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
                if($pa['start_date_month'] == "2"){
                    $startDate = $pa['start_date_year'] . "-0" . $pa['start_date_month'] . "-" . "28";
                }else{
                    if($pa['start_date_month'] < 10){
                        $startDate = $pa['start_date_year'] . "-0" . $pa['start_date_month'] . "-" . "30";
                    }else{
                        $startDate = $pa['start_date_year'] . "-" . $pa['start_date_month'] . "-" . "30";
                    }
                }

                if($pa['end_date_month'] == "2"){
                    $endDate = $pa['end_date_year'] . "-0" . $pa['end_date_month'] . "-" . "28";
                }else{
                    if($pa['end_date_month'] < 10){
                        $endDate = $pa['end_date_year'] . "-0" . $pa['end_date_month'] . "-" . "30";
                    }else{
                        $endDate = $pa['end_date_year'] . "-" . $pa['end_date_month'] . "-" . "30";
                    }
                }

                Portofolio::create([
                    'project_name' => $pa['project_name'],
                    'project_url' => $pa['project_url'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'id_user' => $user['id_user']
                ]);
            }
        }

        if($portofolioEdit){
            foreach($portofolioEdit as $pe){
                $record = Portofolio::where('id_portofolio', $pe['id_portofolio'])->first();
    
                if($record){

                    if($pe['start_date_month'] == "2"){
                        $startDate = $pe['start_date_year'] . "-0" . $pe['start_date_month'] . "-" . "28";
                    }else{
                        if($pe['start_date_month'] < 10){
                            $startDate = $pe['start_date_year'] . "-0" . $pe['start_date_month'] . "-" . "30";
                        }else{
                            $startDate = $pe['start_date_year'] . "-" . $pe['start_date_month'] . "-" . "30";
                        }
                    }
    
                    if($pe['end_date_month'] == "2"){
                        $endDate = $pe['end_date_year'] . "-0" . $pe['end_date_month'] . "-" . "28";
                    }else{
                        if($pe['end_date_month'] < 10){
                            $endDate = $pe['end_date_year'] . "-0" . $pe['end_date_month'] . "-" . "30";
                        }else{
                            $endDate = $pe['end_date_year'] . "-" . $pe['end_date_month'] . "-" . "30";
                        }
                    }

                    Portofolio::where('id_portofolio', $record->id_portofolio)->update([
                        'project_name' => $pe['project_name'],
                        'project_url' => $pe['project_url'],
                        'start_date' => $startDate,
                        'end_date' => $endDate,
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
            'id_job' => $profession->id_job
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

    public function detailPortfolio(Request $request){
        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);

        $result = new stdClass;

        // Get detail user portfolio
        $portfolio = Portofolio::where('id_user', $userId)
        ->select('id_portofolio', 'id_user', 'project_name', 'project_url', 'start_date', 'end_date')
        ->when(request()->has('limit'), function ($query) {
            $limit = request()->input('limit', null);
            if($limit != 'all') {
                $query->limit($limit);
            }
        })
        ->orderBy('start_date', 'ASC')
        ->get();

        $result->portfolio = $portfolio;

        $result->meta = [
            'limit' => request()->input('limit', null),
        ];

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $result,
        ]);
    }

    public function detailCertificate(Request $request){
        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);

        $result = new stdClass;

        // Get detail User Certificate
        $certificate = Certificate::with(['submission' => function ($querySubmission){
            $querySubmission->with(['event' => function ($queryEvent) {
                $queryEvent->with(['community' => function ($queryCommunity) {
                    $queryCommunity->select('id_community', 'title');
                }])
                ->select('id_event', 'id_community', 'title', 'image');
            }])->select('id_submission', 'id_event');
        }])
        ->where('id_user', $userId)
        ->when(request()->has('limit'), function ($query) {
            $limit = request()->input('limit', null);
            if($limit != 'all') {
                $query->limit($limit);
            }
        })
        ->select('id_certificate', 'id_submission', 'id_user', 'issue_date', 'expire_date')
        ->orderBy('issue_date', 'DESC')
        ->get();

        $result->certificate = $certificate;

        $result->meta = [
            'limit' => request()->input('limit', null),
        ];

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $result,
        ]);
    }

    public function detailCommunity(Request $request){
        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);

        $result = new stdClass;

        // Get detail user community
        $user_community = CommunityUser::where('status', 'active')->orWhere('status', 'running')->where('id_user', $userId)->pluck('id_community');
        $community = Community::where('status', 'active')
        ->whereIn('id_community', $user_community)
        ->select('id_community', 'title', 'image', 'start_date', 'end_date')
        ->when(request()->has('limit'), function ($query) {
            $limit = request()->input('limit', null);
            if($limit != 'all') {
                $query->limit($limit);
            }
        })
        ->orderBy('start_date', 'DESC')
        ->get();

        $result->community = $community;

        $result->meta = [
            'limit' => request()->input('limit', null),
        ];

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $result,
        ]);
    }

    public function detailPost(Request $request){
        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);
        
        $result = new stdClass;

        // Get detail post user
        $post = Timeline::with(['community' => function ($queryCommunity) {
            $queryCommunity->select('id_community', 'title', 'image');
        }, 'user' => function ($queryUser) {
            $queryUser->select('id_user', 'full_name', 'profile_picture', 'id_job', 'id_company')
            ->with(['job' => function ($queryJob) {
                $queryJob->select('id_job', 'job_title');
            }, 'company' => function ($queryCompany) {
                $queryCompany->select('id_company', 'company_name');
            }]);
        }])
        ->with(['file_attachment' => function ($queryFileAttachment) {
            $queryFileAttachment->where('related_to', 'id_timeline');
        }])
        ->where('id_user', $userId)
        ->whereNull('deleted_at')
        ->select('id_timeline', 'id_community', 'id_user', 'description', 'additional_data', 'created_at')
        ->withCount(['react as total_like' => function ($query) {
            $query->where('related_to', '=', 'id_timeline');
        }])
        ->withCount(['comment as total_comment' => function ($query) {
            $query->where('related_to', '=', 'id_timeline');
        }])
        ->when(request()->has('limit'), function ($query) {
            $limit = request()->input('limit', null);
            if($limit != 'all') {
                $query->limit($limit);
            }
        })
        ->orderBy('created_at', 'DESC')
        ->get();

        $result->post = $post;

        $result->meta = [
            'limit' => request()->input('limit', null),
        ];

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $result,
        ]);
    }
    
    public function detailActivity(Request $request){
        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);

        $result = new stdClass;

        // Get detail activity user
        $today = now()->toDateString();
        $activity = Submission::where('id_user', $userId)->pluck('id_event');
        $event = Event::with(['community' => function ($item) {
            $item->select('id_community', 'title', 'image');
        }])
        ->whereIn('id_event', $activity)
        ->where('additional_data->date->start', '<=', $today)
        ->orderBy('additional_data->date->start', 'DESC')
        ->select('id_event', 'id_community', 'id_user', 'title', 'image', 'category', 'additional_data', 'status')
        ->withCount('submission as people_joined')
        ->when(request()->has('limit'), function ($query) {
            $limit = request()->input('limit', null);
            if($limit != 'all') {
                $query->limit($limit);
            }
        })->get();

        foreach ($event as $item){
            $item->additional_data = json_decode($item->additional_data);
        }

        $result->activity = $event;

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $result,
        ]);
    }

    public function post_like_unlike(Request $request){
        $validator = Validator::make($request->all(), [
            'id_timeline' => 'required',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }
        
        // Get id user from bearer token
        $userId = get_id_user_jwt($request);

        // Like Unlike post
        $check_liked_post = React::where('related_to', 'id_timeline')->where('id_related_to', $request->id_timeline)->where('id_user', $userId)->first();

        if($check_liked_post != null){
            $check_liked_post->delete();

            return response_json(200, 'success', 'Post unliked successfully');
        } else {
            React::create([
                'related_to' => 'id_timeline',
                'id_related_to' => $request->id_timeline,
                'id_user' => $userId,
                'created_at' => Carbon::now()->toDateTimeString()
            ]);

            return response_json(200, 'success','Post liked successfully');
        }
    }
    
    public function follow_user(Request $request){
        $request->validate([
            'follow_id_user' => 'required'
        ]);

        $userId = get_id_user_jwt($request);

        $query = Follow::insert([
            'id_user' => $request->follow_id_user,
            'followed_by' => $userId
        ]);

        if($query){ 
            return response()->json([
                'code' => 200,
                'status' => 'success follow user',
            ]);
        }

        return response()->json([
            'code' => 409,
            'status' => 'failed follow user',
        ]);
    }

    public function unfollow_user(Request $request){
        $request->validate([
            'unfollow_id_user' => 'required'
        ]);

        $userId = get_id_user_jwt($request);

        $query = Follow::where([['id_user', $request->unfollow_id_user], ['followed_by', $userId]])->delete();

        if($query){ 
            return response()->json([
                'code' => 200,
                'status' => 'success unfollow user',
            ]);
        }

        return response()->json([
            'code' => 409,
            'status' => 'failed unfollow user',
        ]);
    }
}