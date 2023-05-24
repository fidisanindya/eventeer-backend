<?php 

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

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

?>