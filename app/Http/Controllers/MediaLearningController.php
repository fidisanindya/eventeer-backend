<?php

namespace App\Http\Controllers;

use stdClass;
use App\Models\Event;
use App\Models\LogEvent;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class MediaLearningController extends Controller
{
    public function get_list_media(Request $request)
    {
        // Get user id from jwt
        $user_id = get_id_user_jwt($request);

        $result = new stdClass;

        $id_community = $request->input('id_community');
        $category = $request->input('category');
          
        $limit = $request->input('limit', 6);
        $start = $request->input('start', 0); 
        $page = ceil(($start + 1) / $limit);

        if($category == 'video') {
            $query = Event::where('id_community', $id_community)
            ->where('category', 'video')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->with('user');

            $totalData = $query->count();

            if ($limit !== null) {
                $query->limit($limit);
                if ($start !== null) {
                    $query->offset($start);
                }
            }

            $videos = $query->get();
        
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

            $result->video = $transformedVideos;

        } else if($category == 'podcast') {
            $query = Event::where('id_community', $id_community)
            ->where('category', 'podcast')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->with('user');

            $totalData = $query->count();

            if ($limit !== null) {
                $query->limit($limit);
                if ($start !== null) {
                    $query->offset($start);
                }
            }

            $podcasts = $query->get();

            $transformedPodcasts = [];
            foreach ($podcasts as $podcast) {
                $total_podcast = 0;
                $total_duration = 0;

                if($podcast->additional_data != null) {
                    $additional_data = json_decode($podcast->additional_data);
                    if (isset($additional_data->episodeList)) {
                        $total_podcast += count($additional_data->episodeList);

                        foreach ($additional_data->episodeList as $episode) {
                            if (isset($episode->length)) {
                                $parts = explode(':', $episode->length);
                                $hours = 0;
                                $minutes = 0;
                                $seconds = 0;

                                if (count($parts) == 2) {
                                    // Format: menit:detik
                                    $minutes = (int)$parts[0];
                                    $seconds = (int)$parts[1];
                                } elseif (count($parts) == 3) {
                                    // Format: jam:menit:detik
                                    $hours = (int)$parts[0];
                                    $minutes = (int)$parts[1];
                                    $seconds = (int)$parts[2];
                                }

                                $total_duration += ($hours * 3600) + ($minutes * 60) + $seconds;
                            }
                        }
                    }
                }

                $viewers = LogEvent::where('id_event', $podcast->id_event)->count();

                $hours = floor($total_duration / 3600);
                $minutes = floor(($total_duration % 3600) / 60);
                // $seconds = $total_duration % 60;

                $total_duration_formatted = '';
                if ($hours > 0) {
                    $total_duration_formatted .= $hours . 'h ';
                }
                if ($minutes > 0) {
                    $total_duration_formatted .= $minutes . 'm';
                }
                // $total_duration_formatted .= $seconds . 's';

                $transformedPodcasts[] = [
                    'id_event' => $podcast->id_event,
                    'title' => $podcast->title,
                    'image' => $podcast->image,
                    'total_podcast' => $total_podcast,
                    'total_duration' => $total_duration_formatted,
                    'full_name' => $podcast->user->full_name,
                    'profile_picture' => $podcast->user->profile_picture,
                    'created_at' => date('F d', strtotime($podcast->created_at)),
                ];
            }

            $result->podcast = $transformedPodcasts;

        } else if($category == 'article') {

        } else {

        }

        $result->meta = [
            "start" => $start,
            "limit" => $limit,
            "total_data" => $totalData,
            "current_page" => $page,
            "total_page" => ceil($totalData / $limit)
        ];

        return response_json(200, 'success', $result);
    }
}

?>

