<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$pdo = getDB();

$stmtAuth = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmtAuth->execute([$_SESSION['user_id']]);
$currentUser = $stmtAuth->fetch(PDO::FETCH_ASSOC);

if (!$currentUser || $currentUser['role'] !== 'admin') {
    // non-admins get booted back to the main portal
    header("Location: index.php");
    exit;
}

$statusError = '';
$statusSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'create_medal') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $iconUrl = trim($_POST['icon_url'] ?? '');

        if (empty($title) || empty($iconUrl)) {
            $statusError = "Medal title and Icon URL are required.";
        } elseif (!filter_var($iconUrl, FILTER_VALIDATE_URL)) {
            $statusError = "Please enter a valid image URL for the icon.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO medals (title, description, icon_url) VALUES (?, ?, ?)");
            $stmt->execute([$title, $description, $iconUrl]);
            $statusSuccess = "New medal '$title' created successfully!";
        }
    }

    if ($_POST['action'] === 'award_medal') {
        $targetUserId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
        $medalId = filter_input(INPUT_POST, 'medal_id', FILTER_VALIDATE_INT);

        if (!$targetUserId || !$medalId) {
            $statusError = "Please select both a target user and a medal.";
        } else {
            // Check if user already has this exact medal
            $stmtCheck = $pdo->prepare("SELECT id FROM user_medals WHERE user_id = ? AND medal_id = ?");
            $stmtCheck->execute([$targetUserId, $medalId]);

            if ($stmtCheck->fetch()) {
                $statusError = "This user has already been awarded this medal!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO user_medals (user_id, medal_id, awarded_at) VALUES (?, ?, NOW())");
                $stmt->execute([$targetUserId, $medalId]);
                $statusSuccess = "Medal successfully awarded!";
            }
        }
    }
}

$users = $pdo->query("SELECT id, first_name, last_name, email, role FROM users ORDER BY last_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$medals = $pdo->query("SELECT id, title, description, icon_url FROM medals ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Award Medals</title>
    <link rel="stylesheet" href="https://unpkg.com/7.css">
    <link rel="stylesheet" href="styles/award_medal.css">
</head>
<body>

<div class="window active">
    <div class="title-bar">
        <div class="title-bar-text">Science Olympiad Award System</div>
        <div class="title-bar-controls">
            <button aria-label="Minimize"></button>
            <button aria-label="Maximize"></button>
            <a href="index.php" style="text-decoration:none;"><button aria-label="Close"></button></a>
        </div>
    </div>

    <div class="window-body has-space">

        <?php if ($statusError): ?>
            <div role="tooltip" class="balloon" style="display: flex; align-items: center; gap: 6px;">
                <img src="https://win98icons.alexmeub.com/icons/png/msg_error-2.png" alt="Error" style="width: 16px; height: 16px;">
                <span><?= htmlspecialchars($statusError) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($statusSuccess): ?>
            <div role="tooltip" class="balloon" style="display: flex; align-items: center; gap: 6px;">
                <img src="https://win98icons.alexmeub.com/icons/png/msg_information-2.png" alt="Info" style="width: 16px; height: 16px;">
                <span><?= htmlspecialchars($statusSuccess) ?></span>
            </div>
        <?php endif; ?>

        <fieldset style="margin-bottom: 14px;">
            <legend>Award Medal to Member</legend>
            <form method="POST" action="award_medal.php">
                <input type="hidden" name="action" value="award_medal">

                <div class="field-row">
                    <label for="user_id">Select Member:</label>
                    <select id="user_id" name="user_id" required>
                        <option value="">-- Choose User --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>">
                                <?= htmlspecialchars($u['last_name'] . ', ' . $u['first_name'] . ' (' . $u['email'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field-row">
                    <label for="medal_id">Select Medal:</label>
                    <select id="medal_id" name="medal_id" required>
                        <option value="">-- Choose Medal --</option>
                        <?php foreach ($medals as $m): ?>
                            <option value="<?= $m['id'] ?>">
                                <?= htmlspecialchars($m['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
                    <button type="submit" class="default">Award Medal</button>
                </div>
            </form>
        </fieldset>

        <fieldset>
            <legend>Create New Medal Category</legend>
            <form method="POST" action="award_medal.php">
                <input type="hidden" name="action" value="create_medal">

                <div class="field-row">
                    <label for="title">Medal Title:</label>
                    <input type="text" id="title" name="title" placeholder="e.g. 1st Place - Circuit Lab" required>
                </div>

                <div class="field-row">
                    <label for="description">Description:</label>
                    <input type="text" id="description" name="description" placeholder="e.g. Awarded for top performance at Regionals">
                </div>

                <div class="field-row">
                    <label for="icon_url">Icon Image URL:</label>
                    <input type="url" id="icon_url" name="icon_url" value="https://win98icons.alexmeub.com/icons/png/certificate-0.png" required>
                </div>

                <div style="display: flex; justify-content: flex-end; margin-top: 10px;">
                    <button type="submit">Create Medal</button>
                </div>
            </form>
        </fieldset>

        <section style="display: flex; justify-content: flex-end; gap: 6px; margin-top: 14px;">
            <a href="profile.php" style="text-decoration:none;">
                <button type="button">My Profile</button>
            </a>
            <a href="index.php" style="text-decoration:none;">
                <button type="button">Main Menu</button>
            </a>
        </section>

    </div>
</div>

</body>
</html>