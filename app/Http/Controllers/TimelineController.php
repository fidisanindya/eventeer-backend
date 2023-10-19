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
use App\Models\Comment;
use App\Models\Follow;
use App\Models\Interest;
use App\Models\React;
use App\Models\UserProfile;
use Carbon\Carbon;

class TimelineController extends Controller
{
    public function post_feed(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_community' => 'required|numeric',
            'description' => 'string',
            'single_link' => 'string',
            'video' => 'file|mimes:mp4,avi',
            'picture.*' => 'image|mimes:jpeg,jpg,png,gif',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }

        // Get id_user from Bearer Token
        $userId = get_id_user_jwt($request);

        $additionalData = null;

        if ($request->has('single_link') || $request->has('video') || $request->has('picture')) {
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

            $additionalData = json_encode($additionalData, JSON_UNESCAPED_SLASHES);
        }

        $timeline = Timeline::create([
            'id_user' => $userId,
            'id_community' => $request->id_community,
            'description' => $request->description ?: null,
            'additional_data' => $additionalData,
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

        $image = Image::make($imageData)->resize(1000, null, function ($constraint) {
            $constraint->aspectRatio();
        });

        $filename = date('dmYhis') . uniqid() .'_feeds.' . $imageData->getClientOriginalExtension();
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

    public function get_list_feed(Request $request)
    {
        // Get user id from jwt
        $user_id = get_id_user_jwt($request);

        $result = new stdClass;

        $id_community = $request->input('id_community');
        
        $limit = $request->input('limit', 10);
        $start = $request->input('start', 0); 
        $page = ceil(($start + 1) / $limit);

        $query = Timeline::where('id_community', $id_community)
        ->whereNull('deleted_at')
        ->with(['user', 'user.job'])
        ->orderBy('created_at', 'desc');

        $totalData = $query->count();

        if ($limit !== null) {
            $query->limit($limit);
            if ($start !== null) {
                $query->offset($start);
            }
        }
        
        $timelines = $query->get();

        $transformedTimeline = [];
        foreach($timelines as $timeline) {
            if($timeline->additional_data != null) {
                $timeline->additional_data = json_decode($timeline->additional_data);                  
            }
           
            $full_name = isset($timeline->user) ? $timeline->user->full_name : null;
            $profile_picture = isset($timeline->user) ? $timeline->user->profile_picture : null;
            $job_title = isset($timeline->user) ? ($timeline->user)->job->job_title : null;
            $company = isset($timeline->user) ? ($timeline->user)->company->company_name : null;
            $count_like = React::where('related_to', 'id_timeline')->where('id_related_to', $timeline->id_timeline)->count();
            $count_comment = Comment::where('related_to', 'id_timeline')->where('id_related_to', $timeline->id_timeline)->count();
            $count_reply = Comment::where('related_to', 'id_comment')->where('id_related_to', $timeline->id_timeline)->count();
            $count_comment += $count_reply;
            
            $transformedTimeline[] = [
                'id_timeline' => $timeline->id_timeline,
                'id_user' => $timeline->id_user,
                'full_name' => $full_name,
                'profile_picture' => $profile_picture,
                'job_title' => $job_title,
                'company' => $company,
                'description' => $timeline->description,
                'additional_data' => $timeline->additional_data,
                'created_at' => $timeline->created_at,
                'like' => $count_like,
                'comment' => $count_comment
            ];
        }

        $result->timeline = $transformedTimeline;

        $result->meta = [
            "start" => $start,
            "limit" => $limit,
            "total_data" => $totalData,
            "current_page" => $page,
            "total_page" => ceil($totalData / $limit)
        ];

        return response()->json([
            "code" => 200,
            "status" => "success get feeds",
            "result" => $result
        ], 200);

    }

    public function get_detail_feed(Request $request)
    {
        $id_timeline = $request->input('id_timeline');

        $result = new stdClass;

        $data = Timeline::where('id_timeline', $id_timeline)
        ->whereNull('deleted_at')
        ->with(['user', 'user.job', 'user.company', 'user.city'])
        ->first();

        if($data->additional_data != null) {
            $data->additional_data = json_decode($data->additional_data);                  
        }

        $full_name = isset($data->user) ? $data->user->full_name : null;
        $profile_picture = isset($data->user) ? $data->user->profile_picture : null;
        $job_title = isset($data->user) ? ($data->user)->job->job_title : null;
        $company = isset($data->user) ? ($data->user)->company->company_name : null;
        $comment = Comment::select('id_comment', 'comment', 'id_user', 'created_at')
        ->where('related_to', 'id_timeline')
        ->where('id_related_to', $id_timeline)
        ->with(['user', 'user.job', 'user.company']) 
        ->get();
        $count_like = React::where('related_to', 'id_timeline')->where('id_related_to', $data->id_timeline)->count();
        $count_comment = Comment::where('related_to', 'id_timeline')->where('id_related_to', $data->id_timeline)->count();
       
        $transformedComments = [];
        if (!$comment->isEmpty()) {
            foreach ($comment as $commentItem) {
                $commentUser = $commentItem->user;
        
                $commentFullName = isset($commentUser) ? $commentUser->full_name : null;
                $commentProfilePicture = isset($commentUser) ? $commentUser->profile_picture : null;
                $commentJobTitle = isset($commentUser) ? $commentUser->job->job_title : null;
                $commentCompany = isset($commentUser) ? $commentUser->company->company_name : null;
                $reply = Comment::select('id_comment', 'comment', 'id_user', 'created_at')
                ->where('related_to', 'id_comment')
                ->where('id_related_to', $commentItem->id_comment)
                ->with(['user', 'user.job', 'user.company']) 
                ->get();
                $comment_count_like = React::where('related_to', 'id_comment')->where('id_related_to', $commentItem->id_comment)->count();
                $comment_count_comment = Comment::where('related_to', 'id_comment')->where('id_related_to', $commentItem->id_comment)->count();

                $transformedReplies = [];
                if (!$reply->isEmpty()) {
                    foreach ($reply as $replyItem) {
                        $replyUser = $replyItem->user;
                
                        $replyFullName = isset($replyUser) ? $replyUser->full_name : null;
                        $replyProfilePicture = isset($replyUser) ? $replyUser->profile_picture : null;
                        $replyJobTitle = isset($replyUser) ? $replyUser->job->job_title : null;
                        $replyCompany = isset($replyUser) ? $replyUser->company->company_name : null;
                        $reply_count_like = React::where('related_to', 'id_comment')->where('id_related_to', $replyItem->id_comment)->count();
                
                        $transformedReplies[] = [
                            'id_comment' => $replyItem->id_comment,
                            'comment' => $replyItem->comment,
                            'id_user' => $replyItem->id_user,
                            'full_name' => $replyFullName,
                            'profile_picture' => $replyProfilePicture,
                            'job_title' => $replyJobTitle,
                            'company' => $replyCompany,
                            'created_at' => $replyItem->created_at,
                            'count_like' => $reply_count_like,
                        ];
                    }
                }     

                $count_comment += $comment_count_comment;
        
                $transformedComments[] = [
                    'id_comment' => $commentItem->id_comment,
                    'comment' => $commentItem->comment,
                    'id_user' => $commentItem->id_user,
                    'full_name' => $commentFullName,
                    'profile_picture' => $commentProfilePicture,
                    'job_title' => $commentJobTitle,
                    'company' => $commentCompany,
                    'created_at' => $commentItem->created_at,
                    'count_like' => $comment_count_like,
                    'count_comment' => $comment_count_comment,
                    'reply' => $transformedReplies
                ];
            }
        }

        $timeline = [
            'id_timeline' => $data->id_timeline,
            'id_user' => $data->id_user,
            'full_name' => $full_name,
            'profile_picture' => $profile_picture,
            'job_title' => $job_title,
            'company' => $company,
            'description' => $data->description,
            'additional_data' => $data->additional_data,
            'created_at' => $data->created_at,
            'count_like' => $count_like,
            'count_comment' => $count_comment,
            'comment' => $transformedComments
        ];

        $interestUser = UserProfile::select('value')
            ->where([['id_user', '=', $data->id_user], ['key_name', '=', 'id_interest']])
            ->get();

        $interestData = Interest::select('interest_name')
            ->whereIn('id_interest', $interestUser)
            ->pluck('interest_name')
            ->toArray();

        $follower = Follow::where('id_user', $data->id_user)->count();
        $following = Follow::where('followed_by', $data->id_user)->count();
        $coverImage =  isset($data->user) ? $data->user->cover_image : null;
        $city = isset($data->user) ? ($data->user)->city->city_name : null;
        $province = isset($data->user) ? ($data->user)->city->province->province_name : null;

        $socialMedia = UserProfile::select('key_name', 'value')
        ->whereIn('key_name', ['instagram', 'twitter', 'youtube', 'github', 'linkedin', 'website'])
        ->where('id_user', $data->id_user)
        ->get();
        $socialMediaArray = [];
        foreach ($socialMedia as $sm) {
            $socialMediaArray[$sm->key_name] = $sm->value;
        };
            
        $profile = [
            'full_name' => $full_name,
            'profile_picture' => $profile_picture,
            'cover_image' => $coverImage,
            'job_title' => $job_title,
            'company' => $company,
            'city' => $city,
            'province' => $province,
            'interest' => $interestData,
            'follower' => $follower,
            'following' => $following,
            'social_media' => $socialMediaArray
        ];

        $result->timeline = $timeline;

        $result->profile = $profile;

        return response()->json([
            "code" => 200,
            "status" => "success get detail feed",
            "result" => $result
        ], 200);
    }

    public function send_like_unlike_feed(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'related_to' => 'required',
            'id_related_to' => 'required',
        ]);

        if ($validator->fails()) {
            return response_json(422, 'failed', $validator->messages());
        }

        $userId = get_id_user_jwt($request);

        if($request->related_to == 'id_timeline') {
            $check_feed = Timeline::where('id_timeline', $request->id_related_to)->first();

            if($check_feed){
                $check_liked_feed = React::where('related_to', 'id_timeline')->where('id_related_to', $request->id_related_to)->where('id_user', $userId)->first();

                if($check_liked_feed){
                    $check_liked_feed->delete();
                    return response_json(200, 'success', 'Post unliked successfully');
                } 

                React::create([
                    'related_to' => 'id_timeline',
                    'id_related_to' => $request->id_related_to,
                    'id_user' => $userId,
                    'created_at' => Carbon::now()->toDateTimeString()
                ]);

                return response_json(200, 'success','Post liked successfully');
            }
            return response_json(404, 'failed','Feed not found');
        } else if ($request->related_to == 'id_comment') {
            $check_comment = Comment::where('id_comment', $request->id_related_to)->first();

            if($check_comment){
                $reaction = React::where([['related_to', 'id_comment'], ['id_related_to', $request->id_related_to], ['id_user', $userId]])->first();

                if($reaction){
                    $reaction->delete();
                    return response_json(200, 'success','Comment unliked successfully');
                }
    
                React::create([
                    'related_to' => 'id_comment',
                    'id_related_to' => $request->id_related_to,
                    'id_user' => $userId
                ]);
    
                return response_json(200, 'success','Comment liked successfully');
            }

            return response_json(404, 'failed','Comment not found');
        } else {
            return response_json(404, 'failed','Related to not suitable');
        }
    }
}

?>