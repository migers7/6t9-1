<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once '../config/utils.php';
include_once '../models/user.php';

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);

$user = new User();
$user->username = $params["username"];
$user->password = sha1($params["password"]);
echo $user->login();