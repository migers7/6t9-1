<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once '../config/database.php';
require __DIR__ . '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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
    public $phone = "";

    private $conn;
    private $tableName = "users";

    public function __construct()
    {
        $this->conn = (new Database())->getConnection();
    }

    private function isUsernameAvailable()
    {
        if (count($this->findByUsername()) > 0) return false;
        return true;
    }

    private function isEmailAvailable()
    {
        if (count($this->findByEmail()) > 0) return false;
        return true;
    }

    public function userExists()
    {
        return !$this->isUsernameAvailable();
    }

    public function add()
    {
        //return buildErrorResponse("User registration is closed. We will be open soon. Thank you!");
        if ($this->isUsernameAvailable()) {
            if ($this->isEmailAvailable()) {
                $this->verificationCode = generateVerificationCode();
                $sql = "insert into "
                    . $this->tableName
                    . "(username, email, password, gender, verificationCode, country, memberSince) "
                    . "values("
                    . "'$this->username',"
                    . "'$this->email',"
                    . "'$this->password',"
                    . "'$this->gender',"
                    . "'$this->verificationCode',"
                    . "'$this->country',"
                    . "'$this->memberSince');";

                if ($this->conn->query($sql) > 0) {
                    $this->sendVerificationEmail();
                    $sql = "INSERT INTO profile(username) VALUES('$this->username')";
                    $this->conn->query($sql);
                    return buildSuccessResponse($this, "Registration completed successfully. A verification code was sent to " . $this->email);
                } else {
                    return buildErrorResponse("Failed to create account. Please try again.");
                }
            } else {
                return buildErrorResponse("This email is already used.");
            }
        } else {
            return buildErrorResponse("This username is already used.");
        }
    }

    public function sendVerificationEmail()
    {
        return $this->email($this->email, "SixT9 Account Verification", "Your verification code is " . $this->verificationCode);
    }

    public function resendCode()
    {
        $this->verificationCode = generateVerificationCode();
        $sql = "update " . $this->tableName . " set verificationCode = '$this->verificationCode' where username = '$this->username';";
        if ($this->sendVerificationEmail() && $this->conn->query($sql) > 0) {
            return buildSuccessResponse($this->findByUsername(), $message = "A verification code was sent to " . $this->email);
        } else {
            return buildErrorResponse("Failed to send email. Please try again.");
        }
    }

    private function findByUsername()
    {
        $sql = "select * from " . $this->tableName . " where username = '$this->username';";
        $res = $this->conn->query($sql)->fetch_assoc();
        if ($res == null) $res = array();
        else {
            $this->removeConfidentialValues($res);
            $res["isVerified"] = (boolean)$res["isVerified"];
        }
        return $res;
    }

    private function findByEmail()
    {
        $sql = "select * from " . $this->tableName . " where email = '$this->email';";
        $res = $this->conn->query($sql)->fetch_assoc();
        if ($res == null) $res = array();
        else $this->removeConfidentialValues($res);
        return $res;
    }

    public function setAsVerified()
    {
        $sql = "update " . $this->tableName . " set isVerified = 1 where username = '$this->username';";
        if ($this->conn->query($sql) > 0) {
            return buildSuccessResponse($this->findByUsername(), "Account activated successfully.");
        } else {
            return buildErrorResponse("Failed to activate account. Please try again.");
        }
    }

    public function login()
    {
        $sql = "select * from " . $this->tableName . " where username = '$this->username' and password = '$this->password';";
        $res = $this->conn->query($sql);
        $res = $res->fetch_assoc();
        if ($res == null) return buildErrorResponse("Incorrect username or password");
        $res["isVerified"] = (boolean)$res["isVerified"];
        $res["isAdmin"] = (boolean)$res["isAdmin"];
        $res["isStaff"] = (boolean)$res["isStaff"];
        $res["isOwner"] = (boolean)$res["isOwner"];
        $res["isMentor"] = (boolean)$res["isMentor"];
        $res["isMerchant"] = (boolean)$res["isMerchant"];
        $res["userLevel"] = (int)$res["userLevel"];
        $res["balance"] = (double)$res["balance"];
        $res = $this->removeConfidentialValues($res);
        return buildSuccessResponse($res, "Logged in successfully.");
    }

    private function removeConfidentialValues($res)
    {
        unset($res["password"]);
        return $res;
    }

    public function email($to, $subject, $body) {
        $mail = new PHPMailer(true);

        try {
            //Server settings
            $mail->SMTPDebug = 1;
            $mail->isSMTP();                                      // Set mailer to use SMTP
            $mail->Host = 'smtp.gmail.com';  // Specify main and backup SMTP servers
            $mail->SMTPAuth = true;                               // Enable SMTP authentication
            $mail->Username = 'sixt9app@gmail.com';                 // SMTP username
            $mail->Password = 'anjaliee764O67';                           // SMTP password
            $mail->SMTPSecure = 'ssl';                            // Enable TLS encryption, `ssl` also accepted
            $mail->Port = 465;                                    // TCP port to connect to

            //Recipients
            $mail->setFrom('sixt9app@gmail.com');
            $mail->addAddress($to);     // Add a recipient

            //Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}