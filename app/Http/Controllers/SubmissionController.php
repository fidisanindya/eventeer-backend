<?php

namespace App\Http\Controllers;

use stdClass;
use App\Models\Event;
use App\Models\Submission;
use App\Models\CommunityUser;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function get_list_submission(Request $request)
    {
        // Get user id from jwt
        $user_id = get_id_user_jwt($request);

        $id_community = $request->input('id_community');
        $sort_by_status = $request->input('sort_by_status', 'all');
        $sort_by_time = $request->input('sort_by_time', 'newest_assigned');
        $limit = $request->input('limit') ?? 5;
        $start = $request->input('start') ?? 0;

        $result = new stdClass;

        // Cek apakah pengguna sudah bergabung dengan komunitas
        $userIsMember = CommunityUser::where('id_community', $id_community)
            ->where('id_user', $user_id)
            ->exists();

        if (!$userIsMember) {
            return response_json(403, 'error', 'This community is private. Join to see their activities and feeds.');
        }

        $submissionQuery = Event::where('id_community', $id_community)
            ->where('category', 'submission')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'DESC');

        if ($limit !== null) {
            $submissionQuery->limit($limit);
            if ($start !== null) {
                $submissionQuery->offset($start);
            }
        }

        $submission = $submissionQuery->get();

        $submission->makeHidden(['image', 'category', 'additional_data', 'status', 'id_user', 'deleted_at']);
        foreach ($submission as $item) {
            if ($item->additional_data !== null) {
                $item->additional_data = json_decode($item->additional_data);
            }
        }

        $totalData = $submissionQuery->count();

        foreach ($submission as $item) {
            if (isset($item->additional_data->date) && isset($item->additional_data->date->start) && isset($item->additional_data->date->end)) {
                $startDate = strtotime($item->additional_data->date->start);
                $endDate = strtotime($item->additional_data->date->end);

                $currentTime = time();

                $startDay = date('d', $startDate);
                $endDay = date('d', $endDate);
                $startMonthYear = date('F Y', $startDate);
                $endMonthYear = date('F Y', $endDate);

                // Range Deadline
                if ($startMonthYear === $endMonthYear) {
                    $item->date = "$startDay - $endDay $startMonthYear";
                } else {
                    $item->date = "$startDay $startMonthYear - $endDay $endMonthYear";
                }

                // Sisa Waktu Deadline
                $timeLeft = $endDate - $currentTime;

                if ($timeLeft <= 0) {
                    $item->duration = "Expired";
                } else {
                    $daysLeft = floor($timeLeft / (60 * 60 * 24));
                    $hoursLeft = floor(($timeLeft % (60 * 60 * 24)) / (60 * 60));

                    if ($daysLeft > 0) {
                        $item->duration = "$daysLeft days left";
                    } else {
                        $item->duration = "$hoursLeft hours left";
                    }
                }

                // Status
                if ($currentTime < $startDate) {
                    $item->sub_status = "New";
                } else {
                    $submissionData = Submission::where('id_event', $item->id_event)
                        ->where('id_user', $user_id)
                        ->first();

                    if (!$submissionData) {
                        $item->sub_status = "Not Finished";
                    } elseif ($submissionData->status === "confirmed") {
                        $item->sub_status = "Submitted";
                    } else {
                        $item->sub_status = "Not Finished";
                    }
                }
            } else {
                $item->date = '';
                $item->duration = '';
                $item->sub_status = '';
            }
        }

        // Pemanggilan fungsi sorting berdasarkan sort_by_time
        if ($sort_by_time === 'newest_assigned') {
            $submission = $this->sort_by_newest_assigned($submission);
        } elseif ($sort_by_time === 'nearest_deadline') {
            $submission = $this->sort_by_nearest_deadline($submission);
        } elseif ($sort_by_time === 'furthest_deadline') {
            $submission = $this->sort_by_furthest_deadline($submission);
        } else {
            return response_json(400, 'error', 'Invalid value for sort_by_time.');
        }

        // Pemanggilan fungsi sorting berdasarkan sort_by_status
        if ($sort_by_status === 'all') {
            // Tidak ada sorting tambahan untuk "all"
        } elseif ($sort_by_status === 'new') {
            $submission = $this->sort_by_status_new($submission);
        } elseif ($sort_by_status === 'submitted') {
            $submission = $this->sort_by_status_submitted($submission);
        } elseif ($sort_by_status === 'not_finished') {
            $submission = $this->sort_by_status_not_finished($submission);
        } else {
            return response_json(400, 'error', 'Invalid value for sort_by_status.');
        }

        // Hasil
        $result->submission = $submission->values();

        $result->meta = [
            "start" => $start,
            "limit" => $limit,
            "total_data" => $totalData
        ];

        return response_json(200, 'success', $result);
    }

    // Sort By Status
    private function sort_by_status_new($submission)
    {
        return $submission->where('sub_status', 'New');
    }

    private function sort_by_status_submitted($submission)
    {
        return $submission->where('sub_status', 'Submitted');
    }

    private function sort_by_status_not_finished($submission)
    {
        return $submission->where('sub_status', 'Not Finished');
    }

    // Sort By Time
    private function sort_by_newest_assigned($submission)
    {
        return $submission->sort(function ($a, $b) {
            $timestampA = strtotime($a->updated_at ?? $a->created_at);
            $timestampB = strtotime($b->updated_at ?? $b->created_at);
            
            return $timestampB - $timestampA;
        });
    }

    private function sort_by_nearest_deadline($submission)
    {
        return $submission->sortBy(function ($item) {
            if (isset($item->additional_data->date) && isset($item->additional_data->date->end)) {
                $endDate = strtotime($item->additional_data->date->end);
                return abs($endDate - time());
            }
            return PHP_INT_MAX;
        });
    }

    private function sort_by_furthest_deadline($submission)
    {
        return $submission->sortBy(function ($item) {
            if (isset($item->additional_data->date) && isset($item->additional_data->date->end)) {
                $endDate = strtotime($item->additional_data->date->end);
                return -abs($endDate - time());
            }
            return PHP_INT_MIN;
        });
    }

    public function getDetailSubmission(Request $request, $id_event){
        $user_id = get_id_user_jwt($request);
        $data_event = Event::where('id_event', $id_event)->first();
    
        if(!$data_event){
            return response()->json([
                "code" => 404,
                "status" => "submission not found",
            ], 404);
        }
    
        $my_submission = Submission::where('id_user', $user_id)
            ->where('id_event', $data_event->id_event)
            ->first();
        
        $data_event->my_submission =  json_decode($my_submission->additional_data) ?? null;
        $data_event->total_submitted = Submission::where('id_event', $data_event->id_event)->count();
        $data_event->additional_data = json_decode($data_event->additional_data);
    
        return response()->json([
            "code" => 200,
            "status" => "success get detail submission",
            "result" => $data_event
        ], 200);
    }
}

    

