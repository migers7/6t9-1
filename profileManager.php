<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'db_utils.php';
include_once 'userUtils.php';
include_once 'blogUtils.php';

class ProfileManager
{
    public function __construct()
    {

    }

    public function getProfile($username, $viewer)
    {
        $sql = "SELECT * FROM profile WHERE username = '$username'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) {
            $userUtils = new UserUtils();
            $relation = $userUtils->getRelation($viewer, $username);
            $sql = "SELECT email FROM users WHERE username = '$username'";
            $pdo = getConn();
            $emailRes = cast($pdo->query($sql))[0];
            $pdo = null;
            $basic = $userUtils->findUser($username);
            if ($username == $viewer) {
                $basic["email"] = $emailRes["email"];
                $canUpdateBirthday = true;
                if ($basic["birthday"] != "") $canUpdateBirthday = false;
                $basic["attribute"] = ["can_update_dob" => $canUpdateBirthday];
            }
            $res[0]["basicInfo"] = $basic;
            $res[0]["relationType"] = $relation;
            $res[0]["status"] = (new BlogUtils())->getStatus($username);
            $likes = $res[0]["likes"];
            $res[0]["is_liked"] = false;
            if ($likes != null) {
                $likers = explode(" ", $likes);
                $oldCount = count($likers);
                $uniqueLikers = array_unique($likers);
                $newCount = count($uniqueLikers);
                if ($oldCount != $newCount) {
                    $updatedValue = "";
                    for ($i = 0; $i < count($uniqueLikers); $i++) {
                        if ($i > 0) $updatedValue .= " ";
                        $updatedValue .= $uniqueLikers[$i];
                    }
                    $sql = "UPDATE profile SET likes = '$updatedValue' WHERE username  ='$username'";
                    $pdo = getConn();
                    $pdo->exec($sql);
                    $pdo = null;
                }
                $res[0]["likes"] = $newCount;
                if (in_array($viewer, $likers)) {
                    $res[0]["is_liked"] = true;
                }
            } else {
                $res[0]["likes"] = 0;
            }
            $res[0]["blog"] = array();
            if ($viewer != $username) {
                $this->increaseFootPrint($username);
            }
            $sql = "SELECT COUNT(other) AS followers FROM user_relations WHERE username = '$username' AND (relationType = 'confirm' OR relationType = 'friends')";
            $pdo = getConn();
            $followers = cast($pdo->query($sql));
            if (count($followers) > 0) {
                $res[0]["followers"] = (int)$followers[0]["followers"];
            }
            $sql = "SELECT COUNT(other) AS followings FROM user_relations WHERE username = '$username' AND (relationType = 'requested' OR relationType = 'friends')";
            $followings = cast($pdo->query($sql));
            if (count($followings) > 0) {
                $res[0]["followings"] = (int)$followings[0]["followings"];
            }
            $sql = "SELECT COUNT(other) AS friendsCount FROM user_relations WHERE username = '$username' AND relationType = 'friends'";
            $friendsCount = cast($pdo->query($sql));
            if (count($friendsCount) > 0) {
                $res[0]["friendsCount"] = (int)$friendsCount[0]["friendsCount"];
            }
            $sql = "SELECT COUNT(status) AS postCount FROM blog WHERE username = '$username'";
            $postCount = cast($pdo->query($sql));
            if (count($postCount) > 0) {
                $res[0]["postCount"] = (int)$postCount[0]["postCount"];
            }
            if ($relation == "friends" || $viewer == $username) {
                $sql = "SELECT id, text, image_url, TIME_TO_SEC(TIMEDIFF(NOW(), time_stamp)) time_ago FROM activity WHERE username = '$username' ORDER BY id DESC LIMIT 10";
                $res[0]["activities"] = cast($pdo->query($sql));
                $pdo = null;
            } else {
                $res[0]["activities"] = array();
            }
            return buildSuccessResponse($res[0]);
        }
        return buildErrorResponse("No profile found for $username");
    }

    public function getProfileRaw($username)
    {
        $sql = "SELECT * FROM profile WHERE username = '$username'";
        $pdo = getConn();
        $res = cast($pdo->query($sql));
        $pdo = null;
        $userUtils = new UserUtils();
        $res[0]["basicInfo"] = $userUtils->findUser($username);
        $res[0]["status"] = (new BlogUtils())->getStatus($username);
        $res[0]["isOnline"] = $userUtils->isOnline($username);
        $res[0]["likes"] = 0;
        return $res[0];
    }

    public function getShortProfile($username)
    {
        $userUtils = new UserUtils();
        $user = $userUtils->findUser($username);
        $res = array();
        $res["username"] = $username;
        $res["status"] = (new BlogUtils())->getStatus($username);
        $res["isOnline"] = $userUtils->isOnline($username);
        $res["dp"] = $user["dp"];
        $res["gender"] = $user["gender"];
        return $res;
    }


    public function increaseGiftCount($username)
    {
        $pdo = getConn();
        $sql = "UPDATE profile SET gifts = gifts + 1, gifts_daily = gifts_daily + 1 WHERE username = '$username'";
        $pdo->exec($sql);
        $pdo = null;
    }

    public function increaseFootPrint($username)
    {
        $pdo = getConn();
        $sql = "UPDATE profile SET footprints = footprints + 1 WHERE username = '$username'";
        $pdo->exec($sql);
        $pdo = null;
    }
}