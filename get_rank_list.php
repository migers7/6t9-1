<?php

include_once 'config.php';
include_once 'auth_util.php';
include_once 'LeaderBoardManager.php';

$headers = apache_request_headers();

$validationError = AuthUtil::validate($headers);
if ($validationError != null) {
    echo $validationError;
    exit(0);
}

$username = $headers["username"];

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

$friendsOnly = false;
if ($params["friendsOnly"] == "1") $friendsOnly = true;
$id = (int)$params["id"];

$lbm = new LeaderBoardManager();
echo ApiHelper::buildSuccessResponse($lbm->getLeaderBoard($id, $username, $friendsOnly));
$lbm->__destruct();
