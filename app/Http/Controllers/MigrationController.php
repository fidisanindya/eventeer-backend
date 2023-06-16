<?php

namespace App\Http\Controllers;

use App\Models\GroupMessage;
use App\Models\GroupMessageRolemember;
use App\Models\MessageRoom;
use App\Models\MessageUser;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MigrationController extends Controller
{
    public function migration_about_me(Request $request){
        $request->validate([
            'limit' => 'required'
        ]);
        
        $user = UserProfile::select('*')->where('key_name', 'about_me')->limit($request->limit)->get();
        
        foreach($user as $us){
            $query = User::where('id_user', $us->id_user)->update([
                'bio' => $us->value
            ]);
            
            if($query){
                UserProfile::where('id_user_profile', $us->id_user_profile)->delete();
            }
        }

        return response_json(200, 'success', 'Success Migration');
    }

    public function migrate_id_job(Request $request){
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }

        $limit = $request->limit;

        $userProfile = UserProfile::where('key_name', 'id_job')->limit($limit)->get();

        if($userProfile->first() != null) {
            foreach ($userProfile as $up){
                User::where('id_user', $up->id_user)->update([
                    'id_job'    => $up->value,
                ]);;
                
                UserProfile::where('id_user_profile', $up->id_user_profile)->delete();
            }
    
            return response_json(200, 'success', 'Migrated ' . $limit . ' data successfully');
        }

        return response_json(500, 'failed', 'No data to migrate');
    }

    public function migrate_id_company(Request $request){
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }
        
        $limit = $request->limit;

        $userProfile = UserProfile::where('key_name', 'id_company')->limit($limit)->get();
        
        if($userProfile->first() != null){
            foreach ($userProfile as $up){
                User::where('id_user', $up->id_user)->update([
                    'id_company'    => $up->value,
                ]);
    
                UserProfile::where('id_user_profile', $up->id_user_profile)->delete();
            }
    
            return response_json(200, 'success', 'Migrated ' . $limit . ' data successfully');
        }

        return response_json(500, 'failed', 'No data to migrate');
    }

    public function migrate_group_message_to_message_room(Request $request){
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }
        
        $limit = $request->limit;

        $group_message = GroupMessage::limit($limit)->get();
        foreach ($group_message as $gm) {
            MessageRoom::create([
                'id_user' => $gm->id_user,
                'title' => $gm->title,
                'image' => $gm->image,
                'type' => $gm->type,
                'additional_data' => $gm->additonal_data,
                'deleted_at' => $gm->deleted_at,
                'created_at' => $gm->created_at,
                'updated_at' => $gm->updated_at,
            ]);

            GroupMessage::where('id_groupmessage', $gm->id_groupmessage)->delete();
        }
        return response_json(200, 'success', 'Migrated ' . $limit . ' data successfully');
    }

    public function migrate_group_message_rolemember_to_message_user(Request $request){
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }
        
        $limit = $request->limit;

        $group_message = GroupMessageRolemember::limit($limit)->get();
        foreach ($group_message as $gm) {
            MessageUser::create([
                'id_user' => $gm->id_user,
                'id_message_room' => $gm->id_groupmessage,
                'role' => $gm->role,
                'created_at' => $gm->created_at,
                'deleted_at' => $gm->deleted_at,
                'updated_at' => $gm->updated_at,
            ]);

            GroupMessageRolemember::where('id_groupmessage', $gm->id_groupmessage)->delete();
        }
        return response_json(200, 'success', 'Migrated ' . $limit . ' data successfully');
    }
}
