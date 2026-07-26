<?php
session_start();
require_once __DIR__ . '/sender.php';
require_once __DIR__ . '/db.php';

$error = '';
$success = '';
$step = $_SESSION['step'] ?? 'auth'; // steps: 'auth' or 'verify_otp'

// tab active states
$activeTab = $_GET['tab'] ?? 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // login controller
    if ($_POST['action'] === 'login') {
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';

        if ($email && $password) {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $success = "Welcome back, " . htmlspecialchars($user['first_name']) . "!";
            } else {
                $error = "Invalid email or password.";
            }
        } else {
            $error = "Please fill in all fields.";
        }
    }

    // signup controller
    if ($_POST['action'] === 'signup') {
        $firstName = parseStringForHTML($_POST['first_name'] ?? '');
        $lastName  = parseStringForHTML($_POST['last_name'] ?? '');
        $email     = parseEmailFrom($_POST['email'] ?? '');
        $track     = $_POST['track'] ?? '';
        $password  = $_POST['password'] ?? '';

        $validTracks = ['biochem', 'physics', 'earth', 'build'];

        if ($firstName && $lastName && $email && in_array($track, $validTracks) && strlen($password) >= 6) {
            $pdo = getDB();
            
            // check if user already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "An account with this email already exists.";
                $activeTab = 'signup';
            } else {
                $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $payload = json_encode([
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'track'      => $track,
                    'password'   => $passwordHash
                ]);

                $stmt = $pdo->prepare("DELETE FROM otps WHERE email = ?");
                $stmt->execute([$email]);

                $stmt = $pdo->prepare("INSERT INTO otps (email, otp_code, payload, expires_at) VALUES (?, ?, ?, ?)");
                $stmt->execute([$email, $otp, $payload, $expiresAt]);

                try {
                    sendOTP($email, $firstName, $otp);
                    $_SESSION['pending_email'] = $email;
                    $_SESSION['step'] = 'verify_otp';
                    $step = 'verify_otp';
                    $success = "OTP sent to $email. Please check your inbox.";
                } catch (Exception $e) {
                    $error = "Failed to send email. " . $e->getMessage();
                    $activeTab = 'signup';
                }
            }
        } else {
            $error = "Please complete all fields correctly (Password must be 6+ chars).";
            $activeTab = 'signup';
        }
    }

    if ($_POST['action'] === 'verify_otp') {
        $otpEntered = trim($_POST['otp_code'] ?? '');
        $email = $_SESSION['pending_email'] ?? '';

        if ($email && strlen($otpEntered) === 6) {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM otps WHERE email = ? AND otp_code = ? AND expires_at > NOW()");
            $stmt->execute([$email, $otpEntered]);
            $otpRecord = $stmt->fetch();

            if ($otpRecord) {
                $userData = json_decode($otpRecord['payload'], true);

                $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password_hash, track, role) VALUES (?, ?, ?, ?, ?, 'member')");
                $stmt->execute([
                    $userData['first_name'],
                    $userData['last_name'],
                    $email,
                    $userData['password'],
                    $userData['track']
                ]);

                $stmt = $pdo->prepare("DELETE FROM otps WHERE email = ?");
                $stmt->execute([$email]);

                unset($_SESSION['pending_email']);
                $_SESSION['step'] = 'auth';
                $step = 'auth';
                $success = "Account created successfully! You may now login.";
                $activeTab = 'login';
            } else {
                $error = "Invalid or expired OTP code.";
            }
        } else {
            $error = "Please enter a valid 6-digit OTP.";
        }
    }
}

// reset session state if user requests
if (isset($_GET['cancel_otp'])) {
    unset($_SESSION['pending_email']);
    $_SESSION['step'] = 'auth';
    header("Location: index.php?tab=signup");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SciOly User Portal</title>
    <link rel="stylesheet" href="https://unpkg.com/7.css">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background-color: #005a9e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .window {
            width: 420px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
        }

        .field-group {
            margin-bottom: 12px;
        }

        .field-group label {
            display: block;
            margin-bottom: 4px;
        }

        .field-group input, .field-group select {
            width: 100%;
            box-sizing: border-box;
        }

        /* Spacing for native 7.css tooltip */
        div[role="tooltip"] {
            margin-bottom: 12px;
        }
    </style>
</head>
<body>

<div class="window active">
    <div class="title-bar">
        <div class="title-bar-text">Science Olympiad User Portal</div>
        <div class="title-bar-controls">
            <button aria-label="Minimize"></button>
            <button aria-label="Maximize"></button>
            <button aria-label="Close"></button>
        </div>
    </div>

    <div class="window-body has-space">

        <?php if ($error): ?>
            <div role="tooltip" class="balloon">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div role="tooltip" class="balloon">
                <strong>Notice:</strong> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($step === 'verify_otp'): ?>
            <fieldset>
                <legend>Security Verification</legend>
                <p>We've sent a 6-digit OTP code to <strong><?= htmlspecialchars($_SESSION['pending_email']) ?></strong>.</p>
                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="verify_otp">
                    <div class="field-group">
                        <label for="otp_code">Enter 6-Digit Code:</label>
                        <input type="text" id="otp_code" name="otp_code" maxlength="6" pattern="\d{6}" required placeholder="000000" style="text-align: center; letter-spacing: 4px; font-size: 16px;">
                    </div>
                    
                    <section style="display: flex; justify-content: flex-end; gap: 6px; margin-top: 14px;">
                        <button type="submit" class="default">Verify & Create Account</button>
                        <a href="index.php?cancel_otp=1" style="text-decoration:none;"><button type="button">Cancel</button></a>
                    </section>
                </form>
            </fieldset>

        <?php else: ?>
            <section class="tabs">
                <menu role="tablist" aria-label="Authentication Options">
                    <button role="tab" id="tab-login" aria-controls="panel-login" aria-selected="<?= $activeTab === 'login' ? 'true' : 'false' ?>">Log In</button>
                    <button role="tab" id="tab-signup" aria-controls="panel-signup" aria-selected="<?= $activeTab === 'signup' ? 'true' : 'false' ?>">Sign Up</button>
                </menu>

                <article role="tabpanel" id="panel-login" <?= $activeTab !== 'login' ? 'hidden' : '' ?>>
                    <form method="POST" action="index.php?tab=login">
                        <input type="hidden" name="action" value="login">
                        
                        <div class="field-group">
                            <label for="login-email">Email Address:</label>
                            <input type="email" id="login-email" name="email" required>
                        </div>

                        <div class="field-group">
                            <label for="login-password">Password:</label>
                            <input type="password" id="login-password" name="password" required>
                        </div>

                        <section style="display: flex; justify-content: flex-end; gap: 6px; margin-top: 14px;">
                            <button type="submit" class="default">Log In</button>
                        </section>
                    </form>
                </article>

                <article role="tabpanel" id="panel-signup" <?= $activeTab !== 'signup' ? 'hidden' : '' ?>>
                    <form method="POST" action="index.php?tab=signup">
                        <input type="hidden" name="action" value="signup">

                        <div style="display: flex; gap: 8px;">
                            <div class="field-group" style="flex: 1;">
                                <label for="first_name">First Name:</label>
                                <input type="text" id="first_name" name="first_name" required>
                            </div>
                            <div class="field-group" style="flex: 1;">
                                <label for="last_name">Last Name:</label>
                                <input type="text" id="last_name" name="last_name" required>
                            </div>
                        </div>

                        <div class="field-group">
                            <label for="track">Intended Track:</label>
                            <select id="track" name="track" required>
                                <option value="" disabled selected>Select a track...</option>
                                <option value="biochem">Biochemistry</option>
                                <option value="physics">Physics</option>
                                <option value="earth">Earth Science</option>
                                <option value="build">Build</option>
                            </select>
                        </div>

                        <div class="field-group">
                            <label for="signup-email">Email Address:</label>
                            <input type="email" id="signup-email" name="email" required>
                        </div>

                        <div class="field-group">
                            <label for="signup-password">Password:</label>
                            <input type="password" id="signup-password" name="password" minlength="6" required>
                        </div>

                        <section style="display: flex; justify-content: flex-end; gap: 6px; margin-top: 14px;">
                            <button type="submit" class="default">Send Verification OTP</button>
                        </section>
                    </form>
                </article>
            </section>
        <?php endif; ?>

    </div>
</div>

<script>
    const tabs = document.querySelectorAll('[role="tab"]');
    const panels = document.querySelectorAll('[role="tabpanel"]');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.setAttribute('aria-selected', 'false'));
            panels.forEach(p => p.hidden = true);

            tab.setAttribute('aria-selected', 'true');
            const targetPanel = document.getElementById(tab.getAttribute('aria-controls'));
            if (targetPanel) {
                targetPanel.hidden = false;
            }

            const tooltips = document.querySelectorAll('[role="tooltip"]');
            tooltips.forEach(tooltip => {
                tooltip.style.display = 'none';
            });
        });
    });
</script>
</body>
</html>