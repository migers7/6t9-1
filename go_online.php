<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'config.php';
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
$timeStamp = date("Y-m-d H:i:s", time());

$sql = "INSERT INTO online_users(username, lastOnline) VALUES('$username', '$timeStamp') ON DUPLICATE KEY UPDATE lastOnline = '$timeStamp'";
$pdo = ApiHelper::getInstance();
if ($pdo->exec($sql) > 0) {
    $pdo = null;
    echo ApiHelper::buildSuccessResponse(true);
} else {
    $pdo = null;
    echo ApiHelper::buildSuccessResponse(false);
}
