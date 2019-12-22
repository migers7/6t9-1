<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 4/1/19
 * Time: 5:43 PM
 */

include_once 'config.php';
include_once 'firebaseManager.php';
include_once 'account_utils.php';

class Card
{

    private $cardId;
    private $cardValue;

    public function __construct($cardId, $cardValue)
    {
        $this->cardId = $cardId;
        $this->cardValue = $cardValue;
    }

    public function getCardId()
    {
        return $this->cardId;
    }

    public function getCardValue()
    {
        return $this->cardValue;
    }
}

class CardHelper
{

    private $CARD_TYPES = ["S", "C", "H", "D"];
    private $CARD_STR = ["2", "3", "4", "5", "6", "7", "8", "9", "T", "J", "Q", "K", "A", "M"];

    public function getRandomCard()
    {
        $cardId = rand(0, 12);
        $cardType = $this->CARD_TYPES[rand(0, 3)];
        $cardStr = $this->CARD_STR[$cardId];
        $cardValue = $cardStr . $cardType;
        return new Card($cardId, $cardValue);
    }

    public function getMaxCard()
    {
        $cardId = 13;
        $cardType = $this->CARD_TYPES[rand(0, 3)];
        $cardStr = $this->CARD_STR[$cardId];
        $cardValue = $cardStr . $cardType;
        return new Card($cardId, $cardValue);
    }
}

class LowCardConstants
{

    public static $RESULT_SUCCESS = 0;
    public static $RESULT_ERROR = 1;
    public static $NOT_IN_GAME = 2018;
    public static $RESULT_ALREADY_DRAWN = 2019;

}

class SmartLowCardBot
{
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

    public function join($username, $roomName)
    {
        // check if user is in room
        $isInRoom = ApiHelper::rowExists("SELECT id FROM room_users WHERE roomName = '$roomName' AND username = '$username'");
        if (!$isInRoom) {
            return ApiHelper::buildErrorResponse("You are not in $roomName chat room.");
        }

        // check if user already joined
        if (count($this->query("SELECT id FROM lowcard_bot WHERE username = '$username' AND roomName = '$roomName'")) > 0) {
            return ApiHelper::buildErrorResponse("You already joined the game");
        }

        // check if a game is already in progress
        $currentGameConditions = $this->query("SELECT state FROM game WHERE roomName = '$roomName' AND gameName = 'lowcard' FOR UPDATE");
        if (count($currentGameConditions) > 0) {
            $row = $currentGameConditions[0];
            if ($row["state"] > 0) return ApiHelper::buildErrorResponse("A game is in progress. Please wait.");
        } else {
            return ApiHelper::buildErrorResponse("No game is running.");
        }

        // verify if user has enough balance

        $amount = $this->query("SELECT amount FROM game WHERE roomName = '$roomName' AND gameName = 'lowcard'")[0]["amount"];

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

        $description = "Spent $amount BDT for LowCard game in $roomName chat room";
        $au = new AccountUtils();
        $au->addHistory($username, "-", $amount, $description);
        $au = null;

        $transferable = 1;
        if ($nonT) $transferable = 0;
        $this->addPlayer($username, $roomName, $amount, $transferable);

        FirebaseManager::getInstance()->getReference("chats/$roomName")->push(
            MessageHelper::getLowCardJoinText($roomName, "$username has joined the game")
        );

        return buildSuccessResponse("You have joined the game");
    }

    public function drawByUser($username, $roomName)
    {
        $rows = $this->query("SELECT state FROM game WHERE roomName = '$roomName' AND gameName = 'lowcard'");
        $round = 0;
        if (count($rows) > 0) $round = $rows[0]["state"];
        $card = (new CardHelper())->getRandomCard();
        $result = $this->draw($username, $roomName, $round, $card);
        if ($result == LowCardConstants::$RESULT_SUCCESS) {
            FirebaseManager::getInstance()->getReference("chats/$roomName")->push(
                MessageHelper::getLowCardDrawText($roomName, "$username: " . $card->getCardValue())
            );
            return ApiHelper::buildSuccessResponse("You drew " . $card->getCardValue());
        } else if ($result == LowCardConstants::$NOT_IN_GAME) {
            return ApiHelper::buildErrorResponse("You are not in this game");
        } else {
            return buildErrorResponse("Invalid command or argument");
        }
    }

    public function draw($username, $roomName, $round, Card $card = null, $canDraw = false)
    {
        if (ApiHelper::rowExists("SELECT id FROM lowcard_bot WHERE username = '$username' AND roomName = '$roomName' FOR UPDATE") == false) {
            return LowCardConstants::$NOT_IN_GAME;
        }
        $sql = "SELECT id FROM lowcard_bot WHERE username = '$username' AND roomName = '$roomName' AND round = $round FOR UPDATE";
        if (ApiHelper::rowExists($sql)) return LowCardConstants::$RESULT_ALREADY_DRAWN;
        if (!$canDraw) {
            $drawState = $this->query("SELECT can_draw FROM lowcard_state WHERE roomName = '$roomName'");
            if (count($drawState) > 0) $canDraw = $drawState[0]["can_draw"];
        }
        if (!$canDraw) return LowCardConstants::$RESULT_ERROR;
        $cardHelper = new CardHelper();
        if ($card == null) $card = $cardHelper->getRandomCard();
        $id = "$username#$roomName";
        $cardId = $card->getCardId();
        $cardValue = $card->getCardValue();
        $timeStamp = date("Y-m-d H:i:s", time());
        $sql = "INSERT INTO lowcard_bot(id, username, roomName, round, card_id, card_value, time_stamp) VALUES('$id', '$username', '$roomName', $round, $cardId, '$cardValue', '$timeStamp')"
            . " ON DUPLICATE KEY UPDATE round = $round, card_id = $cardId, card_value = '$cardValue', time_stamp = '$timeStamp'";
        if ($this->exec($sql)) return LowCardConstants::$RESULT_SUCCESS;
        return LowCardConstants::$RESULT_ERROR;
    }

    public function addPlayer($username, $roomName, $amount, $transferable = 1)
    {
        $id = "$username#$roomName";
        $sql = "INSERT INTO lowcard_bot(id, username, roomName, round, card_id, card_value, amount, transferable) VALUES('$id', '$username', '$roomName', 0, 0, 'NO_CARD', $amount, $transferable)"
            . " ON DUPLICATE KEY UPDATE round = 0, card_id = 0, card_value = 'NO_CARD', amount = $amount";
        $this->exec($sql);
    }

    /**
     * @param String username    The player who starts the game
     * @param String roomName    The room where player starts the game
     * @param String amount      The joining amount of the game, Double value in (5-50000) range
     * @return false|string|void
     * @throws  Exception          If there is any constraint mismatch
     */
    public function run($username, $roomName, $amount)
    {
        // throw new Exception("Unable to start a game. LowCard is temporarily disabled.");

        // check if user is in room
        $isInRoom = ApiHelper::rowExists("SELECT id FROM room_users WHERE roomName = '$roomName' AND username = '$username'");
        if (!$isInRoom) {
            throw new Exception("You are not in $roomName chat room.");
        }

        // check if a game is already in progress
        $currentGameConditions = $this->query("SELECT state FROM game WHERE roomName = '$roomName' AND gameName = 'lowcard' FOR UPDATE");
        if (count($currentGameConditions) > 0) {
            $row = $currentGameConditions[0];
            if ($row["state"] == 0) throw new Exception("A game is currently ON. Type !j to join.");
            throw new Exception("A game is in progress. Please wait.");
        }

        // check if the amount is valid
        if ($amount < 5 || $amount > 50000) {
            throw new Exception("Amount must be in the range 5 - 50000.");
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

        $description = "Spent $amount BDT for LowCard game in $roomName chat room";
        $au = new AccountUtils();
        $au->addHistory($username, "-", $amount, $description);
        $au = null;

        // add game as state
        $sql = "INSERT INTO lowcard_state(roomName, can_draw) VALUES ('$roomName', 0) ON DUPLICATE KEY UPDATE can_draw = 0";
        $this->exec($sql);
        $gameId = mt_rand(100, 1000000);
        // start the game, entry in game table
        $sql = "INSERT INTO game(roomName, gameName, amount, gameId) VALUES('$roomName', 'lowcard', $amount, $gameId)";
        $this->exec($sql);

        $transferable = 1;
        if ($nonT) $transferable = 0;
        $this->addPlayer($username, $roomName, $amount, $transferable);

        $this->draw($username, $roomName, 0, null);
        $ref = FirebaseManager::getInstance()->getReference("chats/$roomName");
        $ref->push(MessageHelper::getLowCardStartedText(
            $roomName,
            "LowCard started. !j to join, cost BDT $amount [40 sec]"
        ));

        // wait for 40 sec for players to join
        sleep(4);

        // check if multiple instance of game started
        $sql = "SELECT id, gameId FROM game WHERE roomName = '$roomName' AND gameName = 'lowcard' FOR UPDATE";
        $rows = $this->query($sql);
        if (count($rows) == 0) {
            throw new Exception("Bot has been stopped.");
        }
        if (count($rows) != 1) {
            $adminUtils = new AdminUtils();
            $adminUtils->stopBot($roomName, "sixt9");
            sleep(3);
            $adminUtils->addBot("lowcard", $roomName);
            throw new Exception("An error occurred");
        }

        if (count($rows) == 1) {
            if ($gameId != $rows[0]["gameId"]) {
                $adminUtils = new AdminUtils();
                $adminUtils->stopBot($roomName, "sixt9");
                sleep(3);
                $adminUtils->addBot("lowcard", $roomName);
                throw new Exception("An error occurred");
            }
        } else {
            $adminUtils = new AdminUtils();
            $adminUtils->stopBot($roomName, "sixt9");
            sleep(3);
            $adminUtils->addBot("lowcard", $roomName);
            throw new Exception("An error occurred");
        }

        sleep(36);


        $players = $this->query("SELECT username, transferable FROM lowcard_bot WHERE roomName = '$roomName'");
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
                MessageHelper::getLowCardNotEnufPlayerText($roomName, $text)
            );
            throw new Exception($text);
        }

        $this->toggleCanDraw($roomName, 1);
        $stateUpdated = $this->updateGameState($roomName, 1);
        $ref->push(
            MessageHelper::getLowCardInfoText($roomName, "Game begins. Lowest card is out!")
        );

        $round = 1;
        $ref->push(
            MessageHelper::getLowCardInfoText($roomName, "Round #$round. Players !d to draw [20 seconds]")
        );

        $skipNextRound = false;
        $accountUtils = new AccountUtils();
        $wasTied = false;

        while ($round < 200) {
            if (!$skipNextRound) sleep(22);
            else {
                // consider previous cards for this round as we have to skip
                $sql = "UPDATE lowcard_bot SET round = $round WHERE roomName = '$roomName' AND round < $round";
                $this->exec($sql);
                $skipNextRound = false;
            }
            // check if multiple instance of game started
            $sql = "SELECT id, gameId FROM game WHERE roomName = '$roomName' AND gameName = 'lowcard' FOR UPDATE";
            $ids = $this->query($sql);
            if (count($ids) == 1) {
                if ($gameId != $ids[0]["gameId"]) {
                    $adminUtils = new AdminUtils();
                    $adminUtils->stopBot($roomName, "sixt9");
                    sleep(3);
                    $adminUtils->addBot("lowcard", $roomName);
                    throw new Exception("An error occurred");
                    break;
                }
            } else {
                $adminUtils = new AdminUtils();
                $adminUtils->stopBot($roomName, "sixt9");
                sleep(3);
                $adminUtils->addBot("lowcard", $roomName);
                throw new Exception("An error occurred");
                break;
            }
            // Time up
            $this->toggleCanDraw($roomName, 0);
            sleep(1);
            $ref->push(
                MessageHelper::getLowCardInfoText($roomName, "Times up! Tallying cards...")
            );

            // get the last one who drew so that we can decide if we need an UP
            $sql = "SELECT time_stamp FROM lowcard_bot WHERE roomName = '$roomName' AND round = $round ORDER BY time_stamp DESC LIMIT 1";
            $lastDraws = $this->query($sql);
            if (count($lastDraws) > 0) {
                $lastDrawTime = strtotime($lastDraws[0]["time_stamp"]);
                $diff = time() - $lastDrawTime;
                if ($diff < 7) {
                    $skipNextRound = true;
                }
            }
            if ($wasTied) $skipNextRound = false;

            $playersLeftToDraw = $this->query("SELECT username FROM lowcard_bot WHERE roomName = '$roomName' AND round != $round FOR UPDATE");
            if (count($playersLeftToDraw) != 0) {
                $skipNextRound = false;
            }
            $cardHelper = new CardHelper();
            $botDraws = array();
            foreach ($playersLeftToDraw as $item) {
                $username = $item["username"];
                $card = $cardHelper->getRandomCard();
                if ($this->draw($username, $roomName, $round, $card, true) == LowCardConstants::$RESULT_SUCCESS) {
                    $botDraws[$username] = $card->getCardValue();
                }
            }
            foreach ($botDraws as $username => $cardValue) {
                $ref->push(
                    MessageHelper::getLowCardDrawText($roomName, "Bot draws for $username: $cardValue")
                );
            }
            $draws = $this->query("SELECT username, card_id, card_value, transferable FROM lowcard_bot WHERE roomName = '$roomName' AND round = $round ORDER BY card_id ASC FOR UPDATE");
            $size = count($draws);
            if ($size < 2) throw new Exception("An error occurred");
            $tiedPlayers = array();
            $tiedPlayers[] = $draws[0];
            for ($i = 1; $i < $size; $i++) {
                if ($draws[$i]["card_id"] == $draws[0]["card_id"]) {
                    $tiedPlayers[] = $draws[$i];
                }
            }
            $tiedPlayerCount = count($tiedPlayers);
            ++$round;
            $this->updateGameState($roomName, $round);
            if ($tiedPlayerCount < 2) { // no ties
                // eliminate lowest card holder
                $lowestCardHolder = $draws[0]["username"];
                $lowestCard = $draws[0]["card_value"];
                $this->exec("DELETE FROM lowcard_bot WHERE username = '$lowestCardHolder' AND roomName = '$roomName'");
                $tieBroken = "";
                if ($wasTied) {
                    $tieBroken = "Tie broken! ";
                    $wasTied = false;
                }
                $ref->push(
                    MessageHelper::getLowCardInfoText($roomName, "$tieBroken$lowestCardHolder out with the lowest card $lowestCard!")
                );
                // increment game count
                $sql = "UPDATE profile SET games = games + 1, lowcard_played = lowcard_played + 1 WHERE username = '$lowestCardHolder'";
                $this->exec($sql);

                // add as spent credit
                $accountUtils->increaseLevel($lowestCardHolder, $amount, false);

                if ($size == 2) {
                    // game ends
                    $this->clear($roomName);
                    $this->stop($roomName);

                    // add credit and history
                    $winAmount = $playerCount * $amount * 0.9;
                    $winner = $draws[1]["username"];
                    if($draws[1]["transferable"]) {
                        $this->exec("UPDATE users SET balance = balance + $winAmount WHERE username = '$winner'");
                    }
                    else {
                        $this->exec("UPDATE users SET balance2 = balance2 + $winAmount WHERE username = '$winner'");
                    }
                    $description = "Won $winAmount BDT from LowCard game in $roomName chat room";
                    $accountUtils->addHistory($winner, "+", $winAmount, $description);

                    // increment game play and win count
                    $sql = "UPDATE profile SET games = games + 1, lowcard_played = lowcard_played + 1, lowcard_won = lowcard_won + 1 WHERE username = '$winner'";
                    $this->exec($sql);
                    // add credit spend
                    // add as spent credit
                    $accountUtils->increaseLevel($winner, $amount, false);

                    // send game result
                    $ref->push(
                        MessageHelper::getLowCardInfoText($roomName, "LowCard game over! $winner wins BDT $winAmount! CONGRATS!!")
                    );
                    $ref->push(
                        MessageHelper::getLowCardInfoText($roomName, "Play LowCard. Type !start to start a new game, !start < amount > for custom entry.")
                    );
                    return;
                }

                if (!$skipNextRound) {
                    // show current players
                    $size--;
                    $playerText = "Players are ($size):";
                    for ($i = 1; $i <= $size; $i++) {
                        if ($i > 1) $playerText .= ",";
                        $playerText .= " ";
                        $playerText .= $draws[$i]["username"];
                    }

                    $ref->push(
                        MessageHelper::getLowCardInfoText($roomName, $playerText)
                    );
                    $ref->push(
                        MessageHelper::getLowCardInfoText($roomName, "All players next round in 5 seconds")
                    );
                    sleep(3);
                    $this->toggleCanDraw($roomName, 1);
                    $ref->push(
                        MessageHelper::getLowCardInfoText($roomName, "Round #$round. Players !d to draw [20 seconds]")
                    );
                }
                $wasTied = false;
            } else {
                $skipNextRound = false;
                $wasTied = true;
                // we have got tie
                // let others draw a big card
                $maxCard = $cardHelper->getMaxCard();
                for ($i = $tiedPlayerCount; $i < $size; $i++) {
                    $this->draw($draws[$i]["username"], $roomName, $round, $maxCard, true);
                }
                $playerText = "Tied players ($tiedPlayerCount):";
                for ($i = 0; $i < $tiedPlayerCount; $i++) {
                    if ($i > 0) $playerText .= ",";
                    $playerText .= " ";
                    $playerText .= $tiedPlayers[$i]["username"];
                }

                $ref->push(
                    MessageHelper::getLowCardInfoText($roomName, $playerText)
                );

                $ref->push(
                    MessageHelper::getLowCardInfoText($roomName, "Tied players draw again, next round in 5 seconds")
                );
                sleep(3);
                $this->toggleCanDraw($roomName, 1);
                $ref->push(
                    MessageHelper::getLowCardInfoText($roomName, "Round #$round. Tied players !d to draw [20 seconds]")
                );
            }
        }
    }

    public function toggleCanDraw($roomName, $can)
    {
        $sql = "UPDATE lowcard_state SET can_draw = $can WHERE roomName = '$roomName'";
        return $this->exec($sql);
    }

    public function updateGameState($roomName, $round)
    {
        $sql = "UPDATE game SET state = $round WHERE roomName = '$roomName' AND gameName = 'lowcard'";
        return $this->exec($sql);
    }

    public function clear($roomName)
    {
        $sql = "DELETE FROM lowcard_bot WHERE roomName = '$roomName'";
        return $this->exec($sql);
    }

    public function stop($roomName)
    {
        $sql = "DELETE FROM game WHERE roomName = '$roomName' AND gameName = 'lowcard'";
        $sql2 = "DELETE FROM lowcard_state WHERE roomName = '$roomName'";
        return $this->exec($sql) && $this->exec($sql2);
    }

    public function refund($roomName, $amount, $rows)
    {
        $accountUtils = new AccountUtils();
        for ($i = 0, $n = count($rows); $i < $n; $i++) {
            $username = $rows[$i]["username"];
            $transferable = $rows[$i]["transferable"];
            $accountUtils->addCredit($username, $amount, $transferable);
            $accountUtils->addHistory($username, "+", $amount, "Refunded $amount BDT from LowCard game in $roomName chat room");
        }
        return true;
    }
}
