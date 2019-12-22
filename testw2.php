<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 4/22/2019
 * Time: 5:04 PM
 */



include_once 'config.php';
include_once 'notification_utils.php';
$sql = "SELECT username, email, balance FROM users WHERE last_login LIKE '2019-05-25%' AND active = 1 AND userLevel < 10";
$rows = ApiHelper::query($sql);

$names = "";

for ($i = 0; $i < count($rows); $i++) {
    if($i > 0) $names .= ",";
    $username = $rows[$i]["username"];
    $names .= "'$username'";
}

$sql = "DELETE FROM users WHERE username IN ($names)";
ApiHelper::exec($sql);
$sql = "DELETE FROM profile WHERE username IN ($names)";
ApiHelper::exec($sql);
$sql = "DELETE FROM user_relations WHERE username IN ($names) OR other IN ($names)";
ApiHelper::exec($sql);
$sql = "DELETE FROM favorite_rooms WHERE username IN ($names)";
ApiHelper::exec($sql);
$sql = "DELETE FROM profile WHERE username IN ($names)";
ApiHelper::exec($sql);
$sql = "DELETE FROM merchant_pin WHERE username IN ($names)";
ApiHelper::exec($sql);
$sql = "DELETE FROM online_users WHERE username IN ($names)";
ApiHelper::exec($sql);
$sql = "DELETE FROM modship WHERE username IN ($names)";
ApiHelper::exec($sql);
$sql = "DELETE FROM security_qa WHERE username IN ($names)";
ApiHelper::exec($sql);
$sql = "DELETE FROM banned_users WHERE username IN ($names)";
ApiHelper::exec($sql);
$sql = "DELETE FROM blocked_users WHERE username IN ($names)";
ApiHelper::exec($sql);
