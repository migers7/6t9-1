<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 9/23/2018
 * Time: 5:10 PM
 */

include_once 'gameManager.php';
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

/*
if($username != "rex") {
    echo "Game is currently turned off due to some maintenance. Have patience.";
    exit(0);
}*/
$amount = str_replace(",", ".", $params["amount"]);

if (is_numeric($amount) || (int)$amount > 0) {
    echo (new GameManager())->startProcess($params["roomName"], $username, $amount);
} else {
    echo buildErrorResponse("Amount $amount is invalid, please enter valid amount.");
}
