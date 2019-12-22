<?php

include_once 'firebaseManager.php';
include_once 'db_utils.php';


$sql = "SELECT name, announcement FROM rooms WHERE announcement != '' AND nos > 0";
$pdo = getConn();
$res = cast($pdo->query($sql));
$sql = "DELETE FROM online_users";
$pdo->exec($sql);
$pdo = null;

$ref = FirebaseManager::getInstance()->getReference("chats");

for ($i = 0; $i < count($res); $i++) {
    $name = $res[$i]["name"];
    $announcement = $res[$i]["announcement"];
    $ref->getChild($name)->push(MessageHelper::getRoomInfo($name, "Announcement: <<$announcement>>", MessageTypes::$INFO_ANNOUNCEMENT));
}

$ref = null;