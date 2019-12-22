<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'userUtils.php';
include_once 'db_utils.php';
include_once 'notification_utils.php';
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

$uu = new UserUtils();
$to = $params["to"];
$files = "";
if (isset($params["files"])) $files = $params["files"];
if ($uu->findUser($to) == null) {
    echo buildErrorResponse("No user found for username $to");
    exit(0);
}

$sql = "SELECT id FROM blocked_users WHERE username = '$to' AND blocked_name = '$username'";
$pdo = getConn();
$count = count(cast($pdo->query($sql)));
$pdo = null;
if ($count > 0) {
    echo buildErrorResponse("Unable to send email to $to");
    exit(0);
}

$subject = str_replace("''", "", $params["subject"]);
$body = str_replace("''", "", $params["body"]);

$pdo = getConn();
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
$fromUser = $uu->findUser($username);
$imageUrl = $fromUser["dp"];

$sql = "INSERT INTO emails(username, sender, recipient, subject, body, seen, image_url, files) VALUES('$to', '$username', '$to', '$subject', '$body', false, '$imageUrl', '$files')";
$done = $pdo->exec($sql);
if ($done > 0) {
    $sql = "INSERT INTO emails(username, sender, recipient, subject, body, seen, image_url, files) VALUES('$username', '$username', '$to', '$subject', '$body', true, '$imageUrl', '$files')";
    $done = $pdo->exec($sql);
    (new NotificationUtils())->push($to, "email_notification", "Email", "You have received an email.");
    echo buildSuccessResponse((boolean)$done);
} else {
    echo buildErrorResponse("Failed to send email. Probably the body contains invalid character. Avoid using single quotes and other special characters.");
}
$pdo = null;
