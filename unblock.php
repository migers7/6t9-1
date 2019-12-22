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

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

$username = $headers["username"];
$blocked_name = $params["blocked_name"];

if ((new UserUtils())->findUser($blocked_name) == null) {
    $pdo = null;
    echo buildErrorResponse("No user found named $blocked_name");;
    exit(0);
}

$sql = "DELETE FROM blocked_users WHERE username = '$username' AND blocked_name = '$blocked_name'";
$pdo = getConn();
$pdo->exec($sql);
$pdo = null;

echo buildSuccessResponse($blocked_name, "You have successfully unblocked $blocked_name.");
