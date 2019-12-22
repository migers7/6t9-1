<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'config.php';

$headers = apache_request_headers();
if (!array_key_exists("token", $headers) || !array_key_exists("username", $headers)) {
    echo ApiHelper::buildErrorResponse("Unauthorized request.");;
    exit(0);
}

$pdo = ApiHelper::getInstance();
$username = $headers["username"];
$token = $headers["token"];
$sql = "SELECT * FROM users WHERE username = '$username' AND accessToken = '$token' AND isVerified = 1 AND active = 1";
if (count(ApiHelper::cast($pdo->query($sql))) == 0) {
    $pdo = null;
    echo ApiHelper::buildErrorResponse("Unauthorized request.");;
    exit(0);
}

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

$sql = "DELETE FROM online_users WHERE username = '$username'";
if ($pdo->exec($sql)) {
    $pdo = null;
    echo ApiHelper::buildSuccessResponse(true);
} else {
    $pdo = null;
    echo ApiHelper::buildSuccessResponse(false);
}
