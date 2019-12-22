<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
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
$username = $headers["username"];
$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

if(isset($params["id"])) echo buildSuccessResponse((new RoomUtils())->findFavoriteRooms($username, $params["id"]));
else echo buildSuccessResponse((new RoomUtils())->findFavoriteRooms($username));