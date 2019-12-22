<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 9/9/2018
 * Time: 9:36 PM
 */

include_once 'room_utils.php';
include_once 'db_utils.php';
include_once 'auth_util.php';

$headers = apache_request_headers();

$validationError = AuthUtil::validate($headers);
if ($validationError != null) {
    echo $validationError;
    exit(0);
}

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

echo (new RoomUtils())->search($params["roomName"], $params["id"]);
