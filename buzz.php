<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 2/18/2019
 * Time: 12:08 AM
 */

include_once 'db_utils.php';
include_once 'notification_utils.php';

$headers = apache_request_headers();
if (!array_key_exists("token", $headers) || !array_key_exists("username", $headers)) {
    echo buildErrorResponse("Unauthorized request.");
    exit(0);
}

$pdo = getConn();
$username = $headers["username"];
$token = $headers["token"];
$sql = "SELECT * FROM users WHERE username = '$username' AND accessToken = '$token' AND isVerified = 1 AND active = 1";
$res = cast($pdo->query($sql));
if (count($res) == 0) {
    echo buildErrorResponse("Unauthorized request.");
    $pdo = null;
    exit(0);
}

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

$to = $params["to"];

(new NotificationUtils())->push($to, "Buzz", $username);
echo buildSuccessResponse("You buzzed $to");