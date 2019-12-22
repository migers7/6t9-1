<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

class Database {
    private $connection;
    private $host = "localhost";
    private $dbName = "6t9";
    private $dbUser = "admin6t9";
    private $dbPassword = "admin6t9";

    public function __construct() {
        try {
            $this->connection = mysqli_connect($this->host, $this->dbUser, $this->dbPassword, $this->dbName);
        } catch (Exception $e) {
            echo $e;
        }
    }

    public function getConnection() {
        return $this->connection;
    }
}
