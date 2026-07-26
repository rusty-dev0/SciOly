<?php
session_start();
require_once __DIR__ . '/db.php';

// get target user id from GET or default to whoever is logged in
$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$userId && isset($_SESSION['user_id'])) {
    $userId = (int)$_SESSION['user_id'];
}

if (!$userId) {
    header("Location: index.php");
    exit;
}

$pdo = getDB();
$profileError = '';
$profileSuccess = '';

// handle profile updates (if user is viewing their own profile)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $userId) {
    
    // update the bio
    if (isset($_POST['action']) && $_POST['action'] === 'update_bio') {
        $newBio = trim($_POST['bio'] ?? '');
        $wordCount = !empty($newBio) ? count(preg_split('/\s+/', $newBio)) : 0;
        
        if ($wordCount > 100) {
            $profileError = "Bio cannot exceed 100 words (currently $wordCount words).";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET bio = ? WHERE id = ?");
            $stmt->execute([$newBio, $userId]);
            $profileSuccess = "Bio updated successfully!";
        }
    }

    // update the pfp
    if (isset($_POST['action']) && $_POST['action'] === 'update_pfp') {
        $newAvatarUrl = trim($_POST['avatar_url'] ?? '');

        if (!empty($newAvatarUrl) && filter_var($newAvatarUrl, FILTER_VALIDATE_URL)) {
            $stmt = $pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
            $stmt->execute([$newAvatarUrl, $userId]);
            $profileSuccess = "Profile picture updated successfully!";
        } else {
            $profileError = "Please enter a valid Image URL (e.g., https://example.com/image.png).";
        }
    }
}

// fetch user info
$stmt = $pdo->prepare("SELECT id, first_name, last_name, email, role, track, bio, avatar_url FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

// fetch medals assigned to this user
$stmtMedals = $pdo->prepare("
    SELECT m.title, m.description, m.icon_url, um.awarded_at 
    FROM user_medals um
    JOIN medals m ON um.medal_id = m.id
    WHERE um.user_id = ?
    ORDER BY um.awarded_at DESC
");
$stmtMedals->execute([$userId]);
$medals = $stmtMedals->fetchAll(PDO::FETCH_ASSOC);

// format track labels nicely
$trackNames = [
    'biochem' => 'Biochemistry',
    'physics' => 'Physics',
    'earth'   => 'Earth Science',
    'build'   => 'Build'
];
$displayTrack = $trackNames[$user['track']] ?? ucfirst($user['track']);

// is the current logged-in user looking at their own profile?
$isOwner = isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $user['id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> - Profile</title>
    <link rel="stylesheet" href="https://unpkg.com/7.css">
    <link rel="stylesheet" href="styles/profile.css">
</head>
<body>

<div class="window active">
    <div class="title-bar">
        <div class="title-bar-text">User Profile - <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
        <div class="title-bar-controls">
            <button aria-label="Minimize"></button>
            <button aria-label="Maximize"></button>
            <a href="index.php" style="text-decoration:none;"><button aria-label="Close"></button></a>
        </div>
    </div>

    <div class="window-body has-space">

        <?php if ($profileError): ?>
            <div role="tooltip" class="balloon" style="display: flex; align-items: center; gap: 6px;">
                <img src="https://win98icons.alexmeub.com/icons/png/msg_error-2.png" alt="Error" style="width: 16px; height: 16px;">
                <span><?= htmlspecialchars($profileError) ?></span>
            </div>
        <?php endif; ?>
        <?php if ($profileSuccess): ?>
            <div role="tooltip" class="balloon" style="display: flex; align-items: center; gap: 6px;">
                <img src="https://win98icons.alexmeub.com/icons/png/msg_information-2.png" alt="Info" style="width: 16px; height: 16px;">
                <span><?= htmlspecialchars($profileSuccess) ?></span>
            </div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="avatar-container" <?= $isOwner ? 'onclick="togglePfpForm()"' : '' ?> title="<?= $isOwner ? 'Click to change profile picture' : '' ?>">
                <img src="<?= htmlspecialchars($user['avatar_url'] ?: 'https://win98icons.alexmeub.com/icons/png/user_card-0.png') ?>" alt="Profile Picture" class="avatar">
                
                <?php if ($isOwner): ?>
                    <div class="avatar-overlay">
                        Edit
                    </div>
                <?php endif; ?>
            </div>

            <div class="user-details" style="flex: 1;">
                <h2><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                <div class="meta-text" style="display: flex; align-items: center; gap: 4px;">
                    <img src="https://win98icons.alexmeub.com/icons/png/envelope_closed-0.png" alt="Email" style="width: 16px; height: 16px;">
                    <span><?= htmlspecialchars($user['email']) ?></span>
                </div>
                <div class="badge-bar">
                    <span class="badge <?= htmlspecialchars($user['role']) ?>"><?= htmlspecialchars($user['role']) ?></span>
                    <span class="badge track">Track: <?= htmlspecialchars($displayTrack) ?></span>
                </div>
            </div>
        </div>

        <?php if ($isOwner): ?>
            <fieldset id="pfp-form-section" style="display: none; margin-bottom: 12px;">
                <legend>Change Profile Picture</legend>
                <form method="POST" action="profile.php?id=<?= $userId ?>">
                    <input type="hidden" name="action" value="update_pfp">
                    <div style="display: flex; gap: 6px; align-items: center;">
                        <input type="url" name="avatar_url" placeholder="https://example.com/avatar.png" value="<?= htmlspecialchars($user['avatar_url'] ?? '') ?>" required style="flex: 1; font-size: 11px;">
                        <button type="submit" class="default">Save</button>
                        <button type="button" onclick="togglePfpForm()">Cancel</button>
                    </div>
                </form>
            </fieldset>
        <?php endif; ?>

        <fieldset style="margin-bottom: 12px;">
            <legend>Biography</legend>
            <?php if ($isOwner): ?>
                <form method="POST" action="profile.php?id=<?= $userId ?>">
                    <input type="hidden" name="action" value="update_bio">
                    <textarea id="bio-input" name="bio" placeholder="Write a short bio (max 100 words)..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px;">
                        <span class="word-counter" id="word-count">0 / 100 words</span>
                        <button type="submit" class="default">Save Bio</button>
                    </div>
                </form>
            <?php else: ?>
                <p style="margin: 0; font-size: 12px; font-style: <?= empty($user['bio']) ? 'italic' : 'normal' ?>;">
                    <?= !empty($user['bio']) ? nl2br(htmlspecialchars($user['bio'])) : 'No biography provided yet.' ?>
                </p>
            <?php endif; ?>
        </fieldset>

        <fieldset>
            <legend>Medals & Honors (<?= count($medals) ?>)</legend>
            <?php if (empty($medals)): ?>
                <p style="font-size: 11px; color: #666; margin: 4px 0; font-style: italic;">No medals awarded yet.</p>
            <?php else: ?>
                <div class="medals-grid">
                    <?php foreach ($medals as $medal): ?>
                        <div class="medal-card">
                            <img src="<?= htmlspecialchars($medal['icon_url']) ?>" class="medal-icon" alt="Medal">
                            <div class="medal-info">
                                <h4><?= htmlspecialchars($medal['title']) ?></h4>
                                <p><?= htmlspecialchars($medal['description']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </fieldset>

        <section style="display: flex; justify-content: flex-end; gap: 6px; margin-top: 12px;">
            <?php if ($isOwner): ?>
                <a href="logout.php" style="text-decoration:none;">
                    <button type="button">Log Out</button>
                </a>
            <?php endif; ?>
            <a href="index.php" style="text-decoration:none;">
                <button type="button">Back</button>
            </a>
        </section>

    </div>
</div>

<script>
    // live word counter for bio
    const bioInput = document.getElementById('bio-input');
    const wordCountDisplay = document.getElementById('word-count');

    if (bioInput) {
        function updateCount() {
            const text = bioInput.value.trim();
            const words = text ? text.split(/\s+/).length : 0;
            wordCountDisplay.textContent = `${words} / 100 words`;

            if (words > 100) {
                wordCountDisplay.style.color = '#d32f2f';
                wordCountDisplay.style.fontWeight = 'bold';
            } else {
                wordCountDisplay.style.color = '#666';
                wordCountDisplay.style.fontWeight = 'normal';
            }
        }

        bioInput.addEventListener('input', updateCount);
        updateCount();
    }

    // toggle avatar selector
    function togglePfpForm() {
        const pfpSection = document.getElementById('pfp-form-section');
        if (pfpSection) {
            pfpSection.style.display = pfpSection.style.display === 'none' ? 'block' : 'none';
        }
    }
</script>
</body>
</html>