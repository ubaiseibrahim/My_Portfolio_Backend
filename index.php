<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
 http_response_code(200);
 exit();
}

http_response_code(200);
echo json_encode(array(
 "status" => "ok",
 "message" => "My Portfolio Backend API",
 "endpoints" => array("projects.php", "contact.php", "resume.php", "auth.php")
));
?>
