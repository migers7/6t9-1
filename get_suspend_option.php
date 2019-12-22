<?php

include_once 'auth_util.php';
include_once 'config.php';

$headers = apache_request_headers();

$validationError = AuthUtil::validate($headers);
if ($validationError != null) {
    echo $validationError;
    exit(0);
}
$username = $headers["username"];

$sql = "SELECT username, isAdmin, isStaff, isOwner FROM users WHERE username = '$username' AND (isAdmin = 1 OR isStaff = 1 OR isOwner = 1)";
$self = ApiHelper::queryOne($sql);

if ($self == null) {
    echo ApiHelper::buildErrorResponse("You do not have authorization to perform this action");
    exit(0);
}

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);
$target = $params["username"];

$sql = "SELECT username, isMentor, isMerchant, isAdmin, isStaff, isOwner FROM users WHERE username = '$target' AND active = 1";
$user = ApiHelper::queryOne($sql);
if($user == null) {
    echo ApiHelper::buildErrorResponse("No active user found named $target");
    exit(0);
}

if ($user["isAdmin"] || $user["isStaff"] || $user["isOwner"]) {
    echo ApiHelper::buildErrorResponse("Unable to suspend user $target. May be you do not have authorization to perform this action");
    exit(0);
}

$data = array();
$data[] = ["id" => 1, "checked" => false, "text" => "Suspend all the accounts that have same device"];

if ($self["isStaff"] || $self["isOwner"]) {
    $data[] = ["id" => 2, "checked" => false, "text" => "Erase credits from account"];

    /*if($user["isMentor"] || $user["isMerchant"]) {
        $data[] = ["id" => 3, "checked" => false, "text" => "Remove merchant/mentor color"];
    }*/
}

echo ApiHelper::buildSuccessResponse($data);