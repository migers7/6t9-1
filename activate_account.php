<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'db_utils.php';
include_once 'auth_util.php';

$headers = apache_request_headers();

$validationError = AuthUtil::validateApp($headers);
if ($validationError != null) {
    echo $validationError;
    exit(0);
}
$username = $headers["username"];

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

if (!isset($params["code"])) {
    $pdo = null;
    echo buildErrorResponse("No verification code was provided");
    exit(0);
}

$code = $params["code"];
$sql = "SELECT id FROM verification_codes WHERE username = '$username' AND code = '$code'";
$pdo = getConn();
$codes = cast($pdo->query($sql));

if (count($codes) > 0) {
    $sql = "update users set isVerified = 1 where username = '$username';";
    $pdo->exec($sql);
    $sql = "DELETE FROM verification_codes WHERE username = '$username'";
    $pdo->exec($sql);
    $sql = "SELECT * FROM users WHERE username = '$username'";
    $res = cast($pdo->query($sql));
    $pdo = null;
    echo buildSuccessResponse($res[0], "Account activated successfully.");
} else {
    $pdo = null;
    echo buildErrorResponse("Invalid code");
    exit(0);
}
