<?php

include_once 'QueryHelper.php';
include_once 'config.php';
include_once 'auth_util.php';

$headers = apache_request_headers();

$validationError = AuthUtil::validate($headers);
if ($validationError != null) {
    echo $validationError;
    exit(0);
}

$qh = new QueryHelper();
$items = $qh->query("SELECT * FROM leader_board_item");
echo ApiHelper::buildSuccessResponse($items);
$qh->close();
