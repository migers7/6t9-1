<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 11/18/2018
 * Time: 12:11 PM
 */

require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    //Server settings
    $mail->isSMTP();                                      // Set mailer to use SMTP
    $mail->Host = 'smtp.gmail.com';  // Specify main and backup SMTP servers
    $mail->SMTPAuth = true;                               // Enable SMTP authentication
    $mail->Username = 'sixt9app@gmail.com';                 // SMTP username
    $mail->Password = 'anjaliee764O67';                           // SMTP password
    $mail->SMTPSecure = 'ssl';                            // Enable TLS encryption, `ssl` also accepted
    $mail->Port = 465;                                    // TCP port to connect to

    //Recipients
    $mail->setFrom('sixt9app@gmail.com');
    $mail->addAddress("ahqmrf@gmail.com");     // Add a recipient

    //Content
                                    // Set email format to HTML
    $mail->Subject = "Test";
    $msg = '<html>
<head>
    
</head>
<body>
    <div style="height:80px; padding:15px; background-color:#E68585;">

    </div>
    <div id="content">
        <h3>Welcome to 69!</h3>

        Your email verification code is <b>Code</b><br />
        To download our app, please visit <a href="https://drive.google.com/open?id=1_RUYihvMPEFGLq3_E4-1jW2EPF7bGi81">here</a><br />
        <br />
        <br />
        If you did not request for any verification, please ignore this email.

    </div>
</body>
</html>';
    $mail->Body    = '<div>
        <h3>Welcome to 69!</h3>

        Your email verification code is <b>Code</b><br />
        To download our app, please visit <a href="https://drive.google.com/open?id=1_RUYihvMPEFGLq3_E4-1jW2EPF7bGi81">here</a><br />
        <br />
        <br />
        If you didnt request for any verification, please ignore this email.

    </div>';
    $mail->MsgHTML($msg);

    $mail->send();
    echo "Ok";
} catch (Exception $e) {
    echo "Error";
}