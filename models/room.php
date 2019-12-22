<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once '../config/database.php';
include_once '../config/utils.php';
include_once '../models/user.php';

class Room
{
    private $tableName = "rooms";
    private $conn;

    public $name;
    public $owner;
    public $capacity;
    public $announcement;

    public function __construct()
    {
        $this->conn = (new Database())->getConnection();
    }

    public function insert()
    {
        $sql = "insert into"
            . $this->tableName
            . "(name, owner, capacity) values("
            . "'$this->name',"
            . "'$this->owner',"
            . "'$this->capacity');";

        if ($this->conn->query($sql) > 0) {
            return buildSuccessResponse($this, "Room created successfully.");
        } else {
            return buildErrorResponse("Failed to create room. Please try again.");
        }
    }

    public function mod($username)
    {
        $user = new User();
        $user->username = $username;
        if ($user->userExists()) {
            if($this->isModerator($username)) {
                return buildErrorResponse($username . " is already a moderator of this room.");
            }
            $sql = "insert into modship(roomName, username) values('$this->name','$username');";
            if ($this->conn->query($sql) > 0) {
                return buildSuccessResponse(["roomName" => $this->name, "username" => $username], $username . " is now a moderator of this room");
            } else {
                return buildErrorResponse("Failed. Please try again.");
            }
        } else {
            return buildErrorResponse("No user found for username " . $username);
        }
    }

    public function demod($username)
    {
        $user = new User();
        $user->username = $username;
        if ($user->userExists()) {
            if($this->isModerator($username)) {
                $sql = "delete from modship where roomName = '$this->name' and username = '$username';";
                if ($this->conn->query($sql) > 0) {
                    return buildSuccessResponse(["roomName" => $this->name, "username" => $username], $username . " is no longer a moderator of this room");
                } else {
                    return buildErrorResponse("Failed. Please try again.");
                }
            } else {
                return buildErrorResponse($username . " is not a moderator of this chat room.");
            }
        } else {
            return buildErrorResponse("No user found for username " . $username);
        }
    }

    public function isModerator($username) {
        $sql = "select * from modship where roomName = '$this->name' and username = '$username';";
        $rows = $this->conn->query($sql);
        if ($rows->num_rows > 0) return true;
        return false;
    }

    public function get($username)
    {
        $sql = "select * from " . $this->tableName . " where name = '$this->name';";
        $res = $this->conn->query($sql)->fetch_assoc();
        if ($res != null) {
            $res["isModerator"] = $this->isModerator($username);
            return buildSuccessResponse($res);
        }
        return buildErrorResponse("No room found for name " . $this->name);
    }

    public function changeAnnouncement($announcement, $username)
    {
        $sql = "update " . $this->tableName . " set announcement = '$announcement';";
        if ($this->conn->query($sql) > 0) {
            $sql = "select * from " . $this->tableName . " where name = '$this->name';";
            $res = $this->conn->query($sql)->fetch_assoc();
            $res["isModerator"] = $this->isModerator($username);
            return buildSuccessResponse($res, "Announcement has been updated.");
        } else {
            return buildErrorResponse("Failed to update announcement.");
        }
    }

    public function turnOfAnnouncement($username)
    {
        $sql = "update " . $this->tableName . " set announcement = '';";
        if ($this->conn->query($sql) > 0) {
            $sql = "select * from " . $this->tableName . " where name = '$this->name';";
            $res = $this->conn->query($sql)->fetch_assoc();
            $res["isModerator"] = $this->isModerator($username);
            return buildSuccessResponse($res, "Announcement has been turned off.");
        } else {
            return buildErrorResponse("Failed to turn announcement off.");
        }
    }
}