<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 9/16/2018
 * Time: 12:00 AM
 */

include_once 'config.php';
include_once 'notification_utils.php';
include_once 'firebaseManager.php';
include_once 'QueryHelper.php';

$currTime = time();
$sql = "SELECT username, roomName, fcmToken FROM last_message T WHERE TIMESTAMPDIFF(MINUTE,T.time_stamp,NOW()) > 20";
//$sql = "SELECT username, roomName, fcmToken FROM last_message";
$queryHelper = new QueryHelper();
$notificationManager = new NotificationUtils();
$ref = FirebaseManager::getInstance();
$rows = $queryHelper->query($sql);
$queryHelper->exec("DELETE FROM last_message WHERE TIMESTAMPDIFF(MINUTE, time_stamp, NOW()) > 20");
//$queryHelper->exec("DELETE FROM last_message");
$info = array();

$sql = "SELECT username FROM users WHERE isStaff = 1 OR isAdmin = 1 OR isOwner = 1";
$adminsList = $queryHelper->query($sql);
$admins = array();
foreach ($adminsList as $row) {
    $admins[] = $row["username"];
}

foreach ($rows as $row) {
    $username = $row["username"];
    $roomName = $row["roomName"];
    $token = $row["fcmToken"];

    if (in_array($username, $admins)) continue;
 //   if($username == "heart-beat") continue;

    if (!array_key_exists($roomName, $info)) {
        $info[$roomName] = array();
    }

    $info[$roomName][] = ["username" => $username, "token" => $token];
}

foreach ($info as $roomName => $values) {
    $sql = "DELETE FROM room_users WHERE roomName = '$roomName' AND username IN (";
    $nos = count($values);
    for ($i = 0; $i < $nos; $i++) {
        $username = $values[$i]["username"];
        $token = $values[$i]["token"];
        $messageText = "You are not in $roomName chat room";
        $notificationManager->pushWithToken($username, $token, "Auto left", $roomName, $messageText);
        $ref->getReference("chats/" . $roomName)->push(MessageHelper::getRoomLeftMessage($username, $roomName));

        if ($i > 0) {
            $sql .= ", ";
        }
        $sql .= "'$username'";
    }
    $sql .= ")";
    $queryHelper->exec($sql);
    $queryHelper->exec("UPDATE rooms SET nos = nos - $nos WHERE name = '$roomName'");
}

$queryHelper->close();
