<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once '../config/database.php';
include_once '../config/utils.php';

class CommandUtils
{
    private $conn;
    private $tableName = "commands";
    private $commands = array();

    public function __construct()
    {
        $this->conn = (new Database())->getConnection();
        $sql = "select * from " . $this->tableName . ";";
        $res = $this->conn->query($sql);
        while ($row = $res->fetch_assoc()) {
            array_push($this->commands, $row);
        }
    }

    public function getCommandValue($cmd)
    {
        if($cmd == "/roll") {
            $res = $this->commands[0];
            $res["command"] = "/roll";
            $res["value"] = "rolls " . rand(1, 100);
            return buildSuccessResponse($res);
        }
        for ($i = 0; $i < count($this->commands); $i++) {
            if ($this->commands[$i]["command"] == $cmd) {
                return buildSuccessResponse($this->commands[$i]);
            }
        }
        return buildErrorResponse("No such command exists.");
    }
}