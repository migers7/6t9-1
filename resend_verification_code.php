<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'db_utils.php';
include_once 'EmailUtils.php';
include_once 'auth_util.php';

$headers = apache_request_headers();

$validationError = AuthUtil::validate($headers, true);
if ($validationError != null) {
    echo $validationError;
    exit(0);
}
$username = $headers["username"];

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);
$pdo = getConn();
$rows = cast($pdo->query("SELECT id FROM email_limit WHERE username = '$username' AND e_type = 'code' AND time_stamp <= (NOW()- INTERVAL 1 DAY)"));
$pdo = null;
$email = $params["email"];
if(count($rows) > 2) {
    echo buildErrorResponse("You have requested for verification code too many times. Please contact with contact6t9@gmail.com from the email address $email if you still have not got verification code. Do not forget to mention the username in your message.");
    exit(0);
}

$verificationCode = generateVerificationCode();
$subject = "SixT9 Account Verification";
$body = "You just created an account with username '$username'. Your verification code is $verificationCode";
$sql = "UPDATE users SET verificationCode = '$verificationCode' WHERE username = '$username';";
$pdo = getConn();
$sql = "INSERT INTO verification_codes(username, code, email) VALUES('$username', '$verificationCode', '$email')";
$pdo = getConn();
$done = $pdo->exec($sql);
$pdo = null;

if ($done) {
    $pdo = getConn();
    $pdo->exec("INSERT INTO email_limit(username, e_type) VALUES ('$username', 'code')");
    $pdo = null;
    if (EmailUtils::emailTo($email, $subject, $body)) {
        echo buildErrorResponse("A verification code was sent to $email. Please also check spam folders if you do not get emails.");
    } else {
        echo buildErrorResponse("Failed to send verification code.");
    }
} else {
    echo buildErrorResponse("Failed to generate verification code.");
}
