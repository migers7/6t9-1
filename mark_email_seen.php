<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'userUtils.php';
include_once 'db_utils.php';
include_once 'notification_utils.php';
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

$sql = "UPDATE emails SET seen = 1 WHERE id IN ($id)";
$pdo = getConn();
$pdo->exec($sql);

$pdo = null;
echo buildSuccessResponse(true);