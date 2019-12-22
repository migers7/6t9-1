<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 12/10/18
 * Time: 8:14 PM
 */

require_once 'db_utils.php';
require_once 'notification_utils.php';
require_once 'userUtils.php';
include_once 'auth_util.php';

$headers = apache_request_headers();

$validationError = AuthUtil::validate($headers);
if ($validationError != null) {
    echo $validationError;
    exit(0);
}
$username = $headers["username"];

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

$to = $params["to"];
$message = $params["message"];

$uu = new UserUtils();
$user = $uu->findUser($username);
if ($user != null) {
    $color = "#3F51B5";
    if ($user["isMentor"] === true || $user["isMerchant"] === true) $color = $user["color"];
    if ($user["isAdmin"] === true || $user["isStaff"] === true || $user["isOwner"] === true) $color = "#F9A825";
    $nu = new NotificationUtils();
    $text = json_encode([
        "id" => generateVerificationCode(24),
        "sender" => $username,
        "from" => $username,
        "text" => $message,
        "color" => $color,
        "type" => 2,
        "time" => time() . "",
        "key" => null,
        "privateChat" => true
    ]);
    $nu->push($to, "Private Chat", "You have a new message", $text);
    echo buildSuccessResponse($text);
} else {
    echo buildErrorResponse("Failed to send message");
}
