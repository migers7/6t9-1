<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 1/13/19
 * Time: 6:27 PM
 */

require __DIR__ . '/vendor/autoload.php';

use Aws\Ses\SesClient;
use Aws\Exception\AwsException;

include_once 'config.php';
include_once 'notification_utils.php';
include_once 'file_config.php';


class EmailUtils
{
    private static function email($to, $subject, $body)
    {
        $SesClient = new SesClient([
            'credentials' => [
                'key' => 'AKIAJMTCMTJ67SP2DOXQ',
                'secret' => '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'
            ],
            'version' => 'latest',
            'region' => 'us-east-1'
        ]);

        $sender_email = 'no-reply@6t9.app';
        $recipient_emails = [$to];
        $plaintext_body = $body;
        $char_set = 'UTF-8';

        try {
            $SesClient->sendEmail([
                'Destination' => [
                    'ToAddresses' => $recipient_emails,
                ],
                'ReplyToAddresses' => [$sender_email],
                'Source' => $sender_email,
                'Message' => [
                    'Body' => [
                        'Text' => [
                            'Charset' => $char_set,
                            'Data' => $plaintext_body,
                        ],
                    ],
                    'Subject' => [
                        'Charset' => $char_set,
                        'Data' => $subject,
                    ],
                ],
            ]);
            return true;
        } catch (AwsException $e) {
            return false;
        }
    }

    public static function emailTo($to, $subject, $body)
    {
        if (self::email($to, $subject, $body)) return true;
        return false;
    }

    public static function sendInappEmail($sender, $receiver, $subject, $body)
    {
        $pdo = ApiHelper::getInstance();
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
        $user = ApiHelper::queryOne("SELECT dp FROM users WHERE username = '$sender'");
        $imageUrl = $user["dp"];

        $sql = "INSERT INTO emails(username, sender, recipient, subject, body, seen, image_url) VALUES(
'$sender', '$sender', '$receiver', '$subject', '$body', false, '$imageUrl')";
        $pdo->exec($sql);
        (new NotificationUtils())->push($receiver, "email_notification", "Email", "You have received an email.");

        $sql = "INSERT INTO emails(username, sender, recipient, subject, body, seen, image_url) VALUES(
'$receiver', '$sender', '$receiver', '$subject', '$body', true, '$imageUrl')";
        $pdo->exec($sql);
        $pdo = null;
    }

    public static function sendInformationEmail($receiver, $subject, $body)
    {
        $pdo = ApiHelper::getInstance();
        $sender = "No reply";
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
        (new NotificationUtils())->push($receiver, "email_notification", "Email", "You have received an email.");
        $sql = "INSERT INTO emails(username, sender, recipient, subject, body, seen, canReply) VALUES(
'$receiver', '$sender', '$receiver', '$subject', '$body', false, 0)";
        $pdo->exec($sql);
        $pdo = null;
    }
}
