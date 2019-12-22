<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'db_utils.php';
include_once 'userUtils.php';

class AccountUtils
{
    public function __construct()
    {
    }

    public function getBalance($username)
    {
        $pdo = getConn();
        $sql = "SELECT * from users WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $sql = "SELECT id FROM merchant_pin WHERE username = '$username'";
        $count = count(cast($pdo->query($sql)));
        $pdo = null;
        $res = cast($stmt)[0];
        unset($res["password"]);
        $res["pinSet"] = (boolean)($count > 0);
        return $res;
    }

    public function getUser($username)
    {
        $pdo = getConn();
        $sql = "SELECT * from users WHERE username = '$username'";
        $res = cast($pdo->query($sql));
        if (count($res) > 0) {
            $user = $res[0];
            unset($user["email"]);
            unset($user["merchantSince"]);
            unset($user["mentor"]);
            unset($user["password"]);
            unset($user["accessToken"]);
            unset($user["token"]);
            unset($user["balance"]);
            unset($user["balance2"]);
            unset($user["creditSpent"]);
            unset($user["daily_spent"]);
            unset($user["revenue"]);
            unset($user["verificationCode"]);
            unset($user["temp_password"]);
            return $user;
        }
        return null;
    }

    public function getBalanceAmount($username)
    {
        $res = $this->getBalance($username);
        return $res["balance"] + $res["balance2"];
    }

    public function userExists($username)
    {
        $pdo = getConn();
        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $pdo = null;
        if ($stmt->rowCount() > 0) return true;
        return false;
    }

    public function addCredit($user, $amount, $transferrable = false)
    {
        $pdo = getConn();
        $done = 0;
        if ($transferrable) {
            $sql = "UPDATE users SET balance = balance + $amount WHERE username = '$user'";
            $done = $pdo->exec($sql);
        } else {
            $sql = "UPDATE users SET balance2 = balance2 + $amount WHERE username = '$user' AND spend_from_main_account = 0";
            $done = $pdo->exec($sql);
        }
        if (!$done) {
            $sql = "INSERT INTO pending_transactions(username, amount) VALUES ('$user', $amount)";
            $pdo->exec($sql);
        }
        $pdo = null;
    }

    public function addCreditNonTransferable($user, $amount)
    {
        $pdo = getConn();
        $sql = "UPDATE users SET balance2 = balance2 + ? WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$amount, $user]);
        $pdo = null;
    }

    public function deductCredit($user, $amount, $nonTransferable = false)
    {
        $pdo = getConn();
        $sql = "UPDATE users SET balance = balance - ? WHERE username = ?";
        if ($nonTransferable) $sql = "UPDATE users SET balance2 = balance2 - ? WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$amount, $user]);
        $pdo = null;
    }

    public function increaseLevel($user, $amount, $compress = false)
    {
        $actualAmount = $amount;
        $threshold = 10;
        if ($compress) {
            if ($amount > $threshold) $amount = min($amount / 10, 1000);
        }
        if ($amount > 1000) $amount = 1000;
        $pdo = getConn();
        $sql = "UPDATE users SET creditSpent = creditSpent + $amount WHERE username = '$user'";
        $pdo->exec($sql);
        $sql = "UPDATE users SET daily_spent = daily_spent + $actualAmount WHERE username = '$user'";
        $pdo->exec($sql);
        $pdo = null;
        $userNow = $this->getBalance($user);
        $level = $userNow["userLevel"];
        $amount = $userNow["creditSpent"];
        if ($level < 10) {
            if ($amount > 5.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        } else if ($level < 20) {
            if ($amount > 100.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        } else if ($level < 30) {
            if ($amount > 1000.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        } else if ($level < 40) {
            if ($amount > 1200.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        } else if ($level < 50) {
            if ($amount > 1500.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        } else if ($level < 60) {
            if ($amount > 1800.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        } else if ($level < 70) {
            if ($amount > 2000.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        } else if ($level < 80) {
            if ($amount > 2500.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        } else if ($level < 500) {
            if ($amount > 4000.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        } else if ($level >= 500) {
            if ($amount > 50000.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        }
        return $this->getBalance($user);
    }

    public function deductCreditAndIncreaseLevel($user, $amount, $compress = false, $nonTransferable = false)
    {
        $actualAmount = $amount;
        $sql = "UPDATE users SET balance = balance - ? WHERE username = ?";
        if ($nonTransferable) $sql = "UPDATE users SET balance2 = balance2 - ? WHERE username = ?";
        $pdo = getConn();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$actualAmount, $user]);
        $threshold = 10;
        if ($compress) {
            if ($amount > $threshold) $amount = min($amount / 10, 100);
        }
        $sql = "UPDATE users SET creditSpent = creditSpent + ? WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$amount, $user]);
        $sql = "UPDATE users SET daily_spent = daily_spent + ? WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$actualAmount, $user]);
        $pdo = null;
        $userNow = $this->getBalance($user);
        $level = $userNow["userLevel"];
        $amount = $userNow["creditSpent"];
        if ($level < 10) {
            if ($amount > 5.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        } else if ($level < 20) {
            if ($amount > 100.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        } else if ($level < 30) {
            if ($amount > 1000.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        } else if ($level < 40) {
            if ($amount > 1200.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        } else if ($level < 50) {
            if ($amount > 1500.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        } else if ($level < 60) {
            if ($amount > 1800.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        } else if ($level < 70) {
            if ($amount > 2000.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        } else if ($level < 80) {
            if ($amount > 2500.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        } else if ($level < 500) {
            if ($amount > 4000.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        } else if ($level >= 500) {
            if ($amount > 50000.00) return $this->resetCreditSpentAndIncreaseLevel($user);
        }
        return $this->getBalance($user);
    }

    public function findUser($username)
    {
        $username = strtolower($username);
        $pdo = getConn();
        $sql = " SELECT * FROM users WHERE username = '$username'";
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) {
            $user = $res[0];
            unset($user["email"]);
            unset($user["merchantSince"]);
            unset($user["mentor"]);
            unset($user["password"]);
            unset($user["accessToken"]);
            unset($user["token"]);
            unset($user["balance"]);
            unset($user["balance2"]);
            unset($user["creditSpent"]);
            unset($user["daily_spent"]);
            unset($user["revenue"]);
            unset($user["verificationCode"]);
            unset($user["temp_password"]);
            return $user;
        }
        return null;
    }

    public function resetCreditSpentAndIncreaseLevel($username)
    {
        $pdo = getConn();
        $sql = "UPDATE users SET userLevel = userLevel + 1 WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $sql = "UPDATE users SET creditSpent = 0.00 WHERE username = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $pdo = null;
        $user = $this->getBalance($username);
        if ($user["userLevel"] % 10 == 0) {
            $pdo = getConn();
            $sql = "UPDATE rooms SET capacity = capacity + 10 WHERE owner = '$username' and capacity < 80";
            $pdo->exec($sql);
            $pdo = null;
        }
        return $user;
    }

    public function updateChatRoomCapacityIfNecessary($username)
    {
        $pdo = getConn();
        $user = $this->findUser($username);
        if ($user["userLevel"] % 10 == 0) {
            $sql = "UPDATE rooms SET capacity = capacity + 10 WHERE owner = '$username' and capacity < 80";
            $pdo->exec($sql);
        }
        $pdo = null;
    }

    public function addHistory($user, $type, $amount, $description = "", $interactor = "")
    {
        $pdo = getConn();
        $sql = "SELECT balance FROM users WHERE username = '$user'";
        $rows = cast($pdo->query($sql));
        $balance = -1;
        if (count($rows) > 0) {
            $balance = $rows[0]["balance"];
        }
        $sql = "INSERT INTO account_history(username, type, amount, description, interactor, balance) VALUES(?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user, $type, $amount, $description, $interactor, $balance]);
        $pdo = null;
    }

    public function transferCredit($fromName, $toName, $amount, $pin = "ab", $tag = "false")
    {
        if ($this->userExists($toName) == false) return buildErrorResponse("No user found for username $toName");
        if (strlen($pin) != 2) {
            if (strlen($pin) != 6 || is_numeric($pin) == false) return buildErrorResponse("Invalid pin");
            $sql = "SELECT pin FROM merchant_pin WHERE username = '$fromName'";
            $pdo = getConn();
            $pinRes = cast($pdo->query($sql));
            if (count($pinRes) == 0) {
                return buildErrorResponse("No pin set. Please go to Settings and set a pin.");
            }
            if ($pinRes[0]["pin"] != $pin) {
                return buildErrorResponse("Pin does not match.");
            }
        }
        $shouldTag = (boolean)($tag == "true");
        if ($amount < 0) return buildErrorResponse("Invalid amount");
        if ($fromName == $toName) return buildErrorResponse("Cannot transfer credit to yourself");
        $tagAmount = 415;
        $from = $this->getBalance($fromName);
        $to = $this->getBalance($toName);
        if ($to == null) {
            return buildErrorResponse("No user found for username " . $toName);
        }
        if ($from["balance"] - 41.95 < $amount) {
            return buildErrorResponse("Keep at least 41.95 BDT in your account");
        }
        $fromIsAdmin = $from["isStaff"] == true || $from["isOwner"] == true;
        $toIsAdmin = $to["isStaff"] == true || $to["isOwner"] == true || $to["isAdmin"] == true;
        $fromIsMentor = $from["isMentor"] == true;
        $fromIsMerchant = $from["isMerchant"] == true;
        $toIsMentor = $to["isMentor"] == true;
        $toIsMerchant = $to["isMerchant"] == true;
        $fromIsBlue = $fromIsMerchant == false && $fromIsMentor == false && $fromIsAdmin == false;
        $toIsBlue = $toIsMentor == false && $toIsMerchant == false && $toIsAdmin == false;
        $mentor = $to["mentor"];

        $sql = "SELECT * FROM transfer_limit WHERE id = '$fromName'";
        $pdo = getConn();
        $rows = cast($pdo->query($sql));
        $didTransfer = 0;
        if(count($rows) > 0) {
            $didTransfer = $rows[0]["daily"];
        }
        $didTransfer += $amount;

        if($fromIsBlue && $toIsBlue) {
            $canTransfer = 100000 - $amount;
            if($canTransfer < 0) $canTransfer = 0;
            if($didTransfer > 100000) {
                return buildErrorResponse("You can transfer at most 100000 BDT in total per day. The amount you wanted to transfer exceeds the limit after the transaction ($didTransfer). You may transfer at most $canTransfer BDT before tonight 00:00 AM (GMT + 6)");
            }
        }

        if ($fromIsMerchant && $toIsMentor) {
            return buildErrorResponse("Unable to transfer credits.");
        }
        if ($fromIsBlue && ($toIsMentor || $toIsMerchant)) {
            return buildErrorResponse("Unable to transfer credits.");
        }

        if($fromIsMerchant) {
            $canTransfer = 500000 - $amount;
            if($canTransfer < 0) $canTransfer = 0;
            if($didTransfer > 500000) {
                return buildErrorResponse("You can transfer at most 500000 BDT in total per day. The amount you wanted to transfer exceeds the limit after the transaction ($didTransfer). You may transfer at most $canTransfer BDT before tonight 00:00 AM (GMT + 6)");
            }
        }

        // transfer starts
        $this->addCredit($toName, $amount, true);
        $this->addHistory(
            $fromName,
            "-",
            $amount,
            "Transferred " . $amount . " BDT to " . $toName,
            $toName
        );
        $this->deductCredit($fromName, $amount);
        $this->addHistory(
            $toName,
            "+",
            $amount,
            "Received " . $amount . " BDT from " . $fromName,
            $fromName
        );
        $sql = "INSERT INTO transfer_limit(id, daily, monthly) VALUES ('$fromName', $amount, $amount) ON DUPLICATE KEY UPDATE daily = daily + $amount, monthly = monthly + $amount";
        $pdo->exec($sql);
        $pdo = null;
        // transfer ends
        if ($amount < $tagAmount) $shouldTag = false;

        $canBeMentor = ($fromIsMentor || $fromIsMerchant) && $toIsAdmin == false;
        if ($canBeMentor && $shouldTag) {
            if ($toIsBlue && $mentor == '') {
                if ($this->tag($toName, $fromName) && $amount >= 415)
                    return buildSuccessResponse($this->getBalance($fromName), "Credits transferred and user tagged successfully.");
            }
            return buildSuccessResponse($this->getBalance($fromName), "Credits transferred successfully. Unable to tag the user. Either you have reached the maximum number of users you can tag a day which is 4 or the user is already tagged by someone else.");
        }
        return buildSuccessResponse($this->getBalance($fromName), "Credits transferred successfully.");
    }

    public function tag($username, $mentor)
    {
        $pdo = getConn();
        $sql = "SELECT username FROM users WHERE isMerchant = 0 AND mentor = '$mentor'";
        $res = cast($pdo->query($sql));
        if (count($res) >= 20) {
            $pdo = null;
            return false;
        }
        // set timezone to bangladesh
        date_default_timezone_set('Asia/Dhaka');
        $since = date("Y-m-d H:i:s", time());
        $substr = substr($since, 0, 10);
        $sql = "SELECT id FROM users WHERE isMerchant = 0 AND isMentor = 0 AND mentor = '$mentor' AND merchantSince LIKE '$substr%'";
        $rows = cast($pdo->query($sql));
        if (count($rows) >= 4) {
            $pdo = null;
            return false;
        }
        $sql = "UPDATE users SET mentor = '$mentor', merchantSince = '$since' WHERE username = '$username'";
        $pdo->exec($sql);
        $pdo = null;
        return true;
    }


    public function getCurrentBalance($username)
    {
        $res = $this->getBalance($username);
        $pdo = getConn();
        $sql = "SELECT id FROM merchant_pin WHERE username = '$username'";
        $count = count(cast($pdo->query($sql)));
        $pdo = null;
        $res["pinSet"] = (boolean)($count > 0);
        return buildSuccessResponse($res);
    }

    public function getAccountHistory($username)
    {
        $res = $this->getBalance($username);
        $pdo = getConn();
        $sql = "SELECT * FROM account_history WHERE username = '$username' ORDER BY id DESC LIMIT 100";
        $history = cast($pdo->query($sql));
        $pdo = null;
        $res["account_history"] = $history;
        return buildSuccessResponse($res);
    }
}
