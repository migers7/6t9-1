<?php
/**
 * Created by PhpStorm.
 * User: rex
 * Date: 6/27/2019
 * Time: 10:02 PM
 */

require 'vendor/autoload.php';


$email = new \SendGrid\Mail\Mail();
try {
    $email->setFrom("team@6t9.app", "Team SixT9");
} catch (\SendGrid\Mail\TypeException $e) {
}
$email->setSubject("Sending with Twilio SendGrid is Fun");
$email->addTo("ahqmrf@gmail.com", "Ariful Hoque MAruf");
$email->addContent("text/plain", "and easy to do anywhere, even with PHP");
$email->addContent(
    "text/html", "<strong>and easy to do anywhere, even with PHP</strong>"
);
$sendgrid = new \SendGrid('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');
try {
    $response = $sendgrid->send($email);
    print $response->statusCode() . "\n";
    print_r($response->headers());
    print $response->body() . "\n";
} catch (Exception $e) {
    echo 'Caught exception: '. $e->getMessage() ."\n";
}