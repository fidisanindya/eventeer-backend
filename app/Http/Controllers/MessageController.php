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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    public function create_group_message(Request $request){
        $request->validate([
            'title' => 'required',
        ]);

        $authorizationHeader = $request->header('Authorization');

        $jwtParts = explode(' ', $authorizationHeader);
        $jwtToken = $jwtParts[1];

        $publicKey = env("JWT_PUBLIC_KEY"); 
        $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
        
        $userId = $decoded->data->id_user;

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

        return response()->json([
            "code" => 200,
            "status" => "success",
            "result" => $data
        ], 200);
    }

    public function send_message(Request $request){
        $request->validate([
            "text" => "required",
            "id_message_room" => "required"
        ]);

         // Get id_user from Bearer Token
         $authorizationHeader = $request->header('Authorization');

         $jwtParts = explode(' ', $authorizationHeader);
         $jwtToken = $jwtParts[1];
 
         $publicKey = env("JWT_PUBLIC_KEY"); 
         $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
         
         $userId = $decoded->data->id_user;
 
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
                     "id_message_room" => $request->id_message_room
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
                     "id_message_room" => $request->id_message_room
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
             "id_message_room" => $request->id_message_room
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
            return response()->json([
                'code'      => 422,
                'status'    => 'failed',
                'result'    => $validator->messages(),
            ], 422);
        }

        // Get id_user from Bearer Token
        $authorizationHeader = $request->header('Authorization');
        $jwtParts = explode(' ', $authorizationHeader);
        $jwtToken = $jwtParts[1];
        $publicKey = env("JWT_PUBLIC_KEY"); 
        $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
        $userId = $decoded->data->id_user;

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
        $id_message = $request->input('id_message');
        
        $message = Message::where('_id',  $id_message)->first();

        if($message){    
            return response()->json([
                "code" => 200,
                "status" => "success",
                "result" => $message
            ], 200);
        }

        return response()->json([
            "code" => 404,
            "status" => "message not found"
        ], 404);
    }

    public function get_list_message(Request $request){
        $id_message_room = $request->input('id_message_room');

        $list_message = Message::where('id_message_room', $id_message_room)->get();

        if($list_message){    
            return response()->json([
                "code" => 200,
                "status" => "success",
                "result" => $list_message
            ], 200);
        }

        return response()->json([
            "code" => 404,
            "status" => "no message"
        ], 404);
    }

    public function get_list_files(Request $request){
        $filter = $request->input('filter');
        $id_message_room = $request->input('id_message_room');

        if($filter){
            $list_files = Message::where([['id_message_room', $id_message_room], ['type', $filter]])->get();
        }else{
            $list_files = Message::where([['id_message_room', $id_message_room], ['type', '!=', 'txt']])->get();
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
            return response()->json([
                'code'      => 422,
                'status'    => 'failed',
                'result'    => $validator->messages(),
            ], 422);
        }

        // Get id_user from Bearer Token
        $authorizationHeader = $request->header('Authorization');

        $jwtParts = explode(' ', $authorizationHeader);
        $jwtToken = $jwtParts[1];

        $publicKey = env("JWT_PUBLIC_KEY"); 
        $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
        
        $userId = $decoded->data->id_user;

        // Delete chat
        $check_room_chat = MessageRoom::where('id_message_room', $request->id_message_room)->where('id_user', $userId)->whereNull('deleted_at')->first();

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
            return response()->json([
                'code'      => 422,
                'status'    => 'failed',
                'result'    => $validator->messages(),
            ], 422);
        }

        // Get id_user from Bearer Token
        $authorizationHeader = $request->header('Authorization');

        $jwtParts = explode(' ', $authorizationHeader);
        $jwtToken = $jwtParts[1];

        $publicKey = env("JWT_PUBLIC_KEY"); 
        $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
        
        $userId = $decoded->data->id_user;
        
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
            return response()->json([
                'code'      => 422,
                'status'    => 'failed',
                'result'    => $validator->messages(),
            ], 422);
        }

        // Get id_user from Bearer Token
        $authorizationHeader = $request->header('Authorization');

        $jwtParts = explode(' ', $authorizationHeader);
        $jwtToken = $jwtParts[1];

        $publicKey = env("JWT_PUBLIC_KEY"); 
        $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
        
        $userId = $decoded->data->id_user;

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
            return response()->json([
                'code'      => 422,
                'status'    => 'failed',
                'result'    => $validator->messages(),
            ], 422);
        }

        // Get id_user from Bearer Token
        $authorizationHeader = $request->header('Authorization');

        $jwtParts = explode(' ', $authorizationHeader);
        $jwtToken = $jwtParts[1];

        $publicKey = env("JWT_PUBLIC_KEY"); 
        $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
        
        $userId = $decoded->data->id_user;
        
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
            return response()->json([
                'code'      => 422,
                'status'    => 'failed',
                'result'    => $validator->messages(),
            ], 422);
        }

        // Get id_user from Bearer Token
        $authorizationHeader = $request->header('Authorization');

        $jwtParts = explode(' ', $authorizationHeader);
        $jwtToken = $jwtParts[1];

        $publicKey = env("JWT_PUBLIC_KEY"); 
        $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
        
        $userId = $decoded->data->id_user;
        
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
}
