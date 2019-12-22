<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 1/18/19
 * Time: 11:12 PM
 */


class ApiHelper
{

    private static $pdo = null;

    private function __construct()
    {
    }

    static function getInstance()
    {
        if (self::$pdo == null) {
            $servername = "localhost";
            $username = "tryToHackUsYouBastard";
            $password = "dso438fn3;43feufblsceo394857nwl";
            $dbname = "6t9";
            $dsn = "mysql:host=" . $servername . ";dbname=" . $dbname . ";charset=utf8";
            self::$pdo = new PDO($dsn, $username, $password);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
        return self::$pdo;
    }

    static function close()
    {
        self::$pdo = null;
    }

    static function cast($res)
    {
        if ($res == false) return array();
        $type = array();
        $data = array();
        for ($i = 0; $i < $res->columnCount(); $i++) {
            $column = $res->getColumnMeta($i);
            $type[$column['name']] = $column['native_type'];
        }
        while ($row = $res->fetch()) {
            foreach ($type as $key => $value) {
                if ($value == "LONG") {
                    settype($row[$key], 'int');
                } else if ($value == "TINY") {
                    settype($row[$key], 'boolean');
                } else if ($value == "DOUBLE") {
                    settype($row[$key], 'double');
                } else if ($value == "FLOAT") {
                    settype($row[$key], 'float');
                }
            }
            $data[] = $row;
        }

        return $data;
    }

    static function buildSuccessResponse($data = array(), $message = "Request completed successfully.")
    {
        $res = array();
        $res["data"] = $data;
        $res["message"] = $message;
        $res["statusCode"] = 200;
        return json_encode($res);
    }

    static function buildErrorResponse($message = "Request completed successfully.", $code = 403)
    {
        $res = array();
        $res["data"] = null;
        $res["message"] = $message;
        $res["statusCode"] = $code;
        return json_encode($res);
    }

    static function generateVerificationCode($length = 6)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $string = '';

        for ($i = 0; $i < $length; $i++) {
            $string .= $characters[mt_rand(0, strlen($characters) - 1)];
        }

        return $string;
    }

    static function rowExists($sql)
    {
        $pdo = self::getInstance();
        $res = self::cast($pdo->query($sql));
        $pdo = null;
        if (count($res) > 0) return true;
        return false;
    }

    static function query($sql)
    {
        $pdo = self::getInstance();
        $res = self::cast($pdo->query($sql));
        $pdo = null;
        return $res;
    }

    static function queryOne($sql)
    {
        $res = self::query($sql);
        if (count($res) > 0) return $res[0];
        return null;
    }

    static function exec($sql) {
        $pdo = self::getInstance();
        $res = $pdo->exec($sql);
        $pdo = null;
        return $res;
    }
}
