<?php

namespace App\Http\Controllers;

use App\Jobs\UploadFileSubmission;
use App\Jobs\UploadImageSubmission;
use stdClass;
use App\Models\Event;
use App\Models\Submission;
use App\Models\CommunityUser;
use App\Models\LogEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Image;

class SubmissionController extends Controller
{
    public function get_list_submission(Request $request)
    {
        // Get user id from jwt
        $user_id = get_id_user_jwt($request);

        $id_community = $request->input('id_community');
        $filter_by_status = $request->input('filter_by_status', 'all');
        $sort_by_time = $request->input('sort_by_time', 'newest_assigned');
        
        $limit = $request->input('limit', 5);
        $start = $request->input('start', 0); 
        $page = ceil(($start + 1) / $limit);

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
            ->where(function ($query) {
                $today = Carbon::now(); 
                $query->orWhereNull('additional_data')
                    ->orWhere(function ($innerQuery) use ($today) {
                        $innerQuery->where('additional_data->date->start', '<=', $today->toDateTimeString())
                            ->where('additional_data->date->end', '>=', $today->toDateTimeString());
                    });
            });

        $totalData = $submissionQuery->count();
        $submission = $submissionQuery->get();

        $submission->makeHidden(['description', 'image', 'category', 'additional_data', 'status', 'id_user', 'deleted_at']);

        foreach ($submission as $item) {
            if ($item->additional_data !== null) {
                $item->additional_data = json_decode($item->additional_data);
                if (isset($item->additional_data->date) && isset($item->additional_data->date->start) && isset($item->additional_data->date->end)) {
                    $startDate = strtotime($item->additional_data->date->start);
                    $endDate = strtotime($item->additional_data->date->end);

                    $currentTime = time();

                    $startDay = date('d', $startDate);
                    $endDay = date('d', $endDate);
                    $startMonth = date('M', $startDate);
                    $startYear = date('Y', $startDate);
                    $endMonth = date('M', $endDate);
                    $endYear = date('Y', $endDate);
                    
                    // Range Deadline
                    if ($startMonth === $endMonth && $startYear === $endYear) {
                        $item->date = "$startDay - $endDay $startMonth $startYear";
                    } elseif ($startYear === $endYear) {
                        $item->date = "$startDay $startMonth - $endDay $endMonth $endYear";
                    } else {
                        $item->date = "$startDay $startMonth $startYear - $endDay $endMonth $endYear";
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
                    $existingLog = LogEvent::where('id_event', $item->id_event)
                    ->where('id_user', $user_id)
                    ->first();

                    if (!$existingLog) {
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
        }

        // sort_by_time
        if ($sort_by_time === 'newest_assigned') {
            $submission = $this->sort_by_newest_assigned($submission);
        } elseif ($sort_by_time === 'nearest_deadline') {
            $submission = $this->sort_by_nearest_deadline($submission);
        } elseif ($sort_by_time === 'furthest_deadline') {
            $submission = $this->sort_by_furthest_deadline($submission);
        } else {
            return response_json(400, 'error', 'Invalid value for sort_by_time.');
        }

        // filter_by_status
        if ($filter_by_status === 'all') {
            // Tidak ada filtering tambahan untuk "all"
        } elseif ($filter_by_status === 'new') {
            $submission = $this->filter_by_status_new($submission);
        } elseif ($filter_by_status === 'submitted') {
            $submission = $this->filter_by_status_submitted($submission);
        } elseif ($filter_by_status === 'not_finished') {
            $submission = $this->filter_by_status_not_finished($submission);
        } else {
            return response_json(400, 'error', 'Invalid value for filter_by_status.');
        }

        $filteredData = $submission->values();

        $totalData = $filteredData->count();

        $startIndex = ($page - 1) * $limit;
        $endIndex = min($startIndex + $limit - 1, $totalData - 1);

        $submissionForPage = $filteredData->slice($startIndex, $endIndex - $startIndex + 1);

        $paginator = new LengthAwarePaginator(
            $submissionForPage,
            $totalData,
            $limit,
            $page
        );

        $result->submission = $paginator->values();

        $result->meta = [
            "start" => $start,
            "limit" => $limit,
            "total_data" => $totalData,
            "current_page" => $page,
            "total_pages" =>  $paginator->lastPage()
        ];

        return response_json(200, 'success', $result);
    }

    // Filter By Status
    private function filter_by_status_new($submission)
    {
        return $submission->where('sub_status', 'New');
    }

    private function filter_by_status_submitted($submission)
    {
        return $submission->where('sub_status', 'Submitted');
    }

    private function filter_by_status_not_finished($submission)
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

    public function get_upcoming_submission (Request $request)
    {
        $id_community = $request->input('id_community');
        
        $limit = $request->input('limit') ?? 5;
        $start = $request->input('start') ?? 0;
        $page = ceil(($start + 1) / $limit);

        $result = new stdClass;

        $submissionQuery = Event::where('id_community', $id_community)
            ->where('category', 'submission')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $today = Carbon::now(); 
                $query->orWhereNull('additional_data')
                    ->orWhere(function ($innerQuery) use ($today) {
                        $innerQuery->where('additional_data->date->start', '>', $today->toDateTimeString());
                    });
            });
        
        $submissionQuery->orderByRaw("TIMESTAMPDIFF(SECOND, NOW(), json_unquote(json_extract(`additional_data`, '$.date.start'))) ASC");

        $totalData = $submissionQuery->count();
        $submission = $submissionQuery->get();

        $submission->makeHidden(['description', 'image', 'category', 'additional_data', 'status', 'id_user', 'created_at', 'updated_at', 'deleted_at']);

        foreach ($submission as $item) {
            if ($item->additional_data !== null) {
                $item->additional_data = json_decode($item->additional_data);
                if (isset($item->additional_data->date) && isset($item->additional_data->date->start) && isset($item->additional_data->date->end)) {
                    $startDate = strtotime($item->additional_data->date->start);
                    $endDate = strtotime($item->additional_data->date->end);

                    if (date('H:i:s', $startDate) == '00:00:00') {
                        $startDate = strtotime(date('Y-m-d 23:59:59', $startDate));
                    }

                    $startDay = date('d', $startDate);
                    $endDay = date('d', $endDate);
                    $startMonth = date('M', $startDate);
                    $startYear = date('Y', $startDate);
                    $endMonth = date('M', $endDate);
                    $endYear = date('Y', $endDate);
                    
                    $item->start = date('H.i', $startDate);
                    
                    // Range Deadline
                    if ($startMonth === $endMonth && $startYear === $endYear) {
                        $item->date = "$startDay - $endDay $startMonth $startYear";
                    } elseif ($startYear === $endYear) {
                        $item->date = "$startDay $startMonth - $endDay $endMonth $endYear";
                    } else {
                        $item->date = "$startDay $startMonth $startYear - $endDay $endMonth $endYear";
                    }
                } else {
                    $item->date = '';
                    $item->start = '23.59';
                }
            }
        }

        $data = $submission->values();

        $startIndex = $start;
        $endIndex = min($startIndex + $limit - 1, $totalData - 1);

        $submissionForPage = $data->slice($startIndex, $endIndex - $startIndex + 1);

        $paginator = new LengthAwarePaginator(
            $submissionForPage,
            $totalData,
            $limit,
            $page
        );

        $result->submission = $paginator->values();

        $result->meta = [
            "start" => $start,
            "limit" => $limit,
            "total_data" => $totalData,
            "current_page" => $page,
            "total_pages" =>  $paginator->lastPage()
        ];

        return response_json(200, 'success', $result);
    }

    public function get_history_submission (Request $request)
    {
        $user_id = get_id_user_jwt($request);
        $id_community = $request->input('id_community');
        
        $limit = $request->input('limit') ?? 5;
        $start = $request->input('start') ?? 0;
        $page = ceil(($start + 1) / $limit);

        $result = new stdClass;

        $query = Event::where('id_community', $id_community)
        ->where('category', 'submission')
        ->where('status', 'active')
        ->whereNull('deleted_at')
        ->where(function ($query) {
            $today = Carbon::now(); 
            $query->orWhereNull('additional_data')
                ->orWhere(function ($innerQuery) use ($today) {
                    $innerQuery->where('additional_data->date->end', '<=', $today->toDateTimeString());
                });
        });
 
        $events = $query->get();

        foreach ($events as $event) {
            $submission = Submission::where('id_user', $user_id)
            ->where('id_event', $event->id_event)
            ->first();

            // Status
            if ($submission !== null && $submission->id_user !== null) {
                $event->sub_status = date('d M Y, H:i', strtotime($submission->created_at));
            } else {
                $event->sub_status = 'overdue';
            }

            $event->makeHidden(['description', 'image', 'category', 'additional_data', 'status', 'id_user', 'created_at', 'updated_at', 'deleted_at']);
        }

        $data = $events->values();

        $totalData = $events->count();

        $startIndex = $start;
        $endIndex = min($startIndex + $limit - 1, $totalData - 1);

        $submissionForPage = $data->slice($startIndex, $endIndex - $startIndex + 1);

        $paginator = new LengthAwarePaginator(
            $submissionForPage,
            $totalData,
            $limit,
            $page
        );

        $result->submission = $paginator->values();

        $result->meta = [
            "start" => $start,
            "limit" => $limit,
            "total_data" => $totalData,
            "current_page" => $page,
            "total_pages" => $paginator->lastPage(), 
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
        
        $data_event->my_submission =  $my_submission != null ? json_decode($my_submission->additional_data) : null;
        $data_event->total_submitted = Submission::where('id_event', $data_event->id_event)->count();
        $data_event->additional_data = json_decode($data_event->additional_data);

        // Add Log Event
        $log_status = $my_submission ? "Submitted" : "Not Finished";
        $existingLog = LogEvent::where('id_event', $data_event->id_event)
            ->where('id_user', $user_id)
            ->first();

        if (!$existingLog) {
            $log = new LogEvent();
            $log->id_event = $data_event->id_event;
            $log->id_user = $user_id;
            $log->created_at = now();
            $log->save();
        }

        $data_event->sub_status = $log_status;
    
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

    public function createSubmission(Request $request)
    {
        // Get user id from jwt
        $user_id = get_id_user_jwt($request);

        $request_data = $request->all();

        $imageUrl = $this->processImage($request_data['image'], $request_data['title']);

        $submissionForm = [];
        foreach ($request_data['submission_form'] as $item) {
            $submissionFormItem = [];
            foreach ($item as $key => $value) {
                $submissionFormItem[$key] = $value;
            }
            if ($submissionFormItem['type'] == 'select' && isset($item['data'])) {
                $submissionFormItem['data'] = json_decode($item['data']); 
            }
            $placeholderPrefix = ($submissionFormItem['type'] == 'select') ? 'Select Your ' : (($submissionFormItem['type'] == 'file') ? 'Choose Your ' : 'Input Your ');
            $submissionFormItem['placeholder'] = $placeholderPrefix . $submissionFormItem['title'];
            $submissionForm[] = $submissionFormItem;
        }

        $submission = new Event([
            'id_community' => $request_data['id_community'],
            'title' => $request_data['title'],
            'description' => $request_data['description'],
            'image' => $imageUrl,
            'category' => 'submission',
            'additional_data' => json_encode([
                'date' => [
                    'start' => $request_data['start'],
                    'end' => $request_data['end']
                ],
                'submission_form' => $submissionForm
            ], JSON_UNESCAPED_SLASHES),
            'status' => 'active',
            'id_user' => $user_id
        ]);

        $submission->save();

        return response()->json([
            'code' => 200,
            'status' => 'Submission created successfully',
            'submission' => $submission,
        ], 200);
    }

    public function updateSubmission(Request $request)
    {
        $event = Event::where('id_event', $request->id_event)->first();

        if (!$event) {
            return response()->json(['message' => 'Event not found'], 404);
        }

        $request_data = $request->all();
        $additional_data = json_decode($event->additional_data, true);
        $imageUrl = $event->image;
        if ($request->hasFile('image')) {
            $imageUrl = $this->processImage($request_data['image'], $request_data['title']);
        }

        $event->update([
            'title' => $request_data['title'],
            'description' => $request_data['description'],
            'date' => [
                'start' => $request_data['start'],
                'end' => $request_data['end']
            ],
            'image' => $imageUrl
        ]);

        $submissionForm = [];
        foreach ($request_data['submission_form'] as $item) {
            $submissionFormItem = [];
            foreach ($item as $key => $value) {
                $submissionFormItem[$key] = $value;
            }
            $placeholderPrefix = ($submissionFormItem['type'] == 'select') ? 'Select Your ' : (($submissionFormItem['type'] == 'file') ? 'Choose Your ' : 'Input Your ');
            $submissionFormItem['placeholder'] = $placeholderPrefix . $submissionFormItem['title'];
            $submissionForm[] = $submissionFormItem;
        }

        $additional_data['submission_form'] = $submissionForm;
        $event->additional_data = json_encode($additional_data, JSON_UNESCAPED_SLASHES);
        $event->save();

        return response()->json([
            'code' => 200,
            'status' => 'Event updated successfully',
            'updated' => $event
        ], 200);
    }

    private function processImage($imageData, $title)
    {
        if (!$imageData) {
            return null;
        }

        $image = Image::make($imageData)->resize(400, null, function ($constraint) {
            $constraint->aspectRatio();
        });

        $filename = $title . '_' . date('dmYhis') . '_submission.' . $imageData->getClientOriginalExtension();
        $data = $image->encode($imageData->getClientOriginalExtension())->__toString();
        Storage::put('public/picture_queue/' . $filename, $data);
        UploadImageSubmission::dispatch($filename);
        $key = "userfiles/images/event/" . $filename;
        $imageUrl = config('filesystems.disks.s3.bucketurl') . "/" . $key;

        return $imageUrl;
    }
}

    

