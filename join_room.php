<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'config.php';
include_once 'firebaseManager.php';
include_once 'notification_utils.php';

$headers = apache_request_headers();
if (!array_key_exists("token", $headers) || !array_key_exists("username", $headers)) {
    echo ApiHelper::buildErrorResponse("Unauthorized request.", 801);
    exit(0);
}

$pdo = ApiHelper::getInstance();

$username = $headers["username"];

$token = $headers["token"];
$sql = "SELECT * FROM users WHERE username = '$username' AND accessToken = '$token' AND isVerified = 1 AND active = 1";
$res = ApiHelper::cast($pdo->query($sql));
$pdo = null;

if (count($res) == 0) {
    echo ApiHelper::buildErrorResponse("Unauthorized request.", 801);
    exit(0);
}

if ($res[0]["active"] == 0) {
    echo ApiHelper::buildErrorResponse("Unable to join chat room. Your account has been suspended.");
    exit(0);
}

if (!array_key_exists("device_id", $headers)) {
    echo ApiHelper::buildErrorResponse("Could not verify your request. Please relogin.", 801);
    exit(0);
}

$fcmToken = "";
if (array_key_exists("fcmToken", $headers)) {
    $fcmToken = $headers["fcmToken"];
    $result = (new NotificationUtils())->isValidFCMToken($fcmToken, "verifying..", "verifying token", "no_data");
    if ($result != null) {
        $haha = (array)json_decode($result);
        $info = json_encode($result);
        if ($haha["success"] == 1) {
            $sql = "UPDATE users SET token = '$fcmToken' WHERE  username = '$username'";
            $pdo = ApiHelper::getInstance();
            $pdo->exec($sql);
            $sql = "INSERT INTO fcm(username, token, info, success) VALUES ('$username', '$fcmToken', '$info', 1)
                ON DUPLICATE KEY UPDATE token = '$fcmToken', info = '$info', success = 1";
            $pdo->exec($sql);
            $pdo = null;
        } else {
            echo ApiHelper::buildErrorResponse("Could not verify your request. Please relogin.", 801);
            exit(0);
        }
    } else {
        echo ApiHelper::buildErrorResponse("Could not verify your request. Please relogin.", 801);
        exit(0);
    }
} else {
    echo ApiHelper::buildErrorResponse("Could not verify your request. Please relogin.", 801);
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
    $rp = ApiHelper::cast($pdo->query("SELECT id FROM room_users WHERE username = '$username' AND roomName = '$roomName'"));
    $inRoom = (boolean)(count($rp) > 0);
    $isModerator = false;

    $sql = "SELECT * FROM modship WHERE username = ? and roomName = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $roomName]);
    $res = $stmt->fetchAll();
    if (count($res) > 0) {
        $isModerator = true;
    }

    $isAdmin = $user["isAdmin"] == true || $user["isStaff"] == true || $user["isOwner"] == true;
    if ($isModerator == false) {
        $isModerator = $isModerator || $isAdmin;
    }
    $pdo = null;

    $locked = $roomDetails["locked"];
    $showEnterMessage = !$inRoom;
    $isOwner = $roomDetails["owner"] == $username;

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

        $sql = "SELECT TIME_TO_SEC(TIMEDIFF(NOW(), time_stamp)) time_diff FROM bumped_user WHERE username = '$username' AND roomName = '$roomName'";
        $bump = ApiHelper::queryOne($sql);
        if ($bump != null) {
            $elapsed = $bump["time_diff"];
            if ($elapsed >= 300) {
                ApiHelper::exec("DELETE FROM bumped_user WHERE username = '$username' AND roomName = '$roomName'");
            } else {
                $remaining = 300 - $elapsed;
                $remaining = $remaining / 60;
                $remaining = (int)$remaining;
                if ($remaining < 1) $remaining = 1;
                echo ApiHelper::buildErrorResponse("Unable to join chat room. You were recently bumped from this chat room. You may rejoin after $remaining minutes.");
                exit(0);
            }
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

    $sql = "SELECT DISTINCT roomName FROM room_users WHERE username = '$username'";
    $pdo = ApiHelper::getInstance();
    $count = count(ApiHelper::cast($pdo->query($sql)));
    $pdo = null;
    if ($count >= 5) {
        echo ApiHelper::buildErrorResponse("Unable to join chat room. You have already joined 5 chat rooms.");
        exit(0);
    }

    $deviceId = $headers["device_id"];

    $pdo = ApiHelper::getInstance();
    $pdo->exec("DELETE FROM kicked_users WHERE username = '$username' and roomName = '$roomName'");
    $pdo = null;

    if ($locked) {
        if (!$isModerator && !$isOwner) {
            echo ApiHelper::buildErrorResponse("Unable to join chat room. Chat room is locked.");
            exit(0);
        }
    }
    if ($invisible) {
        if ($isAdmin) $showEnterMessage = false;
    }

    if ($showEnterMessage) {
        $val = 0;
        if ($isModerator) $val = 1;
        $level = $user["userLevel"];
        $sql = "INSERT INTO room_users(roomName, username, isModerator, userLevel, device_id) VALUES('$roomName', '$username', $val, $level, '$deviceId')";
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
        if ($bot == "Lowcard" || $bot == "Cricket") {
            $amount = $gameInfo[0]["amount"];
            if ($gameInfo[0]["state"] == 0) $roomInfo["game_state"] = "$bot game is running. !j to join. Cost BDT $amount [40 seconds]";
            else $roomInfo["game_state"] = "$bot is running. The last man standing wins all!";
        } else {
            $roomInfo["game_state"] = "$bot game is running.";
        }
    } else {
        if ($bot != "") {
            if ($bot == "Lowcard" || $bot == "Cricket") $roomInfo["game_state"] = "Play $bot. Type !start to start a game. Cost BDT 5. For custom entry, type !start < amount >.";
            else $roomInfo["game_state"] = "Play $bot. Type !start to start a new round.";
        }
    }
    $roomInfo["imageShareEnabled"] = $isAdmin || (boolean)$roomInfo["imageShareEnabled"];
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

    $res["users"] = $users;
    $res["user"] = $user;

    $USER = "#3F51B5";
    $SELF = "#2E7D32";
    $ROOM = "#FF5722";
    $OWNER = "#F9A825";
    $ADMIN = "#F9A825";
    $MENTOR = "#f44336";
    $MERCHANT = "#9C27B0";
    $MODERATOR = "#9E9D24";
    $COMMAND = "#C51162";
    $GIFT_SHOWER = "#E91E63";
    $GAME_BOT = "#84BC23";
    $ANNOUNCEMENT = "#8D3028";

    $res["color"] = $MODERATOR;
    $sql = "DELETE FROM recent_rooms WHERE user = '$username' and roomName = '$roomName'";
    $pdo->exec($sql);
    $sql = "INSERT INTO recent_rooms(user, roomName) VALUES('$username', '$roomName')";
    $pdo->exec($sql);

    $id = $username . "#" . $roomName;
    // set timezone to bangladesh
    date_default_timezone_set('Asia/Dhaka');
    $timestamp = date("Y-m-d H:i:s", time());
    $sql = "INSERT INTO last_message(id, username, roomName, time_stamp, fcmToken) VALUES('$id', '$username', '$roomName', '$timestamp', '$fcmToken') ON DUPLICATE KEY UPDATE time_stamp = '$timestamp', fcmToken = '$fcmToken'";
    $pdo->exec($sql);
    $pdo = null;

    echo ApiHelper::buildSuccessResponse($res);

} else {
    echo ApiHelper::buildErrorResponse("No room found for name $roomName");
}

