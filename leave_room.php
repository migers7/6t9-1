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
$sql = "SELECT * FROM users WHERE username = '$username' AND accessToken = '$token'";
$res = ApiHelper::cast($pdo->query($sql));
$pdo = null;

if (count($res) == 0) {
    echo ApiHelper::buildErrorResponse("Unauthorized request.");
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

$ref = FirebaseManager::getInstance();
$inRoom = (boolean)($pdo->query("SELECT COUNT(*) FROM room_users WHERE username = '$username' AND roomName = '$roomName'")->fetchColumn() > 0);

$pdo = null;

if ($inRoom) {
    $sql = "DELETE FROM room_users WHERE roomName = ? and username = ?";
    $pdo = ApiHelper::getInstance();
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$roomName, $username]);
    $sql = "UPDATE rooms SET nos = nos - 1 WHERE name = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$roomName]);
    $pdo = null;
    $ref->getReference("chats/" . $roomName)->push(MessageHelper::getRoomLeftMessage($username, $roomName));
}
$roomNow = $rooms[0];
if ($roomNow["locked"]) {
    if ($roomNow["lockedBy"] == $username) {
        // unlock
        $pdo = ApiHelper::getInstance();
        $sql = "UPDATE rooms SET locked = 0 WHERE name = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$roomName]);
        $sql = "UPDATE rooms SET lockedBy = ? WHERE name = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(["", $roomName]);
        $pdo = null;

        $roomNow["locked"] = false;
        $res = $roomNow;
        $res["showUnlockMessage"] = true;
        if (!$invisible) $ref->getReference("chats/" . $roomName)->push(MessageHelper::getRoomInfo($roomName, "This chat room has been unlocked because the user who locked it has left."));
        echo ApiHelper::buildSuccessResponse($res, "This chat room has been unlocked because the user who locked it has left.");
        exit(0);
    }
}

$pdo = ApiHelper::getInstance();
$sql = "DELETE FROM last_message WHERE username = '$username' and roomName = '$roomName'";
$pdo->exec($sql);
$pdo = null;
echo ApiHelper::buildSuccessResponse($roomNow);
