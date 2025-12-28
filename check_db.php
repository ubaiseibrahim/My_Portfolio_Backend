<?php
header("Content-Type: text/plain");
include_once 'config.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        die("Database connection failed. Check your config.php settings.");
    }

    echo "Database connection successful!\n\n";

    // 1. Check if users table exists
    $table_check = $db->query("SHOW TABLES LIKE 'users'");
    if ($table_check->rowCount() == 0) {
        echo "Creating 'users' table...\n";
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(255) NOT NULL,
            username VARCHAR(100) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            profile_picture VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $db->exec($sql);
        echo "Table 'users' created successfully.\n";
    } else {
        echo "Table 'users' already exists.\n";
    }

    // 2. Check for admin user
    $username = 'ubaiseibrahim';
    $password = 'ubasie@eache';
    $email = 'ubaise@example.com';
    $full_name = 'Ubaise Ibrahim';
    
    $query = "SELECT * FROM users WHERE username = :uname LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([':uname' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    if ($user) {
        echo "User '{$username}' found. Updating password to ensure match...\n";
        $query = "UPDATE users SET password = :pass WHERE username = :uname";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':pass' => $hashed_password,
            ':uname' => $username
        ]);
        echo "Password updated successfully.\n";
    } else {
        echo "User '{$username}' not found. Creating user...\n";
        $query = "INSERT INTO users SET full_name=:name, username=:uname, email=:email, password=:pass, profile_picture=:pic";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ":name" => $full_name,
            ":uname" => $username,
            ":email" => $email,
            ":pass" => $hashed_password,
            ":pic" => "uploads/profiles/default.jpg"
        ]);
        echo "User created successfully.\n";
    }

    echo "\nVerification complete. You should now be able to login with:\n";
    echo "Username: {$username}\n";
    echo "Password: {$password}\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
