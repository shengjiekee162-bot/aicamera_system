<?php
require __DIR__.'/../includes/bootstrap.php';require_login('admin');verify_csrf();header('Content-Type: application/json');
$payload=['contents'=>[['parts'=>[['text'=>'Reply with exactly: Connection OK']]]]];$result=gemini_generate($payload,20);if($result['success']){echo json_encode(['success'=>true,'message'=>'Connection successful using slot '.$result['slot'].' ('.$result['model'].').']);}else{echo json_encode(['success'=>false,'message'=>$result['message']]);}
