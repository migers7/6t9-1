<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 5/26/2019
 * Time: 9:51 PM
 */

include_once 'auth_util.php';
include_once 'config.php';
include_once 'account_utils.php';
include_once 'EmailUtils.php';
include_once 'notification_utils.php';

$headers = apache_request_headers();

$validationError = AuthUtil::validate($headers);
if ($validationError != null) {
    echo $validationError;
    exit(0);
}
$username = $headers["username"];

$sql = "SELECT * FROM users WHERE username = '$username'";
$user = ApiHelper::queryOne($sql);

if ($user == null) {
    echo ApiHelper::buildErrorResponse("Something went wrong. Please try again later");
    exit(0);
}

if ($user["isMentor"] || $user["isStaff"] || $user["isAdmin"]) {
    echo ApiHelper::buildErrorResponse("You cannot be a merchant.");
    exit(0);
}

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

$mentor = trim($params["mentor"]);
$mentorInfo = ApiHelper::queryOne("SELECT isMentor FROM users WHERE username = '$mentor'");
if ($mentorInfo == null || $mentorInfo["isMentor"] == false) {
    echo ApiHelper::buildErrorResponse("No mentor found for username $mentor");
    exit(0);
}


if ($user["isMerchant"]) {
    if ($user["mentor"] != $mentor) {
        echo ApiHelper::buildErrorResponse("You are already a merchant under the tag of " . $user["mentor"]);
        exit(0);
    }
}

$row = ApiHelper::queryOne("SELECT id, mentor FROM promotion WHERE username = '$username'");
if ($row != null) {
    echo ApiHelper::buildErrorResponse("You have already a request for promotion/renewal in progress under " . $row["mentor"]);
    exit(0);
}

$count = count(ApiHelper::query("SELECT id, username, isMerchant FROM users WHERE username IN (SELECT username FROM promotion WHERE mentor = '$mentor' AND status = 'promoted') OR (isMerchant = 1 AND mentor = '$mentor')"));
$isSub = ApiHelper::rowExists("SELECT id FROM users WHERE username = '$username' AND isMerchant = 1 AND mentor = '$mentor'");
if ($count >= 30 && $isSub == false) {
    echo ApiHelper::buildErrorResponse("$mentor has already reached the maximum number of merchants he can tag.");
    exit(0);
}

$sql = "INSERT INTO promotion(username, mentor, type, issued_by, status) VALUES ('$username', '$mentor', 'merchant','sixt9', 'pending')";
if (ApiHelper::exec($sql) > 0) {
    $nu = new NotificationUtils();
    $icon = "https://6t9.app/storage/images/promotion.png";
    $nu->insertWithImageAndLink($mentor, "$username requested to be promoted/renewed as merchant under your tag. Visit merchant arena for more information.", $icon, "https://6t9.app/merchant_arena");
    $nu->push($mentor, "6t9", "You have a new promotion request.");
    echo ApiHelper::buildErrorResponse("Your request has been submitted successfully!");
} else {
    echo ApiHelper::buildErrorResponse("Something went wrong. Please try again later");
}
