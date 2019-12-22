<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */


function getConn()
{
    $servername = "localhost";
    $username = "tryToHackUsYouBastard";
    $password = "dso438fn3;43feufblsceo394857nwl";
    $dbname = "6t9";
    $dsn = "mysql:host=" . $servername . ";dbname=" . $dbname . ";charset=utf8";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}


function cast($res)
{
    if($res == false) return array();
    $type = array();
    $data = array();
    for ($i = 0; $i < $res->columnCount(); $i++) {
        $column = $res->getColumnMeta($i);
        $type[$column['name']] = $column['native_type'];
    }
    while ($row = $res->fetch()) {
        foreach ($type as $key => $value) {
            if ($value == "LONG") {
                settype($row[$key], 'int');
            } else if($value == "TINY") {
                settype($row[$key], 'boolean');
            } else if($value == "DOUBLE") {
                settype($row[$key], 'double');
            } else if($value == "FLOAT") {
                settype($row[$key], 'float');
            }
        }
        $data[] = $row;
    }

    return $data;
}

function buildSuccessResponse($data = array(), $message = "Request completed successfully.")
{
    $res = array();
    $res["data"] = $data;
    $res["message"] = $message;
    $res["statusCode"] = 200;
    return json_encode($res);
}

function buildErrorResponse($message = "Request completed successfully.", $code = 403)
{
    $res = array();
    $res["data"] = null;
    $res["message"] = $message;
    $res["statusCode"] = $code;
    return json_encode($res);
}

function generateVerificationCode($length = 6) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $string     = '';

    for ($i = 0; $i < $length; $i++) {
        $string .= $characters[mt_rand(0, strlen($characters) - 1)];
    }

    return $string;
}

$DICE_GAME_OVER = 17;
