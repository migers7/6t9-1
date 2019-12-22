<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'config.php';
include_once 'firebaseManager.php';

$headers = apache_request_headers();
if (!array_key_exists("token", $headers) || !array_key_exists("username", $headers)) {
    echo ApiHelper::buildErrorResponse("Unauthorized request.");
    exit(0);
}

$pdo = ApiHelper::getInstance();

$username = $headers["username"];
$token = $headers["token"];
$sql = "SELECT * FROM users WHERE username = '$username' AND accessToken = '$token' AND isVerified = 1";
$res = ApiHelper::cast($pdo->query($sql));
$pdo = null;

if (count($res) == 0) {
    echo ApiHelper::buildErrorResponse("Unauthorized request.");
    exit(0);
}

if ($res[0]["active"] == 0) {
    echo ApiHelper::buildErrorResponse("Unable to join chat room. Your account has been suspended.");
    exit(0);
}

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);
$invisible = false;

if (isset($params["invisible"])) {
    $invisible = (boolean)$params["invisible"];
}

$user = $res[0];
$roomName = $params["roomName"];
$sql = "SELECT * FROM rooms WHERE name = '$roomName'";
$pdo = ApiHelper::getInstance();
$rooms = ApiHelper::cast($pdo->query($sql));
$pdo = null;

if (count($rooms) > 0) {
    $roomDetails = $rooms[0];

    $pdo = ApiHelper::getInstance();
    $inRoom = (boolean)($pdo->query("SELECT COUNT(*) FROM room_users WHERE username = '$username' AND roomName = '$roomName'")->fetchColumn() > 0);
    $isModerator = (boolean)($pdo->query("SELECT COUNT(*) FROM modship WHERE username = '$username' AND roomName = '$roomName'")->fetchColumn() > 0);
    $pdo = null;

    $locked = $roomDetails["locked"];
    $showEnterMessage = !$inRoom;
    $isOwner = $roomDetails["owner"] == $user;

    if (!$isModerator && !$isOwner) {

        $pdo = ApiHelper::getInstance();
        $banned = (boolean)($pdo->query("SELECT COUNT(*) FROM banned_users WHERE username = '$username' and roomName = '$roomName'")->fetchColumn() > 0);
        $pdo = null;
        if ($banned) {
            echo ApiHelper::buildErrorResponse("Unable to join chat room. You are banned from this chat room.");
            exit(0);
        }

        $duration = 4000;
        $sql = "SELECT timestamp FROM kicked_users WHERE username = '$username' and roomName = '$roomName'";
        $pdo = ApiHelper::getInstance();
        $kickInfo = cast($pdo->query($sql));
        $pdo = null;
        if (count($kickInfo) > 0) {
            $curtime = time();
            $time = strtotime($kickInfo[0]["timestamp"]);
            $duration = $curtime - $time;
        }

        $left = 1800 - $duration;
        $left = $left / 60;
        if ($duration < 1800) {
            echo ApiHelper::buildErrorResponse("Unable to join chat room. You were recently kicked from this chat room. You may rejoin after " . (int)$left . " minutes.");
            exit(0);
        }
        if ($roomDetails["capacity"] <= $roomDetails["nos"]) {
            echo ApiHelper::buildErrorResponse("Unable to join chat room. Chat room is full.");
            exit(0);
        }
        if ((int)$user["userLevel"] < (int)$roomDetails["minLevel"]) {
            echo ApiHelper::buildErrorResponse("Unable to join chat room. Your level must be at least " . $roomDetails["minLevel"]);
            exit(0);
        }
    }

    $pdo = ApiHelper::getInstance();
    $pdo->exec("DELETE FROM kicked_users WHERE username = '$username' and roomName = '$roomName'");
    $pdo = null;

    if ($locked) {
        if (!$isModerator && !$isOwner) {
            echo ApiHelper::buildErrorResponse("Unable to join chat room. Chat room is locked.");
            exit(0);
        }
    }
    if ($invisible) $showEnterMessage = false;

    if ($showEnterMessage) {
        $sql = "INSERT INTO room_users(roomName, username, isModerator) VALUES('$roomName', '$username', $isModerator)";
        $pdo = ApiHelper::getInstance();
        $pdo->exec($sql);
        $sql = "UPDATE rooms SET nos = nos + 1 WHERE name = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$roomName]);
        $ref = FirebaseManager::getInstance();
        $ref->getReference("chats/" . $roomName)->push(MessageHelper::getRoomEnterMessage($username, $roomName));
    }
    $res = array();
    $res["isModerator"] = $isModerator;
    $res["showEnterMessage"] = $showEnterMessage;
    $roomInfo = $rooms[0];
    $pdo = ApiHelper::getInstance();
    $sql = "SELECT * FROM game WHERE roomName = '$roomName'";
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


    $sql = "SELECT * FROM room_users WHERE roomName = '$roomName'";
    $pdo = ApiHelper::getInstance();
    $users = ApiHelper::cast($pdo->query($sql));
    for ($i = 0; $i < count($users); $i++) {
        $mod = false;
        $user_name = $users[$i]["username"];
        $mod = $mod || (boolean)($pdo->query("SELECT COUNT(*) FROM modship WHERE username = '$user_name' AND roomName = '$roomName'")->fetchColumn() > 0);
        $mod = $mod || ($roomDetails["owner"] == $user_name);
        if ($mod == false) $mod = $mod || (boolean)($pdo->query("SELECT COUNT(*) FROM users WHERE username = '$user_name' AND (isStaff = 1 OR isAdmin = 1 or isOwner = 1)")->fetchColumn() > 0);
        $users[$i]["isModerator"] = $mod;
    }

    $pdo = null;

    $res["users"] = $users;
    $res["user"] = $user;

    $color = ColorConstants::$USER;
    if ($isModerator) $color = ColorConstants::$MODERATOR;
    if ($isOwner) $color = ColorConstants::$OWNER;
    if ($user["isMerchant"] == true) $color = ColorConstants::$MERCHANT;
    if ($user["isMentor"] == true) $color = ColorConstants::$MENTOR;
    if ($user["isAdmin"] == true) $color = ColorConstants::$ADMIN;
    if ($user["isStaff"] == true) $color = ColorConstants::$ADMIN;
    if ($user["isOwner"] == true) $color = ColorConstants::$ADMIN;

    $res["color"] = $color;

    $pdo = ApiHelper::getInstance();
    $sql = "DELETE FROM recent_rooms WHERE user = '$username' and roomName = '$roomName'";
    $pdo->exec($sql);
    $sql = "INSERT INTO recent_rooms(user, roomName) VALUES('$username', '$roomName')";
    $pdo->exec($sql);

    $timestamp = date("Y-m-d H:i:s", time());
    $sql = "INSERT INTO last_message(username, roomName, timestamp) VALUES('$username', '$roomName', '$timestamp')";
    $alreadyHit = (boolean)($pdo->query("SELECT COUNT(*) FROM last_message WHERE username = '$username' and roomName = '$roomName'")->fetchColumn() > 0);
    if ($alreadyHit) {
        $sql = "UPDATE last_message SET timestamp = '$timestamp' WHERE username = '$username' and roomName = '$roomName'";
    }
    $pdo->exec($sql);
    $pdo = null;

    echo ApiHelper::buildSuccessResponse($res);

} else {
    echo ApiHelper::buildErrorResponse("No room found for name $roomName");
}

