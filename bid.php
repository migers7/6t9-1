<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 9/23/2018
 * Time: 5:10 PM
 */

include_once 'gameManager.php';
include_once 'db_utils.php';
include_once 'notification_utils.php';
include_once 'auth_util.php';

$headers = apache_request_headers();
$error = AuthUtil::validateAuth($headers);

if($error != null) {
    echo $error;
    exit(0);
}

$username = $headers["username"];

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

$amount = str_replace(",", ".", $params["amount"]);

if (is_numeric($amount) || (int)$amount > 0) {
    echo (new DiceManager())->bid($username, $params["roomName"], $params["group"], (double)$params["amount"]);
} else {
    echo buildErrorResponse("Amount $amount is invalid, please enter valid amount.");
}
