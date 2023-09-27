<?php

namespace App\Http\Controllers;

use stdClass;
use FFMpeg\FFMpeg;
use App\Models\Event;
use App\Models\LogEvent;
use Illuminate\Http\Request;

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

                $video_duration = $video->additional_data->video_duration;
                list($hours, $minutes, $seconds) = explode(':', $video_duration);

                $video_duration_formatted = "";
                if ($hours > 0) {
                    $video_duration_formatted .= $hours . 'h ';
                }
                if ($minutes > 0) {
                    $video_duration_formatted .= $minutes . 'm';
                }

                $viewers = LogEvent::where('id_event', $video->id_event)->count();

                $transformedVideos[] = [
                    'id_event' => $video->id_event,
                    'title' => $video->title,
                    'image' => $video->image,
                    'video_duration' => $video_duration_formatted,
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
            $query = Event::where('id_community', $id_community)
            ->where('category', 'article')
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

            $articles = $query->get();
        
            $article_duration = 0; 
            $transformedArticles = [];
            foreach ($articles as $article) {
                if($article->additional_data != null) {
                    $article->additional_data = json_decode($article->additional_data);                  
                }

                $article_duration = $this->estimateReadingTime($article->description)  . ' m read';
                
                $viewers = LogEvent::where('id_event', $article->id_event)->count();

                $transformedArticles[] = [
                    'id_event' => $article->id_event,
                    'title' => $article->title,
                    'image' => $article->image,
                    'article_duration' => $article_duration,
                    'full_name' => $article->user->full_name,
                    'profile_picture' => $article->user->profile_picture,
                    'viewers' => $viewers,
                    'created_at' => date('F d', strtotime($article->created_at)),
                ];
            }

            $result->article = $transformedArticles;

        } else {
            return response()->json(["code" => 404, "status" => "Category doesn't match"], 404);
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
            $data_event->duration = $this->estimateReadingTime($data_event->description) . ' min read';

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
        return ceil($wordCount / 225);
    }

}

?>

