<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 6/22/2019
 * Time: 3:13 PM
 */

include_once 'config.php';
include_once 'auth_util.php';
include_once 'notification_utils.php';
include_once 'QueryHelper.php';

$headers = apache_request_headers();

if (AuthUtil::isValidApp($headers) == false || array_key_exists("username", $headers) == false || array_key_exists("token", $headers) == false
    || array_key_exists("fcmToken", $headers) == false) {
    echo ApiHelper::buildErrorResponse("Could not verify your request. Either the app you are using is illegal or the request has missing arguments.", 801);
    exit(0);
}

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

if (isset($params["roomName"]) == false) {
    echo ApiHelper::buildErrorResponse("Invalid request.");
    exit(0);
}

$roomName = $params["roomName"];
$username = $headers["username"];
$accessToken = $headers["token"];
$token = $headers["fcmToken"];

$user = ApiHelper::queryOne("SELECT userLevel, token, isAdmin, isStaff, isOwner FROM users WHERE username = '$username' AND accessToken = '$accessToken'");

if ($user == null) {
    echo ApiHelper::buildErrorResponse("Invalid request.", 801);
    exit(0);
}

$hasSuperAuth = $user["isAdmin"] || $user["isStaff"] || $user["isOwner"];

if (!$hasSuperAuth) {
    if (ApiHelper::rowExists("SELECT id FROM room_users WHERE username = '$username' AND roomName = '$roomName'") == false) {
        $token = $user["token"];
        $messageText = "You are not in $roomName chat room";
        (new NotificationUtils())->pushWithToken($username, $token, "Auto left", $roomName, $messageText);
        echo ApiHelper::buildErrorResponse($messageText);
        exit(0);
    }
}

$id = $username . "#" . $roomName;
// set timezone to bangladesh
date_default_timezone_set('Asia/Dhaka');
$timestamp = date("Y-m-d H:i:s", time());
$queryHelper = new QueryHelper();

$sql = "INSERT INTO last_message(id, time_stamp, username, roomName, fcmToken) VALUES('$id', '$timestamp', '$username', '$roomName', '$token') ON DUPLICATE KEY UPDATE time_stamp = '$timestamp', fcmToken = '$token'";
$updated = $queryHelper->exec($sql);

$sql = "UPDATE users SET creditSpent = creditSpent + 5 WHERE username = '$username'";
$queryHelper->exec($sql);

$queryHelper->close();

echo ApiHelper::buildSuccessResponse("success");