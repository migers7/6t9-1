<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 3/10/2019
 * Time: 8:42 PM
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

$forFriends = false;
if (isset($params["for_friends"])) $forFriends = $params["for_friends"];
$items = ["games" => "Games played", "gifts" => "Gifts received", "gift_sent" => "Gifts sent",
    "gift_sent_daily" => "Gifts sent today", "gifts_daily" => "Gifts received today", "dice_played" => "Dice played today",
    "dice_won" => "Dice won today", "lowcard_played" => "LowCard played today", "lowcard_won" => "LowCard won today"
    ,"cricket_played" => "Cricket played today", "cricket_won" => "Cricket won today"
];
$column = "games";
if (isset($params["for"])) $column = $params["for"];
if (isset($column, $items) == false) {
    echo ApiHelper::buildErrorResponse("Invalid parameters.");
    exit(0);
}
$sql = "SELECT username, $column AS 'result' FROM profile WHERE $column > 0 ORDER BY $column DESC LIMIT 100";
if ($forFriends == "true") $sql = "SELECT username, $column AS 'result' FROM profile WHERE username IN (SELECT other FROM user_relations WHERE username = '$username' and relationType = 'friends') OR username = '$username' ORDER BY $column DESC ";

$pdo = ApiHelper::getInstance();

$filters = array();
foreach ($items as $key => $value) {
    $filters[] = ["id" => $key, "label" => $value];
}

$data = array();
$data["filters"] = $filters;
$data["for"] = $column;
$data["rank_info"] = null;
$data["for_friends"] = $forFriends;
$data["rank_list"] = ApiHelper::cast($pdo->query($sql));
if ($column != "contest") {
    $sql = "SELECT ranks . *
    FROM (
          SELECT @rownum := @rownum +1 rank, p.username
          FROM profile p, (SELECT @rownum :=0)r
          ORDER BY $column DESC
          ) ranks
    WHERE username ='$username'";
    if ($forFriends == "true") {
        $sql = "SELECT ranks . *
        FROM (
              SELECT @rownum := @rownum +1 rank, p.username
              FROM profile p, (SELECT @rownum :=0)r
              WHERE p.username IN (SELECT other FROM user_relations WHERE username = '$username' and relationType = 'friends') OR username = '$username'
              ORDER BY $column DESC
              ) ranks
        WHERE username ='$username'";
    }
    $user_rank = ApiHelper::cast($pdo->query($sql));
    if (count($user_rank) > 0) {
        $data["rank_info"] = "Your rank is " . $user_rank[0]["rank"];
    }
} else {
    $data["for_friends"] = false;
}
$pdo = null;
echo ApiHelper::buildSuccessResponse($data);

