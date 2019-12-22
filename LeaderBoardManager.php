<?php

include_once 'config.php';
include_once 'QueryHelper.php';

class LeaderBoardManager
{
    private $queryHelper;

    public function __construct()
    {
        $this->queryHelper = new QueryHelper();
    }

    public function getLeaderBoard($id, $username, $friendsOnly)
    {
        $data = new stdClass;
        if ($id == 1) {
            $data->list = $this->getAllGames($username, $friendsOnly);
        } else if ($id == 10) {
            $data->list = $this->getLowCard($username, $friendsOnly);
        } else if ($id == 20) {
            $data->list = $this->getCricket($username, $friendsOnly);
        } else if ($id == 30) {
            $data->list = $this->getDice($username, $friendsOnly);
        } else if ($id == 40) {
            $data->list = $this->getGift($username, $friendsOnly);
        } else {
            $data->list = array();
        }
        return $data;
    }

    private function getAllGames($username, $friendsOnly)
    {
        if ($friendsOnly) {
            $sql = "SELECT username, lowcard_played + dice_played + cricket_played AS item_count FROM profile WHERE username = '$username' OR username IN 
(SELECT other FROM user_relations WHERE username = '$username' AND relationType = 'friends') ORDER BY item_count DESC LIMIT 100";
            $sql2 = "SELECT username, lowcard_won + dice_won + cricket_won AS item_count FROM profile WHERE username = '$username' OR username IN 
(SELECT other FROM user_relations WHERE username = '$username' AND relationType = 'friends') ORDER BY item_count DESC LIMIT 100";
        } else {
            $sql = "SELECT username, lowcard_played + dice_played + cricket_played AS item_count FROM profile  ORDER BY item_count DESC LIMIT 100";
            $sql2 = "SELECT username, lowcard_won + dice_won + cricket_won AS item_count FROM profile  ORDER BY item_count DESC LIMIT 100";
        }
        $result = array();
        $result[] = $this->getResult($sql, "Games played");
        $result[] = $this->getResult($sql2, "Games won");
        return $result;
    }

    private function getLowCard($username, $friendsOnly)
    {
        if ($friendsOnly) {
            $sql = "SELECT username, lowcard_played AS item_count FROM profile WHERE username = '$username' OR username IN 
(SELECT other FROM user_relations WHERE username = '$username' AND relationType = 'friends') ORDER BY item_count DESC LIMIT 100";
            $sql2 = "SELECT username, lowcard_won AS item_count FROM profile WHERE username = '$username' OR username IN 
(SELECT other FROM user_relations WHERE username = '$username' AND relationType = 'friends') ORDER BY item_count DESC LIMIT 100";
        } else {
            $sql = "SELECT username, lowcard_played AS item_count FROM profile  ORDER BY item_count DESC LIMIT 100";
            $sql2 = "SELECT username, lowcard_won AS item_count FROM profile  ORDER BY item_count DESC LIMIT 100";
        }
        $result = array();
        $result[] = $this->getResult($sql, "LowCard played");
        $result[] = $this->getResult($sql2, "LowCard won");
        return $result;
    }

    private function getCricket($username, $friendsOnly)
    {
        if ($friendsOnly) {
            $sql = "SELECT username, cricket_played AS item_count FROM profile WHERE username = '$username' OR username IN 
(SELECT other FROM user_relations WHERE username = '$username' AND relationType = 'friends') ORDER BY item_count DESC LIMIT 100";
            $sql2 = "SELECT username, cricket_won AS item_count FROM profile WHERE username = '$username' OR username IN 
(SELECT other FROM user_relations WHERE username = '$username' AND relationType = 'friends') ORDER BY item_count DESC LIMIT 100";
        } else {
            $sql = "SELECT username, cricket_played AS item_count FROM profile  ORDER BY item_count DESC LIMIT 100";
            $sql2 = "SELECT username, cricket_won AS item_count FROM profile  ORDER BY item_count DESC LIMIT 100";
        }
        $result = array();
        $result[] = $this->getResult($sql, "Cricket played");
        $result[] = $this->getResult($sql2, "Cricket won");
        return $result;
    }

    private function getDice($username, $friendsOnly)
    {
        if ($friendsOnly) {
            $sql = "SELECT username, dice_played AS item_count FROM profile WHERE username = '$username' OR username IN 
(SELECT other FROM user_relations WHERE username = '$username' AND relationType = 'friends') ORDER BY item_count DESC LIMIT 100";
            $sql2 = "SELECT username, dice_won AS item_count FROM profile WHERE username = '$username' OR username IN 
(SELECT other FROM user_relations WHERE username = '$username' AND relationType = 'friends') ORDER BY item_count DESC LIMIT 100";
        } else {
            $sql = "SELECT username, dice_played AS item_count FROM profile  ORDER BY item_count DESC LIMIT 100";
            $sql2 = "SELECT username, dice_won AS item_count FROM profile  ORDER BY item_count DESC LIMIT 100";
        }
        $result = array();
        $result[] = $this->getResult($sql, "Dice played");
        $result[] = $this->getResult($sql2, "Dice won");
        return $result;
    }

    private function getGift($username, $friendsOnly)
    {
        if ($friendsOnly) {
            $sql = "SELECT username, gift_sent_daily AS item_count FROM profile WHERE username = '$username' OR username IN 
(SELECT other FROM user_relations WHERE username = '$username' AND relationType = 'friends') ORDER BY item_count DESC LIMIT 100";
            $sql2 = "SELECT username, gifts_daily AS item_count FROM profile WHERE username = '$username' OR username IN 
(SELECT other FROM user_relations WHERE username = '$username' AND relationType = 'friends') ORDER BY item_count DESC LIMIT 100";
        } else {
            $sql = "SELECT username, gift_sent_daily AS item_count FROM profile  ORDER BY item_count DESC LIMIT 100";
            $sql2 = "SELECT username, gifts_daily AS item_count FROM profile  ORDER BY item_count DESC LIMIT 100";
        }
        $result = array();
        $result[] = $this->getResult($sql, "Gifts sent");
        $result[] = $this->getResult($sql2, "Gifts received");
        return $result;
    }

    private function getResult($sql, $title)
    {
        $rankList = $this->queryHelper->query($sql);
        $result = array();
        $result["rank_list"] = $rankList;
        $result["title"] = $title;
        return $result;
    }

    public function __destruct()
    {
        $this->queryHelper->close();
    }
}