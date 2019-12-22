<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 10/12/2018
 * Time: 10:31 PM
 */

include_once 'userUtils.php';


require __DIR__ . '/vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use Kreait\Firebase;
use Firebase\Auth\Token\Exception\InvalidToken;

class FirebaseManager
{

    public static function getInstance()
    {
        $serviceAccount = ServiceAccount::fromJsonFile(__DIR__ . '/secret/239468129369128dhskjdgwly42o4242o46dhlsdkywle42dh/sixt9-2018-fc66aldh203skdh37bf1c9845da.json');

        $firebase = (new Factory)
            ->withServiceAccount($serviceAccount)
            // The following line is optional if the project id in your credentials file
            // is identical to the subdomain of your Firebase project. If you need it,
            // make sure to replace the URL with the URL of your project.
            ->withDatabaseUri('https://sixt9-2018.firebaseio.com/')
            ->create();
        $database = $firebase->getDatabase();
        return $database;
    }

    public static function getAuth() {
        $serviceAccount = ServiceAccount::fromJsonFile(__DIR__ . '/secret/239468129369128dhskjdgwly42o4242o46dhlsdkywle42dh/sixt9-2018-fc66aldh203skdh37bf1c9845da.json');

        $firebase = (new Factory)
            ->withServiceAccount($serviceAccount)
            // The following line is optional if the project id in your credentials file
            // is identical to the subdomain of your Firebase project. If you need it,
            // make sure to replace the URL with the URL of your project.
            ->withDatabaseUri('https://sixt9-2018.firebaseio.com/')
            ->create();
        return $firebase->getAuth();
    }

    public static function isValidToken($token) {
        $serviceAccount = ServiceAccount::fromJsonFile(__DIR__ . '/secret/239468129369128dhskjdgwly42o4242o46dhlsdkywle42dh/sixt9-2018-fc66aldh203skdh37bf1c9845da.json');

        $firebase = (new Factory)
            ->withServiceAccount($serviceAccount)
            // The following line is optional if the project id in your credentials file
            // is identical to the subdomain of your Firebase project. If you need it,
            // make sure to replace the URL with the URL of your project.
            ->withDatabaseUri('https://sixt9-2018.firebaseio.com/')
            ->create();
        try {
            $verifiedIdToken = $firebase->getAuth()->verifyIdToken($token);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

class MessageHelper
{
    public static function getGameMessage($roomName, $text, $type, $color = "#84BC23")
    {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => $type,
            'text' => $text,
            'color' => $color
        ];
    }

    public static function getRoomEnterMessage($username, $roomName)
    {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        $level = (new UserUtils())->findUser($username)["userLevel"];
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => MessageTypes::$ENTER,
            'text' => $username . "[" . $level . "] has entered",
            'color' => ColorCodes::$ROOM_INFO
        ];
    }

    public static function getRoomInfo($roomName, $info, $type = 4) {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => $type,
            'text' => $info,
            'color' => ColorCodes::$ROOM_INFO
        ];
    }

    public static function getKick($roomName, $info) {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => MessageTypes::$KICK,
            'text' => $info,
            'color' => ColorCodes::$ROOM_INFO
        ];
    }

    public static function getBan($roomName, $info) {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => MessageTypes::$BAN,
            'text' => $info,
            'color' => ColorCodes::$ROOM_INFO
        ];
    }

    public static function getSilence($roomName, $info) {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => MessageTypes::$SILENCED,
            'text' => $info,
            'color' => ColorCodes::$ROOM_INFO
        ];
    }

    public static function getBroadcast($roomName, $info) {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => MessageTypes::$BROADCAST,
            'text' => $info,
            'color' => ColorCodes::$ROOM_INFO
        ];
    }

    public static function getDiceBidText($roomName, $text) {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => MessageTypes::$DICE_BID,
            'text' => $text,
            'color' => ColorCodes::$GAME_BOT
        ];
    }

    public static function getLowcardDrawText($roomName, $text) {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => MessageTypes::$LOWCARD_DRAW_CARD,
            'text' => $text,
            'color' => ColorCodes::$GAME_BOT
        ];
    }


    public static function getDiceStartedText($roomName, $text) {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => MessageTypes::$DICE_STARTED,
            'text' => $text,
            'color' => ColorCodes::$GAME_BOT
        ];
    }

    public static function getLowcardStartedText($roomName, $text) {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => MessageTypes::$LOWCARD_STARTED,
            'text' => $text,
            'color' => ColorCodes::$GAME_BOT
        ];
    }

    public static function getLowcardJoinText($roomName, $text) {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => MessageTypes::$LOWCARD_JOIN,
            'text' => $text,
            'color' => ColorCodes::$GAME_BOT
        ];
    }

    public static function getLowcardInfoText($roomName, $text) {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => MessageTypes::$LOWCARD_INFO,
            'text' => $text,
            'color' => ColorCodes::$GAME_BOT
        ];
    }

    public static function getPrivateGiftText($roomName, $text) {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => MessageTypes::$TYPE_PRIVATE_GIFT,
            'text' => $text,
            'color' => ColorCodes::$ROOM_INFO
        ];
    }

    public static function getGiftShowerText($roomName, $text) {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => MessageTypes::$GIFT_SHOWER,
            'text' => $text,
            'color' => ColorCodes::$ROOM_INFO
        ];
    }

    public static function getLowcardNotEnufPlayerText($roomName, $text) {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => MessageTypes::$LOWCARD_NOT_ENOUGH_PLAYER,
            'text' => $text,
            'color' => ColorCodes::$GAME_BOT
        ];
    }

    public static function getRoomLeftMessage($username, $roomName)
    {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        $level = (new UserUtils())->findUser($username)["userLevel"];
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => MessageTypes::$LEFT,
            'text' => $username . "[" . $level . "] has left",
            'color' => ColorCodes::$ROOM_INFO
        ];
    }

    public static function getCricketInfo($roomName, $info) {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => MessageTypes::$CRICKET_INFO,
            'text' => $info,
            'color' => ColorCodes::$GAME_BOT
        ];
    }

    public static function getCricketHitMessage($roomName, $text) {
        $time = time();
        $timeStamp = date("Y-m-d H:i:s", $time);
        return [
            'time' => $timeStamp,
            'timeInMillis' => "" . $time . "" . rand(0, 1000),
            'from' => $roomName,
            'type' => MessageTypes::$CRICKET_BAT,
            'text' => $text,
            'color' => ColorCodes::$GAME_BOT
        ];
    }
}

class MessageTypes
{
    public static $ENTER = 0;
    public static $LEFT = 1;
    public static $TEXT = 2;
    public static $KICK = 3;
    public static $INFO = 4;
    public static $INFO_ANNOUNCEMENT = 12;
    public static $BAN = 10;
    public static $GIFT_SHOWER = 11;
    public static $COMMAND = 13;
    public static $ME = 14;
    public static $UNBAN = 16;
    public static $TYPE_PRIVATE_GIFT = 15;
    public static $DICE_GAME_OVER = 17;
    public static $DICE_BID = 18;
    public static $DICE_STARTED = 19;
    public static $DICE_GAME_RUNNING = 20;
    public static $LOWCARD_STARTED = 21;
    public static $LOWCARD_DRAW_CARD = 22;
    public static $LOWCARD_BOT_DRAWS = 23;
    public static $LOWCARD_JOIN = 24;
    public static $LOWCARD_NOT_ENOUGH_PLAYER = 25;
    public static $LOWCARD_INFO = 26;
    public static $SILENCED = 28;
    public static $BROADCAST = 29;
    public static $CRICKET_INFO = 31;
    public static $CRICKET_BAT = 32;
}

class ColorCodes
{
    public static $USER = "#3F51B5";
    public static $SELF = "#2E7D32";
    public static $ROOM_INFO = "#FF5722";
    public static $OWNER = "#F9A825";
    public static $ADMIN = "#F9A825";
    public static $MODERATOR = "#FFDE03";
    public static $COMMAND = "#C51162";
    public static $GIFT_SHOWER = "#E91E63";
    public static $GAME_BOT = "#84BC23";
    public static $ANNOUNCEMENT = "#8D3028";
}

