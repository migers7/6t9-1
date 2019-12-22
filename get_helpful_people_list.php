<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 5/20/19
 * Time: 3:04 PM
 */

include_once 'config.php';

/*$headers = apache_request_headers();
$error = AuthUtil::validateAuth($headers);

if ($error != null) {
    echo $error;
    exit(0);
}*/

$users = array();

$sql = "SELECT username, country, userLevel, gender, dp FROM users WHERE isMentor = 1 ORDER BY username";
$pdo = ApiHelper::getInstance();
$users["mentors"] = ApiHelper::cast($pdo->query($sql));

$sql = "SELECT username, country, userLevel, gender, dp FROM users WHERE isMerchant = 1 AND active = 1 ORDER BY username";
$users["merchants"] = ApiHelper::cast($pdo->query($sql));

$sql = "SELECT username, country, userLevel, gender, dp FROM users WHERE isStaff = 1 ORDER BY username";
$users["staffs"] = ApiHelper::cast($pdo->query($sql));

$sql = "SELECT username, country, userLevel, gender, dp FROM users WHERE isAdmin = 1 AND username IN ('joe', 'crystal', 'baby', 'heart-beat') ORDER BY username";
$users["admins"] = ApiHelper::cast($pdo->query($sql));

$users["merchantColor"] = "#9C27B0";
$users["mentorColor"] = "#f44336";
$users["adminColor"] = "#F9A825";
$users["staffColor"] = "#EF6C00";

echo ApiHelper::buildSuccessResponse($users);
