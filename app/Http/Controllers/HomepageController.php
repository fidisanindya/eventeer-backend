<?php

namespace App\Http\Controllers;

use stdClass;
use App\Models\User;
use App\Models\Event;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\Follow;
use App\Models\Interest;
use App\Models\Community;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use App\Models\CommunityUser;
use App\Models\CommunityInterest;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Submission;

class HomepageController extends Controller
{
    public function get_homepage(Request $request){
        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);

        $result = new stdClass;

        // Featured Community
        $followed_id = Follow::where('followed_by', $userId)->pluck('id_user');

        if($request->input('filter') == 'interest') {
            $user_interest = UserProfile::where('id_user', $userId)->where('key_name', 'id_interest')->pluck('value');
            if ($user_interest->first() != null) {
                $community_interest = CommunityInterest::whereIn('id_interest', $user_interest)->pluck('community_id');
                $community = Community::whereIn('id_community', $community_interest)
                ->select('id_community', 'title', 'image', 'type', 'banner')
                ->with(['community_interest' => function($queryCommunity){
                    $queryCommunity->with(['interest' => function($queryInterest){
                        $queryInterest->select('id_interest', 'interest_name');
                    }])->select('id_community_interest', 'id_interest', 'community_id');
                }])
                ->withCount(['community_user as total_members' => function ($query) {
                    $query->where('status', '=', 'active')->whereOr('status', '=', 'running');
                }])
                ->withCount(['community_user as total_friends' => function ($query) use ($followed_id) {
                    $query->whereIn('id_user', $followed_id)->where('status', '=', 'active')->whereOr('status', '=', 'running');
                }])
                ->with(['community_user' => function($query) use ($followed_id) {
                    $query->whereIn('id_user', $followed_id)
                    ->select('id_community_user', 'id_user', 'id_community')
                    ->with(['user' => function($queryUser) {
                        $queryUser->whereNotNull('profile_picture')
                        ->select('id_user', 'profile_picture')->take(2)->get();
                    }]);
                }])
                ->where('type', '!=', 'private_whitelist')
                ->where('status', 'active')
                ->limit(8)
                ->get();
            } else {
                $community = null;
            }
        } else if($request->input('filter') == 'all' || $request->input('filter') == null) {
            $community = Community::select('id_community', 'title', 'image', 'type', 'banner')
            ->with(['community_interest' => function($queryCommunity){
                $queryCommunity->with(['interest' => function($queryInterest){
                    $queryInterest->select('id_interest', 'interest_name');
                }])->select('id_community_interest', 'id_interest', 'community_id');
            }])
            ->withCount(['community_user as total_members' => function ($query) {
                $query->where('status', '=', 'active')->whereOr('status', '=', 'running');
            }])
            ->withCount(['community_user as total_friends' => function ($query) use ($followed_id) {
                $query->whereIn('id_user', $followed_id)->where('status', '=', 'active')->whereOr('status', '=', 'running');
            }])
            ->with(['community_user' => function($query) use ($followed_id) {
                $query->whereIn('id_user', $followed_id)
                ->select('id_community_user', 'id_user', 'id_community')
                ->with(['user' => function($queryUser) {
                    $queryUser->whereNotNull('profile_picture')
                    ->select('id_user', 'profile_picture')->take(2)->get();
                }]);
            }])
            ->where('type', '!=', 'private_whitelist')
            ->where('status', 'active')
            ->limit(8)
            ->get();
        }

        $result->featured_community = $community;

        // Featured Event
        $event = Event::with(['community' => function ($item) {
            $item->select('id_community', 'title', 'image');
        }])
        ->whereNull('deleted_at')
        ->where('status', 'active')
        ->where('category', 'event')
        ->select('id_event', 'id_community', 'title', 'image', 'category', 'additional_data', 'status')
        ->withCount('submission as people_joined')
        ->limit(4)
        ->orderBy('additional_data->date->start', 'ASC')->get();

        foreach ($event as $item){
            $item->additional_data = json_decode($item->additional_data);
        }

        $result->featured_event = $event;

        return response_json(200, 'success', $result);
    }
}
