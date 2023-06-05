<?php

namespace App\Http\Controllers;

use stdClass;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    public function get_updates_notif(Request $request){
        // Get user id from jwt
        $user_id = get_id_user_jwt($request);

        $result = new stdClass;

        // Get Reminder For You Notification
        $reminder = Notification::where('id_user', $user_id)
        ->where('tab', 'Updates')
        ->where('section', 'reminder')
        ->whereNull('deleted_at')
        ->select('id_notif', 'id_user', 'status', 'content', 'tab', 'section', 'created_at')
        ->orderBy('created_at', 'DESC')
        ->limit(5)
        ->get();
        $result->reminder = $reminder;

        // Get Invitation and Request Notification
        $invitation = Notification::where('id_user', $user_id)
        ->where('tab', 'Updates')
        ->where('section', 'invitation')
        ->whereNull('deleted_at')
        ->select('id_notif', 'id_user', 'status', 'content', 'tab', 'section', 'created_at')
        ->orderBy('created_at', 'DESC')
        ->limit(5)
        ->get();
        $result->invitation = $invitation;

        // Get Engagement Notification
        $engagement = Notification::with(['user' => function($user){
            $user->select('id_user', 'profile_picture');
        }])
        ->where('id_user', $user_id)
        ->where('tab', 'Updates')
        ->where('section', 'engagement')
        ->whereNull('deleted_at')
        ->orderBy('created_at', 'DESC')
        ->get();
        $engagement->makeHidden('deleted_at');
        foreach ($engagement as $item) {
            if($item->additional_data != null) {
                $item->additional_data = json_decode($item->additional_data);
            }
        }
        $result->engagement = $engagement;

        return response_json(200, 'success', $result);
    }

    public function get_activity_joined_community_notif(Request $request){
        // Get user id from jwt
        $user_id = get_id_user_jwt($request);

        $result = new stdClass;

        // Get Reminder For You Notification
        $reminder = Notification::where('id_user', $user_id)
        ->where('tab', 'Activity')
        ->where('section', 'reminder')
        ->whereNull('deleted_at')
        ->select('id_notif', 'id_user', 'status', 'content', 'tab', 'section', 'created_at')
        ->orderBy('created_at', 'DESC')
        ->limit(5)
        ->get();
        $result->reminder = $reminder;

        // Get Invitation Notification
        $invitation = Notification::where('id_user', $user_id)
        ->where('tab', 'Activity')
        ->where('section', 'invitation')
        ->whereNull('deleted_at')
        ->select('id_notif', 'id_user', 'status', 'content', 'tab', 'section', 'created_at')
        ->orderBy('created_at', 'DESC')
        ->limit(5)
        ->get();
        $result->invitation = $invitation;

        // Get New For You Notification
        $new_activity = Notification::with(['user' => function($user){
            $user->select('id_user', 'profile_picture');
        }])
        ->where('id_user', $user_id)
        ->where('tab', 'Activity')
        ->where('section', 'new_activity')
        ->whereNull('deleted_at')
        ->orderBy('created_at', 'DESC')
        ->get();
        $new_activity->makeHidden('deleted_at');
        foreach ($new_activity as $item) {
            if($item->additional_data != null) {
                $item->additional_data = json_decode($item->additional_data);
            }
        }
        $result->new_activity = $new_activity;

        return response_json(200, 'success', $result);
    }

    public function get_activity_managed_community_notif(Request $request){
        // Get user id from jwt
        $user_id = get_id_user_jwt($request);

        $result = new stdClass;

        // Get New Members Notification
        $reminder = Notification::where('id_user', $user_id)
        ->where('tab', 'Activity')
        ->where('section', 'new_member')
        ->whereNull('deleted_at')
        ->select('id_notif', 'id_user', 'status', 'content', 'tab', 'section', 'created_at')
        ->orderBy('created_at', 'DESC')
        ->limit(5)
        ->get();
        $result->reminder = $reminder;

        // Get Action Notification
        $action = Notification::where('id_user', $user_id)
        ->where('tab', 'Activity')
        ->where('section', 'action')
        ->whereNull('deleted_at')
        ->orderBy('created_at', 'DESC')
        ->limit(5)
        ->get();
        $action->makeHidden('deleted_at');
        foreach ($action as $item) {
            if($item->additional_data != null) {
                $item->additional_data = json_decode($item->additional_data);
            }
        }
        $result->action = $action;

        // Get Engagement Notification
        $engagement = Notification::with(['user' => function($user){
            $user->select('id_user', 'profile_picture');
        }])
        ->where('id_user', $user_id)
        ->where('tab', 'Activity')
        ->where('section', 'engagement')
        ->whereNull('deleted_at')
        ->orderBy('created_at', 'DESC')
        ->get();
        $engagement->makeHidden('deleted_at');
        foreach ($engagement as $item) {
            if($item->additional_data != null) {
                $item->additional_data = json_decode($item->additional_data);
            }
        }
        $result->engagement = $engagement;

        return response_json(200, 'success', $result);
    }

    public function post_read_notif(Request $request){
        $validator = Validator::make($request->all(), [
            'id_notif' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }

        $notif = Notification::where('id_notif', $request->id_notif)->first();

        if($notif != null) {
            $notif->update([
                'status' => 'read'
            ]);

            return response_json(200, 'success', 'Notif Readed');
        }

        return response_json(404, 'failed', 'Notif Not Found');
    }

    public function post_read_all_notif(Request $request){
        $validator = Validator::make($request->all(), [
            'tab' => 'required',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }

        // Get id user from jwt
        $user_id = get_id_user_jwt($request);

        // Read all notif
        Notification::where('id_user', $user_id)
        ->when(request()->has('tab'), function ($query){
            $tab = request()->input('tab');
            if($tab == 'updates'){
                $query->where('tab', 'Updates');
            }elseif($tab == 'joined'){
                $query->where('tab', 'Activity')->whereIn('section', ['reminder', 'invitation', 'new_activity']);
            }elseif($tab == 'managed'){
                $query->where('tab', 'Activity')->whereIn('section', ['new_member', 'action', 'engagement']);
            }
        })->update([
            'status' => 'read'
        ]);
        
        return response_json(200, 'success', 'Successfully marked all as read');
    }
}
