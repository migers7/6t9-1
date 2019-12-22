<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 3/24/2019
 * Time: 12:34 AM
 */

include_once 'db_utils.php';
include_once 'account_utils.php';

// set timezone to bangladesh
date_default_timezone_set('Asia/Dhaka');

/**
 * The following code block calculates the daily revenue for merchants and mentors
 * Mentors/merchants get 2% revenue from the daily spent credit of tagged users
 * ===============================================================================
 */

// get the merchant/mentor list
$sql = "SELECT username FROM users WHERE isMentor = 1 OR isMerchant = 1";
$pdo = getConn();
$merchant_mentors = cast($pdo->query($sql));
$count = count($merchant_mentors);

for ($i = 0; $i < $count; $i++) {
    $username = $merchant_mentors[$i]["username"];

    // get total spent by the tagged users of current merchant/mentor
    $sql = "SELECT SUM(daily_spent) AS trails FROM users WHERE mentor = '$username'";
    $trails_result = cast($pdo->query($sql));
    $trails = 0;
    if (count($trails_result) > 0) {
        $trails = (double)$trails_result[0]["trails"] * 0.025; // 2.5% trails
    }

    // add trails
    $sql = "UPDATE users SET revenue = $trails, balance = balance + $trails WHERE username = '$username'";
    $pdo->exec($sql);
}

/**
 * The following code block calculates the additional revenue earned by mentors from the
 * merchants under them, mentors get 15% revenue from the revenue their merchants earn that day
 * ============================================================================================
 */
// get the mentor list
$sql = "SELECT username FROM users WHERE isMentor = 1";
$mentors_result = cast($pdo->query($sql));
$count = count($mentors_result);

for ($i = 0; $i < $count; $i++) {
    $username = $mentors_result[$i]["username"];

    // get total revenue earned by the merchants under this mentor
    $sql = "SELECT SUM(revenue) AS trails FROM users WHERE mentor = '$username' AND isMerchant = 1";
    $trails_result = cast($pdo->query($sql));
    $trails = 0;
    if (count($trails_result) > 0) {
        $trails = (double)$trails_result[0]["trails"] * 0.15; // 15% additional trails
    }

    // add trails
    $sql = "UPDATE users SET revenue = revenue + $trails, balance = balance + $trails WHERE username = '$username'";
    $pdo->exec($sql);
}

/**
 * The following block updates account history with revenue
 * ========================================================
 */

// get revenue list for all mentors and merchants
$sql = "SELECT username, revenue FROM users WHERE isMerchant = 1 OR isMentor = 1";
$res = cast($pdo->query($sql));
$count = count($res);
for ($i = 0; $i < $count; $i++) {
    $username = $res[$i]["username"];
    $revenue = $res[$i]["revenue"];
    $description = "Earned revenue $revenue BDT from merchant tags";
    $sql = "INSERT INTO account_history(username, type, amount, description, interactor) 
            VALUES('$username', '+', $revenue, '$description', '')";
    $pdo->exec($sql);
}


/**
 *  Reset daily spent for all
 * ==========================
 */
$sql = "UPDATE users SET daily_spent = 0 WHERe daily_spent > 0";
$pdo->exec($sql);

/**
 * The following code block check merchant expiry date and removes merchantship if necessary
 * =========================================================================================
 */

$sql = "UPDATE users SET merchantSince = NULL, mentor = '', color = '', revenue = 0, daily_spent = 0, isMentor = 0, isMerchant = 0 WHERE merchantSince <= (NOW()- INTERVAL 30 DAY) AND (mentor != '' OR isMerchant = 1 OR isMentor = 1)";
$pdo->exec($sql);
$sql = "UPDATE transfer_limit SET daily = 0";
$pdo->exec($sql);
$pdo = null;
