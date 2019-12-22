<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'db_utils.php';
include_once 'profileManager.php';
include_once 'notification_utils.php';
include_once 'blogUtils.php';
include_once 'account_utils.php';

class UserUtils
{
    private $notificationUtils;

    public function __construct()
    {
        $this->notificationUtils = new NotificationUtils();
    }

    public function findUser($username)
    {
        $username = strtolower($username);
        $sql = " SELECT * FROM users WHERE username = '$username'";
        $pdo = getConn();
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

    public function findProfile($username)
    {
        return (new ProfileManager())->getProfileRaw($username);
    }

    public function getUserCount()
    {
        $sql = "SELECT id from users";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        return count($res);
    }

    public function removeConfidentialValues($users)
    {
        for ($i = 0; $i < count($users); $i++) {
            unset($users[$i]["password"]);
            unset($users[$i]["token"]);
            unset($users[$i]["balance"]);
            unset($users[$i]["balance2"]);
            unset($users[$i]["merchantSince"]);
            unset($users[$i]["creditSpent"]);
            unset($users[$i]["verificationCode"]);
            unset($users[$i]["temp_password"]);
            unset($users[$i]["accessToken"]);
            unset($users[$i]["email"]);
            unset($users[$i]["mentor"]);
            unset($users[$i]["revenue"]);
            unset($users[$i]["daily_spent"]);
        }
        return $users;
    }

    public function search($username, $id = 0)
    {
        $username = '%' . $username . '%';
        $sql = "SELECT * FROM users WHERE username LIKE ? AND active = 1 AND id > '$id' ORDER BY id ASC LIMIT 10";
        $pdo = getConn();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $pdo = null;
        return buildSuccessResponse($this->removeConfidentialValues(cast($stmt)));
    }

    public function getFriendRequests($username)
    {
        return buildSuccessResponse($this->findRelatedUsers($username, "confirm"));
    }

    public function getFriends($username, $hideOffline = "false")
    {
        return buildSuccessResponse($this->removeConfidentialValues($this->findRelatedUsers($username, "friends", $hideOffline)));
    }

    public function getBlockedUsers($username)
    {
        $sql = "SELECT * FROM blocked_users WHERE username = '$username'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        $str = "";
        for ($i = 0; $i < count($res); $i++) {
            if ($i > 0) {
                $str = $str . ",";
            }
            $str = $str . $res[$i]["blocked_name"];
        }
        return $str;
    }

    public function getHome($username, $version, $hideOffline = "false")
    {
        $res = array();
        if ($version < 236) {
            $res["compatibility"] = false;
            $res["versionCheckMessage"] = "App update available. Hit the download button to download from play store. If you do not see Update button in play store, just uninstall the current version and install the new one";
            $res["downloadUrl"] = "https://play.google.com/store/apps/details?id=com.app.sixt9";
        } else {
            $this->beOnline($username);
            $res["compatibility"] = true;
            $res["versionCheckMessage"] = "";
            $res["downloadUrl"] = "https://play.google.com/store/apps/details?id=com.app.sixt9";
            $res["status"] = (new BlogUtils())->getStatus($username);
            $res["blocked_users"] = $this->getBlockedUsers($username);
            $res["friends"] = $this->removeConfidentialValues($this->findRelatedUsers($username, "friends", $hideOffline));
            $res["announcement"] = "Do not buy large amount of credits from any non-merchant/non-mentor user. This kind of activity may lead all of your accounts to be permanently suspended. If you find any merchant or mentor selling credits in very low rate, you are free to inform us/complain about that seller with proof. Do not be greedy, greed is not good for SixT9 ;)";
        }
        $res["user"] = (new AccountUtils())->getBalance($username);
        return buildSuccessResponse($res);
    }

    public function findRelatedUsers($username, $relationType, $hideOffline = "false")
    {
        $profileManager = new ProfileManager();
        $sql = "SELECT other FROM user_relations WHERE username = '$username' and relationType = '$relationType'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) {
            $users = array();
            foreach ($res as $user) {
                $shortProfile = $profileManager->getShortProfile(strtolower($user["other"]));
                if ($shortProfile["isOnline"] == false && $hideOffline == "true") continue;
                $users[] = $shortProfile;
            }
            return $users;
        }
        return array();
    }

    public function sendFriendRequest($username, $other)
    {
        $relation = $this->getRelation($other, $username);
        if ($relation != null && $relation == "friends") return buildErrorResponse("You are already following " . $other);
        if ($relation != null && $relation == "requested") return $this->acceptRequest($username, $other);
        return $this->setRelation($username, $other, "request");
    }

    public function acceptRequest($username, $other)
    {
        $this->cancelRelation($username, $other);
        return $this->setRelation($username, $other, "friends");
    }

    public function cancelRelation($username, $other)
    {
        $sql = "DELETE FROM user_relations WHERE username = '$username' and other = '$other'";
        $pdo = getConn();
        $pdo->exec($sql);
        $sql = "DELETE FROM user_relations WHERE other = '$username' and username = '$other'";
        $pdo->exec($sql);
        $pdo = null;
        return buildSuccessResponse("");
    }

    public function setRelation($username, $other, $relationType)
    {
        $u = $this->findUser($other);
        if ($u == null) return buildErrorResponse("No user found for name $other");
        $me = $this->findUser($username);
        $firstType = "";
        $secondType = "";
        $text = "";
        if ($relationType == "request") {
            $firstType = "requested";
            $secondType = "confirm";
            $text = "You are now following " . $other;
            $this->notificationUtils->push($other, "6t9", $username . " started following you.");
            $this->notificationUtils->insertWithImageAndLink($other, $username . " started following you.", $me["dp"], "https://6t9.app/profile/$username");
        }
        if ($relationType == "friends") {
            $firstType = "friends";
            $secondType = "friends";
            $text = "You and " . $other . " are now following each other.";
            $this->notificationUtils->push($other, "6t9", "You and " . $username . " are now following each other.");
            $this->notificationUtils->insertWithImageAndLink($other, "You and " . $username . " are now following each other.", $me["dp"], "https://6t9.app/profile/$username");
            $pdo = getConn();
            $activity = "Became friends with $other";
            $sql = "INSERT INTO activity(username, time_stamp, text) VALUES('$username', NOW(), '$activity')";
            $pdo->exec($sql);
            $activity = "Became friends with $username";
            $sql = "INSERT INTO activity(username, time_stamp, text) VALUES('$other', NOW(), '$activity')";
            $pdo->exec($sql);
            $pdo = null;
        }
        $id = $username . "#" . $other;
        $sql = "INSERT INTO user_relations(id, username, other, relationType) VALUES (?, ?, ?, ?)";
        $pdo = getConn();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id, $username, $other, $firstType]);
        $sql = "INSERT INTO user_relations(id, username, other, relationType) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $id = $other . "#" . $username;
        $stmt->execute([$id, $other, $username, $secondType]);
        $pdo = null;
        return buildErrorResponse($text);
    }

    public function getRelation($username, $other)
    {
        $sql = "SELECT relationType FROM user_relations WHERE username = '$username' and other = '$other'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) return $res[0]["relationType"];
        return null;
    }

    public function beOnline($username)
    {
        $sql = "INSERT INTO online_users(username, lastOnline) VALUES(?, ?)";
        $pdo = getConn();
        $stmt = $pdo->prepare($sql);
        $pdo = null;
        if ($stmt->execute([$username, date("Y-m-d H:i:s", time())])) return buildSuccessResponse(true);
        return buildSuccessResponse(false);
    }

    public function beOffline($username)
    {
        $sql = "DELETE FROM online_users WHERE username = '$username'";
        $pdo = getConn();
        $res = $pdo->exec($sql);
        $pdo = null;
        if ($res) return buildSuccessResponse(true);
        return buildSuccessResponse(false);
    }

    public function getActiveFriends($username)
    {
        $users = $this->findRelatedUsers($username, "friends");
        if (count($users) == 0) return buildSuccessResponse();
        $res = array();
        foreach ($users as $user) {
            if ($this->isOnline($user["username"])) $res[] = $user;
        }
        return buildSuccessResponse($this->removeConfidentialValues($res));
    }

    public function isOnline($username)
    {
        $sql = "SELECT username FROM online_users WHERE username = '$username'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) return true;
        return false;
    }

    public function changePassword($username, $oldPassword, $newPassword, $answer)
    {
        $oldPassword = sha1($oldPassword);
        $sql = "SELECT * FROM users WHERE username = '$username' and (password = '$oldPassword' OR temp_password = '$oldPassword')";
        $sql2 = "SELECT * FROM security_qa WHERE username = '$username' and answer = '$answer'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) {
            $pdo = getConn();
            $res2 = cast($pdo->query($sql2));
            $pdo = null;
            if (count($res2) > 0) {
                $newPassword = sha1($newPassword);
                $sql = "UPDATE users SET password = '$newPassword' WHERE username = '$username'";
                $pdo = getConn();
                $pdo->exec($sql);
                $pdo = null;
                return buildSuccessResponse("Password successfully updated");
            }
            return buildErrorResponse("Security question answer does not match.");
        }
        return buildErrorResponse("Old password doesn't match");
    }


    public function changeSQ($username, $questionId, $answer)
    {
        if ($this->SQExists($username)) {
            // Update sq
            $sql = "UPDATE security_qa SET question = '$questionId', answer = '$answer' WHERE username = '$username'";
            $pdo = getConn();
            $pdo->exec($sql);
            $pdo = null;
            return buildSuccessResponse("Security question updated successfully");
        }
        $sql = "INSERT INTO security_qa(username, question, answer) VALUES(?, ?, ?)";
        $pdo = getConn();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username, $questionId, $answer]);
        $pdo = null;
        return buildSuccessResponse("Security question set successfully");
    }

    public function changeSecurityQuestion($username, $questionId, $answer, $previousQuestionId = -1, $previousAnswer = "")
    {
        if ($questionId > 3 || $questionId < 0) {
            return buildErrorResponse("Invalid question. Try again.");
        }
        if (strlen($answer) < 3) {
            return buildErrorResponse("Very short answer. Ans must be at least 3 chars long.");
        }
        $sql = "SELECT answer, question FROM security_qa WHERE username = '$username'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) {
            if ($previousAnswer != $res[0]["answer"] || $previousQuestionId != $res[0]["question"]) {
                return buildErrorResponse("Old security question and answer do not match.");
            }
            $sql = "UPDATE security_qa SET question = '$questionId', answer = '$answer' WHERE username = '$username'";
            $pdo = getConn();
            $done = $pdo->exec($sql);
            $pdo = null;
            if ($done) {
                return buildSuccessResponse("Security question and answer updated successfully.");
            } else {
                buildErrorResponse("Failed to update security question and answer");
            }
        } else {
            $sql = "INSERT INTO security_qa(username, question, answer) VALUES('$username', '$questionId', '$answer')";
            $pdo = getConn();
            $done = $pdo->exec($sql);
            $pdo = null;
            if ($done) {
                return buildSuccessResponse("Security question and answer set successfully.");
            } else {
                buildErrorResponse("Failed to set security question and answer");
            }
        }

    }

    public function getSQ($username)
    {
        $sql = "SELECT * FROM security_qa WHERE username = '$username'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) return buildSuccessResponse($res[0]);
        return buildSuccessResponse(null);
    }

    public function SQExists($username)
    {
        $sql = "SELECT id FROM security_qa WHERE username = '$username'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        return count($res) > 0;
    }

    public function changePin($username, $pin, $oldPin = "")
    {
        if (is_numeric($pin) == false || strlen($pin) != 6) {
            return buildErrorResponse("Invalid pin. Please enter a valid pin of 6 digits.");
        }
        $sql = "SELECT pin FROM merchant_pin WHERE username = '$username'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) {
            if ($oldPin != $res[0]["pin"]) {
                return buildErrorResponse("Old pin doesn't match");
            }
            $sql = "UPDATE merchant_pin SET pin = '$pin' WHERE username = '$username'";
            $pdo = getConn();
            $done = $pdo->exec($sql);
            $pdo = null;
            if ($done) {
                return buildSuccessResponse("Pin updated successfully.");
            } else {
                return buildErrorResponse("Pin did not change.");
            }
        } else {
            $sql = "INSERT INTO merchant_pin(username, pin) VALUES('$username', '$pin')";
            $pdo = getConn();
            $done = $pdo->exec($sql);
            $pdo = null;
            if ($done) {
                return buildSuccessResponse("Pin set successfully.");
            } else {
                return buildErrorResponse("Failed to set pin. Please try again.");
            }
        }
    }
}
