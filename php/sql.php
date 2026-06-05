<?php
// MiniTube SQL console.

$servername = 'localhost';
$username = 'root';
$password = 'mysql';
$database = 'ahmetsemih_gumus';

function renderSqlPage(string $query = '', ?string $message = null, array $tableRows = [], array $columns = [], string $resultType = '', string $resultSummary = ''): never
{
	$escapedQuery = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');
	$escapedMessage = $message !== null ? htmlspecialchars($message, ENT_QUOTES, 'UTF-8') : '';
	$hasTable = !empty($tableRows) && !empty($columns);

	echo '<!DOCTYPE html>';
	echo '<html lang="en">';
	echo '<head>';
	echo '<meta charset="UTF-8" />';
	echo '<meta name="viewport" content="width=device-width, initial-scale=1.0" />';
	echo '<title>MiniTube SQL</title>';
	echo '<style>
	  :root { color-scheme: dark; }
	  * { box-sizing: border-box; }
	  body {
	    margin: 0;
	    min-height: 100vh;
	    font-family: Arial, sans-serif;
	    color: #f3f4f6;
	    background:
	      radial-gradient(circle at top left, rgba(59, 130, 246, 0.22), transparent 28%),
	      radial-gradient(circle at top right, rgba(34, 197, 94, 0.16), transparent 24%),
	      linear-gradient(135deg, #0b1220, #111827 55%, #0f172a);
	  }
	  .shell {
	    width: min(1200px, calc(100vw - 32px));
	    margin: 24px auto;
	    display: grid;
	    gap: 20px;
	    grid-template-columns: 1fr;
	  }
	  .card {
	    border-radius: 20px;
	    padding: 24px;
	    background: rgba(255, 255, 255, 0.08);
	    border: 1px solid rgba(255, 255, 255, 0.12);
	    box-shadow: 0 24px 70px rgba(0, 0, 0, 0.32);
	    backdrop-filter: blur(14px);
	  }
	  .eyebrow {
	    margin: 0 0 8px;
	    color: #93c5fd;
	    text-transform: uppercase;
	    letter-spacing: 0.14em;
	    font-size: 0.8rem;
	  }
	  h1, h2 {
	    margin: 0 0 12px;
	  }
	  p {
	    margin: 0 0 16px;
	    color: #cbd5e1;
	    line-height: 1.5;
	  }
	  label {
	    display: block;
	    margin-bottom: 10px;
	    font-weight: 700;
	  }
	  textarea {
	    width: 100%;
	    min-height: 180px;
	    resize: vertical;
	    padding: 16px;
	    border-radius: 16px;
	    border: 1px solid rgba(255, 255, 255, 0.14);
	    background: rgba(15, 23, 42, 0.82);
	    color: #fff;
	    font: inherit;
	    outline: none;
	  }
	  textarea:focus {
	    border-color: #60a5fa;
	    box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.18);
	  }
	  .actions {
	    margin-top: 14px;
	    display: flex;
	    gap: 12px;
	    flex-wrap: wrap;
	  }
	  .button {
	    display: inline-flex;
	    align-items: center;
	    justify-content: center;
	    padding: 14px 20px;
	    border-radius: 999px;
	    border: 0;
	    background: #22c55e;
	    color: #fff;
	    font-weight: 700;
	    text-decoration: none;
	    cursor: pointer;
	  }
	  .button:hover { background: #16a34a; }
	  .secondary {
	    background: rgba(255, 255, 255, 0.12);
	  }
	  .secondary:hover {
	    background: rgba(255, 255, 255, 0.18);
	  }
	  .notice {
	    margin-top: 16px;
	    padding: 12px 14px;
	    border-radius: 12px;
	    background: rgba(59, 130, 246, 0.16);
	    border: 1px solid rgba(59, 130, 246, 0.28);
	    color: #dbeafe;
	  }
	  .error {
	    background: rgba(239, 68, 68, 0.16);
	    border-color: rgba(239, 68, 68, 0.28);
	    color: #fecaca;
	  }
	  .result-meta {
	    margin-bottom: 14px;
	    color: #cbd5e1;
	  }
	  .table-wrap {
	    overflow-x: auto;
	    border-radius: 16px;
	    border: 1px solid rgba(255, 255, 255, 0.12);
	  }
	  table {
	    width: 100%;
	    border-collapse: collapse;
	    min-width: 720px;
	    background: rgba(15, 23, 42, 0.74);
	  }
	  th, td {
	    padding: 12px 14px;
	    text-align: left;
	    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
	    vertical-align: top;
	    word-break: break-word;
	  }
	  th {
	    position: sticky;
	    top: 0;
	    background: rgba(30, 41, 59, 0.95);
	    color: #bfdbfe;
	  }
	  .empty {
	    color: #cbd5e1;
	    font-style: italic;
	  }
	</style>';
	echo '</head>';
	echo '<body>';
	echo '<main class="shell">';
	echo '<section class="card">';
	echo '<p class="eyebrow">MiniTube</p>';
	echo '<h1>SQL Console</h1>';
	echo '<p>Type any SQL query for the current database and run it on this page. SELECT-style queries show a table; other statements show an execution summary.</p>';
	echo '<form method="post" action="sql.php">';
	echo '<label for="query">SQL Query</label>';
	echo '<textarea id="query" name="query" spellcheck="false" placeholder="SELECT * FROM VIDEOS LIMIT 10;">' . $escapedQuery . '</textarea>';
	echo '<div class="actions">';
	echo '<button class="button" type="submit">Execute Query</button>';
	echo '<a class="button secondary" href="../html/index.html">Back to start</a>';
	echo '</div>';
	echo '</form>';

	if ($message !== null) {
		$noticeClass = $resultType === 'error' ? 'notice error' : 'notice';
		echo '<div class="' . $noticeClass . '">' . $escapedMessage . '</div>';
	}

	if ($resultSummary !== '') {
		echo '<p class="result-meta">' . htmlspecialchars($resultSummary, ENT_QUOTES, 'UTF-8') . '</p>';
	}

	echo '</section>';

	if ($hasTable) {
		echo '<section class="card">';
		echo '<h2>Result</h2>';
		echo '<div class="table-wrap">';
		echo '<table>';
		echo '<thead><tr>';
		foreach ($columns as $column) {
			echo '<th>' . htmlspecialchars((string) $column, ENT_QUOTES, 'UTF-8') . '</th>';
		}
		echo '</tr></thead>';
		echo '<tbody>';
		foreach ($tableRows as $row) {
			echo '<tr>';
			foreach ($columns as $column) {
				$value = $row[$column] ?? '';
				if ($value === null) {
					$value = 'NULL';
				} elseif (is_bool($value)) {
					$value = $value ? '1' : '0';
				} elseif (!is_scalar($value)) {
					$value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				}
				echo '<td>' . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody>';
		echo '</table>';
		echo '</div>';
		echo '</section>';
	} elseif ($message !== null && $resultType !== 'error' && $resultSummary === '') {
		echo '<section class="card"><p class="empty">No tabular result to display.</p></section>';
	}

	echo '</main>';
	echo '<script>document.getElementById("query").focus();</script>';
	echo '</body>';
	echo '</html>';
	exit;
}

$query = trim($_POST['query'] ?? '');

if ($query === '') {
	renderSqlPage();
}

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
	renderSqlPage($query, 'Database connection failed: ' . $conn->connect_error, [], [], 'error');
}

$conn->set_charset('utf8mb4');

$result = $conn->query($query);

if ($result === false) {
	renderSqlPage($query, 'Query error: ' . $conn->error, [], [], 'error');
}

if ($result === true) {
	$affectedRows = $conn->affected_rows;
	$summary = 'Statement executed successfully. Affected rows: ' . $affectedRows . '.';
	renderSqlPage($query, $summary, [], [], 'success', $summary);
}

$rows = [];
$columns = [];
while ($field = $result->fetch_field()) {
	$columns[] = $field->name;
}

while ($row = $result->fetch_assoc()) {
	$rows[] = $row;
}

$rowCount = count($rows);
$summary = $rowCount . ' row' . ($rowCount === 1 ? '' : 's') . ' returned.';

$result->free();
$conn->close();

renderSqlPage($query, null, $rows, $columns, 'success', $summary);
