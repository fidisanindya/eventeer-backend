<?php 

namespace App\Http\Controllers;

use stdClass;
use App\Models\Event;
use App\Models\Submission;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function get_list_submission(Request $request)
    {
        // Get id_user from Bearer Token
        $limit = $request->input('limit');
        $start = $request->input('start');

        $result = new stdClass;

        $submissionQuery = Event::where('category', 'submission')
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

        $submission->makeHidden(['image','category','additional_data','status','id_user','deleted_at','created_at','updated_at']);
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
        
                $startDay = date('d', $startDate);
                $endDay = date('d', $endDate);
                $startMonthYear = date('F Y', $startDate);
                $endMonthYear = date('F Y', $endDate);
        
                if ($startMonthYear === $endMonthYear) {
                    // Jika bulan dan tahun sama
                    $item->date = "$startDay - $endDay $startMonthYear";
                } else {
                    // Jika berbeda bulan atau tahun
                    $item->date = "$startDay $startMonthYear - $endDay $endMonthYear";
                }
            } else {
                $item->date = '';
            }
        }

        foreach ($submission as $item) {
            if (isset($item->additional_data->date) && isset($item->additional_data->date->end)) {
                $endDate = strtotime($item->additional_data->date->end);
                $currentTime = time();
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
            } else {
                $item->duration = '';
            }
        }

        $result->submission = $submission;

        $result->meta = [
            "start" => $start,
            "limit" => $limit,
            "total_data" => $totalData
        ];

        return response_json(200, 'success', $result);
    }    
}

?>