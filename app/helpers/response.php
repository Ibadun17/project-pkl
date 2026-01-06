<?php
function ok($data = null, $message = 'OK'){
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success'=>true,'message'=>$message,'data'=>$data]);
  exit;
}

function fail($message='Error', $status=400, $data=null){
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['success'=>false,'message'=>$message,'error'=>$message,'data'=>$data]);
  exit;
}
