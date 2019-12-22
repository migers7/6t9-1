<?php

include_once 'auth_util.php';
include_once 'config.php';
include_once 'adminUtils.php';
include_once 'admin_log.php';
include_once 'EmailUtils.php';
include_once 'notification_utils.php';

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

$sql = "SELECT email, username, isMentor, isMerchant, isAdmin, isStaff, isOwner, token FROM users WHERE username = '$target'";
$user = ApiHelper::queryOne($sql);

if ($user["isAdmin"] || $user["isStaff"] || $user["isOwner"]) {
    echo ApiHelper::buildErrorResponse("Unable to suspend user $target. May be you do not have authorization to perform this action");
    exit(0);
}

$idString = $params["ids"];
$ids = explode(" ", $idString);
$note = $params["note"];
$reasonId = $params["reason_id"];
$durationId = $params["duration_id"];
$updateStr = "active = 0, mentor = '', merchantSince = NULL, accessToken = 'hojoborolo', color = '', isMentor = 0, isMerchant = 0";
$multi = null;
foreach ($ids as $id) {
    if ($id == "1") {
        $multi = (new AdminUtils())->suspendSameDevice($target);
    } else if ($id == "2") {
        $updateStr .= ", balance = 0, balance2 = 0";
    }
}

$reasons = ["Flooding", "Abusing", "Intentionally poking and misbehaving with users", "Illegal credit", "Hacking", "Spamming"];
$durations = ["1 day", "7 days", "Lifetime"];
$reason = $reasons[$reasonId];
$duration = $durations[$durationId];

$sql = "UPDATE users SET $updateStr WHERE username = '$target'";
ApiHelper::exec($sql);

$subject = "Your account has been suspended";
$body = "$target \n\nYour account has been suspended for $duration.\n";
$body .= "\nReason: $reason.";
if ($note != "") {
    $body .= "\n\nRemarks: $note";
}

$description = "$username has suspended $target";
if ($multi != null) {
    $body .= "\n\nWe have detected the following accounts also belong to you and therefore we have also suspended those.";
    $body .= "\n$multi";
    $description .= ", $multi";
}

$description .= " for $duration. Reason: $reason";

if ($note != "") {
    $description .= "\nRemarks: $note";
}

EmailUtils::emailTo($user["email"], $subject, $body);

AdminLogUtils::log($username, "Suspend", $target, "", $description);

$message = "You have suspended $target";
if ($multi != null) {
    $message .= " and ($multi)";
}

(new NotificationUtils())->pushTopic($target, "Suspend", "Your account has been suspended");

echo ApiHelper::buildSuccessResponse($message);