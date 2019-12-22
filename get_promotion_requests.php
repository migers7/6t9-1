<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 5/26/2019
 * Time: 5:26 PM
 */

include_once 'auth_util.php';
include_once 'config.php';

$headers = apache_request_headers();

$validationError = AuthUtil::validate($headers);
if ($validationError != null) {
    echo $validationError;
    exit(0);
}
$username = $headers["username"];

$sql = "SELECT isStaff, isMentor FROM users WHERE username = '$username'";
$user = ApiHelper::queryOne($sql);

if ($user == null) {
    echo ApiHelper::buildErrorResponse("Something went wrong. Please try again later");
    exit(0);
}

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);
$forStaff = false;
if (isset($params["for"])) {
    $forStaff = $params["for"];
}

if ($forStaff) {
    if (!$user["isStaff"]) {
        echo ApiHelper::buildErrorResponse("You do not have authorization to make this request.");
        exit(0);
    }
    if (!in_array($username, ["rex", "pronoy", "aks", "dynamo"])) {
        echo ApiHelper::buildErrorResponse("No data to show");
        exit(0);
    }
} else {
    if (!$user["isMentor"]) {
        echo ApiHelper::buildErrorResponse("You do not have authorization to make this request.");
        exit(0);
    }
}

if ($forStaff) {
    $sql = "SELECT * FROM promotion WHERE status = 'verified'";

} else {
    $sql = "SELECT * FROM promotion WHERE status = 'pending' AND mentor = '$username'";
}

echo ApiHelper::buildSuccessResponse(ApiHelper::query($sql));