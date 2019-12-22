<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 6/22/2019
 * Time: 3:42 PM
 */

class QueryHelper
{

    private $pdo;

    public function __construct()
    {
        $servername = "localhost";
        $username = "tryToHackUsYouBastard";
        $password = "dso438fn3;43feufblsceo394857nwl";
        $dbname = "6t9";
        $dsn = "mysql:host=" . $servername . ";dbname=" . $dbname . ";charset=utf8";
        $this->pdo = new PDO($dsn, $username, $password);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function reInit() {
        $this->__construct();
    }

    public function cast($res)
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


    public function rowExists($sql)
    {
        $res = $this->query($sql);
        if (count($res) > 0) return true;
        return false;
    }

    public function query($sql)
    {
        $res = $this->cast($this->pdo->query($sql));
        return $res;
    }

    public function queryOne($sql)
    {
        $res = $this->query($sql);
        if (count($res) > 0) return $res[0];
        return null;
    }

    public function exec($sql)
    {
        $res = $this->pdo->exec($sql);
        return $res;
    }

    public function close()
    {
        $this->pdo = null;
    }

    public function __destruct()
    {
        $this->close();
    }
}