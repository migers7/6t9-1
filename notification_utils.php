<?php
/**
 * Created by Ariful Hoque Maruf
 * Jr Software Engineer, Brain Station-23 Ltd.
 * https://www.github.com/ahqmrf
 */

include_once 'config.php';

class NotificationUtils
{

    public function __construct()
    {
    }

    public function getToken($username)
    {
        $sql = "SELECT token FROM users WHERE username = '$username'";
        $pdo = ApiHelper::getInstance();
        $result = $pdo->query($sql)->fetchAll();
        $pdo = null;
        if (count($result) > 0) return $result[0]["token"];
        return null;
    }

    public function getServerKey()
    {
        return "AAAAL-bNEPs:APA91bF_eeEx9nz3qIz-JtvTmh3egOevcddP-GZj102NzJV346YsVFQgwPPCm91yjmguj_RPetxJhMGI2GxrNYqRWGUHydajWXZ4-nK9t5luEKpTC7A0K1tw57iftYYydPEz97TlRoVI";
    }

    public function getURL()
    {
        return "https://fcm.googleapis.com/fcm/send";
    }

    public function getHeaders()
    {
        return array(
            'Authorization:key=' . $this->getServerKey(),
            'Content-Type:application/json'
        );
    }

    public function pushTopic($topic, $title, $message, $data = null)
    {
        $this->pushWithToken($topic, "/topics/$topic", $title, $message, $data);
    }

    public function push($username, $title, $message, $data = null)
    {
        $token = $this->getToken($username);

        if ($token != null) {
            $url = $this->getURL();

            $headers = $this->getHeaders();

            $fields = ['to' => $token, 'data' => ['to' => $username, 'title' => $title, 'body' => $message, 'message' => $data], 'priority' => 'high'];
            $payload = json_encode($fields);
            $curlSession = curl_init();
            curl_setopt($curlSession, CURLOPT_URL, $url);
            curl_setopt($curlSession, CURLOPT_POST, true);
            curl_setopt($curlSession, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlSession, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curlSession, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($curlSession, CURLOPT_POSTFIELDS, $payload);

            $result = curl_exec($curlSession);
        } else {
            return ApiHelper::buildErrorResponse("Token cannot be null");
        }
    }

    public function pushWithToken($username, $token, $title, $message, $data = null)
    {
        if ($token != null) {
            $url = $this->getURL();

            $headers = $this->getHeaders();

            $fields = ['to' => $token, 'data' => ['to' => $username, 'title' => $title, 'body' => $message, 'message' => $data], 'priority' => 'high'];
            $payload = json_encode($fields);
            $curlSession = curl_init();
            curl_setopt($curlSession, CURLOPT_URL, $url);
            curl_setopt($curlSession, CURLOPT_POST, true);
            curl_setopt($curlSession, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlSession, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curlSession, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($curlSession, CURLOPT_POSTFIELDS, $payload);

            $result = curl_exec($curlSession);
        } else {
            return ApiHelper::buildErrorResponse("Token cannot be null");
        }
    }

    public function isValidFCMToken($token, $title, $message, $data = null)
    {

        if ($token != null) {
            $url = $this->getURL();

            $headers = $this->getHeaders();

            $fields = ['to' => $token, 'data' => ['title' => $title, 'body' => $message, 'message' => $data], 'priority' => 'high'];
            $payload = json_encode($fields);
            $curlSession = curl_init();
            curl_setopt($curlSession, CURLOPT_URL, $url);
            curl_setopt($curlSession, CURLOPT_POST, true);
            curl_setopt($curlSession, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlSession, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curlSession, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($curlSession, CURLOPT_POSTFIELDS, $payload);

            $result = curl_exec($curlSession);
            return $result;
        } else {
            return null;
        }
    }

    public function pushGame($username, $gameName, $roomName, $data)
    {
        $token = $this->getToken($username);

        if ($token != null) {
            $url = $this->getURL();

            $headers = $this->getHeaders();

            $fields = ['to' => $token, 'data' => ['to' => $username, 'title' => $gameName, 'body' => $roomName, 'message' => $data], 'priority' => 'high'];
            $payload = json_encode($fields);
            $curlSession = curl_init();
            curl_setopt($curlSession, CURLOPT_URL, $url);
            curl_setopt($curlSession, CURLOPT_POST, true);
            curl_setopt($curlSession, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($curlSession, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlSession, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curlSession, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($curlSession, CURLOPT_POSTFIELDS, $payload);

            $result = curl_exec($curlSession);
        } else {
            return ApiHelper::buildErrorResponse("Token cannot be null");
        }
    }

    public function test($username, $title, $message)
    {
        $this->push($username, "SixT9", "Sending a push withing 5 sec");
        echo "Test";
        sleep(5);
        $this->push($username, $title, $message);
    }

    public function updateToken($username, $token)
    {
        $sql = "UPDATE users SET token = ? WHERE username = ?";
        $pdo = ApiHelper::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$token, $username]);
        if ($stmt->rowCount() > 0) {
            $pdo = null;
            return ApiHelper::buildSuccessResponse($token, "Token updated successfully.");
        }
        $pdo = null;
        return ApiHelper::buildErrorResponse("Failed to update FCM token.");
    }

    public function insert($to, $body, $type = "general", $title = "")
    {
        $sql = "INSERT INTO notification(toWho, body, notificationType, title) VALUES(?, ?, ?, ?)";
        $pdo = ApiHelper::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$to, $body, $type, $title]);
        $pdo = null;
    }

    public function insertWithImage($to, $body, $image, $type = "general", $title = "")
    {
        $sql = "INSERT INTO notification(toWho, body, notificationType, title, image_url) VALUES(?, ?, ?, ?, ?)";
        $pdo = ApiHelper::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$to, $body, $type, $title, $image]);
        $pdo = null;
    }

    public function insertWithImageAndLink($to, $body, $image, $link = "", $type = "general", $title = "")
    {
        $sql = "INSERT INTO notification(toWho, body, notificationType, title, image_url, deep_link) VALUES(?, ?, ?, ?, ?, ?)";
        $pdo = ApiHelper::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$to, $body, $type, $title, $image, $link]);
        $pdo = null;
    }

    public function getNotifications($username, $id = 300000000000000000)
    {
        $limit = 50;
        if($id < 300000000000000000) $limit = 50;
        $sql = "SELECT id, toWho, notificationType, time_stamp, title, body, isRead, image_url, deep_link, TIME_TO_SEC(TIMEDIFF(NOW(), time_stamp)) time_ago FROM notification WHERE toWho = '$username' AND id < $id ORDER BY id DESC LIMIT $limit";
        $pdo = ApiHelper::getInstance();
        $res = ApiHelper::cast($pdo->query($sql));
        $sql = "UPDATE notification SET isRead = 1 WHERE toWho = '$username'";
        $pdo->exec($sql);
        $pdo = null;
        return ApiHelper::buildSuccessResponse($res);
    }
}