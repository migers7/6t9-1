<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'db_utils.php';
include_once 'EmailUtils.php';
include_once 'auth_util.php';
include_once 'ConstantUtils.php';

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
$email = $params["email"];
$rows = cast($pdo->query("SELECT id FROM email_limit WHERE username = '$username' AND e_type = 'code' AND email = '$email'"));
$user = cast($pdo->query("SELECT email FROM users WHERE username = '$username'"))[0];
$pdo = null;
$emailError = ConstantUtils::isValidEmail($username, $email);
if ($emailError != null) {
    echo buildSuccessResponse($user["email"], $emailError);
    exit(0);
}
if (count($rows) > 2) {
    echo buildSuccessResponse($user["email"], "You have requested for verification code too many times. Please contact with contact6t9@gmail.com from the email address $email if you still have not got verification code. Do not forget to mention the username in your message.");
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
    $pdo->exec("INSERT INTO email_limit(username, e_type, email) VALUES ('$username', 'code', '$email')");
    $user = cast($pdo->query("SELECT email FROM users WHERE username = '$username'"))[0];
    $pdo = null;
    if (EmailUtils::emailTo($email, $subject, $body)) {
        echo buildSuccessResponse($email, "");
    } else {
        echo buildSuccessResponse($user["email"], "Failed to send verification code.");
    }
} else {
    echo buildSuccessResponse($user["email"], "Failed to generate verification code.");
}
