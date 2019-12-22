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
$error = AuthUtil::validateAuth($headers);

if($error != null) {
    echo $error;
}

$username = $headers["username"];

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

echo (new GameManager())->draw($username, $params["roomName"]);
