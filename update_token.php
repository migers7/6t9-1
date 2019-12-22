<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'notification_utils.php';

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

echo (new NotificationUtils())->updateToken($params["username"], $params["token"]);