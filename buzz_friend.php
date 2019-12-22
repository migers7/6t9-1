<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 2/18/2019
 * Time: 12:08 AM
 */

include_once 'db_utils.php';
include_once 'notification_utils.php';
include_once 'auth_util.php';

$headers = apache_request_headers();

$validationError = AuthUtil::validateApp($headers);
if ($validationError != null) {
    echo $validationError;
    exit(0);
}

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

$to = $params["to"];

$username = $headers["username"];
(new NotificationUtils())->pushTopic($to, "Buzz", $username);
echo buildSuccessResponse("You buzzed $to");