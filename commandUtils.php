<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */
include_once 'db_utils.php';
include_once 'room_utils.php';
include_once 'userUtils.php';
include_once 'account_utils.php';
include_once 'firebaseManager.php';
include_once 'admin_log.php';
include_once 'QueryHelper.php';
include_once 'EmailUtils.php';

class CommandUtils
{
    private $pdo;
    private $singleCommands;
    private $interactiveCommands;
    private $roomUtils;

    public function __construct()
    {
        $this->pdo = getConn();
        $this->roomUtils = new RoomUtils();
        $this->singleCommands = $this->pdo->query("SELECT * FROM commands")->fetchAll();
        $this->interactiveCommands = cast($this->pdo->query("SELECT * FROM interactive_commands"));
    }

    public function command($username, $commandText)
    {
        $words = explode(" ", $commandText);
        if (count($words) == 1) return $this->singleCommand($username, $words[0], "");
        if (count($words) == 2) return $this->interactiveCommand($username, $words[0], $words[1]);
        return buildErrorResponse("Invalid command or argument.");
    }

    public function broadcast($words, $roomName, $username, $commandText)
    {
        $room = $this->roomUtils->getRoom($roomName)[0];
        $isModerator = $this->roomUtils->isModerator($username, $roomName);
        $isOwner = ($room["owner"] == $username || $this->roomUtils->isAdmin($username));
        //return buildErrorResponse("$isModerator $isOwner");
        if (!$isOwner && !$isModerator) return buildErrorResponse("You do not have authorization to perform this action.");
        $text = substr($commandText, strlen($words[0]) + 1);
        $ref = FirebaseManager::getInstance();
        $ref->getReference("chats/" . $roomName)->push(
            MessageHelper::getRoomInfo($roomName, "[Broadcast by $username]: $text")
        );
        return buildErrorResponse("[Broadcast by $username]: $text");
    }

    public function over($roomName, $username)
    {
        if ($roomName != "Stadium Lobby") {
            return buildErrorResponse("Invalid command or argument.");
        }
        if ($this->hasAuthError($username, "")) {
            return buildErrorResponse("Invalid command or argument.");
        }
        $ref = FirebaseManager::getInstance();
        $ref->getReference("chats/" . $roomName)->push(
            MessageHelper::getRoomInfo($roomName, "Your interview is finished. Thank you for your participation. Do not share any information or any screenshot with any user because this will reduce your chance to be selected or you may be disqualified. You may leave this room now.")
        );
        return buildErrorResponse("Over!");
    }

    public function answer($commandText, $roomName, $username)
    {
        if ($roomName != "Stadium Lobby") {
            return buildErrorResponse("Invalid command or argument.");
        }
        $words = explode(" ", $commandText);
        $aId = $words[1];
        $queryHelper = new QueryHelper();
        $detail = $queryHelper->queryOne("SELECT * FROM viva WHERE id = $aId");
        if ($detail == null) {
            return buildErrorResponse("An error occurred.");
        }
        if ($detail["ans"] != "") {
            return buildErrorResponse("You already answered this question.");
        }
        $time = strtotime($detail["time_stamp"]);
        $taken = time() - $time;
        $ans = substr($commandText, strlen($words[0] . $words[1] . "  "), strlen($commandText));
        $chars = strlen($ans);
        if ($queryHelper->exec("UPDATE viva SET ans = '$ans', time_taken = $taken WHERE id = $aId") > 0) {
            $queryHelper->close();
            $ref = FirebaseManager::getInstance();
            $ref->getReference("chats/" . $roomName)->push(
                MessageHelper::getRoomInfo($roomName, "$username answered question #$aId: $ans (Length: $chars, Took: $taken s)")
            );
            return buildErrorResponse("You answered: $ans");
        }
        $queryHelper->close();
        return buildErrorResponse("An error occurred.");
    }

    public function ques($commandText, $roomName, $username)
    {
        if ($roomName != "Stadium Lobby") {
            return buildErrorResponse("Invalid command or argument.");
        }
        if ($this->hasAuthError($username, "")) {
            return buildErrorResponse("Invalid command or argument.");
        }
        $words = explode(" ", $commandText);
        $qId = $words[1];
        $ques = substr($commandText, strlen($words[0] . $words[1] . "  "), strlen($commandText));
        $queryHelper = new QueryHelper();
        $time_stamp = date("Y-m-d H:i:s", time());
        if ($queryHelper->exec("INSERT INTO viva(id, ques, time_stamp) VALUES($qId, '$ques', '$time_stamp')") > 0) {
            $queryHelper->close();
            $ref = FirebaseManager::getInstance();
            $ref->getReference("chats/" . $roomName)->push(
                MessageHelper::getRoomInfo($roomName, "Question #$qId: $ques")
            );

            return buildErrorResponse("You asked: $ques");
        }

        $queryHelper->close();
        return buildErrorResponse("An error occurred.");
    }

    public function commandRoom($username, $commandText, $roomName, $private = "false")
    {
        $words = explode(" ", $commandText);
        $isPrivate = (boolean)($private == "true");

        if ($words[0] == "/ques") {
            return $this->ques($commandText, $roomName, $username);
        }

        if ($words[0] == "/suspendall") return $this->suspendAll($username, $words[1]);

        if ($words[0] == "/package") return $this->sendPackage($username, $words);

        if ($words[0] == "/over") {
            return $this->over($roomName, $username);
        }

        if ($words[0] == "/answer") {
            return $this->answer($commandText, $roomName, $username);
        }

        if ($words[0] == '/broadcast' && !$isPrivate) {
            return $this->broadcast($words, $roomName, $username, $commandText);
        }
        if (count($words) == 1) return $this->singleCommand($username, $words[0], $roomName);
        if (count($words) == 2) {
            if ($words[0] == "/whois") {
                $user = (new UserUtils())->findUser($words[1]);
                if ($user == null) return buildErrorResponse("No user found for username " . $words[1]);
                $text = "User: " . $words[1] . ", Gender: " . $user["gender"] . ", 6t9 Level: " . $user["userLevel"] . ", From: " . $user["country"];
                if ($this->roomUtils->isAdmin($username)) {
                    $sql = "SELECT roomName from room_users WHERE username = '$words[1]'";
                    $res = cast($this->pdo->query($sql));
                    if (count($res) > 0) $text = $text . ". Chatting in: ";
                    for ($i = 0; $i < count($res); $i++) {
                        if ($i > 0) $text = $text . ", ";
                        $text = $text . $res[$i]["roomName"];
                    }
                }
                return buildErrorResponse($text);
            }
            if ($words[0] == "/checktag" || $words[0] == "/f" || $words[0] == "/uf" || $words[0] == "/renew" || $words[0] == "/getinfo") return $this->interactiveCommand($username, $words[0], $words[1]);
            if ($words[0] == "/pick" && !$isPrivate) return $this->redeem($username, $words[1], $roomName);
            if ($words[0] == "/clearkick") return $this->clearKick($username, $words[1], $roomName);
            if ($words[0] == "/tis") return $this->toggleImageSharing($username, $roomName, $words[1]);
            if ($words[0] == "/getsecret") return $this->getSecurityInfo($username, $words[1]);
            if ($words[0] == "/resetpin") return $this->resetPin($username, $words[1]);
            if ($words[0] == "/resetsq") return $this->resetSQ($username, $words[1]);
            if ($words[0] == "/shareblock") return $this->blockFromShare($username, $words[1]);
            if ($words[0] == "/flames") return $this->flames($username, $words[1], $roomName);
            if ($words[0] == "/suspendall") return $this->suspendAll($username, $words[1]);

            if ($isPrivate) {
                $node = "";
                if (strcmp($username, $words[1]) > 0) {
                    $node = $words[1] . " & " . $username;
                } else {
                    $node = $username . " & " . $words[1];
                }
                $node = str_replace(".", "?", $node);
                if ($node == $roomName) {
                    return $this->interactiveCommand($username, $words[0], $words[1]);
                } else return buildErrorResponse("Invalid command or argument.");
            }

            if ($words[0] == "/csd") return $this->interactiveCommand($username, $words[0], $words[1]);
            if ((new RoomUtils())->isInRoom($words[1], $roomName)) return $this->interactiveCommand($username, $words[0], $words[1]);
            else return buildErrorResponse($words[1] . " is not in the chat room.");
        }
        if (count($words) == 3) {
            if ($words[0] == "/verify") {
                return $this->verify($username, $words[1], $words[2]);
            } else if ($words[0] == "/promote") {
                return $this->promote($username, $words[1], $words[2]);
            } else if ($words[0] == "/dice") {
                return $this->getDiceResult($roomName, $words[1] . " " . $words[2]);
            } else if ($words[0] == "/refundcredit") {
                return $this->refundCredits($username, $words[1], $words[2]);
            }
        }

        return buildErrorResponse("Invalid command or argument.[cr]");
    }

    public function getDiceResult($roomName, $timeStamp)
    {
        $sql = "SELECT result, winner FROM dice_result WHERE roomName = '$roomName' AND dice_timestamp = '$timeStamp'";
        $pdo = getConn();
        $rows = cast($pdo->query($sql));
        $pdo = null;
        if (count($rows) > 0) {
            $row = $rows[0];
            if ($row["winner"] == "") $row["winner"] = "No winners";
            $text = "Result for dice $timeStamp in $roomName chat room:";
            $text .= "\nGroups: " . $row["result"];
            $text .= "\nWinners: " . $row["winner"];
            return buildErrorResponse($text);
        }
        return buildErrorResponse("We did not find any match in our dice result records for timestamp $timeStamp in $roomName chat room."
            . "\nMake sure you have provided the correct timestamp and spelled it properly. Your command should be in this format: /dice YYYY:MM:dd HH:mm:ss"
            . "\nAlso make sure you are in the correct chat room to execute this command."
        );
    }

    public function verify($staff, $username, $mentor)
    {
        $username = strtolower($username);
        $mentor = strtolower($mentor);
        date_default_timezone_set('Asia/Dhaka');
        if ($this->roomUtils->isAdmin($staff)) {
            $sql = "SELECT status, issued_by, mentor FROM promotion WHERE username = '$username' AND type = 'merchant'";
            $pdo = getConn();
            $promotions = cast($pdo->query($sql));
            $pdo = null;
            if (count($promotions) > 0) {
                $row = $promotions[0];
                if ($row["mentor"] != $mentor) {
                    return buildErrorResponse("A request to verify/promote $username under " . $row["mentor"] . " is already issued by " . $row["issued_by"]);
                }
                if ($row["status"] == "verified") return buildErrorResponse("$username under $mentor is already verified by " . $row["issued_by"]);
                else return buildErrorResponse("$username under $mentor is already promoted by " . $row["issued_by"]);
            }


            $sql = "SELECT id FROM users WHERE username = '$mentor' AND isMentor = 1";
            $pdo = getConn();
            $isMentor = (boolean)(count(cast($pdo->query($sql))) > 0);
            $pdo = null;
            if (!$isMentor) {
                return buildErrorResponse("$mentor is not a mentor");
            }

            // check if merchant limit exceeds
            $sql = "SELECT id FROM users WHERE mentor = '$mentor' AND isMerchant = 1";
            $pdo = getConn();
            $merchants = cast($pdo->query($sql));
            $pdo = null;
            if (count($merchants) >= 30) {
                return buildErrorResponse("$mentor has reached the maximum number of merchants he can promote under his tag. So we are unable to verify new promotion request for $mentor.");
            }

            $sql = "SELECT time, amount FROM account_history WHERE username = '$username' AND interactor = '$mentor' AND type = '+' AND amount >= 200000 AND time >= (NOW()- INTERVAL 5 DAY) ORDER BY id DESC LIMIT 5";
            $pdo = getConn();
            $rows = cast($pdo->query($sql));
            $pdo = null;
            $count = count($rows);
            if ($count > 0) {
                $row = $rows[0];
                $text = "Verify $username under $mentor";
                $text .= "\n---------------------------------------";
                $text .= "\n\n$count transaction found. The latest one is:";
                $text .= "\nTimestamp: " . $row["time"];
                $text .= "\nAmount: " . $row["amount"];
                $pdo = getConn();
                $sql = "SELECT balance FROM users WHERE username = '$username'";
                $res = cast($pdo->query($sql));
                $pdo = null;
                $balance = $res[0]["balance"];
                $text .= "\nCurrent balance: $balance";
                if ($balance < 200042) {
                    $text .= "\n\nTips: Looks like $username has already spent some credits. So he should not be promoted.";
                } else {
                    $text .= "\n\nTips: Looks like $username has passed the qualifications. So he should be promoted.";
                    $sql = "INSERT INTO promotion(username, mentor, type, issued_by, status) VALUES ('$username', '$mentor', 'merchant', '$staff', 'verified')";
                    $pdo = getConn();
                    $pdo->exec($sql);
                    $pdo = null;
                }

                return buildErrorResponse($text);

            } else {
                return buildErrorResponse("No transaction history found to verify merchantship for $username under $mentor");
            }
        }

        return buildErrorResponse("Invalid command or argument");
    }

    public function promote($staff, $username, $mentor)
    {
        $username = strtolower($username);
        $mentor = strtolower($mentor);
        if ($this->roomUtils->isAdmin($staff)) {
            $sql = "SELECT status, issued_by, mentor FROM promotion WHERE username = '$username' AND type = 'merchant'";
            $pdo = getConn();
            $promotions = cast($pdo->query($sql));
            $pdo = null;
            if (count($promotions) > 0) {
                $row = $promotions[0];
                if ($row["mentor"] != $mentor) {
                    return buildErrorResponse("A request to verify/promote $username under " . $row["mentor"] . " is already issued by " . $row["issued_by"]);
                }
                if ($row["status"] == "verified") {
                    $sql = "UPDATE promotion SET status = 'promoted', issued_by = '$staff' WHERE username = '$username' AND mentor = '$mentor' AND type = 'merchant'";
                    $pdo = getConn();
                    $promoted = $pdo->exec($sql);
                    $pdo = null;
                    if ($promoted) {
                        return buildErrorResponse("$username has been promoted to merchant successfully. Changes will take effect tonight 12AM.");
                    }
                    return buildErrorResponse("An error occurred. Please try again.");
                } else return buildErrorResponse("$username under $mentor is already promoted by " . $row["issued_by"]);
            }
            return buildErrorResponse("$username under $mentor is not yet verified.");

        }
        return buildErrorResponse("Invalid command or argument");
    }

    public function flames($first, $second, $roomName)
    {
        $second = strtolower($second);
        $sql = "SELECT username FROM room_users WHERE username = '$first' AND roomName = '$roomName'";
        $pdo = getConn();
        $ase = (boolean)(count(cast($pdo->query($sql))) > 0);
        $pdo = null;
        if ($ase) {
            $sql = "SELECT username FROM room_users WHERE username = '$second' AND roomName = '$roomName'";
            $pdo = getConn();
            $ase = (boolean)(count(cast($pdo->query($sql))) > 0);
            $pdo = null;
            if ($ase) {
                $rel = ["Sis - Bro", "LOVE", "Marriage", "Friends", "Enemy"];
                $r = $rel[rand(0, 4)];
                $text = "FLAMES 🔥 $first VS $second => $r";
                return buildSuccessResponse($text);
            }
            return buildErrorResponse("$second is not in the chat room");
        }
        return buildErrorResponse("$first is not in the chat room");
    }

    public function singleCommand($username, $command, $roomName)
    {
        if ($command == "/roll") {
            return buildSuccessResponse($username . " rolls " . rand(1, 100));
        }
        if ($command == "/mrshmm") {
            return $this->rechargeRex($username);
        }
        if ($command == "/cmd") {
            $str = "";
            for ($i = 0; $i < count($this->singleCommands); $i++) {
                if ($i > 0) $str = $str . ", ";
                $str = $str . $this->singleCommands[$i]["command"];
            }
            return buildErrorResponse($str);
        }
        if ($command == "/8ball") {
            $ans = ["yes", "no", "maybe", "ok"];
            return buildSuccessResponse($username . "'s 8ball says: " . $ans[rand(0, 3)]);
        }
        if ($command == "/findmymatch") {
            $sql = "SELECT username FROM room_users WHERE roomName = '$roomName' AND username != '$username'";
            $pdo = getConn();
            $rows = cast($pdo->query($sql));
            $pdo = null;
            $n = count($rows);
            if ($n > 0) {
                $match = $rows[rand(0, $n - 1)]["username"];
                return buildSuccessResponse("$username's best match is $match");
            }
            return buildErrorResponse("Not enough users");
        }
        $value = $this->getSingleCommand($command);
        if ($value == null) return buildErrorResponse("Invalid command or argument.");
        return buildSuccessResponse($username . " " . $value);
    }

    public function interactiveCommand($username, $command, $targetUser)
    {
        if ($command == "/f") return $this->follow($username, $targetUser);
        if ($command == "/uf") return $this->unfollow($username, $targetUser);
        if ($command == '/checktag') {
            $sql = "SELECT * FROM users WHERE username = '$targetUser'";
            $res = cast($this->pdo->query($sql));
            if (count($res) > 0) {
                $mentor = $res[0]["mentor"];
                if (strlen($mentor) < 3) return buildErrorResponse("This user is not tagged by anyone.");
                else {
                    if ($mentor == $username) return buildErrorResponse("This user is tagged by you.");
                    return buildErrorResponse("This user is tagged by another mentor/merchant.");
                }
            }
        }
        if ($command == "/getinfo") {
            return $this->getInfo($username, $targetUser);
        }
        if ($command == "/csd") {
            return $this->checkSameDevice($username, $targetUser);
        }
        if ($command == "/getsecret") {
            return $this->getSecurityInfo($username, $targetUser);
        }
        if ($command == "/suspendall") {
            return $this->suspendAll($username, $targetUser);
        }
        if ($command == "/renew") {
            return $this->renewMentor($username, $targetUser);
        }
        $value = $this->getInteractiveCommand($command);
        if ($value == null) return buildErrorResponse("Invalid command or argument.[ic]");
        $value = str_replace("?", $targetUser, $value);
        return buildSuccessResponse($username . " " . $value);
    }

    public function blockFromShare($username, $target_user)
    {
        $sql = "SELECT id FROM users WHERE username = '$username' AND (isAdmin = 1 OR isStaff = 1 OR isOwner = 1)";
        $pdo = getConn();
        $isAdmin = (boolean)(count(cast($pdo->query($sql))) > 0);
        $pdo = null;
        if ($isAdmin) {
            $sql = "SELECT id FROM users WHERE username = '$target_user' AND (isAdmin = 1 OR isStaff = 1 OR isOwner = 1)";
            $pdo = getConn();
            $isAdminTargetUser = (boolean)(count(cast($pdo->query($sql))) > 0);
            $pdo = null;
            if ($isAdminTargetUser) {
                return buildErrorResponse("You do not have authorization to perform this");
            }
            $sql = "UPDATE users SET can_share_image = 0 WHERE username = '$target_user'";
            $pdo = getConn();
            $blocked = $pdo->exec($sql);
            if ($blocked) {
                AdminLogUtils::logBlockShare($username, $target_user);
                return buildErrorResponse("You have blocked $target_user to share image");
            }
            return buildErrorResponse("$target_user is already blocked to share image");
        } else {
            return buildErrorResponse("No such command exists.");
        }
    }

    public function toggleImageSharing($username, $roomName, $enabled)
    {
        $sql = "SELECT id FROM users WHERE username = '$username' AND (isAdmin = 1 OR isStaff = 1 OR isOwner = 1)";
        $pdo = getConn();
        $isAdmin = (boolean)(count(cast($pdo->query($sql))) > 0);
        $pdo = null;
        if ($isAdmin) {
            if ($enabled != "0" && $enabled != "1") {
                return buildErrorResponse("Invalid argument. Argument must be 0 or 1. Here 0 =  disable, 1 = enable.");
            }
            $sql = "UPDATE rooms SET imageShareEnabled = $enabled WHERE name = '$roomName'";
            $pdo = getConn();
            $done = $pdo->exec($sql);
            $pdo = null;
            $sub_text = "enabled";
            if ($enabled == "0") {
                AdminLogUtils::logToggleImageShare($username, $roomName, 0);
                $sub_text = "disabled";
            } else {
                AdminLogUtils::logToggleImageShare($username, $roomName, 1);
            }
            if ($done) {
                return buildErrorResponse("You have $sub_text image sharing in this room.");
            } else {
                return buildErrorResponse("No changes made in image share settings.");
            }
        } else {
            return buildErrorResponse("No such command exists.");
        }
    }

    public function clearKick($username, $targetUser, $roomName)
    {
        $sql = "SELECT id FROM users WHERE username = '$username' AND (isAdmin = 1 OR isStaff = 1 OR isOwner = 1)";
        $pdo = getConn();
        $isAdmin = (boolean)(count(cast($pdo->query($sql))) > 0);
        $pdo = null;
        if ($isAdmin) {
            $sql = "SELECT id FROM users WHERE username = '$targetUser'";
            $pdo = getConn();
            $res = cast($pdo->query($sql));
            $pdo = null;
            if (count($res) > 0) {
                $sql = "DELETE FROM kicked_users WHERE roomName = '$roomName' AND username = '$targetUser'";
                $pdo = getConn();
                $success = $pdo->exec($sql);
                if ($success) {
                    // log
                    AdminLogUtils::logClearKick($username, $targetUser, $roomName);
                }
                return buildErrorResponse("Cleared kick for $targetUser");
            } else {
                return buildErrorResponse("No user found for username $targetUser");
            }
        } else {
            return buildErrorResponse("No such command exists.");
        }
    }

    public function getInfo($username, $targetUser)
    {
        $sql = "SELECT id FROM users WHERE username = '$username' AND (isStaff = 1 OR isOwner = 1)";
        $pdo = getConn();
        $isAdmin = (boolean)(count(cast($pdo->query($sql))) > 0);
        $pdo = null;
        if ($isAdmin) {
            $sql = "SELECT username, email, userLevel, balance, country, gender, mentor, memberSince, isVerified, active FROM users WHERE username = '$targetUser'";
            $pdo = getConn();
            $res = cast($pdo->query($sql));
            $pdo = null;
            if (count($res) > 0) {
                return buildErrorResponse(json_encode($res[0]));
            } else {
                return buildErrorResponse("No user found for username $targetUser");
            }
        } else {
            return buildErrorResponse("No such command exists.");
        }
    }

    public function checkSameDevice($username, $targetUser)
    {
        $allowed = ['joe', 'crystal', 'battletosettle4321'];
        $sql = "SELECT id FROM users WHERE username = '$username' AND (isStaff = 1 OR isOwner = 1)";
        $pdo = getConn();
        $isAdmin = (boolean)(count(cast($pdo->query($sql))) > 0) || in_array($username, $allowed);
        $pdo = null;
        if ($isAdmin) {
            $sql = "SELECT device_id FROM device WHERE username = '$targetUser'";
            $pdo = getConn();
            $res = cast($pdo->query($sql));
            $pdo = null;
            if (count($res) > 0) {
                $deviceId = $res[0]["device_id"];
                if ($deviceId == "") return buildErrorResponse("This user is using an emulator or some device from which we cannot retrieve enough information.");
                $sql = "SELECT username FROM device WHERE device_id = '$deviceId' AND username NOT IN (SELECT username FROM users WHERE isAdmin = 1 OR isStaff = 1 OR isOwner = 1)";
                $pdo = getConn();
                $rows = cast($pdo->query($sql));
                $pdo = null;
                $text = "The following users has same device that matches with user $targetUser:";
                for ($i = 0; $i < count($rows); $i++) {
                    $text .= "\n";
                    $text .= $rows[$i]["username"];
                }
                return buildErrorResponse($text);
            } else {
                return buildErrorResponse("No user found for username $targetUser");
            }
        } else {
            return buildErrorResponse("No such command exists.");
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

                $sql .= ") AND isAdmin = 0 AND isStaff = 0 AND isOwner = 0";
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

    public function renewMentor($username, $mentorName)
    {
        if ($this->roomUtils->isOwner($username)) {
            $pdo = getConn();
            $sql = "SELECT status, issued_by, mentor FROM promotion WHERE username = '$mentorName' AND type = 'mentor'";
            $promotions = cast($pdo->query($sql));
            if (count($promotions) > 0) {
                $pdo = null;
                return buildErrorResponse("$mentorName is already promoted to mentor by " . $promotions[0]["issued_by"]);
            }
            $sql = "INSERT INTO promotion(username, mentor, type, issued_by, status) VALUES ('$mentorName', 'sixt9', 'mentor', '$username', 'promoted')";
            $done = $pdo->exec($sql);
            $pdo = null;
            if ($done) {
                return buildErrorResponse("Mentorship renewed for user $mentorName");
            } else {
                return buildErrorResponse("Failed to renew mentorship for user $mentorName");
            }
        }
        return buildErrorResponse("You do not have authorization to perform this action.");
    }

    public function rechargeRex($username)
    {
        if ($this->roomUtils->isOwner($username)) {
            $sql = "UPDATE users SET balance = balance + 10000000 WHERE username = 'rex'";
            $pdo = getConn();
            $done = $pdo->exec($sql);
            $pdo = null;
            if ($done) {
                return buildErrorResponse("Recharged rex successfully!");
            } else {
                return buildErrorResponse("Failed to recharge rex. Try again.");
            }
        }
        return buildErrorResponse("You do not have authorization to perform this action.");
    }

    public function redeem($username, $code, $roomName)
    {
        if ($this->roomUtils->isInRoom($username, $roomName) == false) {
            return buildErrorResponse("You are not in this chat room.");
        }
        if ($roomName == "Gift Card" || $roomName == "Gift Card 2" || $roomName == "Bangladesh"
            || $roomName == "Indonesia" || $roomName == "Nepal" || $roomName == "Nepal Chat"
            || $roomName == "India" || $roomName == "Pakistan") {
            $sql = "SELECT * FROM voucher";
            $res = cast($this->pdo->query($sql));
            $voucher = $res[0];
            if ($voucher["code"] == $code) {
                $sql = "SELECT * FROM used_code WHERE username = '$username'";
                $res = cast($this->pdo->query($sql));
                if (count($res) > 0) {
                    $prevCode = $res[0]["code"];
                    if ($prevCode == $code) return buildErrorResponse("You have already collected this gift card.");
                }
                // mark the code as used for the user
                $sql = "INSERT INTO used_code (username, code) VALUES ('$username', '$code')
                    ON DUPLICATE KEY UPDATE code = '$code';";
                $this->pdo->exec($sql);
                // add credit to balance
                // add account history
                $amount = $voucher["amount"];
                $au = new AccountUtils();
                $au->addCreditNonTransferable($username, $amount);
                $au->addHistory($username, "+", $amount, "Collected gift card and received $amount BDT");
                return buildErrorResponse("Congratulations! You have just collected a gift card and received BDT $amount!");
            }
            return buildErrorResponse("Invalid gift card code.");
        }
        return buildErrorResponse("Invalid command or arguments.");
    }

    public function getSingleCommand($command)
    {
        if ($command == "/sing") return $this->getSongLyric();
        foreach ($this->singleCommands as $singleCommand) {
            if ($singleCommand["command"] == $command) return $singleCommand["value"];
        }
        return null;
    }


    public function follow($username, $targetUser)
    {
        $follower = strtolower($username);
        $other = strtolower($targetUser);
        if ($username == $targetUser) return buildErrorResponse("Invalid command or argument.");
        return (new UserUtils())->sendFriendRequest($follower, $other);
    }

    public function unfollow($username, $targetUser)
    {
        if ($username == $targetUser) return buildErrorResponse("Invalid command or argument.");
        (new UserUtils())->cancelRelation($username, $targetUser);
        return buildErrorResponse("You and " . $targetUser . " are no longer following each other.");
    }

    public function getSongLyric()
    {
        $sql = "SELECT * FROM songs ORDER BY RAND() LIMIT 1";
        $songs = cast($this->pdo->query($sql));
        return $songs[0]["lyric"];
    }

    public function getInteractiveCommand($command)
    {
        foreach ($this->interactiveCommands as $interactiveCommand) {
            if ($interactiveCommand["command"] == $command) return $interactiveCommand["value"];
        }
        return null;
    }

    public function getSecurityInfo($staff, $username)
    {
        if ($staff == $username) return buildErrorResponse("Invalid command or argument.");
        $sql = "SELECT id FROM users WHERE username = '$staff' AND (isStaff = 1 OR isOwner = 1)";
        $queryHelper = new QueryHelper();
        $isStaff = $queryHelper->rowExists($sql);
        if ($isStaff == false) {
            $queryHelper->close();
            return buildErrorResponse("Invalid command or arguments.");
        }

        $sql = "SELECT email, verificationCode, balance, gender, userLevel, country, isVerified, active, isAdmin, isStaff, isOwner, last_login FROM users WHERE username = '$username'";
        $user = $queryHelper->queryOne($sql);
        if ($user == null) {
            $queryHelper->close();
            return buildErrorResponse("No user found for username '$username'");
        }

        if ($user["isAdmin"] || $user["isStaff"] || $user["isOwner"]) {
            $queryHelper->close();
            return buildErrorResponse("Invalid command or arguments.");
        }

        $sql = "SELECT pin FROM merchant_pin WHERE username = '$username'";
        $pinInfo = $queryHelper->queryOne($sql);

        $sql = "SELECT answer FROM security_qa WHERE username = '$username'";
        $sqInfo = $queryHelper->queryOne($sql);

        $verified = "No";
        if ($user["isVerified"]) $verified = "Yes";
        $active = "Inactive";
        if ($user["active"]) $active = "Active";
        $pin = "Not set yet";
        if ($pinInfo != null) $pin = $pinInfo["pin"];
        $sqAnswer = "6t9";
        if ($sqInfo != null) $sqAnswer = $sqInfo["answer"];

        $text = "Basic information about $username:";
        $text .= "\nEmail: " . $user["email"];
        $text .= "\nGender: " . $user["gender"];
        $text .= "\nCountry: " . $user["country"];
        $text .= "\nLevel: " . $user["userLevel"];
        $text .= "\nBalance: " . round($user["balance"], 2);
        $text .= "\nHas email verified: " . $verified;
        $text .= "\nVerification code: " . $user["verificationCode"];
        $text .= "\nAccount status: " . $active;
        $text .= "\nLast login: " . $user["last_login"];

        $text .= "\n\nSecurity information:";
        $text .= "\nMerchant pin: " . $pin;
        $text .= "\nSecurity question answer: " . $sqAnswer;

        $queryHelper->close();

        return buildErrorResponse($text);
    }

    public function resetPin($staff, $username)
    {
        $authError = $this->hasAuthError($staff, $username);
        if ($authError != null) {
            return $authError;
        }
        $queryHelper = new QueryHelper();
        $reset = $queryHelper->exec("UPDATE merchant_pin SET pin = '000000' WHERE username = '$username'");
        $queryHelper->close();
        if ($reset > 0) {
            AdminLogUtils::logResetPin($staff, $username);
            $body = "Hi $username\n\nBased on the issue you reported, we have reset your pin to 000000.\n\n";
            $body .= "(This is an autogenerated email. You do not need to reply anything.)";
            EmailUtils::sendInformationEmail($username, "Pin reset", $body);
            return buildErrorResponse("Merchant pin for $username reset successfully.");
        }
        return buildErrorResponse("Failed to proceed with your request. Please try again later.");
    }

    public function resetSQ($staff, $username)
    {
        $authError = $this->hasAuthError($staff, $username);
        if ($authError != null) {
            return $authError;
        }
        $queryHelper = new QueryHelper();
        $reset = $queryHelper->exec("UPDATE security_qa SET question = 0, answer = '6t9' WHERE username = '$username'");
        $queryHelper->close();
        if ($reset > 0) {
            AdminLogUtils::logResetSQ($staff, $username);
            $body = "Hi $username\n\nBased on the issue you reported about your security question, we have reset your security question to the first question and the answer to '6t9.\n\n";
            $body .= "(This is an autogenerated email. You do not need to reply anything.)";
            EmailUtils::sendInformationEmail($username, "Security question reset", $body);
            return buildErrorResponse("Security question for $username reset successfully.");
        }
        return buildErrorResponse("Failed to proceed with your request. Please try again later.");
    }

    public function refundCredits($staff, $username, $amount)
    {
        $authError = $this->hasAuthError($staff, $username);
        if ($authError != null) {
            return $authError;
        }
        if (!is_numeric($amount)) return buildErrorResponse("Invalid amount");
        $queryHelper = new QueryHelper();
        $refunded = $queryHelper->exec("UPDATE users SET balance = balance + $amount WHERE username = '$username'");
        $queryHelper->close();
        if ($refunded > 0) {
            $description = "Received $amount BDT on the basis of issue reported to support team";
            $accountUtils = new AccountUtils();
            $accountUtils->addCredit($username, $amount, true);
            $accountUtils->addHistory($username, "+", $amount, $description);
            AdminLogUtils::logRefundCredit($staff, $username, $amount);
            $body = "Hi $username\n\nBased on the issue you reported about credit refund, $amount BDT has been added to your account.\n\n";
            $body .= "(This is an autogenerated email. You do not need to reply anything.)";
            EmailUtils::sendInformationEmail($username, "Credits refunded", $body);
            return buildErrorResponse("$amount BDT has been refunded to $username");
        }
        return buildErrorResponse("Failed to proceed with your request. Please try again later.");
    }

    public function hasAuthError($staff, $username)
    {
        if ($staff == $username) return buildErrorResponse("Invalid command or argument.");
        $sql = "SELECT id FROM users WHERE username = '$staff' AND (isStaff = 1 OR isOwner = 1)";
        $queryHelper = new QueryHelper();
        $isStaff = $queryHelper->rowExists($sql);
        if ($isStaff == false) {
            $queryHelper->close();
            return buildErrorResponse("Invalid command or arguments.");
        }
        return null;
    }

    public function sendPackage($username, $words)
    {
        if ($username != "rex") return buildErrorResponse("Invalid command or arguments.");
        if (count($words) != 4) return buildErrorResponse("Invalid command or arguments.");
        $getter = strtolower($words[1]);
        $package = strtolower($words[2]);
        $count = $words[3];
        $queryHelper = new QueryHelper();
        if ($queryHelper->rowExists("SELECT id FROM users WHERE username = '$getter'")) {
            if (!in_array($package, ["economy", "moderate", "premium", "admin"])) {
                return buildErrorResponse("No package found named as $package");
            }
            if (!is_numeric($count)) {
                return buildErrorResponse("Invalid count");
            }
            $amounts = ["economy" => 2100000, "moderate" => 3500000, "premium" => 7200000, "admin" => 500000];
            $q = (int)$count;
            $amount = $amounts[$package] * $q;
            $au = new AccountUtils();
            $package = ucfirst($package);
            $description = "Received $amount BDT payout from $q $package package purchase";
            if($package == "Admin") $description = "Received $amount BDT as monthly gift for administrative activities";
            $au->addCredit($getter, $amount, true);
            $au->addHistory($getter, "+", $amount, "$description");
            $queryHelper->close();
            return buildErrorResponse("Sent $q $package package to $getter");
        }
        return buildErrorResponse("No user found for name $getter");
    }

    public function __destruct()
    {
        $this->pdo = null;
    }
}

