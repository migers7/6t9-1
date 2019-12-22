<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'db_utils.php';
include_once 'account_utils.php';
include_once 'room_utils.php';
include_once 'userUtils.php';
include_once 'profileManager.php';
include_once 'firebaseManager.php';
include_once 'notification_utils.php';

class GiftUtils
{
    private $accountUtils;

    public function __construct()
    {
        $this->accountUtils = new AccountUtils();
    }

    public function gift($username, $text, $roomName, $isPrivate = "false")
    {
        $sql = "SELECT TIME_TO_SEC(TIMEDIFF(NOW(), time_stamp)) diff FROM gift_track WHERE username = '$username'";
        $pdo = getConn();
        $rows = cast($pdo->query($sql));
        $pdo = null;
        if (count($rows) > 0) {
            if ($rows[0]["diff"] < 30) {
                $wait = 30 - $rows[0]["diff"];
                return buildErrorResponse("You have to wait $wait sec to send another gift");
            }
        }
        $words = explode(" ", $text);
        if (count($words) >= 3) {
            $userUtils = new UserUtils();
            $user = $userUtils->findUser($words[1]);
            if ($username == $words[1]) return buildErrorResponse("Invalid command or argument.");
            if ($user != null) {
                $giftStr = substr($text, strlen($words[0] . " " . $words[1]) + 1);
                $tokens = explode(" -m ", $giftStr);
                $giftName = $tokens[0];
                $message = null;
                if (count($tokens) > 1) {
                    $message = $tokens[1];
                }
                $pdo = getConn();
                $sql = "SELECT * FROM gifts WHERE name = '$giftName'";
                $res = cast($pdo->query($sql));
                $pdo = null;
                if (count($res) == 0) return buildErrorResponse("No gift found named " . $giftName);

                $gift = $res[0];
                $userSelf = $this->accountUtils->getBalance($username);
                $balance = $userSelf["balance2"];
                $nonT = true;
                if ($userSelf["spend_from_main_account"]) {
                    $balance = $userSelf["balance"];
                    $nonT = false;
                }
                $cost = $gift["price"];
                if ($cost <= 0.83) return buildErrorResponse("This gift is not available as private gift.");
                if ($balance < $cost) return buildErrorResponse("Your account balance is insufficient to purchase this gift.");
                $pdo = getConn();
                $pdo->exec("INSERT INTO gift_track(username) VALUES('$username') ON DUPLICATE KEY UPDATE time_stamp = NOW()");
                $userNow = $this->accountUtils->deductCreditAndIncreaseLevel($username, $cost, true, $nonT);
                $balance = $userNow["balance"];
                if ($nonT) $balance = $userNow["balance2"];
                $this->accountUtils->addHistory($username, "-", $cost, "Purchased " . $gift["name"] . " for " . $user["username"] . " and spent " . $cost . " BDT");

                (new ProfileManager())->increaseGiftCount($user["username"]);
                if ($gift["price"] > 100) {
                    $reward = $gift["price"] / 10;
                    $transferable = true;
                    if ($nonT) $transferable = false;
                    $this->accountUtils->addCredit($user["username"], $reward, $transferable);
                    $this->accountUtils->addHistory(
                        $user["username"],
                        "+", $reward,
                        "Rewarded " . $reward . " BDT for receiving " . $gift["name"] . " from " . $username
                    );
                }

                $this->increaseGiftSentCount($username, 1);

                $icon = $gift["icon"];
                $color = $gift["color"];

                $x = $this->accountUtils->findUser($username);
                $y = $this->accountUtils->findUser($words[1]);

                $pdo = getConn();
                $activity = "Sent a " . $gift["name"] . " to " . $y["username"];
                $sql = "INSERT INTO activity(username, image_url, time_stamp, text) VALUES('$username', '$icon', NOW(), '$activity')";
                $pdo->exec($sql);
                $pdo = null;

                $text = ["user" => ["username" => $x["username"], "userLevel" => $x["userLevel"]], "name" => $gift["name"], "icon" => $icon, "cost" => $cost, "balance" => 0.00, "receiver" => ["username" => $y["username"], "userLevel" => $y["userLevel"]], "color" => $color, "message" => $message];
                $ref = FirebaseManager::getInstance();
                $node = "chats/";
                if ($isPrivate == "true") $node = "pvt_chats/";
                $ref->getReference($node . $roomName)->push(MessageHelper::getPrivateGiftText($roomName, json_encode($text)));
                $balance = round($balance, 2);

                $noti = "$username sent you a " . $gift["name"] . ".";
                if ($message != null) $noti = $noti . "\nMessage: $message";
                $nu = new NotificationUtils();
                $nu->push($words[1], "6t9", $noti);
                $nu->insertWithImage($words[1], $noti, $icon);
                $nu = null;

                return buildErrorResponse("You have spent $cost BDT. Your remaining balance is $balance BDT.");
            }
            return buildErrorResponse("No user found for username " . $words[1]);
        }
        return buildErrorResponse("Invalid command or argument.");
    }

    private function trackContest($giftName, $count, $roomName)
    {
        if (strtolower($giftName) == "sixt9") {
            $sql = "SELECT owner FROM rooms WHERE name = '$roomName'";
            $pdo = getConn();
            $res = cast($pdo->query($sql));
            if (count($res) > 0) {
                if ($res[0]["owner"] == "sixt9") return;
            } else {
                return;
            }
            $sql = "INSERT INTO contest(room_name, gift_count) VALUES('$roomName', $count) ON DUPLICATE KEY UPDATE gift_count = gift_count + $count";
            $pdo->exec($sql);
            $pdo = null;
        }
    }

    public function increaseGiftSentCount($username, $count)
    {
        $sql = "UPDATE profile SET gift_sent = gift_sent + $count, gift_sent_daily = gift_sent_daily + $count WHERE username = '$username'";
        $pdo = getConn();
        $pdo->exec($sql);
        $pdo = null;
    }

    public function shower($username, $roomName, $text)
    {
        $words = explode(" ", $text);
        if (count($words) < 3) return buildErrorResponse("Invalid command or argument.");
        if ($username == $words[1]) return buildErrorResponse("Invalid command or argument.");
        $diff = $this->getLastGiftShowerTimeDiff2($roomName, $username);
        if ($diff < 30) {
            $diff = 30 - $diff;
            return buildErrorResponse("You need to wait at least " . $diff . " sec to make another gift shower.");
        }
        $giftName = substr($text, strlen("/gift all") + 1);
        $sql = "SELECT * FROM gifts WHERE name = '$giftName'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) == 0) return buildErrorResponse("No gift found named " . $giftName);

        $gift = $res[0];
        $user = $this->accountUtils->getBalance($username);
        $balance = $user["balance2"];
        $nonT = true;
        if ($user["spend_from_main_account"]) {
            $balance = $user["balance"];
            $nonT = false;
        }
        $roomUsers = (new RoomUtils())->getUsersWithToken($roomName);
        $userCount = count($roomUsers);
        if ($userCount < 3) return buildErrorResponse("Not enough users to make a gift shower.");
        $this->updateGiftShowerTime2($roomName, $username);

        $cost = $gift["price"] * ($userCount - 1);
        if ($balance < $cost) return buildErrorResponse("Your account balance is insufficient to make this gift shower.");
        $userNow = $this->accountUtils->deductCreditAndIncreaseLevel($username, $cost, true, $nonT);
        $balance = $userNow["balance"];
        if ($nonT) $balance = $userNow["balance2"];
        $this->accountUtils->addHistory($username, "-", $cost, "Spent " . $cost . " BDT for gift shower in chat room " . $roomName);

        $this->increaseGiftSentCount($username, $userCount - 1);
        // $this->trackContest($giftName, $userCount - 1, $roomName);

        $receivers = $this->getReceivers($roomUsers, $username);
        $profileManager = new ProfileManager();
        $nu = new NotificationUtils();
        $rewardTransferable = true;
        if ($nonT) $rewardTransferable = false;
        $icon = $gift["icon"];
        foreach ($roomUsers as $roomUser) {
            if ($roomUser["username"] != $username) {
                $profileManager->increaseGiftCount($roomUser["username"]);
                if ($gift["price"] > 100) {
                    $reward = $gift["price"] / 10;
                    $this->accountUtils->addCredit($roomUser["username"], $reward, $rewardTransferable);
                    $this->accountUtils->addHistory($roomUser["username"],
                        "+", $reward,
                        "Rewarded " . $reward . " BDT for receiving " . $gift["name"] . " from " . $username
                    );
                }
                $noti = "$username sent you a " . $gift["name"] . " in $roomName chat room.";
                $nu->pushWithToken($roomUser["username"], $roomUser["token"], "6t9", $noti);
                $nu->insertWithImage($roomUser["username"], $noti, $icon);
            }
        }
        $nu = null;
        $color = $gift["color"];

        $pdo = getConn();
        $activity = "Showered " . $gift["name"] . " in $roomName chat room";
        $sql = "INSERT INTO activity(username, image_url, time_stamp, text) VALUES('$username', '$icon', NOW(), '$activity')";
        $pdo->exec($sql);
        $pdo = null;

        $x = $this->accountUtils->findUser($username);
        $text = ["username" => $username, "user" => ["userLevel" => $x["userLevel"]], "name" => $gift["name"], "icon" => $icon, "cost" => $cost, "balance" => 0.00, "receivers" => $receivers, "color" => $color];
        $ref = FirebaseManager::getInstance();
        $ref->getReference("chats/" . $roomName)->push(MessageHelper::getGiftShowerText($roomName, json_encode($text)));
        $balance = round($balance, 2);
        return buildErrorResponse("You have spent $cost BDT. Your remaining balance is $balance BDT.");
    }

    public function updateGiftShowerTime2($roomName, $username)
    {
        $id = $username . "#" . $roomName;
        $timeStamp = date("Y-m-d H:i:s", time());
        $sql = "INSERT INTO gift_shower_track(id, time_stamp) VALUES('$id', '$timeStamp') ON DUPLICATE KEY UPDATE time_stamp = '$timeStamp'";
        $pdo = getConn();
        $pdo->beginTransaction();
        $res = $pdo->exec($sql);
        $pdo->commit();
        $pdo = null;
        return $res;
    }

    public function getLastGiftShowerTimeDiff2($roomName, $username)
    {
        $id = $username . "#" . $roomName;
        $sql = "SELECT * FROM gift_shower_track WHERE id = '$id' FOR UPDATE ";
        $pdo = getConn();
        $pdo->beginTransaction();
        $res = cast($pdo->query($sql));
        $pdo->commit();
        $pdo = null;
        if (count($res) > 0) {
            $curtime = time();
            $time = strtotime($res[0]["time_stamp"]);
            return $curtime - $time;
        }
        return 40;
    }

    public function getReceivers($roomUsers, $username)
    {
        $userNames = array();
        foreach ($roomUsers as $user) {
            if ($username != $user["username"]) {
                $userNames[] = $user["username"];
            }
        }
        $count = count($userNames);
        $str = "";
        if ($count > 5) {
            for ($i = 0; $i < 5; $i++) {
                if ($i > 0) $str = $str . ", ";
                $str = $str . $userNames[$i];
            }
            $str = $str . " and " . ($count - 5) . " others";

        } else {
            for ($i = 0; $i < $count; $i++) {
                if ($i == $count - 1) {
                    $str = $str . " and " . $userNames[$i];
                } else {
                    if ($i > 0) $str = $str . ", ";
                    $str = $str . $userNames[$i];
                }
            }
        }
        return $str;
    }

    public function getGiftList($name = "", $id = 0)
    {
        $sql = "SELECT * FROM gifts WHERE name LIKE '%$name%' AND id > '$id' ORDER BY id ASC LIMIT 15";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        return buildSuccessResponse($res);
    }
}
