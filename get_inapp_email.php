<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'userUtils.php';
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


$id = $params["id"];
$sql = "SELECT id, username, sender, subject, body, seen, time, recipient, canReply, files, image_url, TIME_TO_SEC(TIMEDIFF(NOW(), time)) time_ago FROM emails WHERE username = '$username' AND id < $id ORDER BY id DESC LIMIT 50";
$pdo = getConn();
$res = cast($pdo->query($sql));
echo buildSuccessResponse($res);
$pdo = null;
