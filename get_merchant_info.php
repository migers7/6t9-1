<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 9/9/2018
 * Time: 12:19 PM
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
$token = $headers["token"];
$sql = "SELECT * FROM users WHERE username = '$username' AND accessToken = '$token' AND isVerified = 1 AND active = 1";
$pdo = getConn();
$res = cast($pdo->query($sql));
if (count($res) == 0) {
    echo buildErrorResponse("Unauthorized request.");
    $pdo = null;
    exit(0);
}
if ($res[0]["isMentor"] == false && $res[0]["isMerchant"] == false) {
    echo buildSuccessResponse(null);
    //echo buildErrorResponse("Unauthorized request.");
    $pdo = null;
    exit(0);
}

$merchantSince = strtotime($res[0]["merchantSince"]);
$now = time();
$diff = $now - $merchantSince;
$days = (int)($diff / 60 / 60 / 24);
$daysRemaining = 30 - $days;
if ($daysRemaining < 0) $daysRemaining = 0;

$data = array();
$data["days_remaining"] = $daysRemaining;
$data["merchant_issue_date"] = $res[0]["merchantSince"];
$data["mentor"] = $res[0]["mentor"];
$data["days_passed"] = $days;
$data["revenue"] = $res[0]["revenue"];
$requestCount = 0;
if ($res[0]["isMentor"]) {
    $sql = "SELECT id FROM promotion WHERE mentor = '$username' AND status = 'pending'";
    $requestCount = count(cast($pdo->query($sql)));
}
$data["requestCount"] = $requestCount;

$sql = "SELECT username, dp, merchantSince, isMerchant, isMentor FROM users WHERE mentor = '$username' ORDER BY merchantSince DESC";
$tags = cast($pdo->query($sql));

$data["tags"] = $tags;
echo buildSuccessResponse($data);
$pdo = null;
