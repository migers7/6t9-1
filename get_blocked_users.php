<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 7/7/2019
 * Time: 4:58 PM
 */

include_once 'config.php';
include_once 'auth_util.php';
include_once 'QueryHelper.php';

$headers = apache_request_headers();
$error = AuthUtil::validateAuth($headers);

if($error != null) {
    echo $error;
    exit(0);
}

$username = $headers["username"];

$qh = new QueryHelper();
$blocked_users = $qh->query("SELECT username, dp FROM users WHERE username IN (SELECT blocked_name FROM blocked_users WHERE username = '$username')");
$qh->close();
echo ApiHelper::buildSuccessResponse($blocked_users);