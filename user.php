<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

require __DIR__ . '/vendor/autoload.php';
include_once 'config.php';
include_once 'EmailUtils.php';

class User
{
    public $username;
    public $email;
    public $password;
    public $fullName = "";
    public $gender = "";
    public $memberSince;
    public $isVerified = false;
    public $birthday = "";
    public $country = "";
    public $verificationCode = "";
    public $level = 1;
    public $deviceId = "";
    public $appId = "";
    public $phone = "";

    private $tableName = "users";

    public function validateUsername($username)
    {
        $username = strtolower($username);
        if (strlen($username) < 6 || strlen($username) > 20) return false;
        $allowedChars = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'];
        if (in_array($username[0], $allowedChars) == false) return false;
        $allowedDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $allowedSymbols = ['.', '_', '-'];
        for ($i = 0; $i < strlen($username); $i++) {
            if (in_array($username[$i], $allowedChars) || in_array($username[$i], $allowedDigits) || in_array($username[$i], $allowedSymbols)) ;
            else return false;
        }
        return true;
    }


    public function isUsernameAvailable()
    {
        if (count($this->findByUsername()) > 0) return false;
        return $this->validateUsername($this->username);
    }

    private function isEmailAvailable()
    {
        $domains = ["gmail.com", "yahoo.com", "ymail.com", "hotmail.com", "live.com", "outlook.com", "icloud.com"];
        $arr = explode("@", $this->email);
        if (count($arr) != 2) return "Invalid email address";
        if ($arr[1] == "gmail.com") {
            $basic = $arr[0];
            $basic = str_replace(".", "", $basic);
            $basic = str_replace("+", "", $basic);
            $this->email = $basic . "@gmail.com";
        }
        if (filter_var($this->email, FILTER_VALIDATE_EMAIL) == false) return "Invalid email address";
        if (in_array($arr[1], $domains) == false) return "We do not support email address of this domain. Please use gmail, yahoo, ymail, hotmail, live, outlook or icloud.";
        if (count($this->findByEmail()) > 0) return "This email address is already in use.";
        return null;
    }

    public function userExists()
    {
        return !$this->isUsernameAvailable();
    }

    public function add()
    {
        //return ApiHelper::buildErrorResponse("User registration is closed. We will be open soon. Thank you!");
        $this->username = strtolower($this->username);
        if ($this->isUsernameAvailable()) {
            $emailRes = $this->isEmailAvailable();
            if ($emailRes == null) {
                $this->email = strtolower($this->email);
                $this->verificationCode = ApiHelper::generateVerificationCode();
                $sql = "INSERT INTO "
                    . $this->tableName
                    . "(username, email, password, gender, verificationCode, country, memberSince) "
                    . "VALUES("
                    . "'$this->username',"
                    . "'$this->email',"
                    . "'$this->password',"
                    . "'$this->gender',"
                    . "'$this->verificationCode',"
                    . "'$this->country',"
                    . "'$this->memberSince');";

                if ($this->sendVerificationEmail()) {
                    $pdo = ApiHelper::getInstance();
                    $created = $pdo->query($sql);
                    $pdo = null;
                    if ($created > 0) {
                        $sql = "INSERT INTO profile(username) VALUES('$this->username')";
                        $pdo = ApiHelper::getInstance();
                        $pdo->exec($sql);
                        $sql = "INSERT INTO verification_codes(username, code) VALUES('$this->username', '$this->verificationCode')";
                        $pdo->exec($sql);
                        $pdo = null;
                        $this->verificationCode = "";
                        return ApiHelper::buildSuccessResponse($this, "Registration completed successfully. A verification code was sent to " . $this->email);
                    } else {
                        return ApiHelper::buildErrorResponse("Failed to create account. Please try again.");
                    }
                } else {
                    return ApiHelper::buildErrorResponse("Failed to send verification email to " . $this->email . ". Unable to create account.");
                }
            } else {
                return ApiHelper::buildErrorResponse($emailRes);
            }
        } else {
            return ApiHelper::buildErrorResponse("This username is already used or invalid. Please enter a username within 6-20 characters");
        }
    }

    public function sendVerificationEmail()
    {
        return EmailUtils::emailTo($this->email, "SixT9 Account Verification", "You just created an account with username '$this->username'. Your verification code is " . $this->verificationCode);
    }

    public function resendCode()
    {
        $this->verificationCode = ApiHelper::generateVerificationCode();
        $sql = "UPDATE users SET verificationCode = '$this->verificationCode' WHERE username = '$this->username';";
        $pdo = ApiHelper::getInstance();
        $updated = (boolean)($pdo->query($sql) > 0);
        $pdo = null;
        if ($this->sendVerificationEmail() && $updated) {
            $pdo = ApiHelper::getInstance();
            $sql = "INSERT INTO verification_codes(username, code) VALUES('$this->username', '$this->verificationCode')";
            $pdo->query($sql);
            $pdo = null;
            return ApiHelper::buildSuccessResponse($this->findByUsername(), $message = "A verification code was sent to " . $this->email);
        } else {
            return ApiHelper::buildErrorResponse("Failed to send email. Please try again.");
        }
    }

    private function findByUsername()
    {
        $sql = "SELECT * FROM " . $this->tableName . " WHERE username = '$this->username';";
        $pdo = ApiHelper::getInstance();
        $rows = ApiHelper::cast($pdo->query($sql));
        $pdo = null;
        $res = array();
        if (count($rows) > 0) {
            $res = $rows[0];
            $res = $this->removeConfidentialValues($res);
        }
        return $res;
    }

    private function findByEmail()
    {
        $sql = "SELECT * FROM " . $this->tableName . " WHERE email = '$this->email';";
        $pdo = ApiHelper::getInstance();
        $rows = ApiHelper::cast($pdo->query($sql));
        if (count($rows) == 0) return array();
        $res = $rows[0];
        $res = $this->removeConfidentialValues($res);
        return $res;
    }

    public function setAsVerified()
    {
        $sql = "UPDATE users SET isVerified = 1 WHERE username = '$this->username';";
        $pdo = ApiHelper::getInstance();
        $activated = $pdo->query($sql);
        $pdo = null;
        if ($activated > 0) {
            return ApiHelper::buildSuccessResponse($this->findByUsername(), "Account activated successfully.");
        } else {
            return ApiHelper::buildErrorResponse("Failed to activate account. Please try again.");
        }
    }

    public function login()
    {
        $pdo = ApiHelper::getInstance();
        $sql = "SELECT * FROM " . $this->tableName . " WHERE username = '$this->username' and (password = '$this->password' OR temp_password = '$this->password');";
        $rows = ApiHelper::cast($pdo->query($sql));
        if (count($rows) == 0) {
            $pdo = null;
            return ApiHelper::buildErrorResponse("Incorrect username or password");
        }
        $res = $rows[0];
        $token = ApiHelper::generateVerificationCode(255);
        $sql = "UPDATE users SET accessToken = '$token' WHERE username = '$this->username'";
        $pdo->query($sql);
        $sql = "INSERT INTO device(username, device_id, app_id) VALUES('$this->username', '$this->deviceId', '$this->appId') ON DUPLICATE KEY UPDATE device_id = '$this->deviceId', app_id = '$this->appId'";
        $pdo->query($sql);
        $res["accessToken"] = $token;
        $res = $this->removeConfidentialValues($res);

        return ApiHelper::buildSuccessResponse($res, "Logged in successfully.");
    }

    private function removeConfidentialValues($res)
    {
        unset($res["password"]);
        unset($res["verificationCode"]);
        unset($res["token"]);
        return $res;
    }
}

