<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 4/19/19
 * Time: 8:13 PM
 */

include_once 'db_utils.php';
include_once 'auth_util.php';

$headers = apache_request_headers();

$validationError = AuthUtil::validate($headers);
if ($validationError != null) {
    echo $validationError;
    exit(0);
}

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);
$ids = "";

$username = $headers["username"];

if (isset($params["id"])) {
    $ids = explode(" ", $params["id"]);
    $sql = "DELETE FROM emails WHERE id IN (";
    for ($i = 0; $i < count($ids); $i++) {
        if ($i > 0) $sql .= ", ";
        $id = $ids[$i];
        $sql .= "$id";
    }
    $sql .= ")";
    $pdo = getConn();
    $success = (boolean)($pdo->exec($sql) > 0);
    $pdo = null;
    echo buildSuccessResponse($success);
} else {
    echo buildErrorResponse("No email was selected");
}