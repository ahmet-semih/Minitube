<?php
// Author: Ahmet Semih Gümus
// feed.php — Homepage: shows subscribed videos, top channels, and user profile.

$servername = 'localhost';
$username   = 'root';
$password   = 'mysql';
$database   = 'ahmetsemih_gumus';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function daysAgo(string $datetime): int
{
    $uploadedAt = new DateTime($datetime);
    $now        = new DateTime('now');
    return (int) $now->diff($uploadedAt)->format('%a');
}

$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if ($userId <= 0) {
    header('Location: ../html/login.html');
    exit;
}

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    http_response_code(500);
    echo '<h1>Database error</h1><p>' . h($conn->connect_error) . '</p>';
    exit;
}
$conn->set_charset('utf8mb4');

// ── Fetch current user ──────────────────────────────────────────────────────
$userStmt = $conn->prepare(
    'SELECT user_id, username, full_name, country, joined_on, bio, user_image
     FROM USERS WHERE user_id = ? LIMIT 1'
);
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$user) {
    http_response_code(404);
    echo '<h1>User not found</h1>';
    $conn->close();
    exit;
}

// ── Fetch latest videos from subscribed channels ────────────────────────────
$videos     = [];
$videoStmt  = $conn->prepare(
    'SELECT
         v.video_id,
         v.title,
         v.uploaded_at,
         c.channel_id,
         c.name          AS channel_name,
         c.channel_image,
         u.country       AS uploader_country
     FROM SUBSCRIPTIONS s
     JOIN VIDEOS   v ON v.channel_id = s.channel_id
     JOIN CHANNELS c ON c.channel_id = v.channel_id
     JOIN USERS    u ON u.user_id    = c.owner_id
     WHERE s.subscriber_id = ?
     ORDER BY v.uploaded_at DESC, v.video_id DESC'
);
$videoStmt->bind_param('i', $userId);
$videoStmt->execute();
$videoResult = $videoStmt->get_result();
while ($row = $videoResult->fetch_assoc()) {
    $videos[] = $row;
}
$videoStmt->close();

// ── Fetch top 5 channels by subscriber count ────────────────────────────────
$popularChannels = [];
$popularStmt     = $conn->prepare(
    'SELECT
         c.channel_id,
         c.name,
         c.channel_image,
         COUNT(s.subscription_id) AS subscriber_count
     FROM CHANNELS c
     LEFT JOIN SUBSCRIPTIONS s ON s.channel_id = c.channel_id
     GROUP BY c.channel_id, c.name, c.channel_image
     ORDER BY subscriber_count DESC, c.channel_id ASC
     LIMIT 5'
);
$popularStmt->execute();
$popularResult = $popularStmt->get_result();
while ($row = $popularResult->fetch_assoc()) {
    $popularChannels[] = $row;
}
$popularStmt->close();

$userDisplayName = trim((string) ($user['full_name'] ?? ''));
if ($userDisplayName === '') {
    $userDisplayName = (string) $user['username'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Hello, <?php echo h($userDisplayName); ?>!</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #101827, #1f2937);
            color: #fff;
            padding: 24px;
        }
        .container {
            width: min(1200px, 100%);
            margin: 0 auto;
            display: grid;
            gap: 18px;
        }
        .card {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(10px);
            padding: 24px;
        }
        .layout {
            display: grid;
            grid-template-columns: minmax(0, 1.6fr) minmax(320px, 0.9fr);
            gap: 18px;
            align-items: start;
        }
        h1, h2 { margin: 0 0 14px; }
        h1 { font-size: 1.8rem; }
        h2 { font-size: 1.25rem; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            text-align: left;
            padding: 12px 10px;
            background: rgba(255, 255, 255, 0.12);
            color: #e5e7eb;
        }
        tbody td {
            padding: 11px 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            vertical-align: top;
        }
        .video-link, .channel-link {
            color: #93c5fd;
            text-decoration: none;
            font-weight: 700;
        }
        .video-link:hover, .channel-link:hover { text-decoration: underline; }
        .channel-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        .thumb {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            object-fit: cover;
            background: rgba(255, 255, 255, 0.14);
            flex: 0 0 auto;
        }
        .two-stack { display: grid; gap: 18px; }
        .popular-item {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            align-items: center;
        }
        .popular-item:first-child { border-top: 0; padding-top: 0; }
        dl.profile {
            display: grid;
            grid-template-columns: 150px minmax(0, 1fr);
            gap: 10px 12px;
            margin: 0;
        }
        dl.profile dt { color: #cbd5e1; font-weight: 700; }
        dl.profile dd { margin: 0; word-break: break-word; }
        .subtle { color: #d1d5db; }
        .nav { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 999px;
            padding: 11px 16px;
            background: #22c55e;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
        }
        .button.alt { background: rgba(255, 255, 255, 0.14); }
        .button:hover { background: #16a34a; }
        .button.alt:hover { background: rgba(255, 255, 255, 0.22); }
        @media (max-width: 900px) {
            .layout { grid-template-columns: 1fr; }
            dl.profile { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<main class="container">

    <!-- Header -->
    <section class="card">
        <div class="nav">
            <a class="button alt" href="../html/feed.html?user_id=<?php echo $userId; ?>">Refresh Feed</a>
            <a class="button alt" href="../php/sql.php?user_id=<?php echo $userId; ?>">SQL Console</a>
        </div>
        <h1>Hello, <?php echo h($userDisplayName); ?>!</h1>
        <p class="subtle">Latest videos from your subscribed channels, popular channels, and your profile.</p>
    </section>

    <!-- Main layout: videos (left) + sidebar (right) -->
    <section class="layout">

        <!-- Latest videos -->
        <section class="card">
            <h2>Latest Videos From Subscribed Channels</h2>
            <?php if (count($videos) === 0): ?>
                <p class="subtle">You are not subscribed to any channels yet. Browse the top channels on the right and subscribe!</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Channel</th>
                            <th>Country</th>
                            <th>Uploaded</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($videos as $video): ?>
                            <tr>
                                <td>
                                    <a class="video-link"
                                       href="../php/watch.php?user_id=<?php echo $userId; ?>&video_id=<?php echo (int) $video['video_id']; ?>">
                                        <?php echo h((string) $video['title']); ?>
                                    </a>
                                </td>
                                <td>
                                    <a class="channel-link channel-pill"
                                       href="../php/channel.php?user_id=<?php echo $userId; ?>&channel_id=<?php echo (int) $video['channel_id']; ?>">
                                        <img class="thumb"
                                             src="<?php echo h((string) $video['channel_image']); ?>"
                                             alt="<?php echo h((string) $video['channel_name']); ?>" />
                                        <span><?php echo h((string) $video['channel_name']); ?></span>
                                    </a>
                                </td>
                                <td><?php echo h((string) $video['uploader_country']); ?></td>
                                <td><?php echo daysAgo((string) $video['uploaded_at']); ?> days ago</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <!-- Sidebar -->
        <aside class="two-stack">

            <!-- Top channels -->
            <section class="card">
                <h2>Top Channels</h2>
                <?php if (count($popularChannels) === 0): ?>
                    <p class="subtle">No channels found.</p>
                <?php else: ?>
                    <?php foreach ($popularChannels as $popular): ?>
                        <div class="popular-item">
                            <a class="channel-link channel-pill"
                               href="../php/channel.php?user_id=<?php echo $userId; ?>&channel_id=<?php echo (int) $popular['channel_id']; ?>">
                                <img class="thumb"
                                     src="<?php echo h((string) $popular['channel_image']); ?>"
                                     alt="<?php echo h((string) $popular['name']); ?>" />
                                <span><?php echo h((string) $popular['name']); ?></span>
                            </a>
                            <span><?php echo (int) $popular['subscriber_count']; ?> subs</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

            <!-- User profile -->
            <section class="card">
                <h2>Your Profile</h2>
                <dl class="profile">
                    <dt>Full Name</dt>
                    <dd><?php echo h((string) $user['full_name']); ?></dd>
                    <dt>Username</dt>
                    <dd><?php echo h((string) $user['username']); ?></dd>
                    <dt>Country</dt>
                    <dd><?php echo h((string) $user['country']); ?></dd>
                    <dt>Joined</dt>
                    <dd><?php echo h((string) $user['joined_on']); ?></dd>
                    <dt>Bio</dt>
                    <dd><?php echo h((string) ($user['bio'] ?: '—')); ?></dd>
                </dl>
            </section>

        </aside>
    </section>

</main>
</body>
</html>