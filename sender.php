<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require 'vendor/autoload.php';

if (!isset($_GET['email']) || empty(trim($_GET['email']))) {
    http_response_code(400);
    die('Error: Missing recipient email address.');
}

$recipientEmail = filter_var(trim($_GET['email']), FILTER_VALIDATE_EMAIL);

if (!$recipientEmail) {
    http_response_code(400);
    die('Error: Invalid email address format.');
}

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$mail = new PHPMailer(true);

$message = <<<EOD
<html>
    <body>
        <h2>Hello,</h2>

        <p>It looks like you requested to reset your password. Please click <a href="http://example.com/reset-password">here</a> to have it reset.</p>

        <p>Best regards,<br/>
        The StatixLabs Team</p>
    </body>
</html>
EOD;

$message = str_replace("\r\n", "\n", $message);
$message = str_replace("\n", "\r\n", $message);

$message = wordwrap($message, 70, "\r\n");

try {

    $mail->isSMTP();

    $mail->Host       = 'smtp-relay.brevo.com';

    $mail->SMTPAuth   = true;

    $mail->Username   = $_ENV['BREVO_USERNAME'];

    $mail->Password   = $_ENV['BREVO_PASSWORD'];

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

    $mail->Port       = 587;

 

    $mail->setFrom('noreply@statixlabs.org', 'StatixLabs');

    $mail->addAddress($recipientEmail, 'User');

    $mail->addReplyTo('satej@statixlabs.org', 'Support');

 

    $mail->Subject = 'Password Reset Request';

    $mail->Body    = $message;

    $mail->altBody = 'Whoops. Something went wrong.';

    $mail->send();

    echo 'Email sent successfully';

} catch (Exception $e) {

    http_response_code(500);
    echo "Error: {$mail->ErrorInfo}";

}