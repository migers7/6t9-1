<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 11/23/2018
 * Time: 10:49 AM
 */

include_once 'firebaseManager.php';

FirebaseManager::getInstance()->getReference("chats")->remove();