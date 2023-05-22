<?php

namespace App\Http\Controllers;

use App\Jobs\UploadImageChat;
use Image;
use App\Jobs\UploadImageGroup;
use App\Jobs\UploadPDF;
use App\Models\Message;
use App\Models\MessageRoom;
use App\Models\MessageUser;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

    public function get_detail_group($idGroup){
        $data = MessageRoom::where([['id_message_room', $idGroup], ['type', 'group']])->first();

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
            "id_user" => $userId,
            "id_message_room" => $request->id_message_room
        ]);

        return response()->json([
            "code" => 200,
            "status" => "success send new message"
        ], 200);
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
}
