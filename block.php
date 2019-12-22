<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'userUtils.php';
include_once 'db_utils.php';
include_once 'room_utils.php';

$headers = apache_request_headers();
if (!array_key_exists("token", $headers) || !array_key_exists("username", $headers)) {
    echo buildErrorResponse("Unauthorized request.");;
    exit(0);
}

$pdo = getConn();
$username = $headers["username"];
$token = $headers["token"];
$sql = "SELECT * FROM users WHERE username = '$username' AND accessToken = '$token' AND isVerified = 1 AND active = 1";
if(count(cast($pdo->query($sql))) == 0) {
    $pdo = null;
    echo buildErrorResponse("Unauthorized request.");;
    exit(0);
}

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

$blocked_name = $params["blocked_name"];

if((new UserUtils())->findUser($blocked_name) == null) {
    echo buildErrorResponse("No user found named $blocked_name");
    $pdo = null;
    exit(0);
}

if($username == $blocked_name) {
    echo buildErrorResponse("You cannot block yourself.");
    $pdo = null;
    exit(0);
}

if((new RoomUtils())->isAdmin($blocked_name)) {
    echo buildErrorResponse("Unable to block user $blocked_name");
    $pdo = null;
    exit(0);
}

$sql = "SELECT * FROM blocked_users WHERE username = '$username' AND blocked_name = '$blocked_name'";
$x = count(cast($pdo->query($sql)));
$sql = "SELECT * FROM blocked_users WHERE username = '$blocked_name' AND blocked_name = '$username'";
$y = count(cast($pdo->query($sql)));
if($x == 0 && $y == 0) {
    $sql = "INSERT INTO blocked_users(username, blocked_name) VALUES('$username', '$blocked_name')";
    $pdo->exec($sql);
}
$pdo = null;

echo buildSuccessResponse($blocked_name, "You have successfully blocked $blocked_name. Type /unblock $blocked_name to unblock.");
