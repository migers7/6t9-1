<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'notification_utils.php';
include_once 'db_utils.php';

$headers = apache_request_headers();
if (!array_key_exists("token", $headers) || !array_key_exists("username", $headers)) {
    echo buildErrorResponse("Unauthorized request.");
    exit(0);
}

$pdo = getConn();
$username = $headers["username"];
$token = $headers["token"];
$sql = "SELECT * FROM users WHERE username = '$username' AND accessToken = '$token'";
if(count(cast($pdo->query($sql))) == 0) {
    echo buildErrorResponse("Unauthorized request.");
    $pdo = null;
    exit(0);
}
$pdo = null;

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

echo (new NotificationUtils())->push($username, $params["title"], $params["message"]);
