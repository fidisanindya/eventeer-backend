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

class HomepageController extends Controller
{
    public function get_homepage(Request $request){
        // Get id_user from Bearer Token
        $authorizationHeader = $request->header('Authorization');

        $jwtParts = explode(' ', $authorizationHeader);
        $jwtToken = $jwtParts[1];

        $publicKey = env("JWT_PUBLIC_KEY"); 
        $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
        
        $userId = $decoded->data->id_user;

        $result = new stdClass;

        // Featured Community
        if($request->input('filter') == 'interest') {
            $user_interest = UserProfile::where('id_user', $userId)->where('key_name', 'id_interest')->pluck('value');
            if ($user_interest->first() != null) {
                $community_interest = CommunityInterest::whereIn('id_interest', $user_interest)->pluck('community_id');
                $community = Community::whereIn('id_community', $community_interest)->limit(8)->select('id_community', 'title', 'image', 'type', 'banner')->get();
            } else {
                $community = Community::limit(8)->select('id_community', 'title', 'image', 'type', 'banner')->get();
            }
        } else if($request->input('filter') == 'all') {
            $community = Community::limit(8)->select('id_community', 'title', 'image', 'type', 'banner')->get();
        }
        
        foreach ($community as $item) {
            // Add tag to result
            $community_tag = CommunityInterest::where('community_id', $item->id_community)->pluck('id_interest');
            if ($community_tag->first() != null) {
                $tag = Interest::whereIn('id_interest', $community_tag)->select('id_interest', 'interest_name')->get();
            }
            $item->tag = $tag;

            // Add total members to result
            $members = CommunityUser::select('id_community', DB::raw('COUNT(id_user) as total_members'))->where('id_community', $item->id_community)->groupBy('id_community')->first();
            $item->members = $members;

            // Add Friends are member to result
            $followed_id = Follow::where('followed_by', $userId)->pluck('id_user');

            if($followed_id->first() != null) {
                $userCommunities = CommunityUser::whereIn('id_user', $followed_id)->where('id_community', $item->id_community)->pluck('id_user');

                if ($userCommunities->first() != null) {
                    $profilePicture = User::whereIn('id_user', $userCommunities)->whereNotNull('profile_picture')->select('profile_picture')->take(2)->get();
                    
                    $arrayProfile = [];
                    for ($i=0; $i < count($profilePicture); $i++) { 
                        array_push($arrayProfile, $profilePicture[$i]->profile_picture);
                    }

                    $item->friends = [
                        'total_friends'     => count($userCommunities),
                        'profile_picture'   => $arrayProfile,
                    ];
                } else {
                    $item->friends = null;
                }
            } else {
                $item->friends = null;
            }
        }

        $result->featured_community = $community;

        // Featured Event
        $event = Event::with(['community' => function ($item) {
            $item->select('id_community', 'title', 'image');
        }])->where('deleted_at', null)->where('status', 'active')->limit(4)->latest()->get();
        $event->makeHidden(['description', 'created_at', 'updated_at', 'deleted_at', 'id_user', 'vendor_id']);

        foreach ($event as $item){
            $item->additional_data = json_decode($item->additional_data);
        }

        $result->featured_event = $event;

        return response()->json([
            'code'      => 200,
            'status'    => 'success',
            'result'    => $result,
        ]);
    }
}
