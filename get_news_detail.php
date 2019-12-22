<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 3/26/2019
 * Time: 6:08 PM
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

$id = 100000000;
$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);
if (isset($params["id"])) $id = (int)$params["id"];
else {
    echo ApiHelper::buildErrorResponse("Not enough params provided");
    exit(0);
}

$sql = "SELECT * FROM news WHERE id = $id";
$pdo = ApiHelper::getInstance();
$news = ApiHelper::cast($pdo->query($sql));
$pdo = null;

if (count($news) > 0) {
    echo ApiHelper::buildSuccessResponse($news[0]);
} else {
    echo ApiHelper::buildErrorResponse("No news found");
    exit(0);
}