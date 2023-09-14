<?php 

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\EmailQueue;
use App\Models\Notification;
use App\Jobs\SendPushNotification;

function get_id_user_jwt($request){
  $authorizationHeader = $request->header('Authorization');

  $jwtParts = explode(' ', $authorizationHeader);
  $jwtToken = $jwtParts[1];

  $publicKey = env("JWT_PUBLIC_KEY"); 
  $decoded = JWT::decode($jwtToken, new Key($publicKey, 'RS256'));
  
  $userId = $decoded->data->id_user;

  return $userId;
}

function response_json($code, $status, $result){
  return response()->json([
    'code'  => $code,
    'status'=> $status,
    'result'=> $result
  ], $code);
}
 
function send_notification($content, $id_user, $notif_from, $url, $url_mobile, $tab, $section, $category, $additional_data){
  Notification::create([
    'status' => 'unread',
    'content' => $content,
    'id_user' => $id_user,
    'notif_from' => $notif_from,
    'url' => $url,
    'url_mobile' => $url_mobile,
    'tab' => $tab,
    'section' => $section,
    'category' => $category,
    'additional_data' => $additional_data,
    'created_at' => now(),
  ]);
}

function logQueue($to, $message, $subject, $cc='', $bcc='', $headers='', $attachment='0', $is_broadcast=0, $id_event=null, $id_broadcast=0) {
  $logQueue = [
      'to'            => $to,
      'cc'            => $cc,
      'bcc'           => $bcc,
      'message'       => $message,
      'status'        => 'sent',
      'date'          => date('Y-m-d H:i:s'),
      'headers'       => $headers,
      'attachment'    => $attachment,
      'subject'       => $subject,
      'is_broadcast'  => $is_broadcast,
      'id_event'      => $id_event,
      'id_broadcast'  => $id_broadcast,
  ];

  EmailQueue::create($logQueue);
}

?>