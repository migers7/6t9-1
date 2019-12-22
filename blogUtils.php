<?php

/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 9/15/2018
 * Time: 6:37 PM
 */
include_once 'db_utils.php';

class BlogUtils
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = getConn();
    }

    public function getStatus($username)
    {
        $sql = "SELECT * FROM blog WHERE username = '$username' ORDER BY id DESC LIMIT 1";
        $res = cast($this->pdo->query($sql));
        if (count($res)) return $res[0];
        return null;
    }

    public function updateStatus($username, $status)
    {
        $sql = "INSERT INTO blog(username, status) VALUES('$username', '$status')";
        $this->pdo->exec($sql);
        return buildSuccessResponse($this->getStatus($username), "Your status updated successfully.");
    }

    public function getBlog($username)
    {
        $sql = "SELECT * FROM blog WHERE username = '$username' ORDER BY id DESC LIMIT 50";
        return cast($this->pdo->query($sql));
    }
}