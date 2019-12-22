<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 11/21/2018
 * Time: 3:11 AM
 */

$path = "shared_photos";
if (is_dir($path)) {
    $objects = scandir($path);
    foreach ($objects as $object) {
        echo unlink(dirname(__FILE__) . $path . $object);
    }
}