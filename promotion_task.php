<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 5/16/2019
 * Time: 6:02 PM
 */

include_once 'config.php';
include_once 'account_utils.php';
include_once 'notification_utils.php';


$timeStamp = date("Y-m-d H:i:s", time());
$timeStamp = substr($timeStamp, 0, 10);
$timeStamp .= " 00:00:00";

$sql = "SELECT * FROM promotion WHERE status = 'promoted'";
$pdo = ApiHelper::getInstance();
$rows = ApiHelper::cast($pdo->query($sql));

$au = new AccountUtils();
$nu = new NotificationUtils();

foreach ($rows as $row) {
    $mentor = "sixt9";
    $color = "#f44336";
    $username = $row["username"];
    $subject = "Merchantship Renewal";
    $body = "Hi\n\nIt is our pleasure to inform you that your merchantship has been renewed successfully. Please relogin to your account to visit merchant arena and gain merchant color. Your merchantship will remain valid for 30 days from now on."
        . "\n\nRegards\nTeam 6t9";

    if ($row["type"] == "merchant") {
        $mentor = $row["mentor"];
        $color = "#9C27B0";
        $sql = "UPDATE users SET merchantSince = '$timeStamp', mentor = '$mentor', isMerchant = 1, isMentor = 0, accessToken = 'lehalua' WHERE username = '$username'";
        $au->addCredit($username, 200000, true);
        $au->addHistory($username, "+", 200000, "Recharged 200000 BDT for merchantship renewal");
    } else {
        $sql = "UPDATE users SET merchantSince = '$timeStamp', mentor = '$mentor', isMentor = 1, isMerchant = 0, accessToken = 'lehalua' WHERE username = '$username'";
        $au->addCredit($username, 1000000, true);
        $au->addHistory($username, "+", 1000000, "Recharged 1000000 BDT for mentorship renewal");

        $subject = "Mentorship Renewal";
        $body = "Hi\n\nIt is our pleasure to inform you that your mentorship has been renewed successfully. "
            . "1,000,000 BDT credit has been transferred to your account. If you do not get it, please contact any staff. "
            . "Relogin to your account to visit merchant arena and gain mentor color. Your mentortship will remain valid for 30 days from now on."
            . "\n\nRegards\nTeam 6t9";
    }
    $pdo->exec($sql);
    $pdo->exec("UPDATE users SET color = '$color' WHERE username = '$username' AND isStaff = 0 AND isAdmin = 0 AND isOwner = 0");

    $noReply = "No reply";
    $sql = "INSERT INTO emails(username, sender, recipient, subject, body, seen, time) VALUES(
              '$username', '$noReply', '$username', '$subject', '$body', false, '$timeStamp')";
    $pdo->exec($sql);
    $nu->push($username, "email_notification", "Email", "You have received an email.");
}

$sql = "DELETE FROM promotion WHERE status = 'promoted'";
$pdo->exec($sql);
$pdo = null;
