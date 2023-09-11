<?php

namespace App\Http\Controllers;

use stdClass;
use App\Models\User;
use App\Models\Community;
use App\Models\Notification;
use Illuminate\Http\Request;
use App\Models\CommunityUser;
use App\Models\CommunityManager;
use Illuminate\Support\Facades\Validator;
use Pusher\PushNotifications\PushNotifications; 

class NotificationController extends Controller
{
    public function generate_token(Request $request){
        // Get user id from jwt
        $user_id = get_id_user_jwt($request);

        $beamsClient = new PushNotifications([
            "instanceId" => env('PUSHER_APP_ID'),
            "secretKey" => env('PUSHER_APP_KEY'),
        ]);

        $beamsToken = $beamsClient->generateToken(env('PUSHER_PREFIX') . '-' . $user_id);

        return response_json(200, 'success', $beamsToken);
    }

    public function get_updates_notif(Request $request){
        // Get user id from jwt
        $user_id = get_id_user_jwt($request);

        $result = new stdClass;

        // Get Reminder For You Notification
        $reminder = Notification::where('id_user', $user_id)
        ->where('tab', 'Updates')
        ->where('section', 'reminder')
        ->whereNull('deleted_at')
        ->orderBy('created_at', 'DESC')
        ->limit(5)
        ->get();
        $reminder->makeHidden(['notif_from', 'url', 'additional_data', 'deleted_at']);
        $result->reminder = $reminder;

        // Get Invitation and Request Notification
        $invitation = Notification::where('id_user', $user_id)
        ->where('tab', 'Updates')
        ->where('section', 'invitation')
        ->whereNull('deleted_at')
        ->orderBy('created_at', 'DESC')
        ->limit(5)
        ->get();
        $invitation->makeHidden(['notif_from', 'url', 'deleted_at']);
        foreach ($invitation as $item) {
            if($item->additional_data != null) {
                $item->additional_data = json_decode($item->additional_data);
            }
        }
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
        ->orderBy('created_at', 'DESC')
        ->limit(5)
        ->get();
        $reminder->makeHidden('deleted_at');
        foreach ($reminder as $item) {
            if($item->additional_data != null) {
                $item->additional_data = json_decode($item->additional_data);
            }
        }
        $result->reminder = $reminder;

        // Get Invitation Notification
        $invitation = Notification::where('id_user', $user_id)
        ->where('tab', 'Activity')
        ->where('section', 'invitation')
        ->whereNull('deleted_at')
        ->orderBy('created_at', 'DESC')
        ->limit(5)
        ->get();
        $invitation->makeHidden('deleted_at');
        foreach ($invitation as $item) {
            if($item->additional_data != null) {
                $item->additional_data = json_decode($item->additional_data);
            }
        }
        $result->invitation = $invitation;

        // Get New For You Notification
        $new_activity = Notification::with(['community' => function($queryCommunity){
            $queryCommunity->select('id_community', 'image');
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
        $reminder = Notification::with(['community' => function ($queryCommunity) {
            $queryCommunity->select('id_community', 'image');
        }])
        ->where('id_user', $user_id)
        ->where('tab', 'Activity')
        ->where('section', 'new_member')
        ->whereNull('deleted_at')
        ->orderBy('created_at', 'DESC')
        ->limit(5)
        ->get();
        $reminder->makeHidden('deleted_at');
        foreach ($reminder as $item) {
            if($item->additional_data != null) {
                $item->additional_data = json_decode($item->additional_data);
            }
        }
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

    public function post_invitation_confirmation(Request $request){
        $validator = Validator::make($request->all(), [
            'id_community' => 'required',
            'id_user' => 'required',
            'tab' => 'required',
            'confirmation' => 'required',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }

        // Join confirmation
        $community_user = CommunityUser::with('community')->where('id_user', $request->id_user)->where('id_community', $request->id_community)->where('status', 'pending')->first();
        if ($request->tab == 'updates') {
            if ($request->confirmation == 'accept') {
                if ($community_user != null) {
                    $community_user->update([
                        'status' => 'active',
                    ]);

                    // Send Notification
                    $id_manager_community = CommunityManager::where('id_community', $community_user->community->id_community)->pluck('id_user');

                    $user = User::where('id_user', $request->id_user)->first();

                    foreach ($id_manager_community as $value) {
                        $check_notification_exists = Notification::where('id_user', $value)->where('tab', 'Activity')->where('section', 'new_member')->first();
                        
                        if ($check_notification_exists == null) {
                            $additional_data = [
                                'type' => 'null',
                                'post' => 'null',
                                'modal' => [
                                    [
                                        'id_user' => $request->id_user,
                                        'full_name' => $user->full_name,
                                        'profile_picture' => $user->profile_picture,
                                    ],
                                ],
                            ];

                            send_notification('<b>' . $community_user->community->title . '</b> has some new members', $value, $community_user->community->id_community, null, null, 'Activity', 'new_member', null, json_encode($additional_data) );
                        } else {  
                            $new_additional_data = [
                                'id_user' => $request->id_user,
                                'full_name' => $user->full_name,
                                'profile_picture' => $user->profile_picture,
                            ];
                            $additional_data = json_decode($check_notification_exists->additional_data);
                            $additional_data->modal[] = $new_additional_data;

                            $check_notification_exists->update([
                                'status' => 'unread',
                                'additional_data' => json_encode($additional_data),
                            ]);
                        }
                    }

                    // Update additional data in notification
                    $notification = Notification::where('id_notif', $request->id_notif)->first();

                    $additional_data_notif = json_decode($notification->additional_data, true);

                    $index_to_delete = null;
                    foreach ($additional_data_notif['modal'] as $index => $modal) {
                        if ($modal['id_community'] == $request->id_community) {
                            $index_to_delete = $index;
                            break;
                        }
                    }

                    if ($index_to_delete !== null) {
                        unset($additional_data_notif['modal'][$index_to_delete]);
                        $additional_data_notif['modal'] = array_values($additional_data_notif['modal']); 
                    }

                    $updatedAdditionalData = json_encode($additional_data_notif);

                    $notification->additional_data = $updatedAdditionalData;
                    $notification->save();

                    return response_json(200, 'success', 'User successfully joined to this community');
                }

                return response_json(404, 'failed', 'User is not invited to this community/User already joined this community');

            } elseif ($request->confirmation == 'deny') {
                if ($community_user != null) {
                    $community_user->delete();

                    // Update additional data in notification
                    $notification = Notification::where('id_notif', $request->id_notif)->first();

                    $additional_data_notif = json_decode($notification->additional_data, true);

                    $index_to_delete = null;
                    foreach ($additional_data_notif['modal'] as $index => $modal) {
                        if ($modal['id_community'] == $request->id_community) {
                            $index_to_delete = $index;
                            break;
                        }
                    }

                    if ($index_to_delete !== null) {
                        unset($additional_data_notif['modal'][$index_to_delete]);
                        $additional_data_notif['modal'] = array_values($additional_data_notif['modal']); 
                    }

                    $updatedAdditionalData = json_encode($additional_data_notif);

                    $notification->additional_data = $updatedAdditionalData;
                    $notification->save();

                    return response_json(200, 'success', 'User successfully denied to join this community');
                }

                return response_json(404, 'failed', 'User is not invited to this community');
            }
        } elseif ($request->tab == 'managed') {
            if ($request->confirmation == 'accept') {
                if ($community_user != null) {
                    $community_user->update([
                        'status' => 'active'
                    ]);

                    // Send Notification
                    $check_notification_exists = Notification::where('id_user', $request->id_user)->where('tab', 'Updates')->where('section', 'invitation')->first();

                    $community = Community::where('id_community', $request->id_community)->first();

                    if($check_notification_exists == null) {
                        $additional_data = [
                            'type'  => 'null',
                            'post'  => 'null',
                            'modal' => [
                                [
                                'id_community' => $community->id_community,
                                'title' => $community->title,
                                'status' => 'accepted'
                                ],
                            ]
                        ];

                        send_notification('Your request to join a community has responded to. Check this out!', $request->id_user, null, null, null, 'Updates', 'invitation', null, json_encode($additional_data));
                    } else {
                        $new_additional_data = [
                            'id_community' => $community->id_community,
                            'title' => $community->full_name,
                            'status' => 'accepted',
                        ];
                        $additional_data = json_decode($check_notification_exists->additional_data);
                        $additional_data->modal[] = $new_additional_data;

                        $check_notification_exists->update([
                            'status' => 'unread',
                            'additional_data' => json_encode($additional_data)
                        ]);
                    }

                    // Update additional data in notification
                    $notification = Notification::where('id_notif', $request->id_notif)->first();

                    $additional_data_notif = json_decode($notification->additional_data, true);

                    $index_to_delete = null;
                    foreach ($additional_data_notif['modal'] as $index => $modal) {
                        if ($modal['id_user'] == $request->id_user) {
                            $index_to_delete = $index;
                            break;
                        }
                    }

                    if ($index_to_delete !== null) {
                        unset($additional_data_notif['modal'][$index_to_delete]);
                        $additional_data_notif['modal'] = array_values($additional_data_notif['modal']); 
                    }

                    $updatedAdditionalData = json_encode($additional_data_notif);

                    $notification->additional_data = $updatedAdditionalData;
                    $notification->save();

                    return response_json(200, 'success', 'User successfully joined to this community');
                }

                return response_json(404, 'failed', 'User is not requested to join this community/User already joined this community');
            } elseif ($request->confirmation == 'deny') {
                if ($community_user != null) {
                    $community_user->delete();

                    // Send Notification
                    $check_notification_exists = Notification::where('id_user', $request->id_user)->where('tab', 'Updates')->where('section', 'invitation')->first();

                    $community = Community::where('id_community', $request->id_community)->first();

                    if($check_notification_exists == null) {
                        $additional_data = [
                            'type'  => 'null',
                            'post'  => 'null',
                            'modal' => [
                                [
                                'id_community' => $community->id_community,
                                'title' => $community->title,
                                'status' => 'denied'
                                ],
                            ]
                        ];

                        send_notification('Your request to join a community has responded to. Check this out!', $request->id_user, null, null, null, 'Updates', 'invitation', null, json_encode($additional_data));
                    } else {
                        $new_additional_data = [
                            'id_community' => $community->id_community,
                            'title' => $community->full_name,
                            'status' => 'accepted',
                        ];
                        $additional_data = json_decode($check_notification_exists->additional_data);
                        $additional_data->modal[] = $new_additional_data;
                        
                        $check_notification_exists->update([
                            'status' => 'unread',
                            'additional_data' => json_encode($additional_data)
                        ]);
                    }

                    // Update additional data in notification
                    $notification = Notification::where('id_notif', $request->id_notif)->first();

                    $additional_data_notif = json_decode($notification->additional_data, true);

                    $index_to_delete = null;
                    foreach ($additional_data_notif['modal'] as $index => $modal) {
                        if ($modal['id_user'] == $request->id_user) {
                            $index_to_delete = $index;
                            break;
                        }
                    }

                    if ($index_to_delete !== null) {
                        unset($additional_data_notif['modal'][$index_to_delete]);
                        $additional_data_notif['modal'] = array_values($additional_data_notif['modal']); 
                    }

                    $updatedAdditionalData = json_encode($additional_data_notif);

                    $notification->additional_data = $updatedAdditionalData;
                    $notification->save();

                    return response_json(200, 'success', 'User successfully denied to join this community');
                }

                return response_json(404, 'failed', 'User is not requested to join this community/User already joined this community');
            }
        }
    }
}
