<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 10/14/2018
 * Time: 2:28 AM
 */

include_once 'gameManager.php';
include_once 'db_utils.php';
include_once 'auth_util.php';

$headers = apache_request_headers();

$validationError = AuthUtil::validate($headers);
if ($validationError != null) {
    echo $validationError;
    exit(0);
}
$username = $headers["username"];

$pdo = null;
$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

echo (new GameManager())->join($username, $params["roomName"]);
