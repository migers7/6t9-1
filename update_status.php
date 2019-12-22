<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 9/15/2018
 * Time: 6:46 PM
 */

include_once 'blogUtils.php';
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

echo (new BlogUtils())->updateStatus($username, $params["status"]);
