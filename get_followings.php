<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 4/20/2019
 * Time: 1:44 AM
 */

include_once 'config.php';
include_once 'auth_util.php';

$headers = apache_request_headers();

$validationError = AuthUtil::validate($headers);
if ($validationError != null) {
    echo $validationError;
    exit(0);
}
$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);
$id = 0;
if (isset($params["id"])) $id = (int)$params["id"];

$username = $params["username"];

$subsql = "SELECT _id, other, relationType FROM user_relations WHERE username = '$username' AND (relationType = 'requested' OR relationType = 'friends') AND _id > $id ORDER BY _id ASC LIMIT 10";
$pdo = ApiHelper::getInstance();
$rows = ApiHelper::cast($pdo->query($subsql));
$result = array();
$arr = array();
foreach ($rows as $row) {
    $arr[$row["other"]] = ["relation" => $row["relationType"], "id" => $row["_id"]];
}

foreach ($arr as $username => $relation) {
    $sql = "SELECT username, dp, gender, country, userLevel FROM users WHERE username = '$username'";
    $users = ApiHelper::cast($pdo->query($sql));
    if (count($users) > 0) {
        $user = $users[0];
        $user["relationType"] = $relation["relation"];
        $user["id"] = $relation["id"];
        $result[] = $user;
    }
}

$pdo = null;

echo ApiHelper::buildSuccessResponse($result);