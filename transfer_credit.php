<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */


include_once 'account_utils.php';
include_once 'db_utils.php';
include_once 'auth_util.php';

$headers = apache_request_headers();

$validationError = AuthUtil::validate($headers);
if ($validationError != null) {
    echo $validationError;
    exit(0);
}

$pdo = getConn();
$username = $headers["username"];
$token = $headers["token"];
$sql = "SELECT * FROM users WHERE username = '$username' AND accessToken = '$token' AND isVerified = 1 AND active = 1";
$res = cast($pdo->query($sql));
if (count($res) == 0) {
    echo buildErrorResponse("Unauthorized request.", 801);
    $pdo = null;
    exit(0);
}

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

$pdo = null;
if ($res[0]["userLevel"] < 15 && $res[0]["isMentor"] == false && $res[0]["isMerchant"] == false) {
    echo buildErrorResponse("Your level must be at least 15 to transfer credits.");
    exit(0);
}
$pin = "ab";
$tag= "false";
if(isset($params["tag"])) $tag= $params["tag"];
if(isset($params["pin"])) $pin = $params["pin"];
echo (new AccountUtils())->transferCredit($username, $params["to"], $params["amount"], $pin, $tag);
