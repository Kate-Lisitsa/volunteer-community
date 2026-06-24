<?php
// includes/db_connect.php
require_once 'config.php';

class Database {
    private static $instance = null;
    private $connection;
    private $lastError = null;
    
    private function __construct() {
        $connectionOptions = array(
            "Database" => DB_NAME,
            "Uid" => DB_USER,
            "PWD" => DB_PASS,
            "CharacterSet" => "UTF-8",
            "TrustServerCertificate" => true,
            "LoginTimeout" => 30
        );
        
        $this->connection = sqlsrv_connect(DB_HOST, $connectionOptions);
        
        if ($this->connection === false) {
            die("Ошибка подключения: " . print_r(sqlsrv_errors(), true));
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->tryQuery($sql, $params);
        if ($stmt === false) {
            die("Ошибка запроса: " . print_r($this->lastError, true));
        }
        return $stmt;
    }

    public function tryQuery($sql, $params = []) {
        $this->lastError = null;
        $stmt = sqlsrv_query($this->connection, $sql, $params);
        if ($stmt === false) {
            $this->lastError = sqlsrv_errors(SQLSRV_ERR_ALL);
        }
        return $stmt;
    }

    public function getLastError() {
        return $this->lastError;
    }
    
    public function fetchOne($stmt) {
        return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    }
    
    public function fetchAll($stmt) {
        $rows = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $rows[] = $row;
        }
        return $rows;
    }
    
    public function lastInsertId() {
        $stmt = $this->tryQuery('SELECT CAST(SCOPE_IDENTITY() AS BIGINT) AS LastId');
        if ($stmt === false) {
            return null;
        }
        $row = $this->fetchOne($stmt);
        if (!$row) {
            return null;
        }
        $val = $row['LastId'] ?? reset($row);
        if ($val === null || $val === false || $val === '') {
            return null;
        }
        return (int)$val;
    }
}

function db() {
    return Database::getInstance()->getConnection();
}
?>