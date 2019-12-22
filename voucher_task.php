<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 9/16/2018
 * Time: 12:00 AM
 */

include_once 'config.php';
include_once 'firebaseManager.php';
include_once 'QueryHelper.php';

function getVoucherCode($length = 7)
{
    $characters = '0123456789';
    $string = '';

    for ($i = 0; $i < $length; $i++) {
        $start = 0;
        if ($i == 0) $start = 1;
        $string .= $characters[mt_rand($start, 9)];
    }

    return $string;
}

$queryHelper = new QueryHelper();
$code = getVoucherCode();
$amount = mt_rand(20, 50);
$sql = "INSERT INTO voucher (id, code, amount) VALUES (1, '$code', '$amount')
  ON DUPLICATE KEY UPDATE code = '$code', amount = '$amount';";
$queryHelper->exec($sql);

$ref = FirebaseManager::getInstance();

$ref->getReference("chats/" . "Gift Card")->push(MessageHelper::getRoomInfo("Gift Card",
    "Are you bored? Here is a Gift Card with code $code for BDT $amount. Type /pick [code] to collect (valid for 40 sec)"
));
$ref->getReference("chats/" . "Gift Card 2")->push(MessageHelper::getRoomInfo("Gift Card 2",
    "Are you bored? Here is a Gift Card with code $code for BDT $amount. Type /pick [code] to collect (valid for 40 sec)."
));
$ref->getReference("chats/" . "Bangladesh")->push(MessageHelper::getRoomInfo("Bangladesh",
    "Are you bored? Here is a Gift Card with code $code for BDT $amount. Type /pick [code] to collect (valid for 40 sec)."
));
$ref->getReference("chats/" . "Indonesia")->push(MessageHelper::getRoomInfo("Indonesia",
    "Are you bored? Here is a Gift Card with code $code for BDT $amount. Type /pick [code] to collect (valid for 40 sec)."
));
$ref->getReference("chats/" . "Nepal")->push(MessageHelper::getRoomInfo("Nepal",
    "Are you bored? Here is a Gift Card with code $code for BDT $amount. Type /pick [code] to collect (valid for 40 sec)."
));
$ref->getReference("chats/" . "Nepal Chat")->push(MessageHelper::getRoomInfo("Nepal Chat",
    "Are you bored? Here is a Gift Card with code $code for BDT $amount. Type /pick [code] to collect (valid for 40 sec)."
));
$ref->getReference("chats/" . "India")->push(MessageHelper::getRoomInfo("India",
    "Are you bored? Here is a Gift Card with code $code for BDT $amount. Type /pick [code] to collect (valid for 40 sec)."
));
$ref->getReference("chats/" . "Pakistan")->push(MessageHelper::getRoomInfo("Pakistan",
    "Are you bored? Here is a Gift Card with code $code for BDT $amount. Type /pick [code] to collect (valid for 40 sec)."
));

$queryHelper->close();

sleep(40);

$queryHelper->reInit();
$code = getVoucherCode(9);
$amount = 0;
$sql = "INSERT INTO voucher (id, code, amount) VALUES (1, '$code', '$amount')
  ON DUPLICATE KEY UPDATE code = '$code', amount = '$amount';";
$queryHelper->exec($sql);
$queryHelper->close();