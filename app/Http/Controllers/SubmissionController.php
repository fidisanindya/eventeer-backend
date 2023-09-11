<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Submission;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
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
