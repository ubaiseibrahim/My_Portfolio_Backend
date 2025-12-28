<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$database = new Database();

class FileUploader {
    private $target_dir = "uploads/projects/";
    private $allowed_types = array("jpg", "jpeg", "png", "gif");

    private $max_size = 5242880; // 5MB

    public function uploadSingle($file, $sub_dir = "") {
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) return null;
        
        $target_path = $this->target_dir . ($sub_dir ? $sub_dir . "/" : "");
        if (!file_exists($target_path)) mkdir($target_path, 0777, true);

        $file_name = time() . "_" . basename($file["name"]);
        $target_file = $target_path . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        if (!in_array($file_type, $this->allowed_types)) return array("error" => "Invalid file type.");
        if ($file["size"] > $this->max_size) return array("error" => "File too large.");

        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            return $this->target_dir . ($sub_dir ? $sub_dir . "/" : "") . $file_name;
        }
        return array("error" => "Upload failed.");
    }

    public function uploadMultiple($files, $sub_dir = "") {
        $uploaded_paths = array();
        if (!isset($files) || !is_array($files['name'])) return array();
        foreach ($files['name'] as $key => $name) {
            $temp_file = array(
                "name" => $files["name"][$key], "type" => $files["type"][$key],
                "tmp_name" => $files["tmp_name"][$key], "error" => $files["error"][$key], "size" => $files["size"][$key]
            );
            $result = $this->uploadSingle($temp_file, $sub_dir);
            if (is_string($result)) $uploaded_paths[] = $result;
        }
        return $uploaded_paths;
    }
}

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
    case 'post':
        handleCreate($db, $uploader);
        break;
    case 'get':
        if ($id === 'active') {
            handleGetActive($db);
        } elseif ($id) {
            handleGetById($db, $id);
        } else {
            handleGetAll($db);
        }
        break;
    case 'put':
        if ($id) {
            handleUpdate($db, $uploader, $id);
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "ID required for update."));
        }
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


function handleCreate($db, $uploader) {
    $data = array_merge(json_decode(file_get_contents("php://input"), true) ?: [], $_POST);
    if (empty($data['project_name'])) {

        http_response_code(400);
        echo json_encode(array("message" => "Project name is required."));
        return;
    }

    $featured_image = $uploader->uploadSingle($_FILES['featured_image'] ?? null, "featured");
    if (is_array($featured_image) && isset($featured_image['error'])) {
        http_response_code(400);
        echo json_encode($featured_image);
        return;
    }

    $gallery_images = $uploader->uploadMultiple($_FILES['gallery_images'] ?? null, "gallery");

    
    $query = "INSERT INTO projects SET project_name=:name, display_order=:order, project_url=:url, 
              short_description=:short, description=:desc, technologies=:tech, 
              featured_image=:fimg, gallery_images=:gimg, status=:status, 
              start_date=:start, end_date=:end";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ":name" => htmlspecialchars(strip_tags($data['project_name'])),
        ":order" => (int)($data['display_order'] ?? 0),
        ":url" => $data['project_url'] ?? null,
        ":short" => htmlspecialchars(strip_tags($data['short_description'] ?? '')),
        ":desc" => $data['description'] ?? null,
        ":tech" => $data['technologies'] ?? null,
        ":fimg" => $featured_image,
        ":gimg" => json_encode($gallery_images),
        ":status" => $data['status'] ?? 'Active',
        ":start" => $data['start_date'] ?? null,
        ":end" => $data['end_date'] ?? null
    ]);

    http_response_code(201);
    echo json_encode(array("message" => "Project created.", "id" => $db->lastInsertId()));
}

function handleGetAll($db) {
    $stmt = $db->prepare("SELECT * FROM projects ORDER BY created_at DESC");
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($projects as &$p) $p['gallery_images'] = json_decode($p['gallery_images']);
    echo json_encode($projects);
}

function handleGetActive($db) {
    $stmt = $db->prepare("SELECT * FROM projects WHERE status = 'Active' ORDER BY display_order ASC");
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($projects as &$p) $p['gallery_images'] = json_decode($p['gallery_images']);
    echo json_encode($projects);
}

function handleGetById($db, $id) {
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($project) {
        $project['gallery_images'] = json_decode($project['gallery_images']);
        echo json_encode($project);
    } else {
        http_response_code(404);
        echo json_encode(array("message" => "Not found."));
    }
}

function handleUpdate($db, $uploader, $id) {
    $data = array_merge(json_decode(file_get_contents("php://input"), true) ?: [], $_POST);
    $stmt = $db->prepare("SELECT featured_image, gallery_images FROM projects WHERE id = ?");

    $stmt->execute([$id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existing) {
        http_response_code(404);
        echo json_encode(array("message" => "Not found."));
        return;
    }

    $f_img = $uploader->uploadSingle($_FILES['featured_image'] ?? null, "featured") ?: $existing['featured_image'];
    $g_imgs = json_decode($existing['gallery_images'], true) ?: [];
    $new_g_imgs = $uploader->uploadMultiple($_FILES['gallery_images'] ?? null, "gallery");
    $g_imgs = array_merge($g_imgs, $new_g_imgs);


    $query = "UPDATE projects SET project_name=COALESCE(:name, project_name), 
              display_order=COALESCE(:order, display_order), project_url=COALESCE(:url, project_url), 
              short_description=COALESCE(:short, short_description), description=COALESCE(:desc, description), 
              technologies=COALESCE(:tech, technologies), featured_image=:fimg, gallery_images=:gimg, 
              status=COALESCE(:status, status), start_date=COALESCE(:start, start_date), end_date=COALESCE(:end, end_date) 
              WHERE id=:id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ":id" => $id,

        ":name" => $data['project_name'] ?? null,
        ":order" => isset($data['display_order']) ? (int)$data['display_order'] : null,
        ":url" => $data['project_url'] ?? null,
        ":short" => isset($data['short_description']) ? htmlspecialchars(strip_tags($data['short_description'])) : null,
        ":desc" => $data['description'] ?? null,
        ":tech" => $data['technologies'] ?? null,
        ":fimg" => $f_img,
        ":gimg" => json_encode($g_imgs),
        ":status" => $data['status'] ?? null,
        ":start" => $data['start_date'] ?? null,
        ":end" => $data['end_date'] ?? null
    ]);

    echo json_encode(array("message" => "Project updated."));
}

function handleDelete($db, $id) {
    $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
    if ($stmt->execute([$id])) {
        echo json_encode(array("message" => "Project deleted."));
    } else {
        http_response_code(500);
        echo json_encode(array("message" => "Delete failed."));
    }
}

?>
