<?php

namespace App\Http\Controllers;

use App\Jobs\UploadImageChat;
use Image;
use App\Jobs\UploadPDF;
use App\Models\Message;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\MessagePin;
use App\Models\MessageRoom;
use App\Models\MessageUser;
use Illuminate\Http\Request;
use App\Jobs\UploadImageGroup;
use App\Models\Company;
use App\Models\Follow;
use App\Models\Profession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    public function create_group_message(Request $request){
        $request->validate([
            'title' => 'required',
        ]);

        $userId = get_id_user_jwt($request);

        if($request->image){
            $image = Image::make($request->image)->resize(400, null, function ($constraint) {
                $constraint->aspectRatio();
            });

            $filename = date('dmYhis') . '_group.' . $request->image->getClientOriginalExtension();

            $data = $image->encode($request->image->getClientOriginalExtension())->__toString();

            Storage::put('public/picture_queue/' . $filename, $data);

            UploadImageGroup::dispatch($filename);

            $key = "userfiles/chat/" . $filename;

            $imageUrl = config('filesystems.disks.s3.bucketurl') . "/" . $key;
        }

        $query = MessageRoom::create([
            'id_user' => $userId,
            'title' => $request->title,
            'image' => $imageUrl ?? null,
            'type' => 'group',
            'description' => $request->description,
            'additional_data' => $request->additional_data
        ]);

        if($query){
            MessageUser::create([
                'id_user' => $query->id_user,
                'id_message_room' => $query->id_message_room,
                'role' => 'admin',
            ]);
        }else{
            return response()->json([
                "code" => 409,
                "status" => "failed create group message",
            ], 409);
        }

        return response()->json([
            "code" => 200,
            "status" => "success create group message",
        ], 200);
    }

    public function get_detail_group(Request $request){
        $id_group = $request->input('id_message_room');

        $data = MessageRoom::where([['id_message_room', $id_group], ['type', 'group']])->first();

        if(!$data){
            return response()->json([
                "code" => 404,
                "status" => "group not found",
                "result" => null
            ], 404);
        }

        $message_user = MessageUser::with(['user' => function ($query) {
            $query->with(['job' => function($job){
                $job->select('id_job', 'job_title');
            }])->select('id_user', 'full_name', 'profile_picture', 'id_job');
        }])->select('id_user', 'role')->where('id_message_room', $id_group)->get();

        $message_user->makeHidden('id_user');

        $data->list_member = $message_user;

        return response()->json([
            "code" => 200,
            "status" => "success",
            "result" => $data
        ], 200);
    }

    public function send_message(Request $request){
        $request->validate([
            "text" => "required",
            "id_message_room" => "",
            "with_id_user" => ""
        ]);

         // Get id_user from Bearer Token
         $userId = get_id_user_jwt($request);
 
         if(!$request->id_message_room){
            $validator = Validator::make($request->all(), [
                'with_id_user' => 'required|numeric',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'code'      => 422,
                    'status'    => 'failed',
                    'result'    => $validator->messages(),
                ], 422);
            }

            $query = MessageRoom::create([
                'id_user' => $userId,
                'title' => "",
                'type' => 'personal'
             ]);
             
             MessageUser::create([
                'id_user' => $query->id_user,
                'id_message_room' => $query->id_message_room,
                'role' => 'member'
             ]);
             MessageUser::create([
                'id_user' => $request->with_id_user,
                'id_message_room' => $query->id_message_room,
                'role' => 'member'
             ]);
             
        }
        
        if($request->hasFile('text')) {
            if($request->text->getClientOriginalExtension() == 'pdf'){
                $filename = date('dmYhis') . '_pdf.' . $request->text->getClientOriginalExtension();

                Storage::put('public/pdf_queue/' . $filename, file_get_contents($request->text));

                UploadPDF::dispatch($filename);

                $key = "userfiles/chat/" . $filename;

                $pdfUrl = config('filesystems.disks.s3.bucketurl') . "/" . $key;

                Message::insert([
                    "text" => $pdfUrl,
                    "type" => 'pdf',
                    "date" => date('Y-m-d h:i:s'),
                    "id_user" => $userId,
                    "with_id_user" => $request->with_id_user ?? null,
                    "id_message_room" => (int)($request->id_message_room ?? $query->id_message_room),
                    "read" => [],
                ]);

                return response()->json([
                    "code" => 200,
                    "status" => "success send new message"
                ], 200);   
            }else{
                $image = Image::make($request->text)->resize(400, null, function ($constraint) {
                    $constraint->aspectRatio();
                });
    
                $filename = date('dmYhis') . '_picture.' . $request->text->getClientOriginalExtension();
    
                $data = $image->encode($request->text->getClientOriginalExtension())->__toString();
    
                Storage::put('public/picture_queue/' . $filename, $data);
    
                UploadImageChat::dispatch($filename);
    
                $key = "userfiles/chat/" . $filename;
    
                $imageUrl = config('filesystems.disks.s3.bucketurl') . "/" . $key;

                Message::insert([
                    "text" => $imageUrl,
                    "type" => 'photo',
                    "date" => date('Y-m-d h:i:s'),
                    "id_user" => $userId,
                    "with_id_user" => $request->with_id_user ?? null,
                    "id_message_room" => (int)($request->id_message_room ?? $query->id_message_room),
                    "read" => [],
                ]);

                return response()->json([
                    "code" => 200,
                    "status" => "success send new message"
                ], 200);   
            }
        }

        Message::insert([
            "text" => $request->text,
            "type" => 'txt',
            "date" => date('Y-m-d h:i:s'),
            "id_user" => $userId,
            "with_id_user" => $request->with_id_user ?? null,
            "id_message_room" => (int)($request->id_message_room ?? $query->id_message_room),
            "read" => [],
        ]);

         return response()->json([
             "code" => 200,
             "status" => "success send new message"
         ], 200);
    }

    public function post_pin_unpin_chat(Request $request){
        $validator = Validator::make($request->all(), [
            'id_message_room' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }

        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);

        // Pin/Unpin message
        $check_pinned_message = MessagePin::where('id_message_room', $request->id_message_room)->where('id_user', $userId)->whereNull('deleted_at')->first();
        $check_message_room = MessageRoom::where('id_message_room', $request->id_message_room)->whereNull('deleted_at')->first();
        $check_max_pinned = MessagePin::where('id_user', $userId)->whereNull('deleted_at')->count();

        if ($check_pinned_message == null) {
            if($check_message_room == null) {
                return response()->json([
                    'code'  => 404,
                    'status'=> 'failed',
                    'result'=> 'Message room does not exist'
                ], 404);
            }
            if($check_max_pinned >= 4) {
                return response()->json([
                    'code'  => 429,
                    'status'=> 'failed',
                    'result'=> 'Maximum pinned message reached. Please unpin a message before pinning another one.'
                ], 429);
            }
            MessagePin::create([
                'id_user' => $userId,
                'id_message_room' => $request->id_message_room,
            ]);
            return response()->json([
                'code'  => 200,
                'status'=> 'success',
                'result'=> [
                    'id_user'   => $userId,
                    'id_message_room' => $request->id_message_room,
                    'message'   => 'Message room pinned successfully'
                ]
            ], 200);
        } else {
            $check_pinned_message->update([
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);
            return response()->json([
                'code'  => 200,
                'status'=> 'success',
                'result'=> [
                    'id_user'   => $userId,
                    'id_message_room' => $request->id_message_room,
                    'message'   => 'Message room unpinned successfully'
                ]
            ], 200);
        }
    }

    public function get_detail_message(Request $request){
        $id_message_room = $request->input('id_message_room');

        $detail_message = Message::where('id_message_room', (int)$id_message_room)->get();

        // foreach($detail_message as $dm){
        //     $name_user = User::select('full_name')->where('id_user', $dm->id_user)->first();

        //     $dm->name_user = $name_user->full_name;
        // }

        if($detail_message){    
            return response()->json([
                "code" => 200,
                "status" => "success",
                "result" => $detail_message
            ], 200);
        }

        return response()->json([
            "code" => 404,
            "status" => "no message"
        ], 404);
    }

    public function get_list_message(Request $request){
        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);

        $room_user = MessageUser::select('id_message_room')->where('id_user', $userId)->whereNull('deleted_at')->get();

        $data = MessageRoom::select('id_message_room', 'title', 'image', 'type')->whereIn('id_message_room', $room_user)->whereNull('deleted_at')->get();

        // $data_personal = MessageRoom::select('id_message_room', 'title', 'image', 'type')->where('type', 'personal')->whereIn('id_message_room', $room_user)->whereNull('deleted_at')->get();

        foreach($data as $dt){
            if($dt->type == "personal"){
                $data_personal = MessageUser::select('id_user')->where([['id_user', '!=', $userId], ['id_message_room', $dt->id_message_room]])->first();
                $personal_user = User::select('full_name')->where('id_user', $data_personal->id_user)->first();
                $dt->title = $personal_user->full_name;
                $dt->image = $personal_user->profile_picture;
            }
            $last_chat = Message::select('id_user', 'text', 'date')->where('id_message_room', $dt->id_message_room)->orderBy('date', 'desc')->first();
            if($last_chat){
                $dt->time_last_chat = $last_chat->date;
                $dt->last_chat_user = $last_chat->id_user;
                $dt->last_chat = $last_chat->text;
            }else{
                $dt->time_last_chat = null;
                $dt->last_chat_user = null;
                $dt->last_chat = null;
            }

            $pinned_message = MessagePin::where([['id_message_room', $dt->id_message_room], ['id_user', $userId], ['deleted_at', null]])->first();

            if($pinned_message){
                $dt->pinned = true;
            }else{
                $dt->pinned = false;
            }
        }

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $data
        ], 200);
    }

    public function get_list_files(Request $request){
        $filter = $request->input('filter');
        $id_message_room = $request->input('id_message_room');

        if($filter){
            $list_files = Message::where([['id_message_room', (int)$id_message_room], ['type', $filter]])->get();
        }else{
            $list_files = Message::where([['id_message_room', (int)$id_message_room], ['type', '!=', 'txt']])->get();
        }

        if($list_files->count() != 0){
            return response()->json([
                "code" => 200,
                "status" => "success",
                "result" => $list_files
            ], 200);
        }

        return response()->json([
            "code" => 404,
            "status" => "no files"
        ], 404);
    }

    public function delete_chat(Request $request){
        $validator = Validator::make($request->all(), [
            'id_message_room' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }

        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);

        // Delete chat
        $check_room_chat = MessageUser::where('id_message_room', $request->id_message_room)->where('id_user', $userId)->whereNull('deleted_at')->first();

        if ($check_room_chat == null) {
            return response()->json([
                'code'  => 404,
                'status'=> 'failed',
                'result'=> 'Message room not found'
            ], 404);
        } else {
            $check_room_chat->update([
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            return response()->json([
                'code'  => 200,
                'status'=> 'success',
                'result'=> [
                    'id_user'   => $userId,
                    'id_room_chat' => $request->id_message_room,
                    'message'   => 'Message room deleted successfully'
                ]
            ], 200);
        }
    }

    public function post_add_member(Request $request) {
        $validator = Validator::make($request->all(), [
            'id_user' => 'required',
            'id_message_room' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }

        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);
        
        // Check user is admin
        $check_admin = MessageUser::where('id_user', $userId)->where('id_message_room', $request->id_message_room)->whereNull('deleted_at')->first();
        if($check_admin != null){
            if($check_admin->role != 'admin'){
                return response()->json([
                    'code'  => 403,
                    'status'=> 'failed',
                    'result'=> 'User is not an admin in this room'
                ], 403);
            }
        } else if($check_admin == null) {
            return response()->json([
                'code'  => 403,
                'status'=> 'failed',
                'result'=> 'User is not joined in the room'
            ], 403);
        }

        // Add member
        $id_user = json_decode($request->id_user);

        foreach($id_user as $user){
            $check_user = MessageUser::where('id_user', $user)->where('id_message_room', $request->id_message_room)->whereNull('deleted_at')->first();
    
            $check_message_room = MessageRoom::where('id_message_room', $request->id_message_room)->whereNull('deleted_at')->first();
    
            if($check_user == null) {
                if($check_message_room == null) {
                    return response()->json([
                        'code'  => 404,
                        'status'=> 'failed',
                        'result'=> 'Message room does not exist'
                    ], 404);
                }
    
                MessageUser::create([
                    'id_user' => $user,
                    'id_message_room' => $request->id_message_room,
                    'role' => 'member',
                ]);
            }
        }

        return response()->json([
            'code'  => 200,
            'status'=> 'success',
            'result'=> [
                'id_user' => $id_user,
                'id_message_room' => $request->id_message_room,
                'message' => 'All user successfully registered as a member in the room'
            ]
        ], 200);
    }

    public function delete_member(Request $request) {
        $validator = Validator::make($request->all(), [
            'id_user' => 'required|numeric',
            'id_message_room' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }

        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);

        // Check if the user is admin or not
        $check_user = MessageUser::where('id_user', $userId)->where('id_message_room', $request->id_message_room)->whereNull('deleted_at')->first();

        if($check_user != null) {
            if($check_user->role != 'admin'){
                return response()->json([
                    'code'  => 403,
                    'status'=> 'failed',
                    'result'=> 'User is not an admin in this room'
                ], 403);
            }
        } else if($check_user == null) {
            return response()->json([
                'code'  => 403,
                'status'=> 'failed',
                'result'=> 'User is not joined in the room'
            ], 403);
        }

        $check_member = MessageUser::where('id_user', $request->id_user)->where('id_message_room', $request->id_message_room)->whereNull('deleted_at')->first();

        if($check_member == null) {
            return response()->json([
                'code'  => 409,
                'status'=> 'failed',
                'result'=> 'User is not a member in this room'
            ], 409);
        } else {
            $check_member->update([
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            return response()->json([
                'code'  => 200,
                'status'=> 'success',
                'result'=> [
                    'id_user'   => $request->id_user,
                    'id_room_chat' => $request->id_message_room,
                    'message'   => 'User successfully deleted from this message room'
                ]
            ], 200);
        }
    }

    public function post_leave_group(Request $request){
        $validator = Validator::make($request->all(), [
            'id_message_room' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }

        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);
        
        // Check user is admin
        $check_admin = MessageUser::where('id_user', $userId)->where('id_message_room', $request->id_message_room)->whereNull('deleted_at')->first();
        if($check_admin != null){
            if($check_admin->role == 'admin'){
                // Check is there any other admin role before leave
                $check_other_admin = MessageUser::where('role', 'admin')->where('id_message_room', $request->id_message_room)->where('id_user', '!=', $userId)->whereNull('deleted_at')->first();
                if($check_other_admin == null){
                    return response()->json([
                        'code'  => 403,
                        'status'=> 'failed',
                        'result'=> 'Please makes member in the group to admin.'
                    ], 403);
                }
            }
        } else if($check_admin == null) {
            return response()->json([
                'code'  => 403,
                'status'=> 'failed',
                'result'=> 'User is not joined in the room'
            ], 403);
        }

        // Leave group
        $group = MessageUser::where('id_message_room', $request->id_message_room)->where('id_user', $userId)->whereNull('deleted_at')->first();

        if ($group == null) {
            return response()->json([
                'code'  => 404,
                'status'=> 'failed',
                'result'=> 'Group message not found'
            ], 404);
        } else {
            $group->update([
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            return response()->json([
                'code'  => 200,
                'status'=> 'success',
                'result'=> [
                    'id_user'   => $userId,
                    'id_room_chat' => $request->id_message_room,
                    'message'   => 'Successfully leave group message'
                ]
            ], 200);
        }
    }

    public function delete_group(Request $request){
        $validator = Validator::make($request->all(), [
            'id_message_room' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }

        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);
        
        // Check user is admin
        $check_admin = MessageUser::where('id_user', $userId)->where('id_message_room', $request->id_message_room)->whereNull('deleted_at')->first();
        if($check_admin != null){
            if($check_admin->role != 'admin'){
                return response()->json([
                    'code'  => 403,
                    'status'=> 'failed',
                    'result'=> 'User is not admin. Only admin can delete this group.'
                ], 403);
            }
        } else if($check_admin == null) {
            return response()->json([
                'code'  => 403,
                'status'=> 'failed',
                'result'=> 'User is not joined in the room'
            ], 403);
        }

        // Delete group
        $group = MessageUser::where('id_message_room', $request->id_message_room)->whereNull('deleted_at')->first();
        $groupRoom = MessageRoom::where('id_message_room', $request->id_message_room)->where('type', 'group')->whereNull('deleted_at')->first();

        if ($group == null || $groupRoom == null) {
            return response()->json([
                'code'  => 404,
                'status'=> 'failed',
                'result'=> 'Group message not found'
            ], 404);
        } else {
            $group->update([
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);
            $groupRoom->update([
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

            return response()->json([
                'code'  => 200,
                'status'=> 'success',
                'result'=> [
                    'id_room_chat' => $request->id_message_room,
                    'message'   => 'Group message deleted successfully'
                ]
            ], 200);
        }
    }

    public function get_list_friend(Request $request){

        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);

        $follower = Follow::select('followed_by')->where('id_user', $userId)->get();

        $friend = Follow::select('id_user')->where('followed_by', $userId)->whereIn('id_user', $follower)->get();

        $getDataUser = User::with(['job' => function($query){ $query->select('id_job','job_title');}])->with(['company' => function($query){ $query->select('id_company','company_name');}])->select('profile_picture', 'full_name', 'id_job', 'id_company')->whereIn('id_user', $friend)->get();

        $getDataUser->makeHidden(['id_company','id_job']);

        if($friend->count() != 0){
            return response()->json([
                "code" => 200,
                "status" => "success",
                "result" => $getDataUser
            ], 200);
        }

        return response()->json([
            "code" => 404,
            "status" => "no friend"
        ], 404);
    }

    public function post_make_dismiss_admin(Request $request){
        $validator = Validator::make($request->all(), [
            'id_user' => 'required|numeric',
            'id_message_room' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }

        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);

        // Check user is admin
        $check_admin = MessageUser::where('id_user', $userId)->where('id_message_room', $request->id_message_room)->whereNull('deleted_at')->first();
        if($check_admin != null){
            if($check_admin->role != 'admin'){
                return response_json(403, 'failed', 'User is not admin. Only admin can make/dismiss user as admin.');
            } else if ($check_admin->role == 'admin'){
                // Check is there any other admin role before dismiss
                $check_other_admin = MessageUser::where('role', 'admin')->where('id_message_room', $request->id_message_room)->where('id_user', '!=', $userId)->whereNull('deleted_at')->first();
                if($request->id_user == $userId && $check_other_admin == null){
                    return response_json(403, 'failed', 'Please makes member in the group to admin.');
                }
            }
        } else if($check_admin == null) {
            return response_json(403, 'failed', 'User is not joined in the room');
        }

        // Make/dismiss user as admin
        $user = MessageUser::where('id_user', $request->id_user)->where('id_message_room', $request->id_message_room)->whereNull('deleted_at')->first();
        if($user != null) {
            if($user->role == 'admin'){
                $user->update([
                    'role' => 'member',
                ]);
    
                return response_json(200, 'success', 'User successfully updated as member');
            } else if($user->role == 'member'){
                $user->update([
                    'role' => 'admin',
                ]);
    
                return response_json(200, 'success', 'User successfully updated as admin');
            }
        } else {
            return response_json(403, 'failed', 'User is not joined in the room');
        }
    }
    
    public function post_update_group_info(Request $request){
        $validator = Validator::make($request->all(), [
            'id_message_room' => 'required',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }

        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);

        // Check user is admin
        $check_admin = MessageUser::where('id_user', $userId)->where('id_message_room', $request->id_message_room)->whereNull('deleted_at')->first();
        if($check_admin != null){
            if($check_admin->role != 'admin'){
                return response_json(403, 'failed', 'User is not admin. Only admin can update the group info.');
            }
        } else if($check_admin == null) {
            return response_json(403, 'failed', 'User is not joined in the room');
        }

        if ($request->image == 'null'){
            MessageRoom::where('id_message_room', $request->id_message_room)->update([
                'image' => null
            ]);

            return response_json(200, 'success', 'Group Info updated successfully');
        } else if($request->image){
            $old_room = MessageRoom::where('id_message_room', $request->id_message_room)->first();
            if($old_room->type != 'group'){
                return response_json(500, 'failed', 'The room is not group chat');
            }

            $image = Image::make($request->image)->resize(400, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            $filename = date('dmYhis') . '_group.' . $request->image->getClientOriginalExtension();
            $data = $image->encode($request->image->getClientOriginalExtension())->__toString();
            Storage::put('public/picture_queue/' . $filename, $data);
            UploadImageGroup::dispatch($filename);
            $key = "userfiles/chat/" . $filename;
            $imageUrl = config('filesystems.disks.s3.bucketurl') . "/" . $key;
            
            $old_room->update([
                'title' => $request->title ?? $old_room->title,
                'image' => $imageUrl,
                'description' => $request->description ?? $old_room->description,
                'additional_data' => $request->additional_data ?? $old_room->additional_data
            ]);
        } else {
            $old_room = MessageRoom::where('id_message_room', $request->id_message_room)->first();
            if($old_room->type != 'group'){
                return response_json(500, 'failed', 'The room is not group chat');
            }

            $old_room->update([
                'title' => $request->title ?? $old_room->title,
                'description' => $request->description ?? $old_room->description,
                'additional_data' => $request->additional_data ?? $old_room->additional_data
            ]);
        }

        return response_json(200, 'success', 'Group Info updated successfully');
    }

    public function read_message(Request $request){
        $request->validate([
            'id_message_room' => 'required',
        ]);

        $userId = get_id_user_jwt($request);

        $message_unread = Message::where("id_message_room", (int)$request->id_message_room)->whereNotIn('read', [$userId])->get();
        
        foreach($message_unread as $mu){
            if($mu->id_user != $userId){
                $data = $mu->read;
                array_push($data, $userId);
                
                Message::where("id_message_room", (int)$request->id_message_room)->whereNotIn('read', [$userId])->update([
                    'read' => $data
                ]);
            }
        }

        return response()->json([
            "code" =>  200,
            "status" => "success read message"
        ], 200);
    }

    public function total_unread_message(Request $request){
        $id_message_room = $request->input('id_message_room');
        
        $userId = get_id_user_jwt($request);

        $total_unread = Message::where('id_message_room', (int)$id_message_room)->whereNotIn('read', [$userId])->count();

        return response()->json([
            "code" =>  200,
            "status" => "success get total unread message",
            "result" => $total_unread
        ], 200);
    }
}
