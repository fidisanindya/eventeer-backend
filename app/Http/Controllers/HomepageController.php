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
            // $user_interest = UserProfile::where('id_user', $userId)->where('key_name', 'id_interest')->get();
            // if ($user_interest->first() != null) {
            //     foreach ($user_interest as $key => $interest) {
            //         $interest_name = Interest::where('id_interest', $interest->value)->get();
                    
            //         $community_interest = CommunityInterest::where('id_interest', $interest_name[$key]->id_interest)->get();

            //         dd($user_interest, $interest_name, $community_interest);
            //     }
            // } else {

            // }

            $community = Community::limit(8)->get();
        } elseif($request->input('filter') == 'all') {
            $community = Community::limit(8)->get();
        }

        $community->makeHidden(['description', 'location', 'id_vendor', 'created_at', 'updated_at', 'status', 'referral_code', 'start_date', 'end_date']);
        
        foreach ($community as $key => $item) {
            // Add tag to result
            $tag = CommunityInterest::with('interest')->where('community_id', $item->id_community)->get();
            if($tag != null) {
                $tag->each(function ($item) {
                    $item->interest->makeHidden(['created_by', 'created_at', 'updated_at', 'deleted_at']);
                });
                $tag->makeHidden(['created_at', 'updated_at', 'deleted_at']);
            }
            $item->tag = $tag;

            // Add total members to result
            $members = CommunityUser::select('id_community', DB::raw('COUNT(id_user) as total_members'))->where('id_community', $item->id_community)->groupBy('id_community')->first();
            $item->members = $members;

            // Add Friends are member to result
            $followed_id = Follow::where('followed_by', $userId)->pluck('id_user');

            $profilePicture1 = User::where('id_user', $followed_id[0])->get()->pluck('profile_picture');
            $profilePicture2 = User::where('id_user', $followed_id[1])->get()->pluck('profile_picture');
            
            if($key < count($followed_id)){
                $followed_communities = CommunityUser::whereIn('id_user', $followed_id)->select('id_community', DB::raw('COUNT(id_user) as total_friends'))->where('id_community', $item->id_community)->groupBy('id_community')->first();
            }
            $item->friends = [
                'followed'          => $followed_communities,
                'profile_picture'   => [
                    'profile_one'   => $profilePicture1,
                    'profile_two'   => $profilePicture2
                ],
            ];
        }

        $result->featured_community = $community;

        // Featured Event
        $event = Event::with(['community' => function ($item) {
            $item->select('id_community', 'title', 'image');
        }])->where('deleted_at', null)->limit(4)->latest()->get();
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
