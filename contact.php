<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
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
$id = $parts[1] ?? null;

switch ($action) {
    case 'post':
        handleSend($db);
        break;
    case 'get':
        handleGetAll($db);
        break;
    case 'delete':
        if ($id) {
            handleDelete($db, $id);
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "ID required for delete."));
        }
        break;
    default:
        http_response_code(404);
        echo json_encode(array("message" => "Endpoint not found."));
        break;
}


function handleSend($db) {
    $data = array_merge(json_decode(file_get_contents("php://input"), true) ?: [], $_POST);
    if (empty($data['name']) || empty($data['email']) || empty($data['message'])) {

        http_response_code(400);
        echo json_encode(array("message" => "Name, email, and message are required."));
        return;
    }

    $query = "INSERT INTO contact_messages SET name=:name, email=:email, message=:message";
    $stmt = $db->prepare($query);
    $success = $stmt->execute([
        ":name" => htmlspecialchars(strip_tags($data['name'])),
        ":email" => htmlspecialchars(strip_tags($data['email'])),
        ":message" => htmlspecialchars(strip_tags($data['message']))
    ]);

    if ($success) {
        // Send Email
        $to = "admin@example.com";
        $subject = "New Contact from " . $data['name'];
        $body = "Name: " . $data['name'] . "\nEmail: " . $data['email'] . "\n\nMessage:\n" . $data['message'];
        $headers = "From: webmaster@example.com";
        @mail($to, $subject, $body, $headers);

        http_response_code(201);
        echo json_encode(array("message" => "Message sent."));
    } else {
        http_response_code(500);
        echo json_encode(array("message" => "Failed to save message."));
    }
}

function handleGetAll($db) {
    $stmt = $db->prepare("SELECT * FROM contact_messages ORDER BY created_at DESC");
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function handleDelete($db, $id) {
    $stmt = $db->prepare("DELETE FROM contact_messages WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo json_encode(array("message" => "Message deleted."));
    } else {
        http_response_code(500);
        echo json_encode(array("message" => "Delete failed."));
    }
}
?>
