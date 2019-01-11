<?php
require_once 'config.php';

$file_folder = 'images/';
if (IMAGE_YEARS) $file_folder .= date('Y') . '/';
if (IMAGE_MONTHS) $file_folder .= date('m') . '/';
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization');
if(isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/plain') !== false) {
  $format = 'text';
} else {
  header('Content-Type: application/json');
  $format = 'json';
}
// Require access token
if(!array_key_exists('HTTP_AUTHORIZATION', $_SERVER)) {
  header('HTTP/1.1 401 Unauthorized');
  echo json_encode([
    'error' => 'unauthorized',
    'error_description' => 'No authorization header was present in the request'
  ]);
  die();
}
if(!preg_match('/Bearer (.+)/', $_SERVER['HTTP_AUTHORIZATION'], $match)) {
  header('HTTP/1.1 400 Bad Request');
  echo json_encode([
    'error' => 'invalid_authorization',
    'error_description' => 'Invalid authorization header'
  ]);
  die();
}
$token = $match[1];
// Check whether the access token is valid
$ch = curl_init(TOKEN_ENDPOINT);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
  'Authorization: Bearer ' . $token,
  'Accept: application/json'
]);
$response = curl_exec($ch);
$data = json_decode($response, true);
if(!$data || !array_key_exists('scope', $data)) {
  header('HTTP/1.1 401 Unauthorized');
  echo $response;
  die();
}

// Only tokens with "create" or "media" scope can upload files
if(!array_intersect(['create','media'], explode(" ", $data['scope']))) {
  header('HTTP/1.1 401 Unauthorized');
  echo json_encode([
    'error' => 'insufficient_scope',
    'error_description' => 'The access token provided does not have the necessary scope to upload files'
  ]);
  die();
}
// Check for a file
if(!array_key_exists('file', $_FILES)) {
  header('HTTP/1.1 400 Bad Request');
  echo json_encode([
    'error' => 'invalid_request',
    'error_description' => 'The request must have a file upload named "file"'
  ]);
  die();
}
$file = $_FILES['file'];
$ext = mime_type_to_ext($file['type']);
if(!$ext) {
  $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
  if(!$ext)
    $ext = 'txt';
}
$filename = 'file-'.date('YmdHis').'-'.mt_rand(1000,9999).'.'.$ext;
if (!copy($file['tmp_name'], $file_folder . $filename)) {
	throw new Exception('File could not be saved.');
}
$url = $base_url . 'images/' . $filename;
header('HTTP/1.1 201 Created');
header('Location: '.$url);
if($format === 'text') {
  echo $url."\n";
} else {
  echo json_encode([
    'url' => $url
  ]);
}
function mime_type_to_ext($type) {
  $types = [
    'image/jpeg' => 'jpg',
    'image/pjpeg' => 'jpg',
    'image/gif' => 'gif',
    'image/png' => 'png',
    'image/x-png' => 'png',
    'image/svg' => 'svg',
    'audio/x-wav' => 'wav',
    'audio/wave' => 'wav',
    'audio/wav' => 'wav',
    'video/mpeg' => 'mpg',
    'video/quicktime' => 'mov',
    'video/mp4' => 'mp4',
    'audio/x-m4a' => 'm4a',
    'audio/mp3' => 'mp3',
    'audio/mpeg3' => 'mp3',
    'audio/mpeg' => 'mp3',
    'application/json' => 'json',
    'text/json' => 'json',
    'text/html' => 'html',
    'text/plain' => 'txt',
    'application/xml' => 'xml',
    'text/xml' => 'xml',
    'application/x-zip' => 'zip',
    'application/zip' => 'zip',
    'text/csv' => 'csv',
  ];
  if(array_key_exists($type, $types))
    return $types[$type];
  else
    return false;
}