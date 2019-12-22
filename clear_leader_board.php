<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 3/10/2019
 * Time: 5:27 PM
 */

include_once 'db_utils.php';

$sql = "UPDATE profile SET gifts_daily = 0, gift_sent_daily = 0, dice_played = 0, dice_won = 0, lowcard_played = 0, lowcard_won = 0, cricket_played = 0, cricket_won = 0";
$pdo = getConn();
$pdo->exec($sql);
$pdo = null;