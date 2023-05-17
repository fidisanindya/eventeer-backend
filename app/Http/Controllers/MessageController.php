<?php

namespace App\Http\Controllers;

use Image;
use App\Jobs\UploadImageGroup;
use App\Models\MessageGroup;
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

        $query = MessageGroup::create([
            'id_user' => $userId,
            'title' => $request->title,
            'image' => $imageUrl ?? null,
            'type' => 'group',
            'description' => $request->description,
            'additional_data' => $request->additional_data
        ]);

        if($query){
            return response()->json([
                "code" => 200,
                "status" => "success create group message",
            ], 200);
        }
    }

    public function get_detail_group($idGroup){
        $data = MessageGroup::where([['id_message_room', $idGroup], ['type', 'group']])->first();

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
}
