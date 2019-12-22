<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 4/25/19
 * Time: 1:26 PM
 */

include_once 'config.php';
include_once 'auth_util.php';

$headers = apache_request_headers();

$error = AuthUtil::validate($headers, true);

if($error != null) {
    echo $error;
    exit(0);
}

echo ApiHelper::buildSuccessResponse(AuthUtil::logout($headers["username"]));
