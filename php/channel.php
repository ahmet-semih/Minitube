<?php
// Author: Ahmet Semih Gümus
// channel.php — Shows channel info, videos, and handles subscribe/unsubscribe.

$servername = 'localhost';
$username   = 'root';
$password   = 'mysql';
$database   = 'ahmetsemih_gumus';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatDuration(int $seconds): string
{
    $hours            = intdiv($seconds, 3600);
    $minutes          = intdiv($seconds % 3600, 60);
    $remainingSeconds = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }

    return sprintf('%d:%02d', $minutes, $remainingSeconds);
}

$userId    = isset($_REQUEST['user_id'])    ? (int) $_REQUEST['user_id']    : 0;
$channelId = isset($_REQUEST['channel_id']) ? (int) $_REQUEST['channel_id'] : 0;

if ($userId <= 0 || $channelId <= 0) {
    http_response_code(400);
    echo '<h1>Missing parameters</h1>';
    echo '<p>Both user_id and channel_id are required.</p>';
    exit;
}

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    http_response_code(500);
    echo '<h1>Database error</h1>';
    echo '<p>' . h($conn->connect_error) . '</p>';
    exit;
}

$conn->set_charset('utf8mb4');

// ── Handle subscribe / unsubscribe POST ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscription_action'])) {
    $action = $_POST['subscription_action'];

    if ($action === 'subscribe') {
        $stmt = $conn->prepare(
            'INSERT INTO SUBSCRIPTIONS (subscriber_id, channel_id, subscribed_at)
             SELECT ?, ?, NOW()
             WHERE NOT EXISTS (
                 SELECT 1 FROM SUBSCRIPTIONS WHERE subscriber_id = ? AND channel_id = ?
             )'
        );
        if ($stmt) {
            $stmt->bind_param('iiii', $userId, $channelId, $userId, $channelId);
            $stmt->execute();
            $stmt->close();
        }
    } elseif ($action === 'unsubscribe') {
        $stmt = $conn->prepare(
            'DELETE FROM SUBSCRIPTIONS WHERE subscriber_id = ? AND channel_id = ?'
        );
        if ($stmt) {
            $stmt->bind_param('ii', $userId, $channelId);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Redirect back to channel.php (re-render with updated state)
    header('Location: channel.php?user_id=' . $userId . '&channel_id=' . $channelId);
    exit;
}

// ── Fetch channel info ───────────────────────────────────────────────────────
$channelStmt = $conn->prepare(
    'SELECT
         c.channel_id,
         c.name,
         c.channel_image,
         c.category,
         c.description,
         c.created_on,
         u.full_name AS owner_full_name,
         u.country   AS owner_country,
         COUNT(s.subscription_id) AS subscriber_count
     FROM CHANNELS c
     JOIN USERS u ON u.user_id = c.owner_id
     LEFT JOIN SUBSCRIPTIONS s ON s.channel_id = c.channel_id
     WHERE c.channel_id = ?
     GROUP BY c.channel_id, c.name, c.channel_image, c.category,
              c.description, c.created_on, u.full_name, u.country'
);

if (!$channelStmt) {
    http_response_code(500);
    echo '<h1>Query error</h1>';
    echo '<p>' . h($conn->error) . '</p>';
    $conn->close();
    exit;
}

$channelStmt->bind_param('i', $channelId);
$channelStmt->execute();
$channel = $channelStmt->get_result()->fetch_assoc();
$channelStmt->close();

if (!$channel) {
    http_response_code(404);
    echo '<h1>Channel not found</h1>';
    $conn->close();
    exit;
}

// ── Check if current user is subscribed ─────────────────────────────────────
$isSubscribed = false;
$subStmt = $conn->prepare(
    'SELECT 1 FROM SUBSCRIPTIONS WHERE subscriber_id = ? AND channel_id = ? LIMIT 1'
);
if ($subStmt) {
    $subStmt->bind_param('ii', $userId, $channelId);
    $subStmt->execute();
    $subResult    = $subStmt->get_result();
    $isSubscribed = ($subResult && $subResult->num_rows > 0);
    $subStmt->close();
}

// ── Fetch channel videos ─────────────────────────────────────────────────────
$videos    = [];
$videoStmt = $conn->prepare(
    'SELECT video_id, title, duration_seconds, uploaded_at, view_count
     FROM VIDEOS
     WHERE channel_id = ?
     ORDER BY uploaded_at DESC, video_id DESC'
);
if ($videoStmt) {
    $videoStmt->bind_param('i', $channelId);
    $videoStmt->execute();
    $videoResult = $videoStmt->get_result();
    while ($row = $videoResult->fetch_assoc()) {
        $videos[] = $row;
    }
    $videoStmt->close();
}

$description = trim((string) ($channel['description'] ?? ''));
if ($description === '') {
    $description = '(no description)';
}

$subscriptionAction = $isSubscribed ? 'unsubscribe' : 'subscribe';
$subscriptionLabel  = $isSubscribed ? 'Unsubscribe'  : 'Subscribe';

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo h((string) $channel['name']); ?> | MiniTube</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #101827, #1f2937);
            color: #fff;
            min-height: 100vh;
            padding: 24px;
        }
        .container {
            width: min(1100px, 100%);
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
        .top-row { display: flex; gap: 18px; flex-wrap: wrap; justify-content: space-between; align-items: center; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .button {
            display: inline-flex; align-items: center; justify-content: center;
            border: 0; border-radius: 999px; padding: 12px 18px; text-decoration: none;
            cursor: pointer; font-weight: 700; color: #fff; background: #22c55e;
            transition: transform 0.15s ease, background 0.15s ease; font-size: 0.95rem;
        }
        .button:hover { background: #16a34a; transform: translateY(-2px); }
        .button.alt { background: rgba(255, 255, 255, 0.16); }
        .button.alt:hover { background: rgba(255, 255, 255, 0.24); }
        .button.danger { background: #ef4444; }
        .button.danger:hover { background: #dc2626; }
        h1 { margin: 0; font-size: 1.9rem; }
        h2 { margin: 0 0 14px; font-size: 1.3rem; }
        .channel-header {
            display: grid;
            grid-template-columns: 240px minmax(0, 1fr);
            gap: 20px;
            align-items: start;
        }
        .channel-image {
            width: 100%; aspect-ratio: 16 / 9; object-fit: cover;
            border-radius: 14px; background: rgba(255, 255, 255, 0.12);
        }
        .meta-grid {
            margin-top: 12px;
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr);
            gap: 10px 14px;
        }
        .meta-grid dt { color: #cbd5e1; font-weight: 700; }
        .meta-grid dd { margin: 0; color: #f8fafc; word-break: break-word; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: rgba(255, 255, 255, 0.13);
            text-align: left; padding: 12px 10px;
            color: #e5e7eb; font-size: 0.95rem;
        }
        tbody td {
            padding: 11px 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            color: #f9fafb; vertical-align: top;
        }
        .video-link { color: #93c5fd; text-decoration: none; font-weight: 700; }
        .video-link:hover { text-decoration: underline; }
        .muted { color: #d1d5db; }
        @media (max-width: 800px) {
            .channel-header { grid-template-columns: 1fr; }
            .meta-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<main class="container">

    <!-- Top bar -->
    <section class="card">
        <div class="top-row">
            <h1><?php echo h((string) $channel['name']); ?></h1>
            <div class="actions">
                <form action="channel.php" method="post">
                    <input type="hidden" name="user_id"             value="<?php echo $userId; ?>" />
                    <input type="hidden" name="channel_id"          value="<?php echo $channelId; ?>" />
                    <input type="hidden" name="subscription_action" value="<?php echo h($subscriptionAction); ?>" />
                    <button class="button <?php echo $isSubscribed ? 'danger' : ''; ?>" type="submit">
                        <?php echo h($subscriptionLabel); ?>
                    </button>
                </form>
                <a class="button alt" href="../php/feed.php?user_id=<?php echo $userId; ?>">Back to Feed</a>
            </div>
        </div>
    </section>

    <!-- Channel info -->
    <section class="card channel-header">
        <img class="channel-image"
             src="<?php echo h((string) $channel['channel_image']); ?>"
             alt="<?php echo h((string) $channel['name']); ?>" />
        <div>
            <h2>Channel Info</h2>
            <dl class="meta-grid">
                <dt>Category</dt>
                <dd><?php echo h((string) $channel['category']); ?></dd>
                <dt>Owner</dt>
                <dd><?php echo h((string) $channel['owner_full_name']); ?></dd>
                <dt>Owner Country</dt>
                <dd><?php echo h((string) $channel['owner_country']); ?></dd>
                <dt>Created On</dt>
                <dd><?php echo h((string) $channel['created_on']); ?></dd>
                <dt>Subscribers</dt>
                <dd><?php echo (int) $channel['subscriber_count']; ?></dd>
                <dt>Description</dt>
                <dd><?php echo h($description); ?></dd>
            </dl>
        </div>
    </section>

    <!-- Videos -->
    <section class="card">
        <h2>Channel Videos</h2>
        <?php if (count($videos) === 0): ?>
            <p class="muted">This channel has no videos yet.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Duration</th>
                        <th>Upload Date</th>
                        <th>Views</th>
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
                            <td><?php echo h(formatDuration((int) $video['duration_seconds'])); ?></td>
                            <td><?php echo h((string) $video['uploaded_at']); ?></td>
                            <td><?php echo (int) $video['view_count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

</main>
</body>
</html>