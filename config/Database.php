<?php
class Database
{
    private string $servername = "localhost";
    private string $username   = "root";
    private string $password   = "";
    private string $dbname     = "rotaract_kwanza";
    public mysqli $conn;

    public function connect(): mysqli
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $this->conn = new mysqli($this->servername, $this->username, $this->password, $this->dbname);
            $this->conn->set_charset('utf8mb4');
        } catch (mysqli_sql_exception $e) {
            error_log("DB connection failed: " . $e->getMessage());
            die("A database error occurred. Please try again later.");
        }
        return $this->conn;
    }
}
