<?php

namespace App\Http\Controllers;

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
use Illuminate\Support\Facades\DB;

class CommunityController extends Controller
{
    public function getCommunityPublic(Request $request){
        // Get id_user from Bearer Token
        $limit = $request->input('limit');
        $start = $request->input('start');

        $authorizationHeader = $request->header('Authorization');

        $jwtParts = explode(' ', $authorizationHeader);
        $jwtToken = $jwtParts[1];

        $publicKey = env("JWT_PUBLIC_KEY"); 
        $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
        
        $userId = $decoded->data->id_user;

        $result = new stdClass;

        $query = Community::select('id_community', 'title', 'image', 'banner', 'type')->where([['type', '=', 'public'], ['status', '=', 'active']])->withCount(['community_user as total_members' => function ($query) {
            $query->where('status', '=', 'active')->whereOr('status', '=', 'running');
        }]);

        $totalData = Community::select('id_community', 'title', 'image', 'banner', 'type')->where([['type', '=', 'public'], ['status', '=', 'active']])->count();

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
                for ($i=0; $i < 2; $i++) { 
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

        $authorizationHeader = $request->header('Authorization');

        $jwtParts = explode(' ', $authorizationHeader);
        $jwtToken = $jwtParts[1];

        $publicKey = env("JWT_PUBLIC_KEY"); 
        $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
        
        $userId = $decoded->data->id_user;

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
        
        $query = Community::select('id_community', 'title', 'image', 'banner', 'type')->whereIn('id_community', $uniqueId)->where('status', 'active')->withCount(['community_user as total_members' => function ($query) {
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
                for ($i=0; $i < 2; $i++) { 
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
        $authorizationHeader = $request->header('Authorization');

        $jwtParts = explode(' ', $authorizationHeader);
        $jwtToken = $jwtParts[1];

        $publicKey = env("JWT_PUBLIC_KEY"); 
        $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
        
        $userId = $decoded->data->id_user;

        $communityTop = Community::select('id_community', 'title', 'image')->withCount(['community_user as total_members' => function ($query) {
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
                for ($i=0; $i < 2; $i++) { 
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
                } else if ($date != 'anytime') {
                    $query->whereDate('additional_data->date->start', $date);
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
        $authorizationHeader = $request->header('Authorization');

        $jwtParts = explode(' ', $authorizationHeader);
        $jwtToken = $jwtParts[1];

        $publicKey = env("JWT_PUBLIC_KEY"); 
        $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
        
        $userId = $decoded->data->id_user;

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
        ->whereIn('id_community', $public_community_interest)
        ->orWhereIn('id_community', $private_community_joined)
        ->whereNotIn('id_event', $joined_event)
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
                } else if ($date != 'anytime') {
                    $query->whereDate('additional_data->date->start', $date);
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

        $result->event_might_liked = $event;

        $allEvent = Event::whereNull('deleted_at')
        ->where('status', 'active')
        ->where('category', 'event')
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
        $authorizationHeader = $request->header('Authorization');

        $jwtParts = explode(' ', $authorizationHeader);
        $jwtToken = $jwtParts[1];

        $publicKey = env("JWT_PUBLIC_KEY"); 
        $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
        
        $userId = $decoded->data->id_user;
        
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
}
