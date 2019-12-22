<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 1/18/19
 * Time: 11:12 PM
 */

include_once 'config.php';
include_once 'firebaseManager.php';
include_once 'notification_utils.php';

function get_ip_address()
{
    //Just get the headers if we can or else use the SERVER global.
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
    } else {
        $headers = $_SERVER;
    }
    //Get the forwarded IP if it exists.
    if (array_key_exists('X-Forwarded-For', $headers) && filter_var($headers['X-Forwarded-For'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $the_ip = $headers['X-Forwarded-For'];
    } elseif (array_key_exists('HTTP_X_FORWARDED_FOR', $headers) && filter_var($headers['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $the_ip = $headers['HTTP_X_FORWARDED_FOR'];
    } else {

        $the_ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }
    return $the_ip;
}

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

$username = $params["username"];
$password = sha1($params["password"]);


$sql = "SELECT * FROM users WHERE username = '$username' AND (password = '$password' OR temp_password = '$password')";
$pdo = ApiHelper::getInstance();
$res = ApiHelper::cast($pdo->query($sql));
$pdo = null;

$headers = apache_request_headers();


if (array_key_exists("user_profile", $headers)) {
    $key = $headers["user_profile"];
    if ($key != "-1286140040com.app.sixt9" && $key != "1931832758com.app.sixt9" && $key != "-233026959com.app.sixt9.debug" && $key != "-960475199com.app.sixt9.debug"
        && $key != '-803858837com.app.sixt9.debug') {
        echo ApiHelper::buildErrorResponse("Could not verify your request. Please relogin. [H]");
        exit(0);
    }
} else {
    echo ApiHelper::buildErrorResponse("Could not verify your request. Please relogin. [M]");
    exit(0);
}

if (count($res) > 0) {
    /*$sql = "SELECT username FROM users WHERE isStaff = 1 OR isAdmin = 1 OR isOwner = 1";
    $adminsList = ApiHelper::query($sql);
    $admins = array();
    foreach ($adminsList as $row) {
        $admins[] = $row["username"];
    }
    if(in_array($username, $admins) == false) {
        echo ApiHelper::buildErrorResponse("Unable to login to SixT9. SixT9 is sleeping. Will wake up after server maintenance.");
        exit(0);
    }*/
    if (array_key_exists("device_id", $headers)) {
        $deviceId = $headers["device_id"];
        $sql = "INSERT INTO device(username, device_id) VALUES('$username', '$deviceId') ON DUPLICATE KEY UPDATE device_id = '$deviceId'";
        $pdo = ApiHelper::getInstance();
        $pdo->exec($sql);
        $pdo = null;
    }
    if (array_key_exists("application_id", $headers)) {
        $appId = $headers["application_id"];
        $sql = "INSERT INTO device(username, app_id) VALUES('$username', '$appId') ON DUPLICATE KEY UPDATE app_id = '$appId'";
        $pdo = ApiHelper::getInstance();
        $pdo->exec($sql);
        $pdo = null;
    } else {
        echo ApiHelper::buildErrorResponse("There is something missing in your login request. Either the app is illegal or the request has missing arguments.");
        exit(0);
    }

    if (array_key_exists("fcmToken", $headers)) {
        $fcmToken = $headers["fcmToken"];
        /*$sql = "UPDATE users SET token = '$fcmToken' WHERE  username = '$username'";
        $pdo = ApiHelper::getInstance();
        $pdo->exec($sql);
        $pdo = null;*/
        $result = (new NotificationUtils())->isValidFCMToken($fcmToken, "verifying..", "verifying token", "no_data");
        if ($result != null) {
            $haha = (array)json_decode($result);
            if ($haha["success"] == 1) {
                $sql = "UPDATE users SET token = '$fcmToken' WHERE  username = '$username'";
                $pdo = ApiHelper::getInstance();
                $pdo->exec($sql);

                // if user has valid fcm token, then save it
                $info = json_encode($haha);
                $sql = "INSERT INTO fcm(username, token, info, success) VALUES('$username', '$fcmToken', '$info', 1) 
                        ON DUPLICATE KEY UPDATE token = '$fcmToken'";
                $pdo->exec($sql);
                $pdo = null;
            } else {
                echo ApiHelper::buildErrorResponse("Could not verify your login request. Please install the app again and try again later. [Error x01]");
                exit(0);
            }
        } else {
            echo ApiHelper::buildErrorResponse("Could not verify your login request. Please install the app again and try again later.[Error x02]");
            exit(0);
        }
    } else {
        echo ApiHelper::buildErrorResponse("There is something missing in your login request. Either the app is illegal or the request has missing arguments.");
        exit(0);
    }

    $token = ApiHelper::generateVerificationCode(255);
    $timeStamp = date("Y-m-d H:i:s", time());
    $sql = "UPDATE users SET accessToken = '$token', last_login = '$timeStamp' WHERE username = '$username'";
    $pdo = ApiHelper::getInstance();
    $pdo->exec($sql);
    $pdo = null;


    $user = $res[0];
    $user["accessToken"] = $token;
    unset($user["password"]);
    unset($user["verificationCode"]);
    if ($user["active"] == 0) {
        echo ApiHelper::buildErrorResponse("Your account has been suspended.");
        exit(0);
    }
    echo ApiHelper::buildSuccessResponse($user, "Logged in successfully");
} else {
    echo ApiHelper::buildErrorResponse("Incorrect username or password");
}


