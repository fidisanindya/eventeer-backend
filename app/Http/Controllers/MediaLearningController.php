<?php

namespace App\Http\Controllers;

use stdClass;
use FFMpeg\FFMpeg;
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

    public function getDetailMedia(Request $request, $id_event)
    {
        $user_id = get_id_user_jwt($request);
        $data_event = $this->getData($id_event);

        if (!$data_event) {
            return response()->json(["code" => 404, "status" => "Media not found"], 404);
        }

        $relatedEvents = Event::select('id_event')
            ->where(function ($query) use ($data_event) {
                foreach ($data_event->additional_data->category as $category) {
                    $query->orWhereJsonContains('module_event.additional_data->category', $category);
                }
            })
            ->where('id_community', $data_event->id_community)
            ->where('id_event', '!=', $id_event)
            ->where('category', $data_event->category)
            ->orderByDesc(function ($query) {
                $query->selectRaw('count(*)')
                    ->from('log_event')
                    ->whereColumn('log_event.id_event', 'module_event.id_event');
            })
            ->get();

        $data_event->related = $relatedEvents->map(function ($result) {
            return $this->getData($result->id_event);
        });

        LogEvent::firstOrCreate(['id_event' => $data_event->id_event, 'id_user' => $user_id]);

        return response()->json([
            "code" => 200,
            "status" => "success get media learning",
            "result" => $data_event
        ], 200);
    }

    private function getData($id_event)
    {
        $data_event = Event::select('id_event', 'id_community', 'title','category', 'description', 'image', 'additional_data', 'created_at', 'id_user')
            ->where('id_event', $id_event)
            ->first();

        if (!$data_event) {
            return null;
        }

        $data_event->additional_data = json_decode($data_event->additional_data);
        if($data_event->category == 'article'){
            $data_event->duration = $this->estimateReadingTime($data_event->description);

        }
        $viewers = LogEvent::where('id_event', $id_event)->count();
        $data_event->viewers = $viewers;
        $data_event->author_name = $data_event->user->full_name;
        $data_event->author_profile_picture = $data_event->user->profile_picture ?? null;
        $data_event->author_job = $data_event->user->job->job_title ?? null;
        $data_event->makeHidden('user', 'id_user', 'category', 'id_community');

        return $data_event;
    }

    private function estimateReadingTime($articleContent)
    {
        $wordCount = str_word_count($articleContent);
        return ceil($wordCount / 225) . ' min read';
    }

}

?>

