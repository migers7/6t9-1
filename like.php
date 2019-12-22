<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 3/23/2019
 * Time: 5:56 PM
 */

include_once 'config.php';
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

$likedBy = $username;
$username = $params["username"];

if ($username == $likedBy) {
    echo ApiHelper::buildErrorResponse("You cannot like yourself.");
    exit(0);
}

$sql = "SELECT likes FROM profile WHERE username = '$username'";
$pdo = ApiHelper::getInstance();
$res = ApiHelper::cast($pdo->query($sql));
if (count($res) > 0) {
    $likes = $res[0]["likes"];
    $sql = "UPDATE profile SET likes = CONCAT(likes, ' $likedBy') WHERE username = '$username'";
    $liked = false;
    if ($likes == null) {
        $sql = "UPDATE profile SET likes = '$likedBy' WHERE username = '$username'";
    } else {
        $liked = in_array($likedBy, explode(" ", $likes));
    }
    if ($liked == false) {
        $liked = $pdo->exec($sql);
    }
    $pdo = null;
    if ($liked) {
        echo ApiHelper::buildSuccessResponse($username);
        exit(0);
    }
}
$pdo = null;
echo ApiHelper::buildErrorResponse("Something went wrong. Please try again.");