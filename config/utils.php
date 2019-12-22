<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 * @param $data
 * @param string $message
 * @return string
 */

function buildSuccessResponse($data = array(), $message = "Request completed successfully.")
{
    $res = array();
    $res["data"] = $data;
    $res["message"] = $message;
    $res["statusCode"] = 200;
    return json_encode($res);
}

function buildErrorResponse($message = "Request completed successfully.")
{
    $res = array();
    $res["data"] = null;
    $res["message"] = $message;
    $res["statusCode"] = 403;
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