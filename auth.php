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

class FileUploader {
    private $target_dir = "uploads/profiles/";

    private $allowed_types = array("jpg", "jpeg", "png", "gif");

    private $max_size = 5242880; // 5MB

    public function uploadSingle($file) {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
        if (!file_exists($this->target_dir)) mkdir($this->target_dir, 0777, true);

        $file_name = time() . "_" . basename($file["name"]);
        $target_file = $this->target_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        if (!in_array($file_type, $this->allowed_types)) return array("error" => "Invalid file type.");
        if ($file["size"] > $this->max_size) return array("error" => "File too large.");

        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            return $this->target_dir . $file_name;
        }
        return array("error" => "Upload failed.");
    }
}

$database = new Database();
$db = $database->getConnection();
$uploader = new FileUploader();

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
    case 'login':
        handleLogin($db);
        break;
    case 'post':
        handleCreateUser($db, $uploader);
        break;
    case 'get':
        if ($id) {
            handleGetUserById($db, $id);
        } else {
            handleGetAllUsers($db);
        }
        break;
    case 'put':
        if ($id) {
            handleUpdateUser($db, $uploader, $id);
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "ID required for update."));
        }
        break;
    case 'delete':
        if ($id) {
            handleDeleteUser($db, $id);
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

function handleLogin($db) {
    $data = array_merge(json_decode(file_get_contents("php://input"), true) ?: [], $_POST);
    if (empty($data['username']) || empty($data['password'])) {

        http_response_code(400);
        echo json_encode(array("message" => "Username and password are required."));
        return;
    }

    $query = "SELECT * FROM users WHERE username = :uname LIMIT 0,1";
    $stmt = $db->prepare($query);
    $stmt->execute([":uname" => $data['username']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($data['password'], $user['password'])) {
        unset($user['password']);
        http_response_code(200);
        echo json_encode(array("message" => "Login successful.", "user" => $user));
    } else {
        http_response_code(401);
        echo json_encode(array("message" => "Invalid credentials."));
    }
}

function handleCreateUser($db, $uploader) {
    $data = array_merge(json_decode(file_get_contents("php://input"), true) ?: [], $_POST);
    if (empty($data['full_name']) || empty($data['username']) || empty($data['email']) || empty($data['password'])) {

        http_response_code(400);
        echo json_encode(array("message" => "Incomplete data. Full name, username, email, and password are required."));
        return;
    }

    $profile_picture = $uploader->uploadSingle($_FILES['profile_picture'] ?? null);
    if (is_array($profile_picture) && isset($profile_picture['error'])) {
        http_response_code(400);
        echo json_encode($profile_picture);
        return;
    }

    try {
        $query = "INSERT INTO users SET full_name=:name, username=:uname, email=:email, password=:pass, profile_picture=:pic";
        $stmt = $db->prepare($query);
        $hashed_password = password_hash($data['password'], PASSWORD_BCRYPT);

        $stmt->execute([
            ":name" => htmlspecialchars(strip_tags($data['full_name'])),
            ":uname" => htmlspecialchars(strip_tags($data['username'])),
            ":email" => htmlspecialchars(strip_tags($data['email'])),
            ":pass" => $hashed_password,
            ":pic" => $profile_picture
        ]);

        http_response_code(201);
        echo json_encode(array("message" => "User created successfully."));
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(array("message" => "Error: " . $e->getMessage()));
    }
}

function handleGetAllUsers($db) {
    $stmt = $db->prepare("SELECT id, full_name, username, email, profile_picture, created_at FROM users ORDER BY created_at DESC");
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function handleGetUserById($db, $id) {
    $stmt = $db->prepare("SELECT id, full_name, username, email, profile_picture, created_at FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo json_encode($user);
    } else {
        http_response_code(404);
        echo json_encode(array("message" => "User not found."));
    }
}

function handleUpdateUser($db, $uploader, $id) {
    $data = array_merge(json_decode(file_get_contents("php://input"), true) ?: [], $_POST);
    $stmt = $db->prepare("SELECT profile_picture, password FROM users WHERE id = ?");

    $stmt->execute([$id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        http_response_code(404);
        echo json_encode(array("message" => "User not found."));
        return;
    }

    $profile_picture = $uploader->uploadSingle($_FILES['profile_picture'] ?? null) ?: $existing['profile_picture'];
    $hashed_password = !empty($data['password']) ? password_hash($data['password'], PASSWORD_BCRYPT) : $existing['password'];

    $query = "UPDATE users SET 
                full_name = COALESCE(:name, full_name),
                username = COALESCE(:uname, username),
                email = COALESCE(:email, email),
                password = :pass,
                profile_picture = :pic
              WHERE id = :id";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute([
            ":id" => $id,
            ":name" => $data['full_name'] ?? null,
            ":uname" => $data['username'] ?? null,
            ":email" => $data['email'] ?? null,
            ":pass" => $hashed_password,
            ":pic" => $profile_picture
        ]);
        echo json_encode(array("message" => "User updated."));
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(array("message" => "Update failed: " . $e->getMessage()));
    }
}

function handleDeleteUser($db, $id) {
    $stmt = $db->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    if ($stmt->execute([$id])) {
        if ($user && !empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
            unlink($user['profile_picture']);
        }
        echo json_encode(array("message" => "User deleted."));
    } else {
        http_response_code(500);
        echo json_encode(array("message" => "Delete failed."));
    }
}

?>
