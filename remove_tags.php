<?php
    /**
     * Created by PhpStorm.
     * User: maruf
     * Date: 2/12/19
     * Time: 6:56 PM
     */
    
    include_once 'db_utils.php';
    
    $pdo = getConn();
    $sql = "SELECT merchantSince, username FROM users WHERE merchantSince IS NOT NULL AND isMentor = 0 AND isMerchant = 0";
    $blues = cast($pdo->query($sql));
    $curTime = time();
    for ($i = 0; $i < count($blues); $i++) {
        $merchantSince = strtotime($blues[$i]["merchantSince"]);
        if ($curTime - $merchantSince >= 2592000) {
            $username = $blues[$i]["username"];
            $sql = "UPDATE users SET mentor = '', merchantSince = NULL WHERE username = '$username'";
            $pdo->exec($sql);
        }
    }
    $pdo = null;
