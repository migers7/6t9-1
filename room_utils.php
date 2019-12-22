<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'db_utils.php';
include_once "userUtils.php";
include_once 'adminUtils.php';
include_once 'timeUtils.php';
include_once 'gameManager.php';
include_once 'firebaseManager.php';

class RoomUtils
{

    public function __construct()
    {
    }

    public function find($roomName)
    {
        $sql = "SELECT * FROM rooms WHERE name = '$roomName'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        return json_encode($res);
    }

    public function search($roomName, $id)
    {
        $sql = "SELECT * FROM rooms WHERE name LIKE '%$roomName%' and id > $id ORDER BY id ASC LIMIT 20";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        return buildSuccessResponse($res);
    }

    public function findPopularRooms()
    {
        $sql = "SELECT * FROM rooms WHERE nos > 0 ORDER BY RAND() limit 5";
        $pdo = getConn();
        $res = $pdo->query($sql);
        $pdo = null;
        return cast($res);
    }

    public function findGameRooms()
    {
        $sql = "SELECT * FROM rooms WHERE nos > 0 AND owner = 'sixt9' AND  (name LIKE '%lowcard%' OR name LIKE '%game%' OR name LIKE '%cricket%' OR name LIKE '%dice%' OR name LIKE '%bot%') ORDER BY RAND() LIMIT 5";
        $pdo = getConn();
        $res = $pdo->query($sql);
        $pdo = null;
        return cast($res);
    }

    public function getOfficialRooms()
    {
        $sql = "SELECT * FROM rooms WHERE id IN (38, 135, 3099)";
        $pdo = getConn();
        $res = $pdo->query($sql);
        $pdo = null;
        return cast($res);
    }

    public function getGameRooms()
    {
        $sql = "SELECT * FROM rooms WHERE (name LIKE '%game%' OR name LIKE '%lowcard%' OR name LIKE '%dice%') AND nos >= 0 AND owner = 'sixt9' ORDER BY nos limit 5";
        $pdo = getConn();
        $res = $pdo->query($sql);
        $pdo = null;
        return cast($res);
    }

    public function findRecentRooms($user)
    {
        $sql = "SELECT roomName FROM recent_rooms WHERE user = '$user' ORDER BY id DESC LIMIT 5";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        $names = "";
        $f = false;
        for ($i = 0; $i < count($res); $i++) {
            $row = $res[$i];
            if ($f == true) {
                $names = $names . ",";
            }
            $roomName = $row["roomName"];
            $names = $names . "'$roomName'";
            $f = true;
        }
        $pdo = getConn();
        $sql = "SELECT * FROM rooms WHERE name in (" . $names . ")";
        $rooms = $pdo->query($sql);
        $pdo = null;
        return cast($rooms);
    }

    public function findFavoriteRooms($user, $id = 200000000)
    {
        $sql = "SELECT * FROM favorite_rooms WHERE username = '$user' and id < $id ORDER BY id DESC LIMIT 5";

        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        $ids = array();
        $names = "";
        $f = false;
        for ($i = 0; $i < count($res); $i++) {
            $row = $res[$i];
            if ($f == true) {
                $names = $names . ",";
            }
            $roomName = $row["roomName"];
            $names = $names . "'$roomName'";
            $f = true;
            $ids[] = (int)$row["id"];
        }

        sort($ids);

        $sql = "SELECT * FROM rooms WHERE name in (" . $names . ")";
        $pdo = getConn();
        $rooms = cast($pdo->query($sql));
        $pdo = null;
        for ($i = 0; $i < count($rooms); $i++) {
            $rooms[$i]["id"] = $ids[$i];
        }
        return $rooms;
    }

    public function getRooms($user)
    {
        $res = array();
        $res["favorite"] = $this->findFavoriteRooms($user);
        $res["hot"] = $this->findPopularRooms();
        $res["recent"] = $this->findRecentRooms($user);
        $res["official"] = $this->getOfficialRooms();
        $res["game"] = $this->findGameRooms();
        return buildSuccessResponse($res);
    }

    public function isInRoom($user, $room)
    {
        $sql = "SELECT id FROM room_users WHERE username = '$user' and roomName = '$room'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) {
            return true;
        }
        return false;
    }

    public function isModerator($user, $room)
    {
        $sql = "SELECT id FROM modship WHERE username = '$user' and roomName = '$room'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) {
            return true;
        }
        return $this->isAdmin($user);
    }

    public function isRoomAdmin($username, $roomName)
    {
        $sql = "SELECT id FROM rooms WHERE name = '$roomName' and owner = '$username'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) {
            return true;
        }
        return false;
    }

    public function isAdmin($username)
    {
        $sql = "SELECT id FROM users WHERE username = '$username' and (isAdmin = 1 OR isStaff = 1 OR isOwner = 1)";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) {
            return true;
        }
        return false;
    }

    public function isGA($username)
    {
        $sql = "SELECT id FROM users WHERE username = '$username' and isAdmin = 1";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) {
            return true;
        }
        return false;
    }

    public function isStaff($username)
    {
        $sql = "SELECT id FROM users WHERE username = '$username' and isStaff = 1";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) {
            return true;
        }
        return false;
    }

    public function isOwner($username)
    {
        $sql = "SELECT id FROM users WHERE username = '$username' and isOwner = 1";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) {
            return true;
        }
        return false;
    }

    public function getRoom($room)
    {
        $sql = "SELECT * FROM rooms WHERE name = '$room'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        return $res;
    }

    public function getSingleRoom($roomName)
    {
        $res = $this->getRoom($roomName);
        if (count($res) > 0) return $res[0];
        return null;
    }

    public function getUsers($room)
    {
        $sql = "SELECT * FROM room_users WHERE roomName = '$room'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        for ($i = 0; $i < count($res); $i++) {
            $res[$i]["isModerator"] = $this->isModerator($res[$i]["username"], $room) || $this->isRoomAdmin($res[$i]["username"], $room);
        }
        return $res;
    }

    public function getUsersWithToken($room)
    {
        $sql = "SELECT username, token FROM users WHERE username IN (SELECT username FROM room_users WHERE roomName = '$room')";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        return $res;
    }

    public function addToRecentRooms($roomName, $username)
    {
        $pdo = getConn();
        $sql = "DELETE FROM recent_rooms WHERE user = '$username' and roomName = '$roomName'";
        $pdo->exec($sql);
        $sql = "INSERT INTO recent_rooms(user, roomName) VALUES('$username', '$roomName')";
        $pdo->exec($sql);
        $pdo = null;
    }

    public function join($user, $room, $invisible = false)
    {
        $u = (new UserUtils())->findUser($user);
        if ($u["isVerified"] == false) return buildErrorResponse("Unable to join chat room. Your account must be verified.");
        $roomDetails = $this->getRoom($room)[0];
        $locked = $roomDetails["locked"];
        $inRoom = $this->isInRoom($user, $room);
        $showEnterMessage = !$inRoom;
        $isModerator = $this->isModerator($user, $room);
        $isOwner = $roomDetails["owner"] == $user;
        if (!$isModerator && !$isOwner) {

            if ((new AdminUtils())->isBanned($user, $room)) {
                return buildErrorResponse("Unable to join chat room. You are banned from this chat room.");
            }
            $duration = $this->getKickTimeDiff($room, $user);
            $left = 1800 - $duration;
            $left = $left / 60;
            if ($duration < 1800) return buildErrorResponse("Unable to join chat room. You were recently kicked from this chat room. You may rejoin after " . (int)$left . " minutes.");
            if ($roomDetails["capacity"] <= $roomDetails["nos"]) return buildErrorResponse("Unable to join chat room. Chat room is full.");
            if ((int)$u["userLevel"] < (int)$roomDetails["minLevel"]) return buildErrorResponse("Unable to join chat room. Your level must be at least " . $roomDetails["minLevel"]);
        }
        $this->eraseKickInfo($room, $user);
        if ($locked) {
            if (!$isModerator && !$isOwner) return buildErrorResponse("Unable to join chat room. Chat room is locked.");
        }
        if ($invisible) $showEnterMessage = false;
        if ($showEnterMessage) {
            $val = 0;
            if ($isModerator) $val = 1;
            $pdo = getConn();
            $sql = "INSERT INTO room_users(roomName, username, isModerator) VALUES('$room', '$user', $val)";
            $pdo->exec($sql);
            $sql = "UPDATE rooms SET nos = nos + 1 WHERE name = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$room]);
            $pdo = null;
            $ref = FirebaseManager::getInstance();
            $ref->getReference("chats/" . $room)->push(MessageHelper::getRoomEnterMessage($user, $room));
        }
        $res = array();
        $res["isModerator"] = $isModerator;
        $res["showEnterMessage"] = $showEnterMessage;
        $roomInfo = $this->getRoom($room)[0];
        $pdo = getConn();
        $sql = "SELECT * FROM game WHERE roomName = '$room'";
        $gameInfo = cast($pdo->query($sql));
        $pdo = null;
        $running = count($gameInfo) > 0;
        $roomInfo["isGameRunning"] = $running;
        $bot = ucfirst($roomInfo["bot"]);
        if ($running) {
            if ($bot == "Lowcard") {
                $amount = $gameInfo[0]["amount"];
                if ($gameInfo[0]["state"] == 0) $roomInfo["game_state"] = "$bot game is running. !j to join. Cost BDT $amount [40 seconds]";
                else $roomInfo["game_state"] = "$bot is running. The last man standing wins all!";
            } else {
                $roomInfo["game_state"] = "$bot game is running.";
            }
        } else {
            if ($bot != "") {
                if ($bot == "Lowcard") $roomInfo["game_state"] = "Play $bot. Type !start to start a game. Cost BDT 5. For custom entry, type !start <amount>.";
                else $roomInfo["game_state"] = "Play $bot. Type !start to start a new round.";
            }
        }
        $res["roomInfo"] = $roomInfo;
        $res["users"] = $this->getUsers($room);
        $u = (new UserUtils())->findUser($user);
        $res["user"] = $u;

        $color = ColorConstants::$MODERATOR;

        $res["color"] = $color;

        $this->addToRecentRooms($room, $user);
        (new TimeUtils())->hitServer($user, $room);

        return buildSuccessResponse($res);
    }

    public function leaveRoom($room, $user, $invisible = false)
    {
        $ref = FirebaseManager::getInstance();
        if ($this->isInRoom($user, $room)) {
            $pdo = getConn();
            $sql = "DELETE FROM room_users WHERE roomName = ? and username = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$room, $user]);
            $sql = "UPDATE rooms SET nos = nos - 1 WHERE name = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$room]);
            $pdo = null;
            $ref->getReference("chats/" . $room)->push(MessageHelper::getRoomLeftMessage($user, $room));
        }
        $roomNow = $this->getRoom($room)[0];
        if ($roomNow["locked"]) {
            if ($roomNow["lockedBy"] == $user) {
                $this->unlock($room);
                $res = $this->getRoom($room)[0];
                $res["showUnlockMessage"] = true;
                if (!$invisible) $ref->getReference("chats/" . $room)->push(MessageHelper::getRoomInfo($room, "This chat room has been unlocked because the user who locked it has left."));
                return buildSuccessResponse($res, "This chat room has been unlocked because the user who locked it has left.");
            }
        }
        (new TimeUtils())->eraseRecord($user, $room);
        return buildSuccessResponse($this->getRoom($room)[0]);
    }

    public function unlock($roomName)
    {
        $pdo = getConn();
        $sql = "UPDATE rooms SET locked = 0 WHERE name = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$roomName]);
        $sql = "UPDATE rooms SET lockedBy = ? WHERE name = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(["", $roomName]);
        $pdo = null;
    }

    public function getKickTimeDiff($roomName, $username)
    {
        $sql = "SELECT * FROM kicked_users WHERE username = '$username' and roomName = '$roomName'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) {
            $curtime = time();
            $time = strtotime($res[0]["timestamp"]);
            return $curtime - $time;
        }
        return 4000;
    }

    public function eraseKickInfo($roomName, $username)
    {
        $pdo = getConn();
        $pdo->exec("DELETE FROM kicked_users WHERE username = '$username' and roomName = '$roomName'");
        $pdo = null;
    }

    public function getRoomInfo($roomName)
    {
        $room = $this->getRoom($roomName)[0];
        $room["moderators"] = $this->getModeratorList($roomName);
        return buildSuccessResponse($room);
    }

    public function getModeratorList($roomName)
    {
        $moderators = $this->getModerators($roomName);
        $str = "";
        if (count($moderators) > 0) {
            for ($i = 0; $i < count($moderators); $i++) {
                if ($i > 0) $str = $str . ", ";
                $str = $str . $moderators[$i]["username"];
            }
        }
        return $str;
    }

    public function getModerators($roomName)
    {
        $pdo = getConn();
        $sql = "SELECT * FROM modship WHERE roomName = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$roomName]);
        $pdo = null;
        return cast($stmt);
    }

    public function createRoom($details)
    {
        $user = (new UserUtils())->findUser($details["username"]);
        $capacity = 30;
        $level = $user["userLevel"];
        if ($level < 20) return buildErrorResponse("Your level must be at least 20 to create a chat room.");
        if ($level >= 10 && $level < 20) $capacity = 30;
        if ($level >= 20 && $level < 30) $capacity = 40;
        if ($level >= 30 && $level < 40) $capacity = 50;
        if ($level >= 40 && $level < 50) $capacity = 60;
        if ($level >= 50 && $level < 60) $capacity = 70;
        if ($level >= 60 && $level < 70) $capacity = 80;
        if ($level >= 70) $capacity = 100;
        $roomName = trim($details["roomName"]);
        if (count($this->getRoom($roomName)) > 0) return buildErrorResponse("A chat room with this name already exists.");
        $sql = "INSERT INTO rooms(name, owner, description, capacity) VALUES(?, ?, ?, ?)";
        $pdo = getConn();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$roomName, $details["username"], $details["description"], $capacity]);
        $sql = "INSERT INTO favorite_rooms(username, roomName) VALUES(?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$details["username"], $roomName]);
        $pdo = null;
        return buildSuccessResponse($this->getRoom($roomName)[0], $message = "Chat room created successfully.");
    }

    public function isFavorite($username, $roomName)
    {
        $sql = "SELECT id FROM favorite_rooms WHERE username = '$username' and roomName = '$roomName'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) return true;
        return false;
    }

    public function addToFavorite($username, $roomName)
    {
        if ($this->isFavorite($username, $roomName)) return buildErrorResponse("This room is already in your favorite room list.");
        $pdo = getConn();
        $sql = "INSERT INTO favorite_rooms(username, roomName) VALUES(?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $roomName]);
        $pdo = null;
        return buildSuccessResponse("Chat room added to favorite list.");
    }

    public function removeFromFavorite($username, $roomName)
    {
        if ($this->isFavorite($username, $roomName)) {
            $sql = "DELETE FROM favorite_rooms WHERE username = '$username' and roomName = '$roomName'";
            $pdo = getConn();
            $pdo->exec($sql);
            $pdo = null;
            return buildSuccessResponse("Chat room removed from favorite list.");
        }
        return buildErrorResponse("This room is not in your favorite room list.");
    }

    public function isPublicRoom($roomName)
    {
        $sql = "SELECT id FROM rooms WHERE name = '$roomName' AND owner = 'sixt9'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) return true;
        return false;
    }
}

class ColorConstants
{
    public static $USER = "#3F51B5";
    public static $SELF = "#2E7D32";
    public static $ROOM = "#FF5722";
    public static $OWNER = "#F9A825";
    public static $ADMIN = "#F9A825";
    public static $MENTOR = "#f44336";
    public static $MERCHANT = "#9C27B0";
    public static $MODERATOR = "#9E9D24";
    public static $COMMAND = "#C51162";
    public static $GIFT_SHOWER = "#E91E63";
    public static $GAME_BOT = "#84BC23";
    public static $ANNOUNCEMENT = "#8D3028";
}
