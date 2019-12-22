<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 9/30/2018
 * Time: 12:52 AM
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
$username = $headers["username"];

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);
$hideOffline = "false";
if(isset($params["hide_offline"])) $hideOffline = $params["hide_offline"];

echo (new UserUtils())->getHome($username, (int)$params["version"], $hideOffline);
