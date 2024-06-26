<?php

namespace App\Http\Controllers;

use App\Jobs\EventVerfication;
use App\Jobs\UploadImageFeeds;
use App\Models\Comment;
use stdClass;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Event;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\Follow;
use App\Models\Community;
use App\Models\Submission;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use App\Models\CommunityUser;
use App\Models\CommunityInterest;
use App\Models\CommunityManager;
use App\Models\FileAttachment;
use App\Models\React;
use App\Models\Timeline;
use App\Models\TimelineShare;
use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Image;

class CommunityController extends Controller
{
    public function getCommunityPublic(Request $request){
        // Get id_user from Bearer Token
        $limit = $request->input('limit');
        $start = $request->input('start');

        $userId = get_id_user_jwt($request);

        $result = new stdClass;

        $query = Community::select('id_community', 'title', 'image', 'banner', 'type')->where([['type', '!=', 'private_whitelist'], ['status', '=', 'active']])->withCount(['community_user as total_members' => function ($query) {
            $query->where('status', '=', 'active')->whereOr('status', '=', 'running');
        }]);

        $totalData = Community::select('id_community', 'title', 'image', 'banner', 'type')->where([['type', '!=', 'private_whitelist'], ['status', '=', 'active']])->count();

        if ($limit !== null) {
            $query->limit($limit);
            if ($start !== null) {
                $query->offset($start);
            }
        }

        $communityPublic = $query->get();
        
        $followed_id = Follow::where('followed_by', $userId)->pluck('id_user');

        foreach ($communityPublic as $cp => $item){
            // Add tag to result
            $tag = CommunityInterest::with('interest')->where('community_id', $item->id_community)->get();
            $interest = [];
            if($tag != null) {
                $tag->each(function ($item) use (&$interest) {
                    array_push($interest, $item->interest->interest_name);
                });
            }
            $item->tag = $interest;

            if($followed_id->first() != null) {
                if($cp < count($followed_id))  {
                    $followed_communities = CommunityUser::whereIn('id_user', $followed_id)->select('id_community', DB::raw('COUNT(id_user) as total_friends'))->where('id_community', $item->id_community)->groupBy('id_community')->first();
                }

                $profilePicture = User::whereIn('id_user', $followed_id)->whereNotNull('profile_picture')->select('profile_picture')->take(2)->get();
                
                $arrayProfile = [];
                for ($i=0; $i < $profilePicture->count() ; $i++) { 
                    array_push($arrayProfile, $profilePicture[$i]->profile_picture);
                }
                
                $item->friends = [
                    'followed'          => $followed_communities,
                    'profile_picture'   => $arrayProfile,
                ];
            } else {
                $item->friends = null;
            }
        }

        if($communityPublic != null){
            $result->community = $communityPublic;
        }else{
            $result->community = null;
        }

        $result->meta = [
            "start" => $start,
            "limit" => $limit,
            "total_data" => $totalData
        ];

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $result,
        ]);
    }

    public function getCommunityInterest(Request $request){
        // Get id_user from Bearer Token
        $limit = $request->input('limit');
        $start = $request->input('start');

        $userId = get_id_user_jwt($request);

        $interestUser = UserProfile::select('value')->where([['id_user', '=', $userId], ['key_name', '=', 'id_interest']])->get();

        $communityInterest = CommunityInterest::select('community_id')->whereIn('id_interest', $interestUser)->get();

        $idCommunity = [];

        $result = new stdClass;

        foreach ($communityInterest as $ci){
            array_push($idCommunity, $ci->community_id);
        }

        // Delete duplicate ID
        $idCommunity = collect($idCommunity);
        $uniqueId = $idCommunity->unique();
        
        $query = Community::select('id_community', 'title', 'image', 'banner', 'type')->whereIn('id_community', $uniqueId)->where('status', 'active')->where('type', '!=', 'private_whitelist')->withCount(['community_user as total_members' => function ($query) {
            $query->where('status', '=', 'active')->whereOr('status', '=', 'running');
        }]);

        $totalData = Community::select('id_community', 'title', 'image', 'banner', 'type')->whereIn('id_community', $uniqueId)->where('status', 'active')->count();

        if ($limit !== null) {
            $query->limit($limit);
            if ($start !== null) {
                $query->offset($start);
            }
        }

        $communityByInterest = $query->get();

        $followed_id = Follow::where('followed_by', $userId)->pluck('id_user');

        foreach($communityByInterest as $ci => $item){
            // Add tag to result
            $tag = CommunityInterest::with('interest')->where('community_id', $item->id_community)->get();
            $interest = [];
            if($tag != null) {
                $tag->each(function ($item) use (&$interest) {
                    array_push($interest, $item->interest->interest_name);
                });
            }
            $item->tag = $interest;

            if($followed_id->first() != null) {
                if($ci < count($followed_id))  {
                    $followed_communities = CommunityUser::whereIn('id_user', $followed_id)->select('id_community', DB::raw('COUNT(id_user) as total_friends'))->where('id_community', $item->id_community)->groupBy('id_community')->first();
                }

                $profilePicture = User::whereIn('id_user', $followed_id)->whereNotNull('profile_picture')->select('profile_picture')->take(2)->get();
                
                $arrayProfile = [];
                for ($i=0; $i < $profilePicture->count() ; $i++) { 
                    array_push($arrayProfile, $profilePicture[$i]->profile_picture);
                }
                
                $item->friends = [
                    'followed'          => $followed_communities,
                    'profile_picture'   => $arrayProfile,
                ];
            } else {
                $item->friends = null;
            }
        }

        if($communityByInterest != null){
            $result->community = $communityByInterest;
        }else{
            $result->community = null;
        }

        $result->meta = [
            "start" => $start,
            "limit" => $limit,
            "total_data" => $totalData
        ];
        
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $result,
        ]);
    }

    public function getTopCommunity(Request $request){
        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);

        $communityTop = Community::select('id_community', 'title', 'image', 'type')->where('type', '!=', 'private_whitelist')->withCount(['community_user as total_members' => function ($query) {
            $query->where('status', '=', 'active')->whereOr('status', '=', 'running');
        }])
        ->where('status', 'active')
        ->orderBy('total_members', 'DESC')->limit(3)->get();

        $followed_id = Follow::where('followed_by', $userId)->pluck('id_user');

        foreach($communityTop as $ct => $item){
            // Add tag to result
            $tag = CommunityInterest::with('interest')->where('community_id', $item->id_community)->get();
            $interest = [];
            if($tag != null) {
                $tag->each(function ($item) use (&$interest) {
                    array_push($interest, $item->interest->interest_name);
                });
            }
            $item->tag = $interest;

            if($followed_id->first() != null) {
                if($ct < count($followed_id))  {
                    $followed_communities = CommunityUser::whereIn('id_user', $followed_id)->select('id_community', DB::raw('COUNT(id_user) as total_friends'))->where('id_community', $item->id_community)->groupBy('id_community')->first();
                }

                $profilePicture = User::whereIn('id_user', $followed_id)->whereNotNull('profile_picture')->select('profile_picture')->take(2)->get();
                
                $arrayProfile = [];
                for ($i=0; $i < $profilePicture->count() ; $i++) { 
                    array_push($arrayProfile, $profilePicture[$i]->profile_picture);
                }
                
                $item->friends = [
                    'followed'          => $followed_communities,
                    'profile_picture'   => $arrayProfile,
                ];
            } else {
                $item->friends = null;
            }

        }

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $communityTop,
        ]);
    }

    public function getEventAll(Request $request){
        $start = $request->input('start', 0);
        $limit = $request->input('limit', 5);

        $result = new stdClass;

        // All events in eventeer
        $event = Event::with(['community' => function ($item) {
            $item->select('id_community', 'title', 'image');
        }])
        ->whereNull('deleted_at')
        ->where('status', 'active')
        ->where('category', 'event')
        ->when(request()->has('location'), function ($query) {
            $location = request()->input('location', null);
            if($location != null) {
                $query->where('additional_data->location->name', $location);
            }
        })
        ->when(request()->has('date'), function ($query) {
            $date = request()->input('date', null);
            if($date != null && $date != 'anytime') {
                if ($date == 'today') {
                    $query->whereDate('additional_data->date->start', Carbon::today());
                } else if ($date == 'weekly') {
                    $query->whereBetween('additional_data->date->start', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                } else if ($date == 'monthly') {
                    $query->whereBetween('additional_data->date->start', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                } else if ($date != 'anytime') {
                    $query->whereBetween('additional_data->date->start', [$date, request()->input('end_date')]);
                }
            }
        })
        ->select('id_event', 'id_community', 'title', 'image', 'category', 'additional_data', 'status')
        ->withCount('submission as people_joined')
        ->offset($start)->limit($limit)
        ->orderBy('additional_data->date->start', 'ASC')->get();

        foreach ($event as $item){
            $item->additional_data = json_decode($item->additional_data);
        }

        if ($event != null) {
            $result->event = $event;
        } else {
            $result->event = null;
        }
        
        $allEvent = Event::whereNull('deleted_at')
        ->where('status', 'active')
        ->where('category', 'event')
        ->when(request()->has('location'), function ($query) {
            $location = request()->input('location', null);
            if($location != null) {
                $query->where('additional_data->location->name', $location);
            }
        })
        ->when(request()->has('date'), function ($query) {
            $date = request()->input('date', null);
            if($date != null && $date != 'anytime') {
                if ($date == 'today') {
                    $query->whereDate('additional_data->date->start', Carbon::today());
                } else if ($date == 'weekly') {
                    $query->whereBetween('additional_data->date->start', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                } else if ($date == 'monthly') {
                    $query->whereBetween('additional_data->date->start', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                } else if ($date != 'anytime') {
                    $query->whereBetween('additional_data->date->start', [$date, request()->input('end_date')]);
                }
            }
        })
        ->select('id_event', 'id_community', 'title', 'image', 'category', 'additional_data', 'status')
        ->count();

        $result->meta = [
            'start' => $start,
            'limit' => $limit,
            'date'  => $request->input('date', null),
            'location' => $request->input('location', null),
            'total_data' => $allEvent,
        ];

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $result,
        ]);
    }

    public function getEventMightLike(Request $request){
        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);

        // Event Might Like
        $start = $request->input('start', 0);
        $limit = $request->input('limit', 5);

        $result = new stdClass;

        // Event dari community bertipe public dan user belum join dari komunitas dengan interest yang relate & Event yang user belum join dari komunitas bertipe private dan user udah join komunitas tsb
        
        // Get public community that match with user interest and user joined event
        $user_interest = UserProfile::where('id_user', $userId)->where('key_name', 'id_interest')->pluck('value');
        if ($user_interest->first() != null) {
            $community_interest = CommunityInterest::whereIn('id_interest', $user_interest)->pluck('community_id');
            $public_community_interest = Community::where('status', 'active')->where('type', 'public')->whereIn('id_community', $community_interest)->pluck('id_community');
        }
        $joined_event = Submission::where('id_user', $userId)->pluck('id_event');

        // Get private community that user join
        $joined_community = CommunityUser::where('id_user', $userId)->pluck('id_community');
        $private_community_joined = Community::whereIn('id_community', $joined_community)->where('status', 'active')->where('type', 'private')->pluck('id_community');

        // Event that created by the public community that the user haven't joined yet
        $event = Event::with(['community' => function ($item) {
            $item->select('id_community', 'title', 'image');
        }])
        ->whereNull('deleted_at')
        ->where('status', 'active')
        ->where('category', 'event')
        ->when(request()->has('location'), function ($query) {
            $location = request()->input('location', null);
            if($location != null) {
                $query->where('additional_data->location->name', $location);
            }
        })
        ->when(request()->has('date'), function ($query) {
            $date = request()->input('date', null);
            if($date != null && $date != 'anytime') {
                if ($date == 'today') {
                    $query->whereDate('additional_data->date->start', Carbon::today());
                } else if ($date == 'weekly') {
                    $query->whereBetween('additional_data->date->start', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                } else if ($date == 'monthly') {
                    $query->whereBetween('additional_data->date->start', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                } else if ($date != 'anytime') {
                    $query->whereBetween('additional_data->date->start', [$date, request()->input('end_date')]);
                }
            }
        })
        ->whereIn('id_community', $public_community_interest)
        ->orWhereIn('id_community', $private_community_joined)
        ->whereNotIn('id_event', $joined_event)
        ->select('id_event', 'id_community', 'title', 'image', 'category', 'additional_data', 'status')
        ->withCount('submission as people_joined')
        ->offset($start)->limit($limit)
        ->orderBy('additional_data->date->start', 'ASC')->get();

        foreach ($event as $item){
            $item->additional_data = json_decode($item->additional_data);
        }

        $result->event_might_liked = $event;

        $allEvent = Event::whereNull('deleted_at')
        ->where('status', 'active')
        ->where('category', 'event')
        ->when(request()->has('location'), function ($query) {
            $location = request()->input('location', null);
            if($location != null) {
                $query->where('additional_data->location->name', $location);
            }
        })
        ->when(request()->has('date'), function ($query) {
            $date = request()->input('date', null);
            if($date != null && $date != 'anytime') {
                if ($date == 'today') {
                    $query->whereDate('additional_data->date->start', Carbon::today());
                } else if ($date == 'weekly') {
                    $query->whereBetween('additional_data->date->start', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                } else if ($date == 'monthly') {
                    $query->whereBetween('additional_data->date->start', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                } else if ($date != 'anytime') {
                    $query->whereBetween('additional_data->date->start', [$date, request()->input('end_date')]);
                }
            }
        })
        ->whereIn('id_community', $public_community_interest)
        ->orWhereIn('id_community', $private_community_joined)
        ->whereNotIn('id_event', $joined_event)
        ->count();

        $result->meta = [
            'start' => $start,
            'limit' => $limit,
            'date'  => $request->input('date', null),
            'location' => $request->input('location', null),
            'total_data' => $allEvent,
        ];

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $result,
        ]);
    }

    public function getEventTop(){
        $result = new stdClass;

        $topEvent = Event::select('id_event', 'id_community', 'title', 'image', 'category', 'additional_data', 'status')
        ->whereNull('deleted_at')
        ->where('status', 'active')
        ->where('category', 'event')
        ->withCount('submission as people_joined')
        ->orderBy('people_joined', 'DESC')->limit(3)->get();

        foreach ($topEvent as $item){
            $item->additional_data = json_decode($item->additional_data);
        }

        $result->top_event = $topEvent;

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $result,
        ]);
    }

    public function getYourEvent(Request $request){
        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);
        
        $result = new stdClass;

        // Your event
        $submission = Submission::where('id_user', $userId)->pluck('id_event');
        $event = Event::whereIn('id_event', $submission)
        ->whereNull('deleted_at')
        ->where('status', 'active')
        ->where('category', 'event')
        ->select('id_event', 'id_community', 'title', 'image', 'category', 'additional_data', 'status')
        ->withCount('submission as people_joined')
        ->orderBy('additional_data->date->start', 'ASC')->get();

        foreach ($event as $item){
            $item->additional_data = json_decode($item->additional_data);
        }

        if($event->first() != null){
            $result->your_event = $event;
        } else {
            $result->your_event = null;
        }

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $result,
        ]);
    }

    public function getDetailCommunity(Request $request){
        $id_community = $request->input('id_community');

        $userId = get_id_user_jwt($request);

        $followed_id = Follow::where('followed_by', $userId)->pluck('id_user');

        $data_community = Community::withCount(['community_user as total_friends' => function ($query) use ($followed_id) {
            $query->whereIn('id_user', $followed_id)->where('status', '=', 'active')->whereOr('status', '=', 'running');
        }])->with(['community_user' => function($queryComUs) use ($followed_id){
            $queryComUs->with(['user' => function ($queryUser){
                $queryUser->select('id_user', 'profile_picture');
            }])->select('id_community_user', 'id_community', 'id_user')->whereIn('id_user', $followed_id)->where('status', '=', 'active')->whereOr('status', '=', 'running');
        }])->where('id_community', $id_community)->first();

        // Interest
        $interest_community = CommunityInterest::with(['interest' => function ($query) {
            $query->select('id_interest','interest_name');
        }])->select('id_interest')->where('community_id', $data_community->id_community)->get(); 
        $interest_community->makeHidden('id_interest');

        // Total Member
        $total_member = CommunityUser::where('id_community', $data_community->id_community)->where('status', 'active')->count();

        // Friends
        $data_community->tag = $interest_community;
        $data_community->total_member = $total_member;

        // Check Joined
        $checkJoin = CommunityUser::select('status')->where([['id_community', $data_community->id_community], ['id_user', $userId]])->first();

        if(!$checkJoin){
            $data_community->status_joined = "not_joined";
        }else{
            if($checkJoin->status == "pending"){
                $data_community->status_joined = "requested";
            }else if($checkJoin->status == "active"){
                $data_community->status_joined = "joined";
            }
        }

        return response()->json([
            'code' => 200,
            'status' => 'success get detail community',
            'result' => $data_community
        ]);
    }

    public function joinCommunity(Request $request){
        $request->validate([
            'id_community' => 'required',
            'referral_code' => ''
        ]);

        $userId = get_id_user_jwt($request);

        $check_join = CommunityUser::where('id_community', $request->id_community)->where('id_user', $userId)->first();

        $community_type = Community::where('id_community', $request->id_community)->first();

        if($check_join){
            return response()->json([
                'code' => 409,
                'status' => 'you have joined this community'
            ], 409);
        }

        if($community_type){
            if($community_type->type == 'public'){
                CommunityUser::create([
                    'id_community' => $community_type->id_community,
                    'id_user' => $userId,
                    'status' => 'active'
                ]);
            }else if($community_type->type == 'private_code'){
                if ($request->referral_code == $community_type->referral_code){   
                    CommunityUser::create([
                        'id_community' => $community_type->id_community,
                        'id_user' => $userId,
                        'status' => 'active'
                    ]);
                }else{
                    return response()->json([
                        "code" => 409,
                        "status" => "referral code doesn't match"
                    ], 409);
                }
            }else if($community_type->type == 'private_request'){
                CommunityUser::create([
                    'id_community' => $community_type->id_community,
                    'id_user' => $userId,
                    'status' => 'pending'
                ]);
            }

            $cacheKey = "user_communities_{$userId}";
            Cache::forget($cacheKey);

            return response()->json([
                'code' => 200,
                'status' => 'success request join community'
            ], 200);
        }

        return response()->json([
            'code' => 404,
            'status' => 'community not found'
        ], 404);
    }

    public function leaveCommunity(Request $request){
        $request->validate([
            'id_community' => 'required',
        ]);

        $userId = get_id_user_jwt($request);

        $check_join = CommunityUser::where('id_community', $request->id_community)->where('id_user', $userId)->first();

        if(!$check_join){
            return response()->json([
                "code" => 409,
                "status" => "you haven't joined the community yet"
            ], 409);
        }

        $community = Community::where('id_community', $request->id_community)->first();

        if($community){
            $deleteQuery = CommunityUser::where([['id_community', $community->id_community], ['id_user', $userId]])->delete();

            if($deleteQuery){
                $cacheKey = "user_communities_{$userId}";
                Cache::forget($cacheKey);

                return response()->json([
                    'code' => 200,
                    'status' => 'leave the community successfully'
                ], 200);
            }
        }

        return response()->json([
            'code' => 404,
            'status' => 'community not found'
        ], 404);
    }

    public function getDetailEvent(Request $request){
        $id_event = $request->input('id_event');

        $dataEvent = Event::with(['vendor' => function($queryVendor){
            $queryVendor->select('id_vendor', 'vendor_name');
        }])->where('id_event', $id_event)->whereNull('deleted_at')->first();

        if($dataEvent){
            $dataEvent->additional_data = json_decode($dataEvent->additional_data);

            // Like Event
            $likeEvent = React::where([['related_to', 'id_event'], ['id_related_to', $id_event]])->count();
    
            $dataEvent->like_event = $likeEvent;

            return response()->json([
                "code" => 200,
                "status" => "success get detail event",
                "result" => $dataEvent
            ], 200);
        }

        return response()->json([
            "code" => 404,
            "status" => "event not found",
        ], 404);
    }

    public function joinEvent(Request $request){
        $request->validate([
            'id_event' => 'required',
            'full_name' => '',
            'email' => '',
            'phone' => '',
        ]);

        if($request->hit_from == 'web') {
            $link_to = env('LINK_EMAIL_WEB').'/register';
        } elseif ($request->hit_from == 'mobile'){
            $link_to = env('LINK_EMAIL_MOBILE').'/register';
        } else {
            return response()->json([
                'code'      => 404,
                'status'    => 'failed',
                'result'    => 'hit_from body request not available',
            ], 404);
        } 

        $event = Event::where('id_event', $request->id_event)->whereNull('deleted_at')->first();

        if(!$event){
            return response()->json([
                'code' => 404,
                'status' => 'event not found'
            ], 404);
        }

        $authorizationHeader = $request->header('Authorization');

        if($authorizationHeader){
            $userId = get_id_user_jwt($request);

            $check_join = Submission::where('id_event', $request->id_event)->where('id_user', $userId)->first();

            if($check_join){
                return response()->json([
                    'code' => 409,
                    'status' => 'you have joined this event'
                ], 409);
            }

            Submission::create([
                'id_event' => $request->id_event,
                'id_user' => $userId,
                'additional_data' => '',
                'type' => 'submission',
                'status' => 'confirmed'
            ]);

            return response()->json([
                'code' => 200,
                'status' => 'success join event'
            ], 200);
        }else{
            $check_join = Submission::where('id_event', $request->id_event)->where('additional_data', 'LIKE', '%' . $request->email .   '%')->first();

            if($check_join){
                return response()->json([
                    'code' => 409,
                    'status' => 'your email have joined this event'
                ], 409);
            }

            $additional_data = json_encode([
                'full_name' => $request->full_name,
                'email' => $request->email,
                'phone' => $request->phone
            ]);
            
            $query = Submission::create([
                'id_event' => $request->id_event,
                'id_user' => null,
                'additional_data' => $additional_data,
                'type' => 'submission',
                'status' => 'pending'
            ]);

            if($query){
                $details = [
                    'full_name' => $request->full_name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'title' => $event->title,
                    'link_to' => $link_to
                ];

                EventVerfication::dispatch($details);

                return response()->json([
                    'code' => 200,
                    'status' => 'success join event'
                ], 200);
            }
        }
    }

    public function likeUnlikeCommentEvent(Request $request){
        $request->validate([
            'id_comment' => 'required|numeric',
        ]);

        $userId = get_id_user_jwt($request);
        
        $check_comment = Comment::where('id_comment', $request->id_comment)->first();

        if($check_comment){
            $reaction = React::where([['related_to', 'id_comment'], ['id_related_to', $request->id_comment], ['id_user', $userId]])->first();
            if($reaction){
                $reaction->delete();

                return response()->json([
                    'code' => 200,
                    'status' => 'success unlike comment'
                ], 200);
            }

            React::create([
                'related_to' => 'id_comment',
                'id_related_to' => $request->id_comment,
                'id_user' => $userId
            ]);

            return response()->json([
                'code' => 200,
                'status' => 'success like comment'
            ], 200);
        }

        return response()->json([
            'code' => 404,
            'status' => 'comment not found'
        ], 404);
    }

    public function createCommentEvent(Request $request){
        $request->validate([
            'id_event' => 'required|numeric',
            'comment' => 'required|string'
        ]);

        $userId = get_id_user_jwt($request);

        $checkEvent = Event::where('id_event', $request->id_event)->first();

        if($checkEvent){
            $current_time = new DateTime('now');

            Comment::insert([
                'related_to' => 'id_event',
                'id_related_to' => $request->id_event,
                'comment' => $request->comment,
                'id_user' => $userId,
                'created_at' => $current_time->format('Y-m-d H:i:s')
            ]);

            return response()->json([
                'code' => 200,
                'status' => 'success comment event'
            ], 200);
        }

        return response()->json([
            'code' => 404,
            'status' => 'event not found'
        ], 404);
    }

    public function createReplyComment(Request $request){
        $request->validate([
            'id_comment' => 'required|numeric',
            'comment' => 'required|string'
        ]);

        $userId = get_id_user_jwt($request);

        $checkComment = Comment::where([['id_comment', $request->id_comment], ['related_to', '!=', 'id_comment']])->first();

        if ($checkComment){
            $current_time = new DateTime('now');

            Comment::insert([
                'related_to' => 'id_comment',
                'id_related_to' => $request->id_comment,
                'comment' => $request->comment,
                'id_user' => $userId,
                'created_at' => $current_time->format('Y-m-d H:i:s')
            ]);

            return response()->json([
                'code' => 200,
                'status' => 'success reply comment event'
            ], 200);
        }

        return response()->json([
            'code' => 404,
            'status' => 'comment not found'
        ], 404);
    }

    public function getListCommentEvent(Request $request){
        $id_event = $request->input('id_event');

        $checkEvent = Event::where('id_event', $id_event)->whereNull('deleted_at')->first();

        if($checkEvent){
            $commentEvent = Comment::withCount(['react as like' => function ($query) {
                $query->where('related_to', 'id_comment');
            }])->with(['user' => function($query) {
                $query->with(['job' => function($jobQuery){
                    $jobQuery->select('id_job', 'job_title');
                }])->with(['company' => function($companyQuery){
                    $companyQuery->select('id_company', 'company_name');
                }])->select('id_user', 'full_name', 'profile_picture', 'id_job', 'id_company');
            }])->where([['related_to', 'id_event'], ['id_related_to', $id_event]])->get();

            $commentEvent->makeHidden('id_user');

            if($commentEvent->count() != 0){
                foreach($commentEvent as $ce){
                    $commentReply = Comment::withCount(['react as like' => function ($query) {
                        $query->where('related_to', 'id_comment');
                    }])->with(['user' => function($query) {
                        $query->with(['job' => function($jobQuery){
                            $jobQuery->select('id_job', 'job_title');
                        }])->with(['company' => function($companyQuery){
                            $companyQuery->select('id_company', 'company_name');
                        }])->select('id_user', 'full_name', 'profile_picture', 'id_job', 'id_company');
                    }])->where([['related_to', 'id_comment'], ['id_related_to', $ce->id_comment]])->get();

                    $ce->comment_reply = $commentReply;
                }
                
                return response()->json([
                    'code' => 200,
                    'status' => 'success get list comment event',
                    'result' => $commentEvent
                ], 200);
            }

            return response()->json([
                'code' => 200,
                'status' => 'no comments yet',
            ], 200);
        }

        return response()->json([
            'code' => 404,
            'status' => 'event not found'
        ], 404);
    }

    public function getAllUserCommunity(Request $request){
        $limit = $request->input('limit');
        $start = $request->input('start');
        $id_community = $request->input('id_community');

        $query = CommunityUser::with(['user' => function($queryUser) {
            $queryUser->with(['job' => function($queryJob) {
                $queryJob->select('id_job', 'job_title');
            }])->with(['company' => function($queryCompany){
                $queryCompany->select('id_company', 'company_name');
            }])->select('id_user', 'full_name', 'profile_picture', 'id_job', 'id_company');
        }])->select('id_community_user', 'id_community', 'id_user')->where('id_community', $id_community)->where('status', '=', 'active');

        if ($limit !== null) {
            $query->limit($limit);
            if ($start !== null) {
                $query->offset($start);
            }
        }

        $allUserCommunity = $query->get();

        if($allUserCommunity->count() != 0){
            return response()->json([
                "code" => 200,
                "status" => "success get all user in community",
                "result" => $allUserCommunity
            ], 200);
        }

        return response()->json([
            "code" => 404,
            "status" => "community has no members",
        ], 404);
    }

    public function getEventBasedOnCommunity(Request $request){
        $start = $request->input('start', 0);
        $limit = $request->input('limit', 5);

        $result = new stdClass;

        $id_community = $request->input('id_community');

        $event = Event::withCount(['react as like_event' => function($query){
            $query->where('related_to', 'id_event');
        }])
        ->withCount(['comment as comment_event' => function($query){
            $query->where('related_to', 'id_event');
        }])
        ->withCount(['submission as participant_joined' => function($query){
            $query->where('status', 'confirmed');
        }])->where('id_community', (int)$id_community)->where('status', 'active')->whereNull('deleted_at')
        ->offset($start)->limit($limit)
        ->get();

        foreach($event as $evn){
            $evn->additional_data = json_decode($evn->additional_data);

            // ticket available
            if(isset($evn->additional_data->total_seats)){
                $evn->ticket_available = $evn->additional_data->total_seats - $evn->participant_joined;
            }else{
                $evn->ticket_available = 0;
            }
        }

        if ($event != null) {
            $result->event = $event;
        } else {
            $result->event = null;
        }

        $allEvent = Event::where('id_community', (int)$id_community)
        ->where('status', 'active')
        ->whereNull('deleted_at')
        ->count();

        $result->meta = [
            'start' => $start,
            'limit' => $limit,
            'total_data' => $allEvent,
        ];

        if($event->count() != 0){
            return response_json(200, "success get event based on community", $result);
        }

        return response()->json([
            'code' => 404,
            'status' => 'There are no events in this community',
        ], 404);
    }

    public function getEventTopBasedOnCommunity(Request $request){
        $id_community = $request->input('id_community');

        $result = new stdClass;

        $topEvent = Event::select('id_event', 'id_community', 'title', 'image', 'category', 'additional_data', 'status')
        ->whereNull('deleted_at')
        ->where('status', 'active')
        ->where('category', 'event')
        ->where('id_community', $id_community)
        ->withCount('submission as people_joined')
        ->orderBy('people_joined', 'DESC')->limit(3)->get();

        foreach ($topEvent as $item){
            $item->additional_data = json_decode($item->additional_data);
        }

        $result->top_event = $topEvent;

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $result,
        ]);
    }

    public function getYourEventBasedOnCommunity(Request $request){
          $id_community = $request->input('id_community');

          // Get id_user from Bearer Token
          $userId = get_id_user_jwt($request);
        
          $result = new stdClass;
  
          // Your event
          $submission = Submission::where('id_user', $userId)->pluck('id_event');
          $event = Event::whereIn('id_event', $submission)
          ->whereNull('deleted_at')
          ->where('status', 'active')
          ->where('category', 'event')
          ->where('id_community', $id_community)
          ->select('id_event', 'id_community', 'title', 'image', 'category', 'additional_data', 'status')
          ->withCount('submission as people_joined')
          ->orderBy('additional_data->date->start', 'ASC')->get();
  
          foreach ($event as $item){
              $item->additional_data = json_decode($item->additional_data);
          }
  
          if($event->first() != null){
              $result->your_event = $event;
          } else {
              $result->your_event = null;
          }
  
          return response()->json([
              'code' => 200,
              'status' => 'success',
              'result' => $result,
          ]);
    }

    public function getCommunityByUser(Request $request)
    {
        $user_id = get_id_user_jwt($request);

        $cacheKey = "user_communities_{$user_id}";

        $result = Cache::remember($cacheKey, 172800, function () use ($user_id, $request) {
            $communities = CommunityUser::where('id_user', $user_id)->pluck('id_community');

            $query = Community::select('id_community', 'title', 'image', 'type', 'status')
                ->whereIn('id_community', $communities)
                ->withCount(['community_user' => function ($query) {
                    $query->where('status', 'active');
                }])
                ->where('status', 'active');

            if ($request->input('community') !== null) {
                $query->where('title', 'like', '%' . $request->input('community') . '%');
            }

            return $query->get();
        });

        return response_json(200, 'success', $result);
    }

    public function getManagedCommunityByUser(Request $request)
    {
        $user_id = get_id_user_jwt($request);

        $communities = CommunityManager::where('id_user', $user_id)->pluck('id_community');

        $query = Community::select('id_community', 'title', 'image', 'type', 'status')
            ->whereIn('id_community', $communities)
            ->withCount('community_user')
            ->where('status', 'active');

        if ($request->input('community') !== null) {
            $query->where('title', 'like', '%' . $request->input('community') . '%');
        }

        $result = $query->get();

        return response_json(200, 'success', $result);
    }

    public function postShareCommunity(Request $request)
    {
        $request->validate([
            'id_community' => 'numeric',
            'description' => 'required|string',
            'id_related_to' => 'numeric'
        ]);

        $user_id = get_id_user_jwt($request);
        $current_time = now();
        $imageUrls = [];

        if ($request->hasFile('additional_data')) {
            foreach ($request->file('additional_data') as $imageData) {
                $imageUrl = $this->processImage($imageData);
                if ($imageUrl) {
                    $imageUrls[] = $imageUrl;
                }
            }
        }

        $timeline = $this->createTimeline($user_id, $request->id_community, $request->description, $current_time);

        foreach ($imageUrls as $imageUrl) {
            $this->attachFileToTimeline($timeline, $imageUrl);
        }

        if ($request->id_related_to !== null) {
            $this->shareToCommunity($timeline, $request->id_related_to, $current_time);
        }

        return response()->json([
            'code' => 200,
            'status' => 'share community success'
        ], 200);
    }

    private function processImage($imageData)
    {
        if (!$imageData) {
            return null;
        }

        $image = Image::make($imageData)->resize(400, null, function ($constraint) {
            $constraint->aspectRatio();
        });

        $filename = date('dmYhis') . uniqid() . '_feeds.' . $imageData->getClientOriginalExtension();
        $data = $image->encode($imageData->getClientOriginalExtension())->__toString();
        Storage::put('public/picture_queue/' . $filename, $data);
        UploadImageFeeds::dispatch($filename);
        $key = "userfiles/images/journey/" . $filename;
        $imageUrl = config('filesystems.disks.s3.bucketurl') . "/" . $key;

        return $imageUrl;
    }

    private function createTimeline($userId, $communityId, $description, $createdAt)
    {
        return Timeline::insertGetId([
            'id_user' => $userId,
            'id_community' => $communityId,
            'description' => $description,
            'created_at' => $createdAt
        ]);
    }

    private function attachFileToTimeline($timelineId, $imageUrl)
    {
        FileAttachment::insert([
            'related_to' => 'id_timeline',
            'id_related_to' => $timelineId,
            'attachment_type' => 'photo',
            'attachment_file' => $imageUrl,
            'created_at' => now()
        ]);
    }

    private function shareToCommunity($timelineId, $relatedToId, $current_time)
    {
        TimelineShare::insert([
            'id_timeline' => $timelineId,
            'related_to' => 'id_community',
            'id_related_to' => $relatedToId,
            'created_at' => $current_time,
            'updated_at' => $current_time
        ]);
    }   

    public function createCommunity(Request $request)
    {
        $user_id = get_id_user_jwt($request);
        $current_time = now();
        
        $same_title = Community::where('title', $request->title)->count();
        if($same_title > 0){
            return response()->json([
                'code' => 403,
                'status' => 'The title already used'
            ], 403);
        }
        
        $community_id = Community::insertGetId([
            'title' => $request->title,
            'description' => $request->description,
            'type' => $request->type,
            'status' => 'active',
            'created_at' => $current_time,
            'updated_at' => $current_time   
        ]);

        if($request->type == 'private_code'){
            Community::where('id_community', $community_id)->update([
                'referral_code' => $request->referral_code
            ]);
        }
        
        $interest = $request->id_interest;
        foreach($interest as $result){
            CommunityInterest::insert([
                'community_id' => $community_id,
                'id_interest' => $result,
                'created_at' => $current_time
            ]);
        }

        CommunityManager::insert([
            'id_user' => $user_id,
            'id_community' => $community_id
        ]);

        return response()->json([
            'code' => 200,
            'status' => 'create community success'
        ], 200);
    }

    public function listCommunityManager(Request $request){
        $limit = $request->input('limit');
        $start = $request->input('start');
        $id_community = $request->input('id_community');

        $query = CommunityManager::with(['user' => function($queryUser) {
            $queryUser->with(['job' => function($queryJob) {
                $queryJob->select('id_job', 'job_title');
            }])->with(['company' => function($queryCompany){
                $queryCompany->select('id_company', 'company_name');
            }])->select('id_user', 'full_name', 'profile_picture', 'id_job', 'id_company');
        }])->select('id_user')->where('id_community', $id_community);

        if ($limit !== null) {
            $query->limit($limit);
            if ($start !== null) {
                $query->offset($start);
            }
        }

        $allUserCommunity = $query->get();

        if($allUserCommunity->count() != 0){
            return response()->json([
                "code" => 200,
                "status" => "success get all community manager",
                "result" => $allUserCommunity
            ], 200);
        }

        return response()->json([
            "code" => 404,
            "status" => "community has no manager",
        ], 404);
    }
}
