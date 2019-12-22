<?php

include_once 'config.php';
include_once 'auth_util.php';
include_once 'QueryHelper.php';
include_once 'ConstantUtils.php';

$headers = apache_request_headers();
$error = AuthUtil::validateAuth($headers);

if ($error != null) {
    echo $error;
    exit(0);
}

$username = $headers["username"];
$qh = new QueryHelper();

$json_body = file_get_contents('php://input');
$params = (array)json_decode($json_body);
$user = $qh->queryOne("SELECT username, email, fullName, gender, country, birthday FROM users WHERE username = '$username'");

function validateDate($date, $format = 'Y-m-d')
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

if (isset($params["full_name"])) {
    $fullName = $params["full_name"];
    if (preg_match("/^([a-zA-Z' ]+)$/", $fullName) && strlen($fullName) > 0) {
        $sql = "UPDATE users SET fullName = '$fullName' WHERE username = '$username'";
        $res = $qh->exec($sql);
        if ($res > 0) {
            echo ApiHelper::buildSuccessResponse($fullName, "");
        } else if ($res == 0) {
            echo ApiHelper::buildSuccessResponse($user["fullName"], "No changes made");
        } else {
            echo ApiHelper::buildSuccessResponse($user["fullName"], "Failed to update name");
        }
    } else {
        echo ApiHelper::buildSuccessResponse($user["fullName"], "$fullName is not a valid name");
    }
} else if (isset($params["dob"])) {
    $dob = $params["dob"];
    if (validateDate($dob)) {
        $sql = "UPDATE users SET birthday = '$dob' WHERE username = '$username'";
        $res = $qh->exec($sql);
        if ($res > 0) {
            echo ApiHelper::buildSuccessResponse($dob, "");
        } else if ($res == 0) {
            echo ApiHelper::buildSuccessResponse($user["birthday"], "No changes made");
        } else {
            echo ApiHelper::buildSuccessResponse($user["birthday"], "Failed to update date of birth");
        }
    } else {
        echo ApiHelper::buildSuccessResponse($user["birthday"], "$dob is not a valid date");
    }
} else if (isset($params["gender"])) {
    $gender = $params["gender"];
    if (in_array($gender, ["Male", "Female"])) {
        $sql = "UPDATE users SET gender = '$gender' WHERE username = '$username'";
        $res = $qh->exec($sql);
        if ($res > 0) {
            echo ApiHelper::buildSuccessResponse($gender, "");
        } else if ($res == 0) {
            echo ApiHelper::buildSuccessResponse($user["gender"], "No changes made");
        } else {
            echo ApiHelper::buildSuccessResponse($user["gender"], "Failed to update gender");
        }
    } else {
        echo ApiHelper::buildSuccessResponse($user["gender"], "$gender is not a valid sex");
    }
} else if (isset($params["country"])) {
    $country = $params["country"];
    if (ConstantUtils::isValidCountry($country)) {
        $sql = "UPDATE users SET country = '$country' WHERE username = '$username'";
        $res = $qh->exec($sql);
        if ($res > 0) {
            echo ApiHelper::buildSuccessResponse($country, "");
        } else if ($res == 0) {
            echo ApiHelper::buildSuccessResponse($user["country"], "No changes made");
        } else {
            echo ApiHelper::buildSuccessResponse($user["country"], "Failed to update country");
        }
    } else {
        echo ApiHelper::buildSuccessResponse($user["country"], "$country is not a valid country");
    }
} else if (isset($params["email"]) && isset($params["code"])) {
    $email = $params["email"];
    $code = $params["code"];
    $sql = "SELECT * FROM verification_codes WHERE username = '$username' AND code = '$code' AND email = '$email'";
    if (ApiHelper::rowExists($sql)) {
        $sql = "UPDATE users SET email = '$email' WHERE username = '$username'";
        $res = $qh->exec($sql);
        if ($res > 0) {
            echo ApiHelper::buildSuccessResponse($email, "");
        } else if ($res == 0) {
            echo ApiHelper::buildSuccessResponse($user["email"], "No changes made");
        } else {
            echo ApiHelper::buildSuccessResponse($user["email"], "Failed to update email");
        }
    } else {
        echo ApiHelper::buildSuccessResponse($user["email"], "Invalid code or email");
    }
} else {
    echo buildErrorResponse("No changes made");
}