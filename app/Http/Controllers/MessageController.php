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
use App\Models\Job;
use App\Models\JobUser;
use App\Models\Profession;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class MessageController extends Controller
{
    public function create_group_message(Request $request){
        $request->validate([
            'title' => 'required',
        ]);

        $maxSize = Validator::make($request->all(), [
            'image' => 'image|max:10000',
        ]);

        if ($maxSize->fails()) {
            return response_json(422, 'failed', $maxSize->messages());
        }

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
            
            // Add member
            $id_user = json_decode($request->id_user_join);

            foreach($id_user as $user){
                $check_user = MessageUser::where('id_user', $user)->where('id_message_room', $query->id_message_room)->whereNull('deleted_at')->first();
        
                $check_message_room = MessageRoom::where('id_message_room', $query->id_message_room)->whereNull('deleted_at')->first();
        
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
                        'id_message_room' => $query->id_message_room,
                        'role' => 'member',
                    ]);
                }
            }
        }else{
            return response()->json([
                "code" => 409,
                "status" => "failed create group message",
            ], 409);
        }

        $cacheKey = "list_message_{$userId}";
        Cache::forget($cacheKey);

        $result = [
            'message'       => 'Successfully create group message',
            'id_user_joined'=> $request->id_user_joined,
        ];

        return response_json(200, 'success', $result);
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
        }])->select('id_user', 'role')->where('id_message_room', $id_group)->whereNull('deleted_at')->get();

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
            "text" => "",
            "file" => "",
            "id_message_room" => "",
            "with_id_user" => ""
        ]);

        date_default_timezone_set('Asia/Jakarta');

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

        // Menginisialisasi nilai default untuk text dan file
        $text = $request->text ?? null;
        $file = null;
        
        if($request->hasFile('file')) {
            if($request->file->getClientOriginalExtension() == 'pdf'){
                $maxSize = Validator::make($request->all(), [
                    'file' => 'required|file|mimes:pdf|max:30000',
                ]);
        
                if ($maxSize->fails()) {
                    return response_json(422, 'failed', $maxSize->messages());
                }

                // size pdf in kb

                $sizePDF = round($request->file->getSize() / 1024, 2);

                $filename = date('dmYhis') . '_file.' . $request->file->getClientOriginalExtension();

                Storage::put('public/pdf_queue/' . $filename, file_get_contents($request->file));

                UploadPDF::dispatch($filename);

                $key = "userfiles/chat/" . $filename;

                $file = config('filesystems.disks.s3.bucketurl') . "/" . $key;

                $insertedId = Message::insertGetId([
                    "text" => $text,
                    "file" => $file,
                    "type" => 'pdf',
                    "name_file" => $filename,
                    "size_file_kb" => $sizePDF,
                    "date" => date('Y-m-d h:i:s'),
                    "id_user" => $userId,
                    "with_id_user" => $request->with_id_user ?? null,
                    "id_message_room" => (int)($request->id_message_room ?? $query->id_message_room),
                    "read" => [$userId],
                ]);

                $insertedRecord = Message::find($insertedId);

                $cacheKey = "list_message_{$userId}";
                Cache::forget($cacheKey);

                return response()->json([
                    "code" => 200,
                    "status" => "success send new message",
                    "result" => [
                       "text" => $insertedRecord->text,
                       "file" => $insertedRecord->file,
                       "type" => $insertedRecord->type,
                       "name_file" => $insertedRecord->name_file,
                       "size_file_kb" => $insertedRecord->size_file_kb,
                       "date" => $insertedRecord->date,
                       "id_user" => $insertedRecord->id_user,
                       "with_id_user" => $insertedRecord->with_id_user,
                       "id_message_room" => $insertedRecord->id_message_room,
                    ]
                ], 200);
            }else{
                $maxSize = Validator::make($request->all(), [
                    'file' => 'required|image|max:10000',
                ]);
        
                if ($maxSize->fails()) {
                    return response_json(422, 'failed', $maxSize->messages());
                }

                $image = Image::make($request->file)->resize(400, null, function ($constraint) {
                    $constraint->aspectRatio();
                });
    
                $filename = date('dmYhis') . '_picture.' . $request->file->getClientOriginalExtension();
    
                $data = $image->encode($request->file->getClientOriginalExtension())->__toString();
    
                Storage::put('public/picture_queue/' . $filename, $data);

                // size image in kb

                $sizeImg = round(Storage::size('public/picture_queue/' . $filename) / 1024 , 2);
    
                UploadImageChat::dispatch($filename);
    
                $key = "userfiles/chat/" . $filename;
    
                $file = config('filesystems.disks.s3.bucketurl') . "/" . $key;

                $insertedId = Message::insertGetId([
                    "text" => $text,
                    "file" => $file,
                    "type" => 'photo',
                    "name_file" => $filename,
                    "size_file_kb" => $sizeImg,
                    "date" => date('Y-m-d h:i:s'),
                    "id_user" => $userId,
                    "with_id_user" => $request->with_id_user ?? null,
                    "id_message_room" => (int)($request->id_message_room ?? $query->id_message_room),
                    "read" => [$userId],
                ]);

                $insertedRecord = Message::find($insertedId);

                $cacheKey = "list_message_{$userId}";
                Cache::forget($cacheKey);

                return response()->json([
                    "code" => 200,
                    "status" => "success send new message",
                    "result" => [
                        "text" => $insertedRecord->text,
                        "file" => $insertedRecord->file,
                        "type" => $insertedRecord->type,
                        "name_file" => $insertedRecord->name_file,
                        "size_file_kb" => $insertedRecord->size_file_kb,
                        "date" => $insertedRecord->date,
                        "id_user" => $insertedRecord->id_user,
                        "with_id_user" => $insertedRecord->with_id_user,
                        "id_message_room" => $insertedRecord->id_message_room,
                    ]
                ], 200);
            }
        }

        $insertedId = Message::insertGetId([
            "text" => $request->text,
            "file" => $file,
            "type" => 'txt',
            "name_file" => null,
            "size_file_kb" => null,
            "date" => date('Y-m-d h:i:s'),
            "id_user" => $userId,
            "with_id_user" => $request->with_id_user ?? null,
            "id_message_room" => (int)($request->id_message_room ?? $query->id_message_room),
            "read" => [$userId],
        ]);

        $insertedRecord = Message::find($insertedId);

        $cacheKey = "list_message_{$userId}";
        Cache::forget($cacheKey);

         return response()->json([
             "code" => 200,
             "status" => "success send new message",
             "result" => [
                "text" => $insertedRecord->text,
                "file" => $insertedRecord->file,
                "type" => $insertedRecord->type,
                "name_file" => $insertedRecord->name_file,
                "size_file_kb" => $insertedRecord->size_file_kb,
                "date" => $insertedRecord->date,
                "id_user" => $insertedRecord->id_user,
                "with_id_user" => $insertedRecord->with_id_user,
                "id_message_room" => $insertedRecord->id_message_room,
             ]
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
            $cacheKey = "list_message_{$userId}";
            Cache::forget($cacheKey);
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
            $check_pinned_message->delete();
            $cacheKey = "list_message_{$userId}";
            Cache::forget($cacheKey);
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

        $detail_message = Message::where('id_message_room', (int)$id_message_room)->get();

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

        $message = MessageUser::leftJoin('module_message_room', 'module_message_user.id_message_room', '=', 'module_message_room.id_message_room')
        ->leftJoin('module_message_pin', function ($join) use ($userId) {
            $join->on('module_message_room.id_message_room', '=', 'module_message_pin.id_message_room')
                ->where('module_message_pin.id_user', $userId);
        })
        ->select('module_message_room.id_message_room', 'module_message_room.image', 'module_message_room.type')
        ->selectRaw('
            IF(module_message_room.type = "personal",
                (SELECT system_users.full_name FROM system_users WHERE system_users.id_user = (SELECT id_user FROM module_message_user WHERE id_user != ' . $userId . ' AND id_message_room = module_message_room.id_message_room LIMIT 1)),
                module_message_room.title
            ) AS title,
            IF(module_message_room.type = "personal",
                (SELECT system_users.id_user FROM system_users WHERE system_users.id_user = (SELECT id_user FROM module_message_user WHERE id_user != ' . $userId . ' AND id_message_room = module_message_room.id_message_room LIMIT 1)),
                null
            ) AS id_user,
            CASE
                WHEN module_message_pin.id_user IS NOT NULL AND module_message_pin.deleted_at IS NULL THEN true
                ELSE false
            END AS pinned
        ')
        ->where('module_message_user.id_user', $userId)
        ->whereNull('module_message_user.deleted_at')
        ->whereNull('module_message_room.deleted_at')
        ->orderBy('pinned', 'desc')
        ->get();

        foreach($message as $msg){
            if($msg->type == "personal"){
                $data_personal = MessageUser::select('id_user')->where([['id_user', '!=', $userId], ['id_message_room', $msg->id_message_room]])->first();
                $personal_user = User::select('id_user', 'full_name')->where('id_user', $data_personal->id_user)->first();
                $msg->id_user = $personal_user->id_user;
            }
            
            $last_chat = Message::select('id_user', 'text', 'date', 'type')->where('id_message_room', $msg->id_message_room)->orderBy('date', 'desc')->first();
            $jobUser = JobUser::with('job')->where('id_user', $userId)->first();
            $msg->job_title = $jobUser->job->job_title;
            
            if($last_chat){
                $user = User::select('id_user', 'full_name')->where('id_user', $last_chat->id_user)->first();

                $msg->last_chat = [
                    "id_user" => $user->id_user,
                    "full_name" => $user->full_name,
                    "text" => $last_chat->text,
                    "message_type" => $last_chat->type,
                    "time" => $last_chat->date,
                ];
            }else{
                $msg->last_chat = [
                    "id_user" => null,
                    "full_name" => null,
                    "text" => null,
                    "message_type" => null,
                    "time" => null,
                ];
            }

            $total_unread = Message::where('id_message_room', (int)$msg->id_message_room)->whereNotIn('read', [$userId])->count();
            $msg->total_unread = $total_unread;
        }

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $message
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

    public function get_list_message_v2(Request $request){
        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);

        $cacheKey = "list_message_{$userId}";

        $data = Cache::remember($cacheKey, 300, function () use ($userId) {
            $room_user = MessageUser::select('id_message_room')->where('id_user', $userId)->whereNull('deleted_at')->get();

            $data = MessageRoom::select('id_message_room', 'title', 'image', 'type')->whereIn('id_message_room', $room_user)->whereNull('deleted_at')->get();

            // $data_personal = MessageRoom::select('id_message_room', 'title', 'image', 'type')->where('type', 'personal')->whereIn('id_message_room', $room_user)->whereNull('deleted_at')->get();

            foreach($data as $dt){
                if($dt->type == "personal"){
                    $data_personal = MessageUser::select('id_user')->where([['id_user', '!=', $userId], ['id_message_room', $dt->id_message_room]])->first();
                    $personal_user = User::select('id_user', 'full_name', 'profile_picture')->where('id_user', $data_personal->id_user)->first();
                    $dt->id_user = $personal_user->id_user;
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
                
                $total_unread = Message::where('id_message_room', (int)$dt->id_message_room)->whereNotIn('read', [$userId])->count();
                $dt->total_unread = $total_unread;
            }
            return $data;
        });

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $data
        ], 200);       
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

            $cacheKey = "list_message_{$userId}";
            Cache::forget($cacheKey);

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

            $cacheKey = "list_message_{$userId}";
            Cache::forget($cacheKey);

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

            $cacheKey = "list_message_{$userId}";
            Cache::forget($cacheKey);

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

        $getDataUser = User::with(['job' => function($query){ $query->select('id_job','job_title');}])->with(['company' => function($query){ $query->select('id_company','company_name');}])->select('id_user', 'profile_picture', 'full_name', 'id_job', 'id_company')->whereIn('id_user', $friend)->get();

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
            'image' => 'image|max:10000',
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

        $cacheKey = "list_message_{$userId}";
        Cache::forget($cacheKey);

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

    public function getDetailPersonalChat(Request $request, $id_user){
        $user_id = get_id_user_jwt($request); //user login
        $data_user = User::select('id_user', 'id_job', 'profile_picture', 'full_name', 'bio')->where('id_user', $id_user)->first();
        $data_user->job = optional($data_user->job)->job_title;

        $data_user->groups_in_commons = $this->getCommonGroups($id_user, $user_id);

        $message_room = MessageRoom::where('type', 'personal')->pluck('id_message_room');
        $id_message_room = MessageUser::whereIn('id_user', [$id_user, $user_id])
            ->whereIn('id_message_room', $message_room) 
            ->groupBy('id_message_room')
            ->havingRaw('COUNT(DISTINCT id_user) = 2')
            ->pluck('id_message_room')
            ->first();

        $data_user->total_file = Message::where([['id_message_room', $id_message_room], ['type', '!=', 'txt']])->count();
        $data_user->images_files = Message::where([['id_message_room', $id_message_room], ['type',  '!=', 'txt']])->orderBy('date', 'desc')->limit(2)->get();

        return response()->json([
            "code" => 200,
            "status" => "success",
            "result" => $data_user
        ], 200);
    }

    private function getCommonGroups($id_user, $user_id)
    {
        $groupIds = MessageUser::select('id_message_room')
            ->whereIn('id_message_room', function ($query) use ($id_user, $user_id) {
                $query->select('id_message_room')
                    ->from('module_message_user')
                    ->whereIn('id_user', [$id_user, $user_id])
                    ->groupBy('id_message_room')
                    ->havingRaw('COUNT(DISTINCT id_user) = 2');
            })
            ->distinct()
            ->get();
        
        $result = MessageRoom::select('id_message_room', 'title')->whereIn('id_message_room', $groupIds)->where('type', 'group')->get();
        $finalResult = $result->map(function ($item) use ($user_id){
            $membersData = MessageUser::select('system_users.full_name')
                ->join('system_users', 'module_message_user.id_user', '=', 'system_users.id_user')
                ->where('module_message_user.id_message_room', $item->id_message_room)
                ->where('module_message_user.id_user', '!=', $user_id)
                ->pluck('full_name')
                ->toArray();

            return [
                "id_message_room" => $item->id_message_room,
                "title" => $item->title,
                "members" => $membersData,
            ];
        });

        return $finalResult;
    }
}
