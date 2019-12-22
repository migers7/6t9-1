<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 7/7/2019
 * Time: 6:50 PM
 */

include_once 'config.php';
include_once 'auth_util.php';
include_once 'QueryHelper.php';

$headers = apache_request_headers();
$error = AuthUtil::validateAuth($headers);

if ($error != null) {
    echo $error;
    exit(0);
}

sleep(3);

$username = $headers["username"];
$qh = new QueryHelper();
$playing = $qh->rowExists("SELECT id FROM cricket_bot WHERE username = '$username'");
if (!$playing) $playing = $qh->rowExists("SELECT id FROM dice WHERE username = '$username'");
if (!$playing) $playing = $qh->rowExists("SELECT id FROM lowcard_bot WHERE username = '$username'");

$message = "";
$mainEnabled = 1;

if ($playing) {
    $message = "Unable to change this settings while playing games. Please come back later when you are done playing games.";
} else {
    $message = "";
    $json_body = file_get_contents('php://input');
    $params = (array)json_decode($json_body);
    $enabled = $params["spend_from_main_balance"];
    if ($enabled == "true") {
        $mainEnabled = 1;
    } else {
        $mainEnabled = 0;
    }
    $qh->exec("UPDATE users SET spend_from_main_account = $mainEnabled WHERE username = '$username'");
}

$user = $qh->queryOne("SELECT spend_from_main_account FROM users WHERE username = '$username'");
if ($user["spend_from_main_account"] != (boolean)$mainEnabled && $message == "") {
    $message = "No changes made.";
}
$user["message"] = $message;
$qh->close();
echo ApiHelper::buildSuccessResponse($user, $message);