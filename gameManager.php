<?php

/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 9/23/2018
 * Time: 3:31 PM
 */

include_once 'db_utils.php';
include_once 'notification_utils.php';
include_once 'room_utils.php';
include_once 'account_utils.php';
include_once 'firebaseManager.php';
include_once 'lowCardBot.php';
include_once 'adminUtils.php';
include_once 'CricketBot.php';

class GameManager
{
    private $roomUtils;
    private $gameNames;
    private $accountUtils;

    public function __construct()
    {
        $this->roomUtils = new RoomUtils();
        $this->accountUtils = new AccountUtils();
        $this->gameNames = ["lowcard", "dice"]; // If any new game feature is done, add that game name to this array
    }

    public function getBotName($roomName)
    {
        $pdo = getConn();
        $sql = "SELECT * FROM rooms WHERE name = '$roomName'";
        $res = cast($pdo->query($sql));
        $pdo = null;
        return $res[0]["bot"];
    }

    public function join($username, $roomName)
    {
        $botName = $this->getBotName($roomName);
        if ($botName == "lowcard") return (new SmartLowCardBot())->join($username, $roomName);
        else if ($botName == "cricket") return (new CricketBot())->join($username, $roomName);
        return buildErrorResponse("There is currently no game running in this chat room. Type !start to start a new game.");
    }

    public function draw($username, $roomName)
    {
        $botName = $this->getBotName($roomName);
        if ($botName == "lowcard") return (new SmartLowCardBot())->drawByUser($username, $roomName);
        else if ($botName == "cricket") return (new CricketBot())->bat($username, $roomName);
        return buildErrorResponse("There is currently no game running in this chat room. Type !start to start a new game.");
    }

    public function startProcess($roomName, $username, $amount = 5)
    {
        $botName = $this->getBotName($roomName);
        $botName = strtolower($botName);
        if ($botName == "dice") {
            $allowed = ["Dice", "Dice 2", "Dice 3", "Dice 4", "Dice 5", "Goblins from Mars", "Admins Portal"];
            if (in_array($roomName, $allowed) == false) {
                return buildErrorResponse("For playing dice, join official dice chat rooms.");
            }
            return (new DiceManager())->startProcess($roomName);
        }
        if ($botName == "lowcard") {
            $allowed = ["LowCard Bot", "LowCard Bot 2", "LowCard Bot 3", "LowCard Bot 4", "LowCard Bot 5", "Goblins from Mars", "Admins Portal", "LowCard Giant"];
            if (in_array($roomName, $allowed) == false) {
                return buildErrorResponse("For playing LowCard, join official LowCard chat rooms.");
            }
            if($roomName == "LowCard Giant") {
                if($amount < 1000) {
                    return buildErrorResponse("Start minimum 1000 BDT here");
                }
            }
            try {
                (new SmartLowCardBot())->run($username, $roomName, $amount);
                return buildSuccessResponse("Game finished successfully!");
            } catch (Exception $e) {
                return buildErrorResponse($e->getMessage());
            }
        }
        if ($botName == "cricket") {
            $allowed = ["Cricket Bot", "Cricket Bot 2", "Cricket Bot 3", "Cricket Bot 4", "Cricket Bot 5", "Goblins from Mars", "Admins Portal", "Cricket Giant"];
            if (in_array($roomName, $allowed) == false) {
                return buildErrorResponse("For playing Cricket, join official Cricket chat rooms.");
            }
            if($roomName == "Cricket Giant") {
                if($amount < 1000) {
                    return buildErrorResponse("Start minimum 1000 BDT here");
                }
            }
            try {
                (new CricketBot())->run($username, $roomName, $amount);
                return buildSuccessResponse("Game finished successfully!");
            } catch (Exception $e) {
                return buildErrorResponse($e->getMessage());
            }
        }
        return buildErrorResponse("No game to start");
    }
}

class DiceManager
{
    private $notificationUtils;
    private $roomUtils;
    private $accountUtils;
    private $groups = ["r" => "Ram", "s" => "Sham", "j" => "Jodu", "m" => "Modhu", "a" => "Alal", "d" => "Dulal"];
    private $ref;

    public function query($sql)
    {
        $pdo = getConn();
        $pdo->beginTransaction();
        $rows = cast($pdo->query($sql));
        $pdo->commit();
        return $rows;
    }

    public function exec($sql)
    {
        $pdo = getConn();
        $pdo->beginTransaction();
        $done = $pdo->exec($sql);
        $pdo->commit();
        return $done;
    }

    public function countRows($sql)
    {
        return count($this->query($sql));
    }

    public function __construct()
    {
        $this->notificationUtils = new NotificationUtils();
        $this->roomUtils = new RoomUtils();
        $this->accountUtils = new AccountUtils();
        $this->ref = FirebaseManager::getInstance();
    }

    public function startProcess($roomName)
    {
        $delay = mt_rand(1000, 1000000);
        usleep($delay);

        if ($this->isGameRunning($roomName)) return buildErrorResponse("A game is in progress. Please wait till it is over.");

        $sql = "SELECT * FROM rooms WHERE name = '$roomName'";
        $roomInfo = $this->query($sql)[0];
        if ($roomInfo["bot"] == null || $roomInfo["bot"] == "") {
            return buildErrorResponse("No bot added to the room.");
        }
        $timeOfGame = time();
        $time_stamp = date("Y-m-d H:i:s", $timeOfGame);

        $gameId = mt_rand(100, 1000000);

        $sql = "INSERT INTO game(gameName, roomName, time_stamp, gameId) VALUES ('dice', '$roomName', '$time_stamp', '$gameId')";
        $started = $this->exec($sql);
        if (!$started) return buildErrorResponse("Unable to start game");
        $this->ref->getReference("chats/" . $roomName)->push(
            MessageHelper::getDiceStartedText($roomName, "Dice game started [$time_stamp]. Type !b [group] [amount] to bid. Available groups are:\n1Jodu 2Modhu 3Ram 4Sham 5Alal 6Dulal. [40 seconds]")
        );

        sleep(5);
        $sql = "SELECT id, gameId FROM game WHERE roomName = '$roomName' AND gameName = 'dice' FOR UPDATE";
        $rows = $this->query($sql);
        if (count($rows) == 0) {
            return buildErrorResponse("Bot has been stopped.");
        }
        if (count($rows) != 1) {
            $adminUtils = new AdminUtils();
            $adminUtils->stopBot($roomName, "sixt9");
            sleep(3);
            $adminUtils->addBot("dice", $roomName);
            return buildErrorResponse("An error occurred");
        }

        if (count($rows) == 1) {
            if ($gameId != $rows[0]["gameId"]) {
                $adminUtils = new AdminUtils();
                $adminUtils->stopBot($roomName, "sixt9");
                sleep(3);
                $adminUtils->addBot("dice", $roomName);
                return buildErrorResponse("An error occurred");
            }
        } else {
            $adminUtils = new AdminUtils();
            $adminUtils->stopBot($roomName, "sixt9");
            sleep(3);
            $adminUtils->addBot("dice", $roomName);
            return buildErrorResponse("An error occurred");
        }

        sleep(40);
        if (!$this->isGameRunning($roomName)) return buildErrorResponse("Bot has been stopped.");

        // prevent users from bidding
        $this->exec("UPDATE game SET state = 1 WHERE roomName = '$roomName'");

        // tell users that bot is calculating result
        sleep(3);
        $this->ref->getReference("chats/$roomName")->push(MessageHelper::getRoomInfo(
            $roomName, "Dice is calculating result. This may take a while depending on the number of bids and players. Please wait …"));

        // find results
        $mults = [
            [],
            [],
            [2],
            [2, 3, 2, 2, 3, 2, 2, 2, 2, 3, 3, 2, 2, 3],
            [2, 3, 4, 2, 3, 4, 2, 3, 2, 2, 3, 2, 3, 4, 2, 2, 2, 3, 2, 3, 3, 2, 2, 4, 4, 2, 4],
            [2, 3, 4, 2, 3, 4, 2, 3, 5, 2, 2, 5, 3, 2, 5, 3, 4, 2, 2, 2, 5, 5, 3, 2, 3, 3, 2, 2, 4, 4, 2, 4, 2, 5, 3, 2, 5, 3, 4, 2],
            [2, 3, 4, 2, 3, 6, 4, 2, 3, 5, 2, 6, 2, 5, 3, 2, 5, 3, 4, 2, 6, 2, 2, 5, 5, 3, 6, 2, 3, 3, 2, 2, 4, 4, 2, 4, 6, 6]
        ];
        $N = [1, 1, 2, 1, 2, 3, 2, 1, 1, 2, 1, 2, 1, 2, 2, 2, 1, 3, 3, 1, 3, 2, 2, 1, 1, 2, 1, 1, 1, 2, 3, 1, 2, 2];
        $n = $N[mt_rand(0, count($N) - 1)];
        $groupNames = ["Ram", "Sham", "Jodu", "Modhu", "Alal", "Dulal"];
        $shuffled = $groupNames;
        $no = mt_rand(3, 10);
        for ($s = 0; $s < $no; $s++) {
            shuffle($shuffled);
        }
        $max = 6 / $n;
        $roll = array();
        $size = count($mults[$max]) - 1;
        $rollText = "";
        for ($i = 0; $i < $n; $i++) {
            $mul = $mults[$max][mt_rand(0, $size)];
            $dice = $shuffled[$i];
            $roll[] = ["group" => $dice, "mul" => $mul];
            if ($i > 0) $rollText .= ", ";
            $rollText .= "$dice $mul" . "x";
        }

        $sql = "INSERT INTO dice_result(roomName, dice_timestamp, result) VALUES ('$roomName', '$time_stamp', '$rollText')";
        $this->exec($sql);

        $result["result"] = $roll;
        $resText = array();
        $j = 0;
        for ($i = 0; $i < count($roll); $i++) {
            for ($k = 0; $k < $roll[$i]["mul"]; $k++) {
                $resText[] = $roll[$i]["group"];
                $j++;
            }
        }
        for (; $j < 6; $j++) {
            for ($i = 0; $i < 6; $i++) {
                if (!in_array($groupNames[$i], $resText)) {
                    $resText[] = $groupNames[$i];
                    break;
                }
            }
        }
        shuffle($resText);

        $winners = array();
        $winnerNames = array();
        $playerNames = $this->query("SELECT DISTINCT username FROM dice WHERE roomName = '$roomName'");

        for ($k = 0; $k < count($playerNames); $k++) {
            $player = $playerNames[$k]["username"];
            $bids = $this->query("SELECT amount, groupName, transferable FROM dice WHERE roomName = '$roomName' AND username = '$player'");

            for ($i = 0; $i < $n; $i++) {
                $rolledGroup = $roll[$i]["group"];
                $bidAmount = 0;
                $bidAmountNonTransferable = 0;
                for ($b = 0; $b < count($bids); $b++) {
                    if (strcmp(strtolower($bids[$b]["groupName"]), strtolower($rolledGroup)) == 0) {
                        if($bids[$b]["transferable"]) $bidAmount += $bids[$b]["amount"];
                        else $bidAmountNonTransferable += $bids[$b]["amount"];
                    }
                }
                if ($bidAmount < 5 && $bidAmountNonTransferable < 5) continue;
                $winAmount = $bidAmount * ($roll[$i]["mul"] + 1) * 0.9;
                $winAmountNonTransferable = $bidAmountNonTransferable * ($roll[$i]["mul"] + 1) * 0.9;
                $winner = array();
                $winner["winAmount"] = round($winAmount + $winAmountNonTransferable, 1);
                $winner["username"] = $player;
                $winner["bidAmount"] = $bidAmount + $bidAmountNonTransferable;
                $winner["group"] = $rolledGroup;
                $winners[] = $winner;
                $this->accountUtils->addCredit($player, $winAmount, true);
                $this->accountUtils->addCredit($player, $winAmountNonTransferable, false);
                $winAmount += $winAmountNonTransferable;
                $bidAmount += $bidAmountNonTransferable;
                $this->accountUtils->addHistory(
                    $player,
                    "+",
                    $winAmount,
                    "Won $winAmount BDT from Dice game for placing $bidAmount BDT on $rolledGroup in $roomName chat room (dice $time_stamp)",
                    "dice $time_stamp"
                );
                if (in_array($player, $winnerNames) == false) $winnerNames[] = $player;
                if ($winAmount > 100000) {
                    $sql = "INSERT INTO big_wins(username, roomName, amount) VALUES ('$player', '$roomName', '$winAmount')";
                    $this->exec($sql);
                }
            }

            // increase level
            $sql = "SELECT SUM(amount) as totalBidAmount FROM dice WHERE username = '$player' AND roomName = '$roomName'";
            $res = $this->query($sql);
            if (count($res) > 0) {
                $this->accountUtils->increaseLevel($player, $res[0]["totalBidAmount"], true);
            }
        }


        // increment game count in profile starts
        $sql = "UPDATE profile SET games = games + 1, dice_played = dice_played + 1 WHERE username IN (SELECT DISTINCT username FROM dice WHERE roomName = '$roomName')";
        $this->exec($sql);
        // increment game count ends

        // increment game WIN count in profile
        $winnerNames = array_unique($winnerNames);
        $sql = "UPDATE profile SET dice_won = dice_won + 1 WHERE username IN (";
        $winnerNamesAsString = "";
        for ($i = 0; $i < count($winnerNames); $i++) {
            if ($i > 0) {
                $sql .= ",";
                $winnerNamesAsString .= ", ";
            }
            $sql .= "'$winnerNames[$i]'";
            $winnerNamesAsString .= $winnerNames[$i];
        }
        $sql .= ")";
        $this->exec($sql);
        if ($winnerNamesAsString == "") $winnerNamesAsString = "No winners";
        $sql = "UPDATE dice_result SET winner  = '$winnerNamesAsString' WHERE dice_timestamp = '$time_stamp' AND roomName = '$roomName'";
        $this->exec($sql);
        // increment game WIN count ends

        $result["winners"] = $winners;
        $result["roomName"] = $roomName;
        $result["timeStamp"] = $time_stamp;
        $result["text"] = $resText;

        $sql = "DELETE FROM dice WHERE roomName = '$roomName'";
        $this->exec($sql);
        $this->stopGame($roomName);

        $this->ref
            ->getReference('chats/' . $roomName)
            ->push([
                'time' => $time_stamp,
                'timeInMillis' => "" . time() . "" . rand(0, 1000),
                'from' => $roomName,
                'type' => 17,
                'text' => json_encode($result),
                'color' => "#84BC23"
            ]);

        return buildSuccessResponse($result);
    }

    public function isGameRunning($roomName)
    {
        $sql = "SELECT id FROM game WHERE roomName = '$roomName' AND gameName = 'dice' FOR UPDATE";
        return (boolean)($this->countRows($sql) > 0);
    }

    public function getState($roomName)
    {
        $rows = $this->query("SELECT state FROM game WHERE roomName = '$roomName' AND gameName = 'dice'");
        if (count($rows) > 0) {
            return $rows[0]["state"];
        }
        return -1;
    }

    public function bid($username, $roomName, $group, $amount)
    {
        if ($this->isGameRunning($roomName)) {
            if ($this->getState($roomName) < 0) return buildErrorResponse("No game is running.");
            if ($this->getState($roomName) > 0) return buildErrorResponse("The round is over. Wait for result.");
            if ($amount < 5) return buildErrorResponse("You need to bid at least 5 BDT.");
            if ($amount > 15000) return buildErrorResponse("Bid amount must be less than or equal to 15000 BDT.");
            $roomNames = $this->query("SELECT DISTINCT roomName FROM dice WHERE username = '$username'");
            $nor = count($roomNames);
            if ($nor >= 3) {
                $ok = false;
                foreach ($roomNames as $row) {
                    if ($row["roomName"] == $roomName) {
                        $ok = true;
                        break;
                    }
                }
                if (!$ok) {
                    return buildErrorResponse("Unable to bid. You are already playing dice in 3 chat rooms.");
                }
            }
            $rows = $this->query("SELECT time_stamp FROM dice WHERE username = '$username' AND roomName = '$roomName' ORDER BY time_stamp DESC LIMIT 1");
            $bidCount = count($rows);
            if ($bidCount > 0) {
                $time = strtotime($rows[0]["time_stamp"]);
                $diff = time() - $time;
                if ($diff < 3) {
                    $wait = 3 - $diff;
                    return buildErrorResponse("Unable to bid. You have to wait $wait sec to make the next bid.");
                }
            }

            $group = strtolower($group);
            if (!array_key_exists($group, $this->groups)) {
                return buildErrorResponse("The group you bid doesn't exist.");
            }
            $group = $this->groups[$group];
            $userInfo = $this->query("SELECT balance, balance2, spend_from_main_account from users WHERE username = '$username' FOR UPDATE")[0];

            $balance = $userInfo["balance2"];
            $nonT = true;
            if ($userInfo["spend_from_main_account"]) {
                $balance = $userInfo["balance"];
                $nonT = false;
            }
            if ($balance - $amount < 41.95) return buildErrorResponse("Keep at least 41.95 BDT in your account.");

            // deduct credit first
            $sql = "UPDATE users SET balance = balance - $amount WHERE username = '$username' AND balance >= $amount + 41.95";
            if ($nonT) $sql = "UPDATE users SET balance2 = balance2 - $amount WHERE username = '$username' AND balance2 >= $amount + 41.95";
            $deducted = $this->exec($sql);
            if (!$deducted) {
                return buildErrorResponse("Insufficient balance");
            }

            // bid
            $time_stamp = date("Y-m-d H:i:s", time());
            $transferable = 1;
            if ($nonT) $transferable = 0;
            $sql = "INSERT INTO dice(username, roomName, groupName, amount, time_stamp, transferable) VALUES ('$username', '$roomName', '$group', $amount, '$time_stamp', $transferable)";
            $bidden = $this->exec($sql);
            if (!$bidden) {
                // failed to bid so refund
                $sql = "UPDATE users SET balance = balance + $amount WHERE username = '$username'";
                if ($nonT) $sql = "UPDATE users SET balance2 = balance2 + $amount WHERE username = '$username'";
                $refunded = $this->exec($sql);
                return buildErrorResponse("Unable to bid. Try again.");
            }
            $result = array();
            $result["user"] = ["username" => $username];
            $result["text"] = $username . " has bid " . $amount . " BDT on " . $group;
            $result["roomName"] = $roomName;
            $result["username"] = $username;
            $result["gameName"] = "Dice Bot";

            // add history
            $sql = "SELECT time_stamp FROM game WHERE roomName = '$roomName' AND gameName = 'dice'";
            $rows = $this->query($sql);
            $this->accountUtils->addHistory($username, "-", $amount, "Placed bid of $amount BDT on $group in $roomName chat room", "dice " . $rows[0]["time_stamp"]);

            // create the bid text
            $bids = $this->query("SELECT groupName, amount FROM dice WHERE username = '$username' AND roomName = '$roomName'");
            $bidArray = array();
            for ($i = 0, $size = count($bids); $i < $size; $i++) {
                if (!array_key_exists($bids[$i]["groupName"], $bidArray)) {
                    $bidArray[$bids[$i]["groupName"]] = 0;
                }
                $bidArray[$bids[$i]["groupName"]] += $bids[$i]["amount"];
            }
            $subText = "Bids: ";
            foreach ($bidArray as $groupName => $amountValue) {
                $subText .= "$groupName: $amountValue BDT ";
            }
            $result["subText"] = $subText;
            $this->ref->getReference("chats/" . $roomName)->push(MessageHelper::getDiceBidText($roomName, json_encode($result)));
            $result["user"] = $this->query("SELECT * from users WHERE username = '$username'")[0];

            return buildSuccessResponse($result, "You bid on $group");
        }
        return buildErrorResponse("Invalid command or arguments.");
    }

    public function stopGame($roomName)
    {
        $this->exec("DELETE FROM game WHERE roomName = '$roomName'");
    }
}
