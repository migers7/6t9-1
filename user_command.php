<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'commandUtils.php';
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

$private = "false";
if (isset($params["private"])) $private = $params["private"];
if (isset($params["roomName"])) echo (new CommandUtils())->commandRoom($username, $params["command"], $params["roomName"], $private);
else echo (new CommandUtils())->command($username, $params["command"]);
