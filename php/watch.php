<?php
$servername = 'localhost';
$username = 'root';
$password = 'mysql';
$database = 'ahmetsemih_gumus';

function h(string $value): string
{
		return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatDuration(int $seconds): string
{
		$hours = intdiv($seconds, 3600);
		$minutes = intdiv($seconds % 3600, 60);
		$remainingSeconds = $seconds % 60;

		if ($hours > 0) {
				return sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds);
		}

		return sprintf('%d:%02d', $minutes, $remainingSeconds);
}

	function youtubeEmbedUrl(string $url): string
	{
		if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $matches)) {
			return 'https://www.youtube.com/embed/' . $matches[1];
		}

		return $url;
	}

$userId = isset($_REQUEST['user_id']) ? (int) $_REQUEST['user_id'] : 0;
$videoId = isset($_REQUEST['video_id']) ? (int) $_REQUEST['video_id'] : 0;

if ($userId <= 0 || $videoId <= 0) {
		http_response_code(400);
		echo '<h1>Missing parameters</h1>';
		echo '<p>Both user_id and video_id are required.</p>';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comment_body'])) {
		$commentBody = trim((string) $_POST['comment_body']);
		if ($commentBody !== '') {
				$insertStmt = $conn->prepare('INSERT INTO COMMENTS (video_id, user_id, parent_comment_id, body, posted_at) VALUES (?, ?, NULL, ?, NOW())');
				if ($insertStmt) {
						$insertStmt->bind_param('iis', $videoId, $userId, $commentBody);
						$insertStmt->execute();
						$insertStmt->close();
				}
		}

		header('Location: watch.php?user_id=' . $userId . '&video_id=' . $videoId);
		exit;
}

$viewStmt = $conn->prepare('UPDATE VIDEOS SET view_count = view_count + 1 WHERE video_id = ?');
if ($viewStmt) {
		$viewStmt->bind_param('i', $videoId);
		$viewStmt->execute();
		$viewStmt->close();
}

$videoStmt = $conn->prepare(
		'SELECT
				v.video_id,
				v.title,
				v.description,
				v.url,
				v.duration_seconds,
				v.uploaded_at,
				v.view_count,
				c.channel_id,
				c.name AS channel_name,
				u.country AS uploader_country,
				CASE
						WHEN v.view_count >= 1000 THEN "Popular"
						WHEN v.view_count >= 100 THEN "Trending"
						ELSE "New"
				END AS popularity_badge
		 FROM VIDEOS v
		 JOIN CHANNELS c ON c.channel_id = v.channel_id
		 JOIN USERS u ON u.user_id = c.owner_id
		 WHERE v.video_id = ?
		 LIMIT 1'
);

if (!$videoStmt) {
		http_response_code(500);
		echo '<h1>Query error</h1>';
		echo '<p>' . h($conn->error) . '</p>';
		$conn->close();
		exit;
}

$videoStmt->bind_param('i', $videoId);
$videoStmt->execute();
$videoResult = $videoStmt->get_result();
$video = $videoResult ? $videoResult->fetch_assoc() : null;
$videoStmt->close();

if (!$video) {
		http_response_code(404);
		echo '<h1>Video not found</h1>';
		$conn->close();
		exit;
}

$comments = [];
$commentStmt = $conn->prepare(
		'SELECT
				c.comment_id,
				c.video_id,
				c.user_id,
				c.parent_comment_id,
				c.body,
				c.posted_at,
				u.full_name AS author_name,
				p.comment_id AS parent_match_id,
				p.posted_at AS parent_posted_at,
				p.body AS parent_body,
				pu.full_name AS parent_author_name
		 FROM COMMENTS c
		 LEFT JOIN COMMENTS p ON p.comment_id = c.parent_comment_id
		 LEFT JOIN USERS u ON u.user_id = c.user_id
		 LEFT JOIN USERS pu ON pu.user_id = p.user_id
		 WHERE c.video_id = ?
		  ORDER BY
			COALESCE(p.posted_at, c.posted_at) DESC,
			  CASE WHEN c.parent_comment_id IS NULL THEN 0 ELSE 1 END,
				c.posted_at ASC,
				c.comment_id ASC'
);

if ($commentStmt) {
		$commentStmt->bind_param('i', $videoId);
		$commentStmt->execute();
		$commentResult = $commentStmt->get_result();
		if ($commentResult) {
				while ($row = $commentResult->fetch_assoc()) {
						$comments[] = $row;
				}
		}
		$commentStmt->close();
}

$groupedComments = [];
foreach ($comments as $comment) {
		if ($comment['parent_comment_id'] === null) {
				$groupedComments[$comment['comment_id']] = [
						'top' => $comment,
						'replies' => []
				];
		}
}

foreach ($comments as $comment) {
		if ($comment['parent_comment_id'] !== null && isset($groupedComments[(int) $comment['parent_comment_id']])) {
				$groupedComments[(int) $comment['parent_comment_id']]['replies'][] = $comment;
		}
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title><?php echo h((string) $video['title']); ?></title>
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
		h1, h2 { margin: 0 0 14px; }
		h1 { font-size: 1.9rem; }
		h2 { font-size: 1.3rem; }
		.button {
			display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 999px;
			padding: 11px 16px; background: #22c55e; color: #fff; text-decoration: none; font-weight: 700; cursor: pointer;
		}
		.button:hover { background: #16a34a; }
		.button.alt { background: rgba(255,255,255,0.14); }
		.button.alt:hover { background: rgba(255,255,255,0.22); }
		.top-row { display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: center; }
		.layout {
			display: grid;
			grid-template-columns: minmax(0, 1.45fr) minmax(300px, 0.85fr);
			gap: 18px;
			align-items: start;
		}
		.video-frame {
			width: 100%;
			aspect-ratio: 16 / 9;
			border: 0;
			border-radius: 16px;
			background: #000;
		}
		.meta-grid {
			display: grid;
			grid-template-columns: 180px minmax(0, 1fr);
			gap: 10px 14px;
			margin-top: 16px;
		}
		.meta-grid dt { color: #cbd5e1; font-weight: 700; }
		.meta-grid dd { margin: 0; word-break: break-word; }
		.channel-link {
			color: #93c5fd;
			text-decoration: none;
			font-weight: 700;
		}
		.channel-link:hover { text-decoration: underline; }
		.badge {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 5px 10px;
			border-radius: 999px;
			background: rgba(255,255,255,0.14);
			font-weight: 700;
		}
		.comment { padding: 14px 0; border-top: 1px solid rgba(255,255,255,0.12); }
		.comment:first-child { border-top: 0; padding-top: 0; }
		.reply {
			margin-left: 24px;
			border-left: 2px solid rgba(255,255,255,0.14);
			padding-left: 14px;
			margin-top: 10px;
		}
		.comment-meta { color: #cbd5e1; font-size: 0.92rem; margin-bottom: 6px; }
		.comment-body { white-space: pre-wrap; line-height: 1.5; }
		textarea {
			width: 100%;
			min-height: 120px;
			resize: vertical;
			border-radius: 14px;
			border: 1px solid rgba(255,255,255,0.2);
			background: rgba(0,0,0,0.2);
			color: #fff;
			padding: 12px;
			font: inherit;
		}
		.subtle { color: #d1d5db; }
		@media (max-width: 900px) {
			.layout { grid-template-columns: 1fr; }
			.meta-grid { grid-template-columns: 1fr; }
		}
	</style>
</head>
<body>
	<main class="container">
		<section class="card">
			<div class="top-row">
				<h1><?php echo h((string) $video['title']); ?></h1>
				<a class="button alt" href="../php/feed.php?user_id=<?php echo $userId; ?>">Back to Feed</a>
				<a class="button" href="<?php echo h((string) $video['url']); ?>" target="_blank" rel="noopener noreferrer">Open Original Video</a>
			</div>
		</section>

		<section class="layout">
			<section class="card">
				<iframe class="video-frame" src="<?php echo h(youtubeEmbedUrl((string) $video['url'])); ?>" title="Video player" allowfullscreen></iframe>
				<dl class="meta-grid">
					<dt>Channel</dt>
					<dd>
						<a class="channel-link" href="../php/channel.php?user_id=<?php echo $userId; ?>&channel_id=<?php echo (int) $video['channel_id']; ?>">
							<?php echo h((string) $video['channel_name']); ?>
						</a>
					</dd>
					<dt>Uploader Country</dt>
					<dd><?php echo h((string) $video['uploader_country']); ?></dd>
					<dt>Duration</dt>
					<dd><?php echo h(formatDuration((int) $video['duration_seconds'])); ?></dd>
					<dt>Upload Date</dt>
					<dd><?php echo h((string) $video['uploaded_at']); ?></dd>
					<dt>View Count</dt>
					<dd><?php echo (int) $video['view_count']; ?></dd>
					<dt>Popularity</dt>
					<dd><span class="badge"><?php echo h((string) $video['popularity_badge']); ?></span></dd>
				</dl>
				<p class="subtle" style="margin-top:16px;"><?php echo h((string) ($video['description'] ?? '')); ?></p>
			</section>

			<aside class="card">
				<h2>Comments</h2>
				<?php if (count($groupedComments) === 0): ?>
					<p class="subtle">No comments yet.</p>
				<?php else: ?>
					<?php foreach ($groupedComments as $bundle): ?>
						<div class="comment">
							<div class="comment-meta">
								<?php echo h((string) ($bundle['top']['author_name'] ?? 'Unknown')); ?> · <?php echo h((string) $bundle['top']['posted_at']); ?>
							</div>
							<div class="comment-body"><?php echo h((string) $bundle['top']['body']); ?></div>

							<?php foreach ($bundle['replies'] as $reply): ?>
								<div class="reply">
									<div class="comment-meta">
										<?php echo h((string) ($reply['author_name'] ?? 'Unknown')); ?> · <?php echo h((string) $reply['posted_at']); ?>
									</div>
									<div class="comment-body"><?php echo h((string) $reply['body']); ?></div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>

				<div class="comment" style="margin-top:18px;">
					<h2>Post a Comment</h2>
					<form method="post" action="watch.php">
						<input type="hidden" name="user_id" value="<?php echo $userId; ?>" />
						<input type="hidden" name="video_id" value="<?php echo $videoId; ?>" />
						<textarea name="comment_body" required placeholder="Write your comment..."></textarea>
						<div style="margin-top:12px;">
							<button class="button" type="submit">Submit</button>
						</div>
					</form>
				</div>
			</aside>
		</section>
	</main>
</body>
</html>
<?php
$conn->close();
?>
