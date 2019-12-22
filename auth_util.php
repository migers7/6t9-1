<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 3/30/2019
 * Time: 3:13 PM
 */

include_once 'config.php';
include_once 'notification_utils.php';
include_once 'firebaseManager.php';

class AuthUtil
{
    /**
     * validates header
     * @param array of headers of any request
     * @param  boolean true if token ignored false otherwise
     * @return null if the headers are valid and json response otherwise
     */

    private static $SUPPORTED_APPLICATION_PROFILES = ['-1286140040com.app.sixt9', '1931832758com.app.sixt9', '-233026959com.app.sixt9.debug', '-960475199com.app.sixt9.debug',
        '-803858837com.app.sixt9.debug'];
    private static $FCM_TOKEN = 'fcmToken';
    private static $ACCESS_TOKEN = 'token';
    private static $USERNAME = 'username';
    private static $APPLICATION_PROFILE = 'user_profile';



    public static function validate($headers, $ignoreToken = false)
    {
        $errorCode = 801;
        if ($ignoreToken) $errorCode = 403;

        if (array_key_exists(self::$APPLICATION_PROFILE, $headers)
            && array_key_exists(self::$ACCESS_TOKEN, $headers)
            && array_key_exists(self::$FCM_TOKEN, $headers)
            && array_key_exists(self::$USERNAME, $headers)) {

            $token = $headers[self::$ACCESS_TOKEN];
            $username = $headers[self::$USERNAME];
            $fcmToken = $headers[self::$FCM_TOKEN];
            $profile = $headers[self::$APPLICATION_PROFILE];

            // verify if the user is using illegal app (clone or tempered apk)
            if (in_array($profile, self::$SUPPORTED_APPLICATION_PROFILES) == false) {
                return ApiHelper::buildErrorResponse("Could not verify your request. Either the app you are using is illegal or the request has missing arguments. Please download our latest version from play store.", $errorCode);
            }

            if ($ignoreToken == false) {

                // verify user's access token
                if (ApiHelper::rowExists("SELECT id FROM users WHERE username = '$username' AND accessToken = '$token' AND isVerified = 1 AND active = 1") == false) {
                    return ApiHelper::buildErrorResponse("Failed to authorize your request. Please re-login.", $errorCode);
                }

                // verify if user has served an actual fcm token
                $notificationUtils = new NotificationUtils();
                $response = $notificationUtils->isValidFCMToken($fcmToken, "validate__fcm", "validating your fcm token", "no data");
                if ($response == null) {
                    return ApiHelper::buildErrorResponse("Failed to verify your request. Please re-boot your device or try reinstalling the app.", $errorCode);
                }
                $info = (array)json_decode($response);
                if ($info["success"] == 0) {
                    //return ApiHelper::buildErrorResponse("Failed to verify your request. Please re-boot your device or try reinstalling the app.", $errorCode);
                } else {
                    // if user has valid fcm token, then save it
                    $sql = "INSERT INTO fcm(username, token, info, success) VALUES('$username', '$fcmToken', '', 1) 
                        ON DUPLICATE KEY UPDATE token = '$fcmToken'";
                    $pdo = ApiHelper::getInstance();
                    $pdo->exec($sql);
                    $sql = "UPDATE users SET token = '$fcmToken' WHERE username = '$username'";
                    $pdo->exec($sql);
                    $pdo = null;
                }
            }
            return null;
        }
        return ApiHelper::buildErrorResponse("Could not verify your request. Either the app you are using is illegal or the request has missing arguments.", $errorCode);
    }

    public static function validateApp($headers)
    {
        $errorCode = 403;
        if (array_key_exists(self::$APPLICATION_PROFILE, $headers)) {
            // verify if the user is using illegal app (clone or tempered apk)
            $profile = $headers[self::$APPLICATION_PROFILE];
            if (in_array($profile, self::$SUPPORTED_APPLICATION_PROFILES) == false) {
                return ApiHelper::buildErrorResponse("Could not verify your request. Either the app you are using is illegal or the request has missing arguments. Please download our latest version from play store.", $errorCode);
            }
            return null;
        }
        return ApiHelper::buildErrorResponse("Could not verify your request. Either the app you are using is illegal or the request has missing arguments.", $errorCode);
    }

    public static function validateAuth($headers)
    {
        if (array_key_exists(self::$APPLICATION_PROFILE, $headers)
            && array_key_exists(self::$ACCESS_TOKEN, $headers)
            && array_key_exists(self::$USERNAME, $headers)) {
            $token = $headers[self::$ACCESS_TOKEN];
            $username = $headers[self::$USERNAME];
            $profile = $headers[self::$APPLICATION_PROFILE];

            // verify if the user is using illegal app (clone or tempered apk)
            if (in_array($profile, self::$SUPPORTED_APPLICATION_PROFILES) == false) {
                return ApiHelper::buildErrorResponse("Could not verify your request. Either the app you are using is illegal or the request has missing arguments. Please download our latest version from play store.", 801);
            }
            if (ApiHelper::rowExists("SELECT id FROM users WHERE username = '$username' AND accessToken = '$token' AND isVerified = 1 AND active = 1") == false) {
                return ApiHelper::buildErrorResponse("Failed to authorize your request. Please re-login.", 801);
            }
            return null;
        }
        return ApiHelper::buildErrorResponse("Could not verify your request. Either the app you are using is illegal or the request has missing arguments.", 801);
    }

    public static function logout($username)
    {
        $sql = "SELECT roomName FROM room_users WHERE username = '$username'";
        $pdo = ApiHelper::getInstance();
        $rows = ApiHelper::cast($pdo->query($sql));
        $firebase = FirebaseManager::getInstance();

        foreach ($rows as $row) {
            $roomName = $row["roomName"];
            $firebase->getReference("chats/" . $roomName)->push(MessageHelper::getRoomLeftMessage(
                $username,
                $roomName
            ));
            $sql = "UPDATE rooms SET nos = nos - 1 WHERE name = '$roomName'";
            $pdo->exec($sql);
        }
        $sql = "DELETE FROM room_users WHERE username = '$username'";
        $pdo->exec($sql);
        $sql = "DELETE FROM last_message WHERE id LIKE '$username#%'";
        $pdo->exec($sql);
        $pdo = null;
        return true;
    }


    public static function isValidApp($headers) {
        if(array_key_exists(self::$APPLICATION_PROFILE, $headers)) {
            $profile = $headers[self::$APPLICATION_PROFILE];
            return in_array($profile, self::$SUPPORTED_APPLICATION_PROFILES);
        }
        return false;
    }
}