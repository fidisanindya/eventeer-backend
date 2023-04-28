<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\CommunityInterest;
use App\Models\CommunityUser;
use App\Models\Follow;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunityController extends Controller
{
    public function getCommunityPublic(Request $request){
        $request->validate([
            'id_user' => 'required'
        ]);

        $communityPublic = Community::select('id_community', 'title', 'image', 'banner', 'type')->where([['type', '=', 'public'], ['status', '=', 'active']])->withCount(['community_user as total_members' => function ($query) {
            $query->where('status', '=', 'active')->whereOr('status', '=', 'running');
        }])->get();

        $followed_id = Follow::where('followed_by', $request->id_user)->pluck('id_user');

        foreach ($communityPublic as $cp => $item){
            // Add tag to result
            $tag = CommunityInterest::with('interest')->where('community_id', $item->id_community)->get();
            if($tag != null) {
                $tag->each(function ($item) {
                    $item->interest->makeHidden(['created_by', 'created_at', 'updated_at', 'deleted_at', 'icon', 'icon_selected']);
                });
                $tag->makeHidden(['created_at', 'updated_at', 'deleted_at']);
            }
            $item->tag = $tag;

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

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $communityPublic,
        ]);
    }

    public function getCommunityInterest(Request $request){
        $request->validate([
            'id_user' => 'required'
        ]);

        $interestUser = UserProfile::select('value')->where([['id_user', '=', $request->id_user], ['key_name', '=', 'id_interest']])->get();

        $communityInterest = CommunityInterest::select('community_id')->whereIn('id_interest', $interestUser)->get();

        $idCommunity = [];

        foreach ($communityInterest as $ci){
            array_push($idCommunity, $ci->community_id);
        }

        // Delete duplicate ID
        $idCommunity = collect($idCommunity);
        $uniqueId = $idCommunity->unique();
        
        $communityByInterest = Community::select('id_community', 'title', 'image', 'banner', 'type')->whereIn('id_community', $uniqueId)->where('status', 'active')->withCount(['community_user as total_members' => function ($query) {
            $query->where('status', '=', 'active')->whereOr('status', '=', 'running');
        }])->get();

        $followed_id = Follow::where('followed_by', $request->id_user)->pluck('id_user');

        foreach($communityByInterest as $ci => $item){
            // Add tag to result
            $tag = CommunityInterest::with('interest')->where('community_id', $item->id_community)->get();
            if($tag != null) {
                $tag->each(function ($item) {
                    $item->interest->makeHidden(['created_by', 'created_at', 'updated_at', 'deleted_at', 'icon', 'icon_selected']);
                });
                $tag->makeHidden(['created_at', 'updated_at', 'deleted_at']);
            }
            $item->tag = $tag;

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
        
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $communityByInterest,
        ]);
    }

    public function getTopCommunity(Request $request){
        $request->validate([
            'id_user' => 'required'
        ]);

        $communityTop = Community::select('id_community', 'title', 'image')->withCount(['community_user as total_members' => function ($query) {
            $query->where('status', '=', 'active')->whereOr('status', '=', 'running');
        }])
        ->where('status', 'active')
        ->orderBy('total_members', 'DESC')->limit(3)->get();

        $followed_id = Follow::where('followed_by', $request->id_user)->pluck('id_user');

        foreach($communityTop as $ct => $item){
            // Add tag to result
            $tag = CommunityInterest::with('interest')->where('community_id', $item->id_community)->get();
            if($tag != null) {
                $tag->each(function ($item) {
                    $item->interest->makeHidden(['created_by', 'created_at', 'updated_at', 'deleted_at', 'icon', 'icon_selected']);
                });
                $tag->makeHidden(['created_at', 'updated_at', 'deleted_at']);
            }
            $item->tag = $tag;

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
}
