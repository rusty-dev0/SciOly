<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require 'vendor/autoload.php';

function parseStringForHTML($string) {
    if (!is_string($string)) {
        http_response_code(400);
        exit('Error: Invalid parameter.');
    }

    $string = trim(strip_tags($string));

    if ($string === '') {
        http_response_code(400);
        exit('Error: Missing required parameter.');
    }

    $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);

    return $string;
}

function parseEmailFrom($email) {
    $email = parseStringForHTML($email);
    if (!isset($email) || empty(trim($email))) {
        http_response_code(400);
        die('Error: Missing recipient email address.');
    }

    $recipientEmail = filter_var(trim($email), FILTER_VALIDATE_EMAIL);

    if (!$recipientEmail) {
        http_response_code(400);
        die('Error: Invalid email address format.');
    }
    return $recipientEmail;
}

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

function sendOTP($recipientEmail, $name, $otp) {
    global $dotenv;
    $mail = new PHPMailer(true);

    $message = <<<EOD
    <html>
        <body>
            <h3>Hello,</h3>

            <p>Welcome to Science Olympiad, {$name}!</p>

            <p>Thank you for creating an account with us. Your OTP is: <strong style="font-size: 18px; letter-spacing: 2px;">{$otp}</strong></p>

            <p>This code will expire in 10 minutes. If you did not request an account creation, please ignore this email.</p>

            <p>Best regards,<br/>
            <h4>The StatixLabs SciOly Team</h4></p>
        </body>
    </html>
    EOD;

    try {

        $mail->isSMTP();

        $mail->Host       = 'smtp-relay.brevo.com';

        $mail->SMTPAuth   = true;

        $mail->Username   = $_ENV['BREVO_USERNAME'];

        $mail->Password   = $_ENV['BREVO_PASSWORD'];

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port       = 587;

        $mail->isHTML(true); // this should handle wrap and headers

        $mail->setFrom('noreply@statixlabs.org', 'StatixLabs');

        $mail->addAddress($recipientEmail, $name);

        $mail->addReplyTo('satej@statixlabs.org', 'Support');

    

        $mail->Subject = 'Your OTP from StatixLabs';

        $mail->Body    = $message;

        $mail->AltBody = 'Your OTP for StatixLabs is: ' . $otp;

        $mail->send();

        // echo 'Email sent successfully';
        return true;

    } catch (Exception $e) {

        http_response_code(500);
        echo "Error: {$mail->ErrorInfo}";

    }
}

function sendPasswordResetEmail($recipientEmail, $otp) {
    global $dotenv;
    $mail = new PHPMailer(true);

    $message = <<<EOD
    <html>
        <body>
            <h3>Hello,</h3>

            <p>We received a request to reset your password for your Science Olympiad account.</p>

            <p>Your password reset OTP code is: <strong style="font-size: 18px; letter-spacing: 2px;">{$otp}</strong></p>

            <p>This code will expire in 15 minutes. If you did not request a password reset, please ignore this email.</p>

            <p>Best regards,<br/>
            <h4>The StatixLabs SciOly Team</h4></p>
        </body>
    </html>
    EOD;

    try {

        $mail->isSMTP();

        $mail->Host       = 'smtp-relay.brevo.com';

        $mail->SMTPAuth   = true;

        $mail->Username   = $_ENV['BREVO_USERNAME'];

        $mail->Password   = $_ENV['BREVO_PASSWORD'];

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port       = 587;

        $mail->isHTML(true);

        $mail->setFrom('noreply@statixlabs.org', 'StatixLabs');

        $mail->addAddress($recipientEmail, 'User');

        $mail->addReplyTo('satej@statixlabs.org', 'Support');

        $mail->Subject = 'Password Reset OTP - StatixLabs';

        $mail->Body    = $message;

        $mail->AltBody = 'Your password reset OTP code is: ' . $otp;

        $mail->send();

        return true;

    } catch (Exception $e) {

        http_response_code(500);
        echo "Error: {$mail->ErrorInfo}";
        return false;

    }
}