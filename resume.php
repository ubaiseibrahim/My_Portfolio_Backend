<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(array("message" => "Database connection failed."));
    exit();
}

$path = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : '';
$parts = explode('/', $path);
$action = $parts[0] ?? '';

switch ($action) {
    case 'post':
        handleUpload($db);
        break;
    case 'get':
        handleGet($db);
        break;
    default:
        http_response_code(404);
        echo json_encode(array("message" => "Endpoint not found."));
        break;
}

function handleUpload($db) {
    if (!isset($_FILES['resume'])) {
        http_response_code(400);
        echo json_encode(array("message" => "No resume file uploaded."));
        return;
    }

    $target_dir = "uploads/resume/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);

    $file = $_FILES['resume'];
    $file_name = time() . "_" . basename($file["name"]);
    $target_file = $target_dir . $file_name;
    $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    $allowed_types = array("pdf", "doc", "docx");

    if (!in_array($file_type, $allowed_types)) {
        http_response_code(400);
        echo json_encode(array("message" => "Invalid file type. Only PDF, DOC, and DOCX allowed."));
        return;
    }

    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        // Check for existing resume
        $stmt = $db->prepare("SELECT id, file_path FROM resume LIMIT 1");
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Delete old file
            if (file_exists($existing['file_path'])) {
                unlink($existing['file_path']);
            }
            // Update record
            $update_stmt = $db->prepare("UPDATE resume SET file_path = :path WHERE id = :id");
            $update_stmt->execute([':path' => $target_file, ':id' => $existing['id']]);
        } else {
            // Insert new record
            $insert_stmt = $db->prepare("INSERT INTO resume (file_path) VALUES (:path)");
            $insert_stmt->execute([':path' => $target_file]);
        }

        echo json_encode(array("message" => "Resume uploaded successfully.", "file_path" => $target_file));
    } else {
        http_response_code(500);
        echo json_encode(array("message" => "File upload failed."));
    }
}

function handleGet($db) {
    $stmt = $db->prepare("SELECT * FROM resume LIMIT 1");
    $stmt->execute();
    $resume = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resume) {
        echo json_encode($resume);
    } else {
        http_response_code(404);
        echo json_encode(array("message" => "No resume found."));
    }
}
?>
