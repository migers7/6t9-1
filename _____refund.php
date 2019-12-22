<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 4/22/2019
 * Time: 5:04 PM
 */

include_once 'db_utils.php';
include_once 'account_utils.php';

$au = new AccountUtils();
/*
$sql = "SELECT username, amount FROM `account_history` WHERE interactor = 'dice 2019-05-06 05:44:39'";
$pdo = getConn();
$rows = cast($pdo->query($sql));

foreach ($rows as $row) {
    $username = $row["username"];
    $amount = $row["amount"];
    $id = $row["id"];
    $sql = "UPDATE users SET balance = balance + $amount WHERE username = '$username'";
    if ($pdo->exec($sql) > 0) {
        $au->addHistory($username, "+", $amount, "Refunded $amount BDT from pending transaction of server from dice 2019-05-06 05:44:39");
        echo "$username: $amount,";
    }
}*/

$amount = 10800;
$au->addCredit("md.noman", $amount);
$au->addHistory("md.noman", "+", $amount, "Received $amount BDT from pending transaction of server for dice win.");

$pdo = null;
$au = null;