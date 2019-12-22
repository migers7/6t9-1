<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once '../config/utils.php';
include_once '../models/room.php';

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

$room = new Room();
$room->name = $params["name"];
echo $room->get($params["username"]);