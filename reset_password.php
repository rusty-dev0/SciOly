<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('UTC');
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sender.php';

$error = '';
$success = '';
$step = 1;
$email = $_SESSION['reset_email'] ?? '';

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request_otp') {
        $rawEmail = $_POST['email'] ?? '';

        try {
            $email = parseEmailFrom($rawEmail);

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $otp = sprintf("%06d", mt_rand(0, 999999));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                $payload = json_encode(['type' => 'password_reset']);

                $stmtDel = $pdo->prepare("DELETE FROM otps WHERE email = ?");
                $stmtDel->execute([$email]);

                $stmtIns = $pdo->prepare("INSERT INTO otps (email, otp_code, payload, expires_at) VALUES (?, ?, ?, ?)");
                $stmtIns->execute([$email, $otp, $payload, $expiresAt]);

                if (sendPasswordResetEmail($email, $otp)) {
                    $_SESSION['reset_email'] = $email;
                    $step = 2;
                    $success = "Verification code sent to <strong>" . htmlspecialchars($email) . "</strong>. Please check your inbox!";
                } else {
                    $error = "Failed to dispatch verification email. Please try again.";
                }
            } else {
                $error = "No account found associated with that email address.";
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }

    if ($action === 'reset_password') {
        $rawEmail = $_POST['email'] ?? '';
        $otp = trim($_POST['otp'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $step = 2; // stay on step 2 if validation fails

        try {
            $email = parseEmailFrom($rawEmail);

            if (empty($otp) || empty($newPassword)) {
                $error = "All fields are required.";
            } elseif ($newPassword !== $confirmPassword) {
                $error = "New passwords do not match!";
            } elseif (strlen($newPassword) < 6) {
                $error = "Password must be at least 6 characters long.";
            } else {
                $stmt = $pdo->prepare("SELECT id FROM otps WHERE email = ? AND otp_code = ? AND expires_at > NOW()");
                $stmt->execute([$email, $otp]);

                if ($stmt->fetch()) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmtUpdate = $pdo->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
                    $stmtUpdate->execute([$hashedPassword, $email]);

                    $stmtDel = $pdo->prepare("DELETE FROM otps WHERE email = ?");
                    $stmtDel->execute([$email]);

                    unset($_SESSION['reset_email']);
                    $step = 3; // success state
                    $success = "Password updated successfully! You can now log in.";
                } else {
                    $error = "Invalid or expired verification code.";
                }
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SciOly Password Reset Wizard</title>
    <link rel="stylesheet" href="https://unpkg.com/7.css">
    <link rel="stylesheet" href="styles/reset_password.css">
</head>
<body>

<div class="window active">
    <div class="title-bar">
        <div class="title-bar-text">Science Olympiad Password Reset Wizard</div>
        <div class="title-bar-controls">
            <button aria-label="Minimize"></button>
            <button aria-label="Maximize"></button>
            <a href="index.php" style="text-decoration:none;"><button aria-label="Close"></button></a>
        </div>
    </div>

    <div class="window-body has-space">

        <?php if ($error): ?>
            <div role="tooltip" class="balloon" style="display: flex; align-items: center; gap: 6px;">
                <img src="https://win98icons.alexmeub.com/icons/png/msg_error-2.png" alt="Error" style="width: 16px; height: 16px;">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div role="tooltip" class="balloon" style="display: flex; align-items: center; gap: 6px;">
                <img src="https://win98icons.alexmeub.com/icons/png/key_padlock-0.png" alt="Info" style="width: 16px; height: 16px;">
                <span><?= $success ?></span>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <fieldset style="margin-bottom: 12px;">
                <legend>Step 1: Account Identification</legend>
                <form method="POST" action="reset_password.php">
                    <input type="hidden" name="action" value="request_otp">
                    
                    <p style="font-size: 11px; color: #333; margin-top: 0;">Enter your account email address to receive a 6-digit OTP verification code.</p>
                    
                    <div class="field-row">
                        <label for="email">Email Address:</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required placeholder="user@domain.com">
                    </div>

                    <div style="display: flex; justify-content: flex-end; margin-top: 12px;">
                        <button type="submit" class="default">Send Verification Code</button>
                    </div>
                </form>
            </fieldset>
        <?php endif; ?>

        <?php if ($step === 2): ?>
            <fieldset style="margin-bottom: 12px;">
                <legend>Step 2: Enter Verification & New Password</legend>
                <form method="POST" action="reset_password.php">
                    <input type="hidden" name="action" value="reset_password">
                    
                    <div class="field-row">
                        <label for="email">Email Address:</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required readonly style="background-color: #f0f0f0;">
                    </div>

                    <div class="field-row">
                        <label for="otp">OTP Code:</label>
                        <input type="text" id="otp" name="otp" maxlength="6" required placeholder="123456" style="letter-spacing: 2px;">
                    </div>

                    <div class="field-row">
                        <label for="new_password">New Password:</label>
                        <input type="password" id="new_password" name="new_password" required>
                    </div>

                    <div class="field-row">
                        <label for="confirm_password">Confirm Password:</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <div style="display: flex; justify-content: flex-end; margin-top: 12px;">
                        <button type="submit" class="default">Update Password</button>
                    </div>
                </form>
            </fieldset>
        <?php endif; ?>

        <section style="display: flex; justify-content: space-between; align-items: center;">
            <a href="index.php" style="text-decoration:none;">
                <button type="button" class="<?= $step === 3 ? 'default' : '' ?>">
                    <?= $step === 3 ? 'Back to Login' : 'Cancel' ?>
                </button>
            </a>
            <?php if ($step === 2): ?>
                <a href="reset_password.php" style="text-decoration:none;">
                    <button type="button">Resend Code</button>
                </a>
            <?php endif; ?>
        </section>

    </div>
</div>

</body>
</html>