<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 1/17/19
 * Time: 12:48 AM
 */

include_once 'db_utils.php';

$sql = "SELECT name FROM rooms";
$pdo = getConn();
$res = cast($pdo->query($sql));

for ($i = 0, $size = count($res); $i < $size; $i++) {
    $name = $res[$i]["name"];
    $sql = "SELECT username FROM room_users WHERE roomName = '$name'";
    $nos = count(cast($pdo->query($sql)));
    $sql = "UPDATE rooms SET nos = $nos WHERE name = '$name'";
    $pdo->exec($sql);
}

$pdo = null;