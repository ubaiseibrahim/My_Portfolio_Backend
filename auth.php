
<?php

// =====================================================
// CORS CONFIGURATION
// =====================================================

$allowed_origins = [
    'https://ubaiseibrahim.vercel.app'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Vary: Origin");
}

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600");

// Handle browser preflight requests before connecting to the database.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

// =====================================================
// DATABASE CONFIGURATION
// =====================================================

include_once 'config.php';

class FileUploader
{
    private $target_dir = "uploads/profiles/";

    private $allowed_types = ["jpg", "jpeg", "png", "gif"];

    private $max_size = 5242880; // 5 MB

    public function uploadSingle($file)
    {
        if (
            !isset($file) ||
            !is_array($file) ||
            !isset($file['error']) ||
            $file['error'] !== UPLOAD_ERR_OK
        ) {
            return null;
        }

        if (!is_dir($this->target_dir)) {
            if (!mkdir($this->target_dir, 0755, true) && !is_dir($this->target_dir)) {
                return ["error" => "Unable to create upload directory."];
            }
        }

        $file_name = time() . "_" . bin2hex(random_bytes(4)) . "_" .
            basename($file["name"]);

        $target_file = $this->target_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        if (!in_array($file_type, $this->allowed_types, true)) {
            return ["error" => "Invalid file type."];
        }

        if ($file["size"] > $this->max_size) {
            return ["error" => "File too large. Maximum size is 5 MB."];
        }

        // Verify that the uploaded file is actually an image.
        $image_info = @getimagesize($file["tmp_name"]);

        if ($image_info === false) {
            return ["error" => "Uploaded file is not a valid image."];
        }

        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            return $target_file;
        }

        return ["error" => "Upload failed."];
    }
}

// =====================================================
// DATABASE CONNECTION
// =====================================================

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        http_response_code(500);
        echo json_encode(["message" => "Database connection failed."]);
        exit();
    }
} catch (Throwable $e) {
    error_log("Database connection error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode(["message" => "Database connection failed."]);
    exit();
}

$uploader = new FileUploader();

// =====================================================
// ROUTING
// URL examples:
// auth.php/login
// auth.php/post
// auth.php/get
// auth.php/get/1
// auth.php/put/1
// auth.php/delete/1
// =====================================================

$path = isset($_SERVER['PATH_INFO'])
    ? trim($_SERVER['PATH_INFO'], '/')
    : '';

$parts = $path === '' ? [] : explode('/', $path);

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
        if ($id !== null) {
            handleGetUserById($db, $id);
        } else {
            handleGetAllUsers($db);
        }
        break;

    case 'put':
        if ($id !== null) {
            handleUpdateUser($db, $uploader, $id);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "ID required for update."]);
        }
        break;

    case 'delete':
        if ($id !== null) {
            handleDeleteUser($db, $id);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "ID required for delete."]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found."]);
        break;
}

// =====================================================
// LOGIN
// =====================================================

function handleLogin($db)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["message" => "Method not allowed."]);
        return;
    }

    $json = json_decode(file_get_contents("php://input"), true) ?: [];
    $data = array_merge($json, $_POST);

    if (empty($data['username']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode([
            "message" => "Username and password are required."
        ]);
        return;
    }

    try {
        $query = "SELECT * FROM users WHERE username = :uname LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute([":uname" => $data['username']]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($data['password'], $user['password'])) {
            unset($user['password']);

            http_response_code(200);
            echo json_encode([
                "message" => "Login successful.",
                "user" => $user
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["message" => "Invalid credentials."]);
        }
    } catch (PDOException $e) {
        error_log("Login database error: " . $e->getMessage());

        http_response_code(500);
        echo json_encode(["message" => "Login failed due to a server error."]);
    }
}

// =====================================================
// CREATE USER
// =====================================================

function handleCreateUser($db, $uploader)
{
    $json = json_decode(file_get_contents("php://input"), true) ?: [];
    $data = array_merge($json, $_POST);

    if (
        empty($data['full_name']) ||
        empty($data['username']) ||
        empty($data['email']) ||
        empty($data['password'])
    ) {
        http_response_code(400);
        echo json_encode([
            "message" => "Full name, username, email, and password are required."
        ]);
        return;
    }

    $profile_picture = $uploader->uploadSingle(
        $_FILES['profile_picture'] ?? null
    );

    if (is_array($profile_picture) && isset($profile_picture['error'])) {
        http_response_code(400);
        echo json_encode($profile_picture);
        return;
    }

    try {
        $query = "INSERT INTO users
            SET full_name = :name,
                username = :uname,
                email = :email,
                password = :pass,
                profile_picture = :pic";

        $stmt = $db->prepare($query);

        $stmt->execute([
            ":name" => htmlspecialchars(strip_tags($data['full_name'])),
            ":uname" => htmlspecialchars(strip_tags($data['username'])),
            ":email" => htmlspecialchars(strip_tags($data['email'])),
            ":pass" => password_hash($data['password'], PASSWORD_BCRYPT),
            ":pic" => $profile_picture
        ]);

        http_response_code(201);
        echo json_encode(["message" => "User created successfully."]);
    } catch (PDOException $e) {
        error_log("Create user error: " . $e->getMessage());

        http_response_code(500);
        echo json_encode(["message" => "Unable to create user."]);
    }
}

// =====================================================
// GET ALL USERS
// =====================================================

function handleGetAllUsers($db)
{
    try {
        $stmt = $db->prepare(
            "SELECT id, full_name, username, email, profile_picture, created_at
             FROM users
             ORDER BY created_at DESC"
        );

        $stmt->execute();

        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        error_log("Get users error: " . $e->getMessage());

        http_response_code(500);
        echo json_encode(["message" => "Unable to retrieve users."]);
    }
}

// =====================================================
// GET USER BY ID
// =====================================================

function handleGetUserById($db, $id)
{
    try {
        $stmt = $db->prepare(
            "SELECT id, full_name, username, email, profile_picture, created_at
             FROM users
             WHERE id = ?"
        );

        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            echo json_encode($user);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "User not found."]);
        }
    } catch (PDOException $e) {
        error_log("Get user error: " . $e->getMessage());

        http_response_code(500);
        echo json_encode(["message" => "Unable to retrieve user."]);
    }
}

// =====================================================
// UPDATE USER
// =====================================================

function handleUpdateUser($db, $uploader, $id)
{
    $json = json_decode(file_get_contents("php://input"), true) ?: [];
    $data = array_merge($json, $_POST);

    try {
        $stmt = $db->prepare(
            "SELECT profile_picture, password FROM users WHERE id = ?"
        );

        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            http_response_code(404);
            echo json_encode(["message" => "User not found."]);
            return;
        }

        $profile_picture = $existing['profile_picture'];

        if (isset($_FILES['profile_picture'])) {
            $uploaded = $uploader->uploadSingle($_FILES['profile_picture']);

            if (is_array($uploaded) && isset($uploaded['error'])) {
                http_response_code(400);
                echo json_encode($uploaded);
                return;
            }

            if ($uploaded !== null) {
                $profile_picture = $uploaded;
            }
        }

        $hashed_password = !empty($data['password'])
            ? password_hash($data['password'], PASSWORD_BCRYPT)
            : $existing['password'];

        $query = "UPDATE users SET
                    full_name = COALESCE(:name, full_name),
                    username = COALESCE(:uname, username),
                    email = COALESCE(:email, email),
                    password = :pass,
                    profile_picture = :pic
                  WHERE id = :id";

        $stmt = $db->prepare($query);

        $stmt->execute([
            ":id" => $id,
            ":name" => $data['full_name'] ?? null,
            ":uname" => $data['username'] ?? null,
            ":email" => $data['email'] ?? null,
            ":pass" => $hashed_password,
            ":pic" => $profile_picture
        ]);

        echo json_encode(["message" => "User updated."]);
    } catch (PDOException $e) {
        error_log("Update user error: " . $e->getMessage());

        http_response_code(500);
        echo json_encode(["message" => "Unable to update user."]);
    }
}

// =====================================================
// DELETE USER
// =====================================================

function handleDeleteUser($db, $id)
{
    try {
        $stmt = $db->prepare(
            "SELECT profile_picture FROM users WHERE id = ?"
        );

        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            if (
                $user &&
                !empty($user['profile_picture']) &&
                is_file($user['profile_picture'])
            ) {
                unlink($user['profile_picture']);
            }

            echo json_encode(["message" => "User deleted."]);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "User not found."]);
        }
    } catch (PDOException $e) {
        error_log("Delete user error: " . $e->getMessage());

        http_response_code(500);
        echo json_encode(["message" => "Unable to delete user."]);
    }
}
