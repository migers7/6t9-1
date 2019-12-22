<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'db_utils.php';
include_once 'room_utils.php';
include_once 'userUtils.php';
include_once 'notification_utils.php';
include_once 'gameManager.php';
include_once 'firebaseManager.php';
include_once 'account_utils.php';
include_once 'admin_log.php';

class AdminUtils
{
    private $pdo;
    private $isModerator;
    private $isOwner;
    private $roomUtils;
    private $ref;
    private $accountUtils;

    public function __construct()
    {
        $this->roomUtils = new RoomUtils();
        $this->pdo = getConn();
        $this->ref = FirebaseManager::getInstance();
        $this->accountUtils = new AccountUtils();
    }

    public function command($commandText, $roomName, $username)
    {
        $room = $this->roomUtils->getRoom($roomName)[0];
        $this->isModerator = $this->roomUtils->isModerator($username, $roomName);
        $this->isOwner = ($room["owner"] == $username || $this->roomUtils->isAdmin($username));

        $words = explode(" ", $commandText);

        if (count($words) > 1) {
            if ($words[0] == "/setlevel") return $this->setLevel($username, $roomName, (int)$words[1]);
            if ($words[0] == "/silence" && count($words) == 2) return $this->silence($roomName, $username, (int)$words[1]);
            if ($words[0] == "/mod") return $this->mod($words, $username, $roomName);
            if ($words[0] == "/demod") return $this->demod($words, $username, $roomName);
            if ($words[0] == "/suspendall") return $this->suspendAll($username, $words[1]);
            if ($words[0] == "/suspend") return $this->suspend($username, $words[1]);
            if ($words[0] == "/bump") return $this->bump($username, $words[1], $roomName);
            if ($words[0] == "/activate") return $this->activate($username, $words[1]);
            if ($words[0] == "/announce") return $this->changeAnnouncement($words, $roomName, $username, $commandText);
            if ($words[0] == "/description") return $this->changeDescription($words, $roomName, $username, $commandText);
            if ($words[0] == "/broadcast") return $this->broadcast($words, $roomName, $username, $commandText);
            if ($words[0] == "/bot") {
                if ($words[1] == "dice") {
                    if ($this->isOwner) {
                        return $this->addBot("dice", $roomName);
                    } else return buildErrorResponse("You don't have authorization to perform this action.");
                }
                if ($words[1] == "lowcard") {
                    if ($this->isOwner) {
                        return $this->addBot("lowcard", $roomName);
                    } else return buildErrorResponse("You don't have authorization to perform this action.");
                }
                if ($words[1] == "cricket") {
                    if ($this->isOwner) {
                        return $this->addBot("cricket", $roomName);
                    } else return buildErrorResponse("You don't have authorization to perform this action.");
                }
                if ($words[1] == "stop") {
                    if ($this->isOwner) {
                        return $this->stopBot($roomName, $username);
                    } else return buildErrorResponse("You don't have authorization to perform this action.");
                }
            }
        }
        if ($words[0] == "/silence") return $this->silence($roomName, $username, 30);
        if ($words[0] == "/lock") return $this->lock($roomName, $username);
        if ($words[0] == "/unlock") return $this->unlock($roomName, $username);
        return buildErrorResponse("Invalid command or arguments.");
    }

    public function bump($self, $username, $roomName)
    {
        if ($this->hasAuth($self, $username, $roomName)) {
            if ($this->roomUtils->isInRoom($username, $roomName) == false) {
                return buildErrorResponse("$username is not in the chat room.");
            }
            $sql = "INSERT INTO bumped_user(username, roomName, bumped_by) VALUES('$username', '$roomName', '$self')";
            $pdo = getConn();
            $pdo->exec($sql);
            $this->roomUtils->leaveRoom($roomName, $username);
            (new NotificationUtils())->pushTopic($username, "Kick", $roomName, "You have been bumped by $self");
            $this->ref->getReference("chats/" . $roomName)->push(
                MessageHelper::getRoomInfo($roomName, "$username has been bumped by $self")
            );
            $pdo = null;
            if($this->roomUtils->isAdmin($self)) {
                AdminLogUtils::logBump($self, $username, $roomName);
            }
            return buildSuccessResponse("You have bumped $username");
        }
        return buildErrorResponse("You don't have authorization to perform this action.");
    }

    public function suspendSameDevice($targetUser) {
        $sql = "SELECT device_id FROM device WHERE username = '$targetUser'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) {
            $deviceId = $res[0]["device_id"];
            if ($deviceId == "") return buildErrorResponse("This user is using an emulator or some device from which we cannot retrieve enough information.");
            $sql = "SELECT username FROM users WHERE active = 1 AND username IN (SELECT username FROM device WHERE device_id = '$deviceId' AND username != '$targetUser')";
            $pdo = getConn();
            $rows = cast($pdo->query($sql));
            $sql = "UPDATE users SET active = 0, mentor = '', merchantSince = NULL, accessToken = 'hojoborolo', color = '', isMentor = 0, isMerchant = 0 WHERE username IN (";
            $names = "";
            for ($i = 0; $i < count($rows); $i++) {
                $u = $rows[$i]["username"];
                if ($i > 0) {
                    $sql .= ", ";
                    $names .= ", ";
                }
                $sql .= "'$u'";
                $names .= $u;
            }

            $sql .= ") AND isStaff = 0 AND isAdmin = 0 AND isOwner = 0";
            $changed = $pdo->exec($sql);
            $pdo = null;
            if ($changed > 0) {
                return $names;
            }
            return null;
        } else {
            return null;
        }
    }

    public function suspendAll($username, $targetUser)
    {
        $sql = "SELECT id FROM users WHERE username = '$username' AND (isAdmin = 1 OR isStaff = 1 OR isOwner = 1)";
        $pdo = getConn();
        $isAdmin = (boolean)(count(cast($pdo->query($sql))) > 0);
        $pdo = null;
        if ($isAdmin) {
            $sql = "SELECT device_id FROM device WHERE username = '$targetUser'";
            $pdo = getConn();
            $res = cast($pdo->query($sql));
            $pdo = null;
            if (count($res) > 0) {
                $deviceId = $res[0]["device_id"];
                if ($deviceId == "") return buildErrorResponse("This user is using an emulator or some device from which we cannot retrieve enough information.");
                $sql = "SELECT username FROM device WHERE device_id = '$deviceId'";
                $pdo = getConn();
                $rows = cast($pdo->query($sql));
                $sql = "UPDATE users SET active = 0, mentor = '', merchantSince = NULL, accessToken = 'hojoborolo', color = '', isMentor = 0, isMerchant = 0 WHERE username IN (";
                for ($i = 0; $i < count($rows); $i++) {
                    $u = $rows[$i]["username"];
                    if ($i > 0) $sql .= ", ";
                    $sql .= "'$u'";
                }

                $sql .= ") AND isStaff = 0 AND isAdmin = 0 AND isOwner = 0";
                $changed = $pdo->exec($sql);
                $pdo = null;
                if ($changed > 0) {
                    return buildErrorResponse("Suspended $changed users who have the same device id as $targetUser");
                }
                return buildErrorResponse("No suspension occurred");
            } else {
                return buildErrorResponse("No device id found for username $targetUser");
            }
        } else {
            return buildErrorResponse("No such command exists.");
        }
    }

    public function suspend($username, $id)
    {
        if ($this->roomUtils->isAdmin($username)) {
            if ($this->roomUtils->isAdmin($id)) {
                return buildErrorResponse("Unable to suspend user $id");
            } else {
                $sql = "UPDATE users SET active = 0, mentor = '', isMerchant = 0, isMentor = 0, color = '', merchantSince = NULL WHERE username = '$id'";
                $suspended = $this->pdo->exec($sql);
                if ($suspended) {
                    AdminLogUtils::logSuspend($username, $id);
                    return buildErrorResponse("$id has been suspended from all chat rooms");
                }
                return buildErrorResponse("$id is already suspended from all chat rooms");
            }
        }
        return buildErrorResponse("You do not have authorization to perform this action");
    }

    public function activate($username, $id)
    {
        if ($this->roomUtils->isAdmin($username)) {
            if ($this->roomUtils->isAdmin($id)) {
                return buildErrorResponse("Unable to activate user $id");
            } else {
                $sql = "UPDATE users SET active = 1 WHERE username = '$id'";
                $activated = $this->pdo->exec($sql);
                if ($activated) {
                    AdminLogUtils::logActivate($username, $id);
                    return buildErrorResponse("$id has been been freed of suspension from all chat rooms");
                }
                return buildErrorResponse("$id is already freed of suspension from all chat rooms");
            }
        }
        return buildErrorResponse("You do not have authorization to perform this action");
    }

    public function silence($roomName, $username, $duration)
    {
        if (is_numeric($duration) == false) {
            return buildErrorResponse("Invalid command or argument.");
        }
        if (!$this->isOwner && !$this->isModerator) return buildErrorResponse("You do not have authorization to perform this action.");
        $r = cast($this->pdo->query("SELECT silenced FROM rooms WHERE name = '$roomName'"));
        if (count($r) > 0 && $r[0]["silenced"] == true) return buildErrorResponse("This room is already silenced");
        if ($duration > 300 || $duration < 30) return buildErrorResponse("Duration must be 30 - 300 sec.");
        $res = $this->roomUtils->getUsers($roomName);
        $this->pdo->exec("UPDATE rooms SET silenced = 1 WHERE name = '$roomName'");
        for ($i = 0; $i < count($res); $i++) {
            (new NotificationUtils())->push($res[$i]["username"], "silenced", $roomName, "This room has been silenced.");
        }
        $this->ref->getReference("chats/" . $roomName)->push(
            MessageHelper::getSilence($roomName, "This room has been silenced by $username")
        );
        sleep($duration);
        $this->pdo->exec("UPDATE rooms SET silenced = 0 WHERE name = '$roomName'");
        for ($i = 0; $i < count($res); $i++) {
            (new NotificationUtils())->push($res[$i]["username"], "unsilenced", $roomName, "This room has been unsilenced.");
        }
        return buildSuccessResponse("This room has been silenced.");
    }

    public function setLevel($username, $roomName, $minLevel)
    {
        if (!is_int($minLevel) || $minLevel < 1 || $minLevel > 1000) return buildErrorResponse("Level must be integer in the range 1-1000");
        if (!$this->isOwner && !$this->isModerator) return buildErrorResponse("You do not have authorization to perform this action.");
        $this->pdo->exec("UPDATE rooms SET minLevel = $minLevel WHERE name = '$roomName'");
        $this->ref->getReference("chats/" . $roomName)->push(
            MessageHelper::getRoomInfo($roomName, "Minimum level has been set to $minLevel")
        );
        AdminLogUtils::logSetLevel($username, $roomName, $minLevel);
        return buildSuccessResponse("Minimum level has been set to $minLevel.");
    }

    public function addBot($bot, $roomName)
    {
        $roomInfo = $this->roomUtils->getRoom($roomName)[0];
        if ($roomInfo["bot"] == null || $roomInfo["bot"] == "") {
            $sql = "UPDATE rooms SET bot = '$bot' WHERE name = '$roomName'";
            $this->pdo->exec($sql);
            $bot = ucfirst($bot);
            if ($bot == "Lowcard") {
                $this->ref->getReference("chats/" . $roomName)->push(
                    MessageHelper::getLowcardInfoText($roomName, "LowCard Bot has been added to the room")
                );
            } else if ($bot == "Cricket") {
                $this->ref->getReference("chats/" . $roomName)->push(
                    MessageHelper::getCricketInfo($roomName, "Cricket Bot has been added to the room")
                );
            } else {
                $this->ref->getReference("chats/" . $roomName)->push(
                    MessageHelper::getRoomInfo($roomName, "$bot Bot has been added to the room")
                );
            }
            return buildSuccessResponse("$bot Bot has been added to the room");
        }
        return buildErrorResponse("$bot Bot is already added to this room");
    }

    public function stopBot($roomName, $username)
    {
        $stopper = $username;
        $roomInfo = $this->roomUtils->getRoom($roomName)[0];
        if ($roomInfo["bot"] == null || $roomInfo["bot"] == "") {
            return buildErrorResponse("Invalid command or arguments.");
        }
        $bot = strtolower($roomInfo["bot"]);
        if ($bot == "lowcard") {
            if (!$this->roomUtils->isAdmin($username)) {
                return buildErrorResponse("You do not have authorization to perform this action.");
            }
            // stop lowcard bot
            // credits should be refunded
            $pdo = getConn();
            $sql = "SELECT username, amount, transferable FROM lowcard_bot WHERE roomName = '$roomName'";
            $res = cast($pdo->query($sql));
            $sql = "DELETE FROM lowcard_bot WHERE roomName = '$roomName'";
            $pdo->exec($sql);
            $sql = "DELETE FROM lowcard_state WHERE roomName = '$roomName'";
            $pdo->exec($sql);
            for ($i = 0; $i < count($res); $i++) {
                $username = $res[$i]["username"];
                $amount = $res[$i]["amount"];
                $this->accountUtils->addCredit($username, $amount, $res[$i]["transferable"]);
                $this->accountUtils->addHistory(
                    $username,
                    "+",
                    $amount,
                    "Refunded " . $amount . " BDT from LowCard game in " . $roomName . " chat room"
                );

            }

            $this->ref->getReference("chats/" . $roomName)->push(
                MessageHelper::getLowcardInfoText($roomName, "LowCard Bot has been stopped by " . $stopper)
            );

        } else if ($bot == "dice") {
            // stop dice
            $pdo = getConn();
            $sql = "SELECT * FROM dice WHERE roomName = '$roomName'";
            $gameBids = cast($this->pdo->query($sql));
            $sql = "DELETE FROM dice WHERE roomName = '$roomName'";
            $pdo->exec($sql);

            for ($i = 0; $i < count($gameBids); $i++) {
                $bid = $gameBids[$i];
                $bidAmount = $bid["amount"];
                $username = $bid["username"];
                $this->accountUtils->addCredit($username, $bidAmount, $bid["transferable"]);
                $this->accountUtils->addHistory(
                    $username,
                    "+",
                    $bidAmount,
                    "Refunded " . $bidAmount . " BDT from Dice game in " . $roomName . " chat room"
                );
            }
            $this->ref->getReference("chats/" . $roomName)->push(
                MessageHelper::getRoomInfo($roomName, "Dice Bot has been stopped by " . $stopper)
            );

        } else if ($bot == "cricket") {
            // stop cricket
            // credits should be refunded for cricket
            $pdo = getConn();
            $sql = "SELECT username, amount, transferable FROM cricket_bot WHERE roomName = '$roomName'";
            $res = cast($pdo->query($sql));
            $sql = "DELETE FROM cricket_bot WHERE roomName = '$roomName'";
            $pdo->exec($sql);
            $sql = "DELETE FROM cricket_state WHERE roomName = '$roomName'";
            $pdo->exec($sql);
            for ($i = 0; $i < count($res); $i++) {
                $username = $res[$i]["username"];
                $amount = $res[$i]["amount"];
                $this->accountUtils->addCredit($username, $amount, $res[$i]["transferable"]);
                $this->accountUtils->addHistory(
                    $username,
                    "+",
                    $amount,
                    "Refunded " . $amount . " BDT from Cricket game in " . $roomName . " chat room"
                );

            }

            $this->ref->getReference("chats/" . $roomName)->push(
                MessageHelper::getCricketInfo($roomName, "Cricket Bot has been stopped by " . $stopper)
            );
        }

        $sql = "UPDATE rooms SET bot = '' WHERE name = '$roomName'";
        $pdo = getConn();
        $pdo->exec($sql);
        $sql = "DELETE FROM game WHERE roomName = '$roomName'";
        $pdo->exec($sql);
        $pdo = null;

        return buildSuccessResponse("$bot Bot has been stopped by " . $stopper);
    }

    public function isBeingKicked($username, $roomName)
    {
        $sql = "SELECT * FROM public_kick WHERE username = '$username' AND roomName = '$roomName'";
        $count = count(cast($this->pdo->query($sql)));
        if ($count > 0) return true;
        return false;
    }

    public function kick($commandText, $roomName, $username)
    {
        $room = $this->roomUtils->getRoom($roomName)[0];
        $this->isModerator = $this->roomUtils->isModerator($username, $roomName);
        $this->isOwner = ($room["owner"] == $username || $this->roomUtils->isAdmin($username));

        $words = explode(" ", $commandText);

        if (count($words) != 2) return buildErrorResponse("Invalid command or arguments.");
        if ($username == $words[1]) return buildErrorResponse("Invalid command or arguments.");
        if ($this->roomUtils->isAdmin($words[1])) return buildErrorResponse("Invalid command or arguments.");
        if ($this->userExists($words[1]) == false) return buildErrorResponse("No user found for username " . $words[1]);
        if (!$this->roomUtils->isInRoom($words[1], $roomName)) return buildErrorResponse($words[1] . " is not in the chat room");

        if ($this->roomUtils->isModerator($words[1], $roomName) || $room["owner"] == $words[1]) return buildErrorResponse("You do not have authorization to perform this action.");
        if (!$this->isOwner && !$this->isModerator) {
            $kickedUserName = $words[1];
            $kickedByUsername = $username;
            $isPublicKickEnabled = (boolean)$room["public_kick_enabled"];
            if ($isPublicKickEnabled) {
                if ($this->isBeingKicked($kickedUserName, $roomName)) {
                    $sql = "SELECT * FROM public_kick WHERE username = '$kickedUserName' AND roomName = '$roomName' AND voter = '$kickedByUsername'";
                    if (count(cast($this->pdo->query($sql))) > 0) return buildErrorResponse("You already voted to kick $kickedUserName");
                    $userSelf = $this->accountUtils->getBalance($kickedByUsername);
                    $balance = $userSelf["balance"];
                    if ($balance - 24.9 < 41.8) return buildErrorResponse("Not enough balance to start a kick");
                    $sql = "INSERT INTO public_kick(username, roomName, voter) VALUES (?, ?, ?)";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([$kickedUserName, $roomName, $kickedByUsername]);
                    $this->accountUtils->deductCredit($username, 24.9);
                    $this->accountUtils->addHistory($username, "-", 24.9, "Spent 24.9 BDT to vote a kick in $roomName chat room");
                    return buildErrorResponse("You have voted to kick $kickedUserName");
                } else {
                    // start public kick
                    $userSelf = $this->accountUtils->getBalance($kickedByUsername);
                    $balance = $userSelf["balance"];
                    if ($balance - 24.9 < 41.8) return buildErrorResponse("Not enough balance to start a kick");
                    return $this->public_kick($kickedByUsername, $kickedUserName, $roomName);
                }
            }
            return buildErrorResponse("You do not have authorization to perform this action.");
        }
        $this->addAsKickedUser($roomName, $words[1], $username);
        $userUtils = new UserUtils();
        $kickedUser = $userUtils->findUser($words[1]);
        $kickedBy = $userUtils->findUser($username);
        $power = "chat room moderator";
        if ($this->roomUtils->getRoom($roomName)[0]["owner"] == $username) $power = "chat room admin";
        if ($this->roomUtils->isAdmin($username)) $power = "administrator";
        $messageText = "You have been kicked by " . $power . " " . $kickedBy["username"];
        (new NotificationUtils())->push($kickedUser["username"], "Kick", $roomName, $messageText);
        $text = $kickedUser["username"] . "[" . $kickedUser["userLevel"] . "] has been kicked by " . $power . " " . $kickedBy["username"] . "[" . $kickedBy["userLevel"] . "]";
        $this->ref->getReference("chats/" . $roomName)->push(
            MessageHelper::getKick($roomName, $text)
        );

        // log
        if ($power == "administrator") {
            AdminLogUtils::logKick($username, $words[1], $roomName);
        }

        return buildSuccessResponse([
            "kickedUser" => $kickedUser, "kickedBy" => $kickedBy, "room" => $room,
            "authType" => $power
        ]);
    }

    public function public_kick($username, $toBeKicked, $roomName)
    {
        if ($roomName == "Bangladesh") return buildErrorResponse("Invalid command");
        if ($this->roomUtils->isInRoom($username, $roomName) == false) {
            $messageText = "You are not in $roomName chat room";
            (new NotificationUtils())->push($username, "Auto left", $roomName, $messageText);
            return buildErrorResponse("You are not in this chat room.");
        }
        $sql = "INSERT INTO public_kick(username, roomName, voter) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$toBeKicked, $roomName, $username]);
        $this->accountUtils->deductCredit($username, 24.9);
        $this->accountUtils->addHistory($username, "-", 24.9, "Spent 24.9 BDT to vote a kick in $roomName chat room");

        $total = 5;
        $voteNeeded = $total - 1;

        $this->ref->getReference("chats/" . $roomName)->push(
            MessageHelper::getKick($roomName, "A vote to kick $toBeKicked has been started by $username. $voteNeeded more votes needed. 60s remaining.")
        );
        $userUtils = new UserUtils();
        $kickedUser = $userUtils->findUser($toBeKicked);

        sleep(20);
        $sql = "SELECT * FROM public_kick WHERE username = '$toBeKicked' AND roomName = '$roomName'";
        $res = cast($this->pdo->query($sql));
        $vote = count($res);
        if ($this->roomUtils->isInRoom($toBeKicked, $roomName) == false || $vote == 0) {
            $sql = "DELETE FROM public_kick WHERE roomName = '$roomName' AND username = '$toBeKicked'";
            $this->pdo->exec($sql);
            $this->ref->getReference("chats/" . $roomName)->push(
                MessageHelper::getRoomInfo($roomName, "Failed to kick user $toBeKicked. It is possible that the user has already been kicked or has left.")
            );
            return buildErrorResponse("Failed to kick user $toBeKicked. It is possible that the user has already been kicked or has left.");
        }
        if ($vote >= $total) {
            $text = $kickedUser["username"] . "[" . $kickedUser["userLevel"] . "] has been kicked";
            $this->addAsKickedUser($roomName, $toBeKicked, "room users");
            $messageText = "You have been kicked";
            $this->sendInfoToAdmins($res, $roomName, $toBeKicked);
            (new NotificationUtils())->push($toBeKicked, "Kick", $roomName, $messageText);
            $this->ref->getReference("chats/" . $roomName)->push(
                MessageHelper::getKick($roomName, $text)
            );
            return buildErrorResponse($text);
        }

        $voteNeeded = $total - $vote;
        $this->ref->getReference("chats/" . $roomName)->push(
            MessageHelper::getKick($roomName, "A vote to kick $toBeKicked is running. $voteNeeded more votes needed. 40s remaining.")
        );

        sleep(20);
        $sql = "SELECT * FROM public_kick WHERE username = '$toBeKicked' AND roomName = '$roomName'";
        $res = cast($this->pdo->query($sql));
        $vote = count($res);
        if ($this->roomUtils->isInRoom($toBeKicked, $roomName) == false || $vote == 0) {
            $sql = "DELETE FROM public_kick WHERE roomName = '$roomName' AND username = '$toBeKicked'";
            $this->pdo->exec($sql);
            $this->ref->getReference("chats/" . $roomName)->push(
                MessageHelper::getRoomInfo($roomName, "Failed to kick user $toBeKicked. It is possible that the user has already been kicked or has left.")
            );
            return buildErrorResponse("Failed to kick user $toBeKicked. It is possible that the user has already been kicked or has left.");
        }
        if ($vote >= $total) {
            $text = $kickedUser["username"] . "[" . $kickedUser["userLevel"] . "] has been kicked";
            $this->addAsKickedUser($roomName, $toBeKicked, "room users");
            $messageText = "You have been kicked";
            $this->sendInfoToAdmins($res, $roomName, $toBeKicked);
            (new NotificationUtils())->push($toBeKicked, "Kick", $roomName, $messageText);
            $this->ref->getReference("chats/" . $roomName)->push(
                MessageHelper::getKick($roomName, $text)
            );
            return buildErrorResponse($text);
        }

        $voteNeeded = $total - $vote;
        $this->ref->getReference("chats/" . $roomName)->push(
            MessageHelper::getKick($roomName, "A vote to kick $toBeKicked is running. $voteNeeded more votes needed. 20s remaining.")
        );

        sleep(20);
        $sql = "SELECT * FROM public_kick WHERE username = '$toBeKicked' AND roomName = '$roomName'";
        $res = cast($this->pdo->query($sql));
        $vote = count($res);
        if ($this->roomUtils->isInRoom($toBeKicked, $roomName) == false || $vote == 0) {
            $sql = "DELETE FROM public_kick WHERE roomName = '$roomName' AND username = '$toBeKicked'";
            $this->pdo->exec($sql);
            $this->ref->getReference("chats/" . $roomName)->push(
                MessageHelper::getRoomInfo($roomName, "Failed to kick user $toBeKicked. It is possible that the user has already been kicked or has left.")
            );
            return buildErrorResponse("Failed to kick user $toBeKicked. It is possible that the user has already been kicked or has left.");
        }
        if ($vote >= $total) {
            $text = $kickedUser["username"] . "[" . $kickedUser["userLevel"] . "] has been kicked";
            $this->addAsKickedUser($roomName, $toBeKicked, "room users");
            $messageText = "You have been kicked";

            $this->sendInfoToAdmins($res, $roomName, $toBeKicked);

            (new NotificationUtils())->push($toBeKicked, "Kick", $roomName, $messageText);
            $this->ref->getReference("chats/" . $roomName)->push(
                MessageHelper::getKick($roomName, $text)
            );
            return buildErrorResponse($text);
        } else {
            $sql = "DELETE FROM public_kick WHERE roomName = '$roomName' AND username = '$toBeKicked'";
            $this->pdo->exec($sql);
            $this->ref->getReference("chats/" . $roomName)->push(
                MessageHelper::getRoomInfo($roomName, "Not enough votes to kick $toBeKicked")
            );
            return buildErrorResponse("Not enough votes to kick $toBeKicked");
        }
    }

    public function sendInfoToAdmins($votes, $roomName, $toBeKicked)
    {
        $subject = "A user has been kicked";
        $body = "Kicked username: $toBeKicked\nRoom name: $roomName\n";
        $body = $body . "The users who voted are:\n";

        for ($i = 0; $i < count($votes); $i++) {
            $body = $body . $votes[$i]["voter"] . "\n";
        }

        $body = $body . "\n\n[This is an autogenerated email. You do not need to reply anything.]";
        $pdo = getConn();
        $sql = "SELECT username FROM users WHERE isOwner = 1 OR isStaff = 1";
        $res = cast($pdo->query($sql));
        $time_stamp = date("Y-m-d H:i:s", time());
        for ($i = 0; $i < count($res); $i++) {
            $to = $res[$i]["username"];
            $noReply = "No reply";
            $sql = "INSERT INTO emails(username, sender, recipient, subject, body, seen, time, canReply) VALUES(
                '$to', '$noReply', '$to', '$subject', '$body', false, '$time_stamp', 0)";
            $pdo->exec($sql);
            (new NotificationUtils())->push($to, "email_notification", "Email", "You have received an email.");
        }
        $pdo = null;
    }

    public function addAsKickedUser($roomName, $kickedUser, $kickedBy)
    {
        $sql = "DELETE FROM public_kick WHERE roomName = '$roomName' AND username = '$kickedUser'";
        $this->pdo->exec($sql);
        $sql = "INSERT INTO kicked_users(roomName, username, kickedBy, timestamp) VALUES(?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$roomName, $kickedUser, $kickedBy, date("Y-m-d H:i:s", time())]);
        $this->roomUtils->leaveRoom($roomName, $kickedUser);
    }

    public function ban($commandText, $roomName, $username)
    {
        $room = $this->roomUtils->getRoom($roomName)[0];
        $this->isModerator = $this->roomUtils->isModerator($username, $roomName);
        $isAdmin = $this->roomUtils->isAdmin($username);
        $this->isOwner = ($room["owner"] == $username || $isAdmin);

        $words = explode(" ", $commandText);

        if (!$this->isOwner && !$this->isModerator) return buildErrorResponse("You do not have authorization to perform this action.");
        if ($room["owner"] == $words[1]) {
            if (!$this->roomUtils->isAdmin($username)) return buildErrorResponse("You do not have authorization to perform this action.");
        }
        if ($this->roomUtils->isModerator($words[1], $roomName)) {
            if (!$this->isOwner) return buildErrorResponse("You do not have authorization to perform this action.");
        }
        if (count($words) != 3) return buildErrorResponse("Invalid command or arguments.");
        if ($username == $words[1]) return buildErrorResponse("Invalid command or arguments.");
        if ($this->roomUtils->isAdmin($words[1])) return buildErrorResponse("Invalid command or arguments.");
        if ($this->userExists($words[1]) == false) return buildErrorResponse("No user found for username " . $words[1]);

        if ($this->isBanned($words[1], $roomName)) {
            return buildErrorResponse("This user is already banned in this room.");
        }
        $reasons = ["", "flooding in the chat room", "spamming in the chat room", "abusing", "hacking"];
        $id = 1;
        if (in_array($words[2], ["1", "2", "3", "4"])) {
            $id = (int)$words[2];
        } else {
            return buildErrorResponse("Reason must be between 1 to 4");
        }
        $this->addAsBannedUser($roomName, $words[1], $username);
        $this->demod(explode(" ", "/demod " . $words[1]), $username, $roomName);
        $userUtils = new UserUtils();
        $bannedUser = $userUtils->findUser($words[1]);
        $bannedBy = $userUtils->findUser($username);
        $messageText = "You have been banned from this chat room";
        (new NotificationUtils())->push($bannedUser["username"], "Ban", $roomName, $messageText);
        $text = $bannedUser["username"] . "[" . $bannedUser["userLevel"] . "] has been banned by " . $bannedBy["username"] . "[" . $bannedBy["userLevel"] . "]. Reason: "
            . $reasons[$id];

        $this->ref->getReference("chats/" . $roomName)->push(
            MessageHelper::getBan($roomName, $text)
        );

        // log
        if ($isAdmin) {
            AdminLogUtils::logBan($bannedBy["username"], $bannedUser["username"], $roomName, $reasons[$id]);
        }

        return buildSuccessResponse([
            "bannedUser" => $bannedUser, "bannedBy" => $bannedBy, "room" => $room, "reason" => $reasons[$id]
        ]);
    }

    public function isBanned($username, $roomName)
    {
        $sql = "SELECT * FROM banned_users WHERE username = '$username' and roomName = '$roomName'";
        $res = cast($this->pdo->query($sql));
        if (count($res) > 0) return true;
        return false;
    }

    public function addAsBannedUser($roomName, $bannedUser, $bannedBy)
    {
        $sql = "INSERT INTO banned_users(roomName, username, bannedBy) VALUES(?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$roomName, $bannedUser, $bannedBy]);
        $this->roomUtils->leaveRoom($roomName, $bannedUser);
    }

    public function unban($commandText, $roomName, $username)
    {
        $room = $this->roomUtils->getRoom($roomName)[0];
        $isAdmin = $this->roomUtils->isAdmin($username);
        $this->isModerator = $this->roomUtils->isModerator($username, $roomName);
        $this->isOwner = ($room["owner"] == $username || $isAdmin);

        $words = explode(" ", $commandText);

        if (!$this->isOwner && !$this->isModerator) return buildErrorResponse("You do not have authorization to perform this action.");

        if (count($words) != 3) return buildErrorResponse("Invalid command or arguments.");
        if ($username == $words[1]) return buildErrorResponse("Invalid command or arguments.");
        if ($this->roomUtils->isAdmin($words[1])) return buildErrorResponse("Invalid command or arguments.");
        if ($this->userExists($words[1]) == false) return buildErrorResponse("No user found for username " . $words[1]);

        $reasons = ["", "giving user his first chance", "giving user his last chance"];
        $id = 1;
        if (in_array($words[2], ["1", "2"])) {
            $id = (int)$words[2];
        } else {
            return buildErrorResponse("Reason must be between 1 to 2");
        }
        $this->removeFromBannedUser($roomName, $words[1]);
        $userUtils = new UserUtils();
        $bannedUser = $userUtils->findUser($words[1]);
        $unbannedBy = $userUtils->findUser($username);
        $text = $bannedUser["username"] . "[" . $bannedUser["userLevel"] . "] has been unbanned by " . $unbannedBy["username"] . "[" . $unbannedBy["userLevel"] . "]. Reason: "
            . $reasons[$id];

        $this->ref->getReference("chats/" . $roomName)->push(
            MessageHelper::getRoomInfo($roomName, $text)
        );

        // log
        if ($isAdmin) {
            AdminLogUtils::logUnban($unbannedBy["username"], $bannedUser["username"], $roomName, $reasons[$id]);
        }

        return buildSuccessResponse([
            "bannedUser" => $bannedUser, "unbannedBy" => $unbannedBy, "room" => $room, "reason" => $reasons[$id]
        ]);
    }

    public function removeFromBannedUser($roomName, $bannedUser)
    {
        $sql = "DELETE FROM banned_users WHERE roomName = ? and username = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$roomName, $bannedUser]);
    }

    public function lock($roomName, $username)
    {
        if (!$this->isOwner && !$this->isModerator) return buildErrorResponse("You do not have authorization to perform this action.");
        $room = $this->roomUtils->getRoom($roomName)[0];
        if ($room["locked"]) return buildErrorResponse("This chat room is already locked by " . $room["lockedBy"]);
        $sql = "UPDATE rooms SET locked = 1 WHERE name = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$roomName]);
        $sql = "UPDATE rooms SET lockedBy = ? WHERE name = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$username, $roomName]);
        $this->ref->getReference("chats/" . $roomName)->push(
            MessageHelper::getRoomInfo($roomName, "This chat room has been locked by " . $username . ". Only moderators/admins can enter/unlock this chat room.")
        );
        return buildSuccessResponse($message = "This chat room has been locked by " . $username . ". Only moderators/admins can enter/unlock this chat room.");
    }

    public function unlock($roomName, $username)
    {
        if (!$this->isOwner && !$this->isModerator) return buildErrorResponse("You do not have authorization to perform this action.");
        $room = $this->roomUtils->getRoom($roomName)[0];
        if ($room["locked"]) {
            $sql = "UPDATE rooms SET locked = 0 WHERE name = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$roomName]);
            $sql = "UPDATE rooms SET lockedBy = ? WHERE name = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(["", $roomName]);
            $this->ref->getReference("chats/" . $roomName)->push(
                MessageHelper::getRoomInfo($roomName, "This chat room has been unlocked by " . $username)
            );
            return buildSuccessResponse($message = "This chat room has been unlocked by " . $username);
        }
        return buildErrorResponse("This chat room is not locked.");
    }

    public function changeAnnouncement($words, $roomName, $username, $commandText)
    {
        if (!$this->isOwner && !$this->isModerator) return buildErrorResponse("You do not have authorization to perform this action.");
        $announcement = $this->getAnnouncement($roomName);
        if ($words[1] == "off" && count($words) == 2) {
            if ($announcement == "") return buildErrorResponse("There is currently no announcement to turn off.");
            $sql = "UPDATE rooms SET announcement = ? WHERE name = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(["", $roomName]);
            $this->ref->getReference("chats/" . $roomName)->push(
                MessageHelper::getRoomInfo($roomName, $username . " has turned off the announcement")
            );
            return buildSuccessResponse($message = $username . " has turned off the announcement");
        } else {
            if ($announcement != "") return buildErrorResponse("Turn off the previous announcement first.");
            $announcement = substr($commandText, strlen($words[0]) + 1);
            $sql = "UPDATE rooms SET announcement = ? WHERE name = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$announcement, $roomName]);
            $this->ref->getReference("chats/" . $roomName)->push(
                MessageHelper::getRoomInfo($roomName, $username . " has changed the announcement to '$announcement'")
            );
            return buildSuccessResponse($message = $username . " has changed the announcement to '$announcement'");
        }
    }

    public function changeDescription($words, $roomName, $username, $commandText)
    {
        if (!$this->isOwner) return buildErrorResponse("You do not have authorization to perform this action.");
        $description = substr($commandText, strlen($words[0]) + 1);
        $sql = "UPDATE rooms SET description = ? WHERE name = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$description, $roomName]);
        $this->ref->getReference("chats/" . $roomName)->push(
            MessageHelper::getRoomInfo($roomName, $username . " has changed the description to '$description'")
        );
        return buildSuccessResponse($message = $username . " has changed the description to '$description'");
    }

    public function broadcast($words, $roomName, $username, $commandText)
    {
        if (!$this->isOwner && !$this->isModerator) return buildErrorResponse("You do not have authorization to perform this action.");
        $broadcast = substr($commandText, strlen($words[0]) + 1);
        $this->ref->getReference("chats/" . $roomName)->push(MessageHelper::getBroadcast($roomName, "[Broadcast by $username]: $broadcast"));
        return buildSuccessResponse("[Broadcast by $username]: $broadcast");
    }

    public function getAnnouncement($roomName)
    {
        $room = $this->roomUtils->getRoom($roomName)[0];
        return $room["announcement"];
    }

    public function mod($words, $user, $roomName)
    {
        if (!$this->isOwner) return buildErrorResponse("You do not have authorization to perform this action.");
        if (count($words) > 2) return buildErrorResponse("Invalid command or arguments.");
        if ($user == $words[1]) return buildErrorResponse("Invalid command or arguments.");
        if ($this->roomUtils->isAdmin($words[1])) return buildErrorResponse("Invalid command or arguments.");
        if ($this->userExists($words[1]) == false) return buildErrorResponse("No user found for username " . $words[1]);
        if ($this->roomUtils->isModerator($words[1], $roomName)) return buildErrorResponse($words[1] . " is already a moderator of this chat room.");

        $sql = "insert into modship(roomName, username) values(?, ?);";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$roomName, $words[1]]);
        $sql = "UPDATE room_users SET isModerator = true WHERE roomName = ? and username = ?;";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$roomName, $words[1]]);
        $this->ref->getReference("chats/" . $roomName)->push(
            MessageHelper::getRoomInfo($roomName, $words[1] . " is now a moderator of this chat room")
        );

        // log
        if ($this->roomUtils->isAdmin($user)) {
            AdminLogUtils::logMod($user, $words[1], $roomName);
        }

        return buildSuccessResponse($message = $words[1] . " is now a moderator of this chat room");
    }

    public function demod($words, $user, $roomName)
    {
        if (!$this->isOwner) return buildErrorResponse("You do not have authorization to perform this action.");
        if (count($words) > 2) return buildErrorResponse("Invalid command or arguments.");
        if ($user == $words[1]) return buildErrorResponse("Invalid command or arguments.");
        if ($this->roomUtils->isAdmin($words[1])) return buildErrorResponse("Invalid command or arguments.");
        if ($this->userExists($words[1]) == false) return buildErrorResponse("No user found for username " . $words[1]);
        if ($this->roomUtils->isModerator($words[1], $roomName) == false) return buildErrorResponse($words[1] . " is not a moderator of this chat room.");

        $sql = "delete from modship where roomName = ? and username = ?;";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$roomName, $words[1]]);
        $sql = "UPDATE room_users SET isModerator = false WHERE roomName = ? and username = ?;";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$roomName, $words[1]]);
        $this->ref->getReference("chats/" . $roomName)->push(
            MessageHelper::getRoomInfo($roomName, $words[1] . " is no longer a moderator of this chat room")
        );

        // log
        if ($this->roomUtils->isAdmin($user)) {
            AdminLogUtils::logDemod($user, $words[1], $roomName);
        }

        return buildSuccessResponse($message = $words[1] . " is no longer a moderator of this chat room");
    }

    public function userExists($username)
    {
        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$username]);
        $res = $stmt->fetchAll();
        if (count($res) > 0) return true;
        return false;
    }

    public function __destruct()
    {
        $this->pdo = null;
    }

    public function hasAuth($self, $username, $roomName)
    {
        $sql = "SELECT id FROM users WHERE username = '$username' AND (isAdmin = 1 OR isStaff = 1 OR isOwner = 1)";
        $pdo = getConn();
        if (count(cast($pdo->query($sql))) > 0) {
            $pdo = null;
            return false;
        }
        $sql = "SELECT id FROM modship WHERE username = '$username' AND roomName = '$roomName'";
        if (count(cast($pdo->query($sql))) > 0) {
            $pdo = null;
            return false;
        }
        $sql = "SELECT id FROM users WHERE username = '$self' AND (isAdmin = 1 OR isStaff = 1 OR isOwner = 1)";
        if (count(cast($pdo->query($sql))) > 0) {
            $pdo = null;
            return true;
        }

        $sql = "SELECT id FROM users WHERE owner = '$self' AND name = '$roomName'";
        if (count(cast($pdo->query($sql))) > 0) {
            $pdo = null;
            return true;
        }
        return false;
    }
}


