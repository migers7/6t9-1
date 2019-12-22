<?php

/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 9/15/2018
 * Time: 11:52 PM
 */
include_once 'db_utils.php';

class TimeUtils
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = getConn();
    }

    public function hitServer($username, $roomName)
    {
        $timestamp = date("Y-m-d H:i:s", time());
        $sql = "INSERT INTO last_message(username, roomName, timestamp) VALUES('$username', '$roomName', '$timestamp')";
        if ($this->alreadyHit($username, $roomName)) {
            $sql = "UPDATE last_message SET timestamp = '$timestamp' WHERE username = '$username' and roomName = '$roomName'";
        }
        $this->pdo->exec($sql);
    }

    public function eraseRecord($username, $roomName)
    {
        $sql = "DELETE FROM last_message WHERE username = '$username' and roomName = '$roomName'";
        $this->pdo->exec($sql);
    }

    public function alreadyHit($username, $roomName)
    {
        $sql = "SELECT * FROM last_message WHERE username = '$username' and roomName = '$roomName'";
        $res = cast($this->pdo->query($sql));
        if (count($res) > 0) return true;
        return false;
    }

    public function getAllRecords()
    {
        $sql = "SELECT * FROM last_message";
        return cast($this->pdo->query($sql));
    }
}