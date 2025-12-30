<?php
class Database {
    private $host = "localhost";
    private $db_name = "ubaise_ibrahim";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }
}


// <?php
// class Database {
//     private $host = "sql107.infinityfree.com";
//     private $db_name = "if0_40790704_ubaise_ibrahim";
//     private $username = "if0_40790704";
//     private $password = "Q7yOoBA9xUl5I";
//     public $conn;

//     public function getConnection() {
//         $this->conn = null;
//         try {
//             $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
//             $this->conn->exec("set names utf8");
//             $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//         } catch(PDOException $exception) {
//             error_log("Connection error: " . $exception->getMessage());
//         }
//         return $this->conn;
//     }
// }
