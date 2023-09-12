<?php

namespace App\Http\Controllers;

use App\Jobs\UploadFileSubmission;
use stdClass;
use App\Models\Event;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SubmissionController extends Controller
{
    public function get_list_submission(Request $request)
    {
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

    public function assignSubmission(Request $request, $id_event)
    {
        $user_id = get_id_user_jwt($request);
        $current_time = now();

        $event = Event::where('id_event', $id_event)->first();
        $submission_form = json_decode($event->additional_data)->submission_form;

        $submission_data = [];
        foreach($submission_form as $form){
            $field_name = $form->form_name;
            if ($request->hasFile($field_name)) {
                $fileUrl = $this->processFile($request->file($field_name));
                $submission_data[$field_name] = $fileUrl;
            }else{
                $submission_data[$field_name] = $request->input($field_name);
            }
        }

        $status = '';
        $data = [
            'id_event' => $id_event,
            'id_user' => $user_id,
            'additional_data' => json_encode($submission_data, JSON_UNESCAPED_SLASHES),
            'type' => 'submission',
            'status' => 'confirmed',
            'updated_at' => $current_time,
        ];

        if($request->type == 'submit'){
            $data['created_at'] = $current_time;
            Submission::insert($data);
            $status = 'submission success assigned';
        }else{
            Submission::where('id_event', $id_event)->where('id_user', $user_id)->update($data);
            $status = 'submission updated';
        }

        return response()->json([
            'code' => 200,
            'status' => $status
        ], 200);
    }

    private function processFile($file)
    {     
        if (!$file) {
            return null;
        }else if($file->getClientOriginalExtension() == 'pdf'){
            $maxSize = Validator::make(['file' => $file], [
                'file' => 'required|file|mimes:pdf|max:30000', 
            ]);
    
            if ($maxSize->fails()) {
                return response_json(422, 'failed', $maxSize->messages());
            }

            $filename = date('dmYhis') . '_' . $file->getClientOriginalName();
            Storage::put('public/pdf_queue/' . $filename, file_get_contents($file));
            UploadFileSubmission::dispatch($filename);
            $key = "userfiles/file_submission/" . $filename;
            $pdfUrl = config('filesystems.disks.s3.bucketurl') . "/" . $key;
            return $pdfUrl;
        }
    }

}

    

