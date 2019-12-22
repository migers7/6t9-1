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

$validationError = AuthUtil::validateApp($headers);
if ($validationError != null) {
    echo $validationError;
    exit(0);
}

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);
$username = $params["username"];

$sql = "SELECT email FROM users WHERE username = '$username'";
$pdo = getConn();
$res = cast($pdo->query($sql));
if (count($res) > 0) {
    $email = $res[0]["email"];
    if ($email != $params["email"]) {
        echo buildErrorResponse("Email does not match.");
        exit(0);
    }
    $pdo = getConn();
    $rows = cast($pdo->query("SELECT id FROM email_limit WHERE username = '$username' AND e_type = 'forgot'"));
    if(count($rows) > 2) {
        $pdo = null;
        echo buildErrorResponse("You reached the maximum number of forgot password request you can make. Please contact with contact6t9@gmail.com.");
        exit(0);
    }
    $subject = "Forgot password request";
    $password = generateVerificationCode(8);
    $hashed = sha1($password);
    $sql = "UPDATE users SET temp_password = '$hashed' WHERE username = '$username'";
    if ($pdo->exec($sql) > 0) {
        $pdo->exec("INSERT INTO email_limit(username, e_type) VALUES ('$username', 'forgot')");
        $body = "You have recently requested for forgot password for account with username $username. We have set the temporary password for this account to '$password' (ignore the quotes)."
            . " Please try to login with this and we strongly recommend to change your password if you can login successfully."
            . ". Regards-- Team 6t9";
        if (EmailUtils::emailTo($email, $subject, $body)) {
            echo buildSuccessResponse("A temporary password has been sent to the email address associated with the username you provided.");
        } else {
            echo buildErrorResponse("Forgot password request failed. Please try again. [ME]");
        }
    } else {
        echo buildErrorResponse("Forgot password request failed. Please try again. [DE]");
    }
} else {
    echo buildErrorResponse("No user found for username $username");
}
$pdo = null;
