<?php

namespace App\Http\Controllers;

use stdClass;
use App\Models\Event;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\Community;
use App\Models\Portofolio;
use App\Models\Submission;
use App\Models\Certificate;
use Illuminate\Http\Request;
use App\Models\CommunityUser;
use App\Http\Controllers\Controller;
use App\Models\Timeline;

class ProfileController extends Controller
{
    public function detailPortfolio(Request $request){
        // Get id_user from Bearer Token
        $authorizationHeader = $request->header('Authorization');

        $jwtParts = explode(' ', $authorizationHeader);
        $jwtToken = $jwtParts[1];

        $publicKey = env("JWT_PUBLIC_KEY"); 
        $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
        
        $userId = $decoded->data->id_user;

        $result = new stdClass;

        // Get detail user portfolio
        $portfolio = Portofolio::where('id_user', $userId)
        ->select('id_portofolio', 'id_user', 'project_name', 'project_url', 'start_date', 'end_date')
        ->when(request()->has('limit'), function ($query) {
            $limit = request()->input('limit', null);
            if($limit != 'all') {
                $query->limit($limit);
            }
        })
        ->orderBy('start_date', 'ASC')
        ->get();

        $result->portfolio = $portfolio;

        $result->meta = [
            'limit' => request()->input('limit', null),
        ];

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $result,
        ]);
    }

    public function detailCertificate(Request $request){
        // Get id_user from Bearer Token
        $authorizationHeader = $request->header('Authorization');

        $jwtParts = explode(' ', $authorizationHeader);
        $jwtToken = $jwtParts[1];

        $publicKey = env("JWT_PUBLIC_KEY"); 
        $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
        
        $userId = $decoded->data->id_user;

        $result = new stdClass;

        // Get detail User Certificate
        $certificate = Certificate::with(['submission' => function ($querySubmission){
            $querySubmission->with(['event' => function ($queryEvent) {
                $queryEvent->with(['community' => function ($queryCommunity) {
                    $queryCommunity->select('id_community', 'title');
                }])
                ->select('id_event', 'id_community', 'title', 'image');
            }])->select('id_submission', 'id_event');
        }])
        ->where('id_user', $userId)
        ->when(request()->has('limit'), function ($query) {
            $limit = request()->input('limit', null);
            if($limit != 'all') {
                $query->limit($limit);
            }
        })
        ->select('id_certificate', 'id_submission', 'id_user', 'issue_date', 'expire_date')
        ->orderBy('issue_date', 'DESC')
        ->get();

        $result->certificate = $certificate;

        $result->meta = [
            'limit' => request()->input('limit', null),
        ];

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $result,
        ]);
    }

    public function detailCommunity(Request $request){
        // Get id_user from Bearer Token
        $authorizationHeader = $request->header('Authorization');

        $jwtParts = explode(' ', $authorizationHeader);
        $jwtToken = $jwtParts[1];

        $publicKey = env("JWT_PUBLIC_KEY"); 
        $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
        
        $userId = $decoded->data->id_user;

        $result = new stdClass;

        // Get detail user community
        $user_community = CommunityUser::where('status', 'active')->orWhere('status', 'running')->where('id_user', $userId)->pluck('id_community');
        $community = Community::where('status', 'active')
        ->whereIn('id_community', $user_community)
        ->select('id_community', 'title', 'image', 'start_date', 'end_date')
        ->when(request()->has('limit'), function ($query) {
            $limit = request()->input('limit', null);
            if($limit != 'all') {
                $query->limit($limit);
            }
        })
        ->orderBy('start_date', 'DESC')
        ->get();

        $result->community = $community;

        $result->meta = [
            'limit' => request()->input('limit', null),
        ];

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $result,
        ]);
    }

    public function detailPost(Request $request){
        // Get id_user from Bearer Token
        $authorizationHeader = $request->header('Authorization');

        $jwtParts = explode(' ', $authorizationHeader);
        $jwtToken = $jwtParts[1];
        
        $publicKey = env("JWT_PUBLIC_KEY"); 
        $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
        
        $userId = $decoded->data->id_user;
        
        $result = new stdClass;

        // Get detail post user
        $post = Timeline::with(['event' => function ($queryEvent) {
            $queryEvent->select('id_event', 'title', 'image');
        }, 'user' => function ($queryUser) {
            $queryUser->select('id_user', 'full_name', 'profile_picture', 'id_job', 'id_company')
            ->with(['job' => function ($queryJob) {
                $queryJob->select('id_job', 'job_title');
            }, 'company' => function ($queryCompany) {
                $queryCompany->select('id_company', 'company_name');
            }]);
        }])
        ->with(['file_attachment' => function ($queryFileAttachment) {
            $queryFileAttachment->where('related_to', 'id_timeline');
        }])
        ->where('id_user', $userId)
        ->whereNull('deleted_at')
        ->select('id_timeline', 'id_event', 'id_user', 'description', 'additional_data', 'created_at')
        ->withCount(['react as total_like' => function ($query) {
            $query->where('related_to', '=', 'id_timeline');
        }])
        ->withCount(['comment as total_comment' => function ($query) {
            $query->where('related_to', '=', 'id_timeline');
        }])
        ->when(request()->has('limit'), function ($query) {
            $limit = request()->input('limit', null);
            if($limit != 'all') {
                $query->limit($limit);
            }
        })
        ->orderBy('created_at', 'DESC')
        ->get();

        $result->post = $post;

        $result->meta = [
            'limit' => request()->input('limit', null),
        ];

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $result,
        ]);
    }
    
    public function detailActivity(Request $request){
        // Get id_user from Bearer Token
        $authorizationHeader = $request->header('Authorization');

        $jwtParts = explode(' ', $authorizationHeader);
        $jwtToken = $jwtParts[1];

        $publicKey = env("JWT_PUBLIC_KEY"); 
        $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
        
        $userId = $decoded->data->id_user;

        $result = new stdClass;

        // Get detail activity user
        $today = now()->toDateString();
        $activity = Submission::where('id_user', $userId)->pluck('id_event');
        $event = Event::with(['community' => function ($item) {
            $item->select('id_community', 'title', 'image');
        }])
        ->whereIn('id_event', $activity)
        ->where('additional_data->date->start', '<=', $today)
        ->orderBy('additional_data->date->start', 'DESC')
        ->select('id_event', 'id_community', 'id_user', 'title', 'image', 'category', 'additional_data', 'status')
        ->withCount('submission as people_joined')
        ->when(request()->has('limit'), function ($query) {
            $limit = request()->input('limit', null);
            if($limit != 'all') {
                $query->limit($limit);
            }
        })->get();

        foreach ($event as $item){
            $item->additional_data = json_decode($item->additional_data);
        }

        $result->activity = $event;

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'result' => $result,
        ]);
    }
}
