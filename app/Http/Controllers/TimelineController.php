<?php

namespace App\Http\Controllers;

use stdClass;
use Image;
use App\Models\Timeline;
use App\Models\LogEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Jobs\UploadImageFeeds;
use App\Jobs\UploadVideoFeeds;

class TimelineController extends Controller
{
    public function post_feed(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_community' => 'required|numeric',
            'description' => 'required|string',
            'single_link' => 'string',
            'video' => 'file|mimes:mp4,avi',
            'picture.*' => 'image|mimes:jpeg,jpg,png,gif',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }

        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);

        $additionalData = [];

        if ($request->has('single_link')) {
            $additionalData['single_link'] = $request->single_link;
        }

        if ($request->has('video')) {
            $additionalData['video'] = $this->processVideo($request->file('video'));
        }
        if ($request->has('picture')) {
            $imageUrls = [];
            $uploadedPictures = $request->file('picture');
            
            if (count($uploadedPictures) > 10) {
                return response_json(422, 'failed', 'Too many pictures. Maximum 10 pictures allowed.');
            }
    
            foreach ($uploadedPictures as $file) {
                $imageUrls[] = $this->processImage($file);
            }
    
            $additionalData['picture'] = $imageUrls;
        }

        $additionalDataJson = json_encode($additionalData, JSON_UNESCAPED_SLASHES);

        $timeline = Timeline::create([
            'id_user' => $userId,
            'id_community' => $request->id_community,
            'description' => $request->description,
            'additional_data' => $additionalDataJson,
            'created_at' => now()
        ]);        

        return response()->json([
            'code' => 200,
            'status' => 'post feed success',
            'timeline' => $timeline
        ], 200);
    }

    private function processImage($imageData)
    {
        if (!$imageData) {
            return null;
        }

        $image = Image::make($imageData)->resize(400, null, function ($constraint) {
            $constraint->aspectRatio();
        });

        $filename = date('dmYhis') . '_feeds.' . $imageData->getClientOriginalExtension();
        $data = $image->encode($imageData->getClientOriginalExtension())->__toString();
        Storage::put('public/picture_queue/' . $filename, $data);
        UploadImageFeeds::dispatch($filename);
        $key = "userfiles/images/journey/" . $filename;
        $imageUrl = config('filesystems.disks.s3.bucketurl') . "/" . $key;

        return $imageUrl;
    }

    private function processVideo($videoData)
    {
        if (!$videoData) {
            return null;
        }
    
        $filename = date('dmYhis') . '_feeds.' . $videoData->getClientOriginalExtension();
        $data = $videoData->get();
        Storage::put('public/video_queue/' . $filename, $data);
        UploadVideoFeeds::dispatch($filename);
        $key = "userfiles/video/" . $filename;
        $videoUrl = config('filesystems.disks.s3.bucketurl') . "/" . $key;
    
        return $videoUrl;
    }

}

?>