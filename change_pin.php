<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 2/2/2019
 * Time: 3:04 PM
 */


include_once 'userUtils.php';
include_once 'db_utils.php';

$headers = apache_request_headers();
if (!array_key_exists("token", $headers) || !array_key_exists("username", $headers)) {
    echo buildErrorResponse("Unauthorized request.");;
    exit(0);
}

$pdo = getConn();
$username = $headers["username"];
$token = $headers["token"];
$sql = "SELECT * FROM users WHERE username = '$username' AND accessToken = '$token' AND isVerified = 1 AND active = 1";
if (count(cast($pdo->query($sql))) == 0) {
    echo buildErrorResponse("Unauthorized request.");
    $pdo = null;
    exit(0);
}
$pdo = null;

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

if (isset($params["oldPin"])) {
    echo (new UserUtils())->changePin($username, $params["pin"], $params["oldPin"]);
} else {
    echo (new UserUtils())->changePin($username, $params["pin"]);
}
