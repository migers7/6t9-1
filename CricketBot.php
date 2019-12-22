<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 5/19/2019
 * Time: 1:26 PM
 */

include_once 'config.php';
include_once 'firebaseManager.php';
include_once 'account_utils.php';

class CricketBot
{

    private $options = [1, 2, 3, 4, 6, -1, -2, -3, -4, 0, -5, -6, -7];
    private $labels = ["One", "Two", "Three", "Four!", "Six!", "Bowled!", "Caught!", "Hit OUT!", "LBW", "3rd Umpire NOT OUT: immune for next ball", "3rd Umpire: OUT!", "Run OUT!", "Stumped!"];

    private function exec($sql)
    {
        $pdo = ApiHelper::getInstance();
        $pdo->beginTransaction();
        $changed = $pdo->exec($sql);
        $pdo->commit();
        $pdo = null;
        return (boolean)($changed > 0);
    }

    private function query($sql)
    {
        $pdo = ApiHelper::getInstance();
        $pdo->beginTransaction();
        $rows = ApiHelper::cast($pdo->query($sql));
        $pdo->commit();
        $pdo = null;
        return $rows;
    }

    private function queryCount($sql)
    {
        return count($this->query($sql));
    }

    public function addHistory($username, $amount, $description, $type)
    {
        $sql = "INSERT INTO account_history(username, type, description, amount) VALUES('$username', '$type', '$description', $amount)";
        $this->exec($sql);
    }

    public function addPlayer($username, $roomName, $amount, $transferable = 1)
    {
        $id = "$username#$roomName";
        $sql = "INSERT INTO cricket_bot(id, username, roomName, amount, transferable) VALUES('$id', '$username', '$roomName', $amount, $transferable)";
        $this->exec($sql);
    }

    public function join($username, $roomName)
    {
        // check if user is in room
        $isInRoom = ApiHelper::rowExists("SELECT id FROM room_users WHERE roomName = '$roomName' AND username = '$username'");
        if (!$isInRoom) {
            return ApiHelper::buildErrorResponse("You are not in $roomName chat room.");
        }

        // check if user already joined
        if (count($this->query("SELECT id FROM cricket_bot WHERE username = '$username' AND roomName = '$roomName'")) > 0) {
            return ApiHelper::buildErrorResponse("You already joined the game");
        }

        $playerCount = $this->queryCount("SELECT id FROM cricket_bot WHERE roomName = '$roomName'");
        if ($playerCount >= 25) return ApiHelper::buildErrorResponse("Too many players already joined this game. Please wait for the next game.");

        // check if a game is already in progress
        $currentGameConditions = $this->query("SELECT state FROM game WHERE roomName = '$roomName' AND gameName = 'cricket' FOR UPDATE");
        if (count($currentGameConditions) > 0) {
            $row = $currentGameConditions[0];
            if ($row["state"] > 0) return ApiHelper::buildErrorResponse("A game is in progress. Please wait.");
        } else {
            return ApiHelper::buildErrorResponse("No game is running.");
        }

        // verify if user has enough balance

        $amount = $this->query("SELECT amount FROM game WHERE roomName = '$roomName' AND gameName = 'cricket'")[0]["amount"];

        $balanceRows = $this->query("SELECT balance, balance2, spend_from_main_account FROM users WHERE username = '$username' FOR UPDATE");
        $balance = $balanceRows[0]["balance2"];
        $nonT = true;
        if ($balanceRows[0]["spend_from_main_account"]) {
            $balance = $balanceRows[0]["balance"];
            $nonT = false;
        }
        if ($balance <= 41.18 + $amount) {
            return ApiHelper::buildErrorResponse("Insufficient balance to join the game.");
        }

        $sql = "UPDATE users SET balance = balance - $amount WHERE username = '$username' AND balance >= $amount + 41.18";
        if ($nonT) $sql = "UPDATE users SET balance2 = balance2 - $amount WHERE username = '$username' AND balance2 >= $amount + 41.18";

        $deducted = $this->exec($sql);
        if (!$deducted) {
            return ApiHelper::buildErrorResponse("Insufficient balance or an error occurred to join the game.");
        }

        $description = "Spent $amount BDT for Cricket game in $roomName chat room";
        $this->addHistory($username, $amount, $description, "-");

        $transferable = 1;
        if ($nonT) $transferable = 0;
        $this->addPlayer($username, $roomName, $amount, $transferable);

        FirebaseManager::getInstance()->getReference("chats/$roomName")->push(
            MessageHelper::getCricketInfo($roomName, "$username has joined the game")
        );

        return buildSuccessResponse("You have joined the game");
    }

    public function pushRunText($roomName, $username, $round)
    {
        $de = [1, 1, 1, 1, 1, 0, 0, 1, 0, 0, 1, 1, 1, 1, 1, 1, 1, 0, 0, 0, 1, 1, 0, 0, 1, 0, 1, 1, 1, 1, 1, 0, 0, 1, 1, 1];
        $pos = $de[mt_rand(0, count($de) - 1)];
        $low = 0;
        $high = count($this->options) - 1;
        if ($pos > 0) $high = 4;
        $index = mt_rand($low, $high);
        $run = $this->options[$index];
        if ($run < 0) { // OUT
            $sql = "UPDATE cricket_bot SET round = '$round', is_out = 1 WHERE username = '$username' AND roomName = '$roomName'";
        } else {
            $sql = "UPDATE cricket_bot SET round = '$round', runs = runs + $run WHERE username = '$username' AND roomName = '$roomName'";
        }
        $this->exec($sql);
        $resultText = $this->labels[$index];
        $text = "$username bats: ? ";
        if ($run == 4 || $run == 6) {
            $text = "$username hits: ?";
        }
        $text .= $resultText;
        $message = ["index" => $index, "text" => $text];
        FirebaseManager::getInstance()->getReference("chats/$roomName")->push(
            MessageHelper::getCricketHitMessage($roomName, json_encode($message))
        );
    }

    public function bat($username, $roomName)
    {
        $rows = $this->query("SELECT round FROM cricket_bot WHERE username = '$username' AND roomName = '$roomName' FOR UPDATE");
        if (count($rows) == 0) {
            return ApiHelper::buildErrorResponse("You are not in this game.");
        }
        $lastBatRound = $rows[0]["round"];
        if ($this->canBat($roomName)) {
            $rows = $this->query("SELECT state FROM game WHERE roomName = '$roomName' AND gameName = 'cricket'");
            $round = 0;
            if (count($rows) > 0) $round = $rows[0]["state"];
            if ($lastBatRound >= $round) {
                return ApiHelper::buildErrorResponse("You already hit");
            }
            $this->pushRunText($roomName, $username, $round);
            return ApiHelper::buildSuccessResponse("You hit");
        }
        return ApiHelper::buildErrorResponse("Invalid command or argument");
    }

    /**
     * @param String username    The player who starts the game
     * @param String roomName    The room where player starts the game
     * @param String amount      The joining amount of the game, Double value in (5-50000) range
     * @return false|string
     * @throws  Exception          If there is any constraint mismatch
     *
     */
    public function run($username, $roomName, $amount)
    {

        // check if user is in room
        $isInRoom = ApiHelper::rowExists("SELECT id FROM room_users WHERE roomName = '$roomName' AND username = '$username'");
        if (!$isInRoom) {
            throw new Exception("You are not in $roomName chat room.");
        }

        // check if a game is already in progress
        $currentGameConditions = $this->query("SELECT state FROM game WHERE roomName = '$roomName' AND gameName = 'cricket' FOR UPDATE");
        if (count($currentGameConditions) > 0) {
            $row = $currentGameConditions[0];
            if ($row["state"] == 0) throw new Exception("A game is currently ON. Type !j to join.");
            throw new Exception("A game is in progress. Please wait.");
        }

        // check if the amount is valid
        if ($amount < 5 || $amount > 50000) {
            throw new Exception("Amount must be in the range 5 - 50000");
        }

        // verify if user has enough balance
        $balanceRows = $this->query("SELECT balance, balance2, spend_from_main_account FROM users WHERE username = '$username' FOR UPDATE");
        $balance = $balanceRows[0]["balance2"];
        $nonT = true;
        if ($balanceRows[0]["spend_from_main_account"]) {
            $balance = $balanceRows[0]["balance"];
            $nonT = false;
        }
        if ($balance <= 41.18 + $amount) {
            return ApiHelper::buildErrorResponse("Insufficient balance to join the game.");
        }

        $sql = "UPDATE users SET balance = balance - $amount WHERE username = '$username' AND balance >= $amount + 41.18";
        if ($nonT) $sql = "UPDATE users SET balance2 = balance2 - $amount WHERE username = '$username' AND balance2 >= $amount + 41.18";

        $deducted = $this->exec($sql);
        if (!$deducted) {
            throw new Exception("Insufficient balance or error to start the game.");
        }

        $description = "Spent $amount BDT for Cricket game in $roomName chat room";
        $this->addHistory($username, $amount, $description, "-");

        // add game as state
        $this->toggleCanBat($roomName, 0);
        // start the game, entry in game table
        $gameId = mt_rand(100, 1000000);
        // start the game, entry in game table
        $sql = "INSERT INTO game(roomName, gameName, amount, gameId) VALUES('$roomName', 'cricket', $amount, $gameId)";
        $this->exec($sql);

        $transferable = 1;
        if ($nonT) $transferable = 0;
        $this->addPlayer($username, $roomName, $amount, $transferable);

        $this->join($username, $roomName);
        $ref = FirebaseManager::getInstance()->getReference("chats/$roomName");
        $ref->push(MessageHelper::getCricketInfo(
            $roomName,
            "Cricket started. !j to join, cost BDT $amount [40 sec]"
        ));

        // wait for 40 sec for players to join
        sleep(4);

        // check if multiple instance of game started
        $sql = "SELECT id, gameId FROM game WHERE roomName = '$roomName' AND gameName = 'cricket' FOR UPDATE";
        $rows = $this->query($sql);
        if (count($rows) == 0) {
            throw new Exception("Bot has been stopped.");
        }
        if (count($rows) != 1) {
            $adminUtils = new AdminUtils();
            $adminUtils->stopBot($roomName, "sixt9");
            sleep(3);
            $adminUtils->addBot("cricket", $roomName);
            throw new Exception("An error occurred");
        }

        if (count($rows) == 1) {
            if ($gameId != $rows[0]["gameId"]) {
                $adminUtils = new AdminUtils();
                $adminUtils->stopBot($roomName, "sixt9");
                sleep(3);
                $adminUtils->addBot("cricket", $roomName);
                throw new Exception("An error occurred");
            }
        } else {
            $adminUtils = new AdminUtils();
            $adminUtils->stopBot($roomName, "sixt9");
            sleep(3);
            $adminUtils->addBot("cricket", $roomName);
            throw new Exception("An error occurred");
        }

        sleep(36);


        $players = $this->query("SELECT username, transferable FROM cricket_bot WHERE roomName = '$roomName'");
        $playerCount = count($players);

        // check if someone has stopped bot
        if ($playerCount == 0) {
            throw new Exception("Bot stopped");
        }

        if ($playerCount < 2) {
            $stopped = $this->stop($roomName);
            $refunded = $this->refund($roomName, $amount, $players);
            if (!$refunded) {
                // TODO file a log
            }
            $cleared = $this->clear($roomName);
            $text = "Joining ends. Not enough players. Need 2";
            $ref->push(
                MessageHelper::getCricketInfo($roomName, $text)
            );
            throw new Exception($text);
        }

        $stateUpdated = $this->updateGameState($roomName, 1);
        $ref->push(
            MessageHelper::getCricketInfo($roomName, "Game begins. Maximum run holder wins the game!")
        );

        $round = 1;

        $accountUtils = new AccountUtils();

        while ($round <= 100) {
            $stateUpdated = $this->updateGameState($roomName, $round);
            $this->toggleCanBat($roomName, 1);
            $ref->push(
                MessageHelper::getCricketInfo($roomName, "Round #$round. Players !d to bat [20 seconds]")
            );
            sleep(19);

            // check if multiple instance of game started
            $sql = "SELECT id, gameId FROM game WHERE roomName = '$roomName' AND gameName = 'cricket' FOR UPDATE";
            $ids = $this->query($sql);
            if (count($ids) == 1) {
                if ($gameId != $ids[0]["gameId"]) {
                    throw new Exception("An error occurred");
                    break;
                }
            } else {
                throw new Exception("An error occurred");
                break;
            }

            // Time up
            $this->toggleCanBat($roomName, 0);
            sleep(1);
            $ref->push(
                MessageHelper::getCricketInfo($roomName, "Times up! Tallying...")
            );
            $playersLeftToBat = $this->query("SELECT username, transferable FROM cricket_bot WHERE round < $round AND roomName = '$roomName'");
            foreach ($playersLeftToBat as $row) {
                $this->pushRunText($roomName, $row["username"], $round);
            }
            $ref->push(
                MessageHelper::getCricketInfo($roomName, "Round is over. Counting runs...")
            );


            $notOutPlayers = $this->query("SELECT username, runs, transferable FROM cricket_bot WHERE roomName = '$roomName' AND is_out = 0 ORDER BY runs DESC");
            $outPlayers = $this->query("SELECT username, runs, transferable FROM cricket_bot WHERE roomName = '$roomName' AND is_out = 1 ORDER BY runs DESC");
            $notOutCount = count($notOutPlayers);


            if ($notOutCount == 1) { // we got our winner
                $winner = $notOutPlayers[0]["username"];
                $runs = $notOutPlayers[0]["runs"];
                // increment game count
                $sql = "UPDATE profile SET games = games + 1, cricket_played = cricket_played + 1, cricket_won =  cricket_won + 1 WHERE username = '$winner'";
                $this->exec($sql);

                // add as spent credit
                $accountUtils->increaseLevel($winner, $amount, false);
                // add credit and history
                $winAmount = $playerCount * $amount * 0.9;
                $accountUtils->addCredit($winner, $winAmount, $notOutPlayers[0]["transferable"]);
                $description = "Won $winAmount BDT from Cricket game in $roomName chat room";
                $accountUtils->addHistory($winner, "+", $winAmount, $description);
                $this->addCount($outPlayers, $amount);

                $ref->push(
                    MessageHelper::getCricketInfo($roomName, "$winner ($runs runs)")
                );
                $ref->push(
                    MessageHelper::getCricketInfo($roomName, "Game over. $winner is the highest run scorer with $runs runs and wins BDT $winAmount! CONGRATS!!!")
                );
                // game ends
                $this->clear($roomName);
                $this->stop($roomName);
                $ref->push(
                    MessageHelper::getCricketInfo($roomName, "Play Cricket. Type !start to start a new game, !start < amount > for custom entry.")
                );
                break;
            } else if ($notOutCount > 1) {
                $sql = "DELETE FROM cricket_bot WHERE roomName = '$roomName' AND is_out = 1";
                $this->exec($sql);
                $this->addCount($outPlayers, $amount);
                if ($round >= 6) {
                    $mostRunHolder = 0;
                    $mostRun = $notOutPlayers[0]["runs"];
                    $playersWithLessRun = array();
                    for ($i = 0; $i < count($notOutPlayers); $i++) {
                        if ($mostRun == $notOutPlayers[$i]["runs"]) {
                            $mostRunHolder++;
                            $ref->push(
                                MessageHelper::getCricketInfo($roomName, $notOutPlayers[$i]["username"] . " ($mostRun runs)")
                            );
                        } else {
                            $playersWithLessRun[] = $notOutPlayers[$i];
                        }
                    }
                    if ($mostRunHolder > 1) {
                        // there are multiple most run holders, so we cannot end game
                        // eliminate the players with less run
                        $sql = "DELETE FROM cricket_bot WHERE roomName = '$roomName' AND runs < $mostRun";
                        $this->exec($sql);
                        $this->addCount($playersWithLessRun, $amount);

                    } else {
                        $winner = $notOutPlayers[0]["username"];
                        $runs = $notOutPlayers[0]["runs"];
                        // increment game win count
                        $sql = "UPDATE profile SET cricket_won =  cricket_won + 1 WHERE username = '$winner'";
                        $this->exec($sql);

                        // add credit and history
                        $winAmount = $playerCount * $amount * 0.9;
                        $accountUtils->addCredit($winner, $winAmount, $notOutPlayers[0]["transferable"]);
                        $description = "Won $winAmount BDT from Cricket game in $roomName chat room";
                        $accountUtils->addHistory($winner, "+", $winAmount, $description);
                        $this->addCount($notOutPlayers, $amount);

                        $ref->push(
                            MessageHelper::getCricketInfo($roomName, "Game over. $winner is the highest run scorer with $runs runs and wins BDT $winAmount! CONGRATS!!!")
                        );
                        // game ends
                        $this->clear($roomName);
                        $this->stop($roomName);
                        $ref->push(
                            MessageHelper::getCricketInfo($roomName, "Play Cricket. Type !start to start a new game, !start < amount > for custom entry.")
                        );
                        break;
                    }


                } else {
                    for ($i = 0; $i < count($notOutPlayers); $i++) {
                        $ref->push(
                            MessageHelper::getCricketInfo($roomName, $notOutPlayers[$i]["username"] . " (" . $notOutPlayers[$i]["runs"] . " runs)")
                        );
                    }
                }
            } else {
                // all players are out, we hereby make the most run holder as winner
                $mostRunHolder = 0;
                $mostRun = $outPlayers[0]["runs"];
                $playersWithLessRun = array();
                for ($i = 0; $i < count($outPlayers); $i++) {
                    if ($mostRun == $outPlayers[$i]["runs"]) {
                        $mostRunHolder++;
                        $ref->push(
                            MessageHelper::getCricketInfo($roomName, $outPlayers[$i]["username"] . " ($mostRun runs)")
                        );
                    } else {
                        $playersWithLessRun[] = $outPlayers[$i];
                    }
                }
                if ($mostRunHolder > 1) {
                    // there are multiple most run holders, so we cannot end game
                    // eliminate the players with less run
                    $sql = "DELETE FROM cricket_bot WHERE roomName = '$roomName' AND runs < $mostRun";
                    $this->exec($sql);
                    $this->addCount($playersWithLessRun, $amount);
                    $sql = "UPDATE cricket_bot SET is_out = 0 WHERE roomName = '$roomName'";
                    $this->exec($sql);

                } else {
                    $winner = $outPlayers[0]["username"];
                    $runs = $outPlayers[0]["runs"];
                    // increment game win count
                    $sql = "UPDATE profile SET cricket_won =  cricket_won + 1 WHERE username = '$winner'";
                    $this->exec($sql);

                    // add credit and history
                    $winAmount = $playerCount * $amount * 0.9;
                    $accountUtils->addCredit($winner, $winAmount, $outPlayers[0]["transferable"]);
                    $description = "Won $winAmount BDT from Cricket game in $roomName chat room";
                    $accountUtils->addHistory($winner, "+", $winAmount, $description);

                    $this->addCount($outPlayers, $amount);

                    $ref->push(
                        MessageHelper::getCricketInfo($roomName, "Game over. $winner is the highest run scorer with $runs runs and wins BDT $winAmount! CONGRATS!!!")
                    );
                    // game ends
                    $this->clear($roomName);
                    $this->stop($roomName);
                    $ref->push(
                        MessageHelper::getCricketInfo($roomName, "Play Cricket. Type !start to start a new game, !start < amount > for custom entry.")
                    );
                    break;
                }
            }

            $round++;
        }
    }

    public function addCount($players, $amount)
    {
        $accountUtils = new AccountUtils();
        foreach ($players as $row) {
            $username = $row["username"];
            // increment game count
            $sql = "UPDATE profile SET games = games + 1, cricket_played = cricket_played + 1 WHERE username = '$username'";
            $this->exec($sql);
            // add as spent credit
            $accountUtils->increaseLevel($username, $amount, false);
        }
    }

    public function canBat($roomName)
    {
        return (boolean)($this->queryCount("SELECT * FROM cricket_state WHERE can_bat = 1 AND roomName = '$roomName'") > 0);
    }

    public function toggleCanBat($roomName, $can)
    {
        $sql = "INSERT INTO cricket_state(roomName, can_bat) VALUES ('$roomName', $can) ON DUPLICATE KEY UPDATE can_bat = $can";
        return $this->exec($sql);
    }

    public function updateGameState($roomName, $round)
    {
        $sql = "UPDATE game SET state = $round WHERE roomName = '$roomName' AND gameName = 'cricket'";
        return $this->exec($sql);
    }

    public function clear($roomName)
    {
        $sql = "DELETE FROM cricket_bot WHERE roomName = '$roomName'";
        return $this->exec($sql);
    }

    public function stop($roomName)
    {
        $sql = "DELETE FROM game WHERE roomName = '$roomName' AND gameName = 'cricket'";
        $sql2 = "DELETE FROM cricket_state WHERE roomName = '$roomName'";
        return $this->exec($sql) && $this->exec($sql2);
    }

    public function refund($roomName, $amount, $rows)
    {
        $accountUtils = new AccountUtils();
        for ($i = 0, $n = count($rows); $i < $n; $i++) {
            $username = $rows[$i]["username"];
            $transferable = $rows[$i]["transferable"];
            $accountUtils->addCredit($username, $amount, $transferable);
            $accountUtils->addHistory($username, "+", $amount, "Refunded $amount BDT from Cricket game in $roomName chat room");
        }
        return true;
    }

}