<?php

// Database object using PDO

class Database
{
    // local development
    private $host = "localhost";
    private $dbname = "quantumOne";
    private $username = "root";
    private $password = "root";

    // live server
//    private $host = "localhost";
//    private $dbname = "quantumOne";
//    private $username = "root";
//    private $password = "";

    private $error;
    private $stmt;

    // Connection variable
    public $conn;

    // Return db connection
    public function openConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->dbname, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch(PDOException $err) {
            echo $this->error = $err->getMessage();
        }
        return $this->conn;
    }
}