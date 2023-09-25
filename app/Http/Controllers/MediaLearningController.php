<?php

namespace App\Http\Controllers;

use stdClass;
use App\Models\Event;
use App\Models\LogEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class MediaLearningController extends Controller
{
    public function get_list_media(Request $request)
    {
        // Get user id from jwt
        $user_id = get_id_user_jwt($request);

        $result = new stdClass;

        $id_community = $request->input('id_community');
        $category = $request->input('category');
          
        $limit = $request->input('limit');
        $start = $request->input('start', 0); 
        $page = ceil(($start + 1) / $limit);

        if($category == 'video') {
            $videos = Event::where('id_community', $id_community)
            ->where('category', 'video')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->with('user') 
            ->get();
        
        $totalData = $videos->count();
       
        $transformedVideos = [];
        foreach ($videos as $video) {
            if($video->additional_data != null) {
                $video->additional_data = json_decode($video->additional_data);
            }
            $viewers = LogEvent::where('id_event', $video->id_event)->count();

            $transformedVideos[] = [
                'id_event' => $video->id_event,
                'title' => $video->title,
                'image' => $video->image,
                'video_duration' => $video->additional_data->video_duration,
                'full_name' => $video->user->full_name,
                'profile_picture' => $video->user->profile_picture,
                'viewers' => $viewers,
                'created_at' => date('F d', strtotime($video->created_at)),
            ];
        }

        $result->media = $transformedVideos;

        } else if($category == 'podcast') {

        } else {

        }

        $result->meta = [
            "start" => $start,
            "limit" => $limit,
            "total_data" => $totalData,
            "current_page" => $page,
            // "total_pages" =>  $paginator->lastPage()
        ];

        return response_json(200, 'success', $result);
    }
}

?>

