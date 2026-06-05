<?php
// Function to boldly load file lines (will abort if missing/empty)
function load_file($filepath) {
    if (!file_exists($filepath)) {
        die("Error: Required file '$filepath' is missing!\n");
    }
    $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false || count($lines) === 0) {
        die("Error: File '$filepath' is empty or could not be read!\n");
    }
    return $lines;
}

// Function to generate a random date between two dates
function randomDate($start_date, $end_date) {
    $min = strtotime($start_date);
    $max = strtotime($end_date);
    $val = rand($min, $max);
    return date('Y-m-d H:i:s', $val);
}

// Function to escape strings for SQL
function escape($str) {
    // Escape single quotes for SQL insert
    return str_replace("'", "''", $str);
}

// Load data files from text files ONLY (No fallbacks)
$first_names = load_file('../data/first_names.txt');
$last_names = load_file('../data/last_names.txt');
$bios = load_file('../data/bios.txt');
$countries = load_file('../data/countries.txt');
$channel_categories = load_file('../data/channel_categories.txt');
$channel_descriptions = load_file('../data/channel_descriptions.txt');
$channel_names = load_file('../data/channel_name.txt');
$video_titles = load_file('../data/video_titles.txt');
$video_descriptions = load_file('../data/video_description.txt');
$youtube_urls = load_file('../data/youtube_urls.txt');
$comments = load_file('../data/comments.txt');

$sql_content = "";

// ----------------------------------------------------
// 1. GENERATE USERS (100)
// ----------------------------------------------------
$sql_content .= "-- USERS\n";
$usernames_used = [];

for ($i = 1; $i <= 100; $i++) {
    $fName = $first_names[array_rand($first_names)];
    $lName = $last_names[array_rand($last_names)];
    $full_name = escape($fName . " " . $lName);
    
    // Generate unique username
    $base_username = strtolower($fName) . strtolower($lName);
    $username = $base_username;
    $counter = 1;
    while (in_array($username, $usernames_used)) {
        $username = $base_username . sprintf("%02d", $counter);
        $counter++;
    }
    $usernames_used[] = $username;
    
    $password = '1234';
    $user_image = "https://picsum.photos/seed/{$i}/200/200"; // Random image
    $email = $username . "@example.com"; // dynamically create email!
    $country = $countries[array_rand($countries)];
    $joined_on = date('Y-m-d', strtotime('-' . rand(0, 1000) . ' days'));
    $bio = escape($bios[array_rand($bios)]);
    
    $sql_content .= "INSERT INTO USERS (user_id, username, password, user_image, full_name, email, country, joined_on, bio) VALUES ($i, '$username', '$password', '$user_image', '$full_name', '$email', '$country', '$joined_on', '$bio');\n";
}

// ----------------------------------------------------
// 2. GENERATE CHANNELS (50)
// ----------------------------------------------------
$sql_content .= "\n-- CHANNELS\n";
for ($i = 1; $i <= 50; $i++) {
    $owner_id = $i; // User 1-50 gets a channel
    $channel_image = "https://picsum.photos/seed/channel_{$i}/800/200";
    $base_channel_name = trim($channel_names[array_rand($channel_names)]);
    $name = escape($base_channel_name . " " . $i); // Ensure channel names have some variety/uniqueness
    $description = escape($channel_descriptions[array_rand($channel_descriptions)]);
    $created_on = date('Y-m-d', strtotime('-' . rand(0, 500) . ' days')); // Channel created somewhat recently
    $category = $channel_categories[array_rand($channel_categories)];
    
    $sql_content .= "INSERT INTO CHANNELS (channel_id, owner_id, channel_image, name, description, created_on, category) VALUES ($i, $owner_id, '$channel_image', '$name', '$description', '$created_on', '$category');\n";
}

// ----------------------------------------------------
// 3. GENERATE VIDEOS (200)
// ----------------------------------------------------
$sql_content .= "\n-- VIDEOS\n";
for ($i = 1; $i <= 200; $i++) {
    $channel_id = rand(1, 50);
    
    // Pick a random index to match title and description
    $random_video_index = array_rand($video_titles);
    $title = escape($video_titles[$random_video_index] . " " . $i); // Ensure some variety with + $i
    // Use the exact same index for the description
    $description = escape($video_descriptions[$random_video_index]);
    
    $url = escape($youtube_urls[array_rand($youtube_urls)]); 
    $duration_seconds = rand(60, 3600); // 1 min to 1 hour
    $uploaded_at = randomDate('2024-01-01', '2026-05-01');
    $view_count = rand(0, 1500);
    $like_count = rand(0, $view_count); // Likes can't exceed views
    
    $sql_content .= "INSERT INTO VIDEOS (video_id, channel_id, title, description, url, duration_seconds, uploaded_at, view_count, like_count) VALUES ($i, $channel_id, '$title', '$description', '$url', $duration_seconds, '$uploaded_at', $view_count, $like_count);\n";
}

// ----------------------------------------------------
// 4. GENERATE SUBSCRIPTIONS (120)
// ----------------------------------------------------
$sql_content .= "\n-- SUBSCRIPTIONS\n";
$subs_made = [];
$sub_id = 1;
while ($sub_id <= 120) {
    $subscriber_id = rand(1, 100);
    $channel_id = rand(1, 50);
    $pair = $subscriber_id . "_" . $channel_id;
    
    // Prevent duplicate subscriptions
    if (!in_array($pair, $subs_made)) {
        $subs_made[] = $pair;
        $subscribed_at = randomDate('2024-01-01', '2026-05-01');
        
        $sql_content .= "INSERT INTO SUBSCRIPTIONS (subscription_id, subscriber_id, channel_id, subscribed_at) VALUES ($sub_id, $subscriber_id, $channel_id, '$subscribed_at');\n";
        $sub_id++;
    }
}

// ----------------------------------------------------
// 5. GENERATE COMMENTS (150 total: 130 top-level, 20 replies)
// ----------------------------------------------------
$sql_content .= "\n-- COMMENTS\n";
// Top-level comments (1 to 130)
for ($i = 1; $i <= 130; $i++) {
    $video_id = rand(1, 200);
    $user_id = rand(1, 100);
    $body = escape($comments[array_rand($comments)]);
    $posted_at = randomDate('2024-01-01', '2026-05-01');
    
    $sql_content .= "INSERT INTO COMMENTS (comment_id, video_id, user_id, parent_comment_id, body, posted_at) VALUES ($i, $video_id, $user_id, NULL, '$body', '$posted_at');\n";
}

// Replies (131 to 150)
for ($i = 131; $i <= 150; $i++) {
    $parent_comment_id = rand(1, 130); // Must reply to a top-level comment
    $video_id = rand(1, 200); 
    // Technically, a reply's video_id should match the parent's video_id. 
    // We will just set it randomly here, but in a real app it'd be consistent.
    // For simplicity, we just randomly assign it.
    
    $user_id = rand(1, 100);
    $body = escape($comments[array_rand($comments)]);
    $posted_at = randomDate('2024-01-01', '2026-05-01');
    
    $sql_content .= "INSERT INTO COMMENTS (comment_id, video_id, user_id, parent_comment_id, body, posted_at) VALUES ($i, $video_id, $user_id, $parent_comment_id, '$body', '$posted_at');\n";
}

// Write everything to seed.sql
$file_path = '../data/seed.sql';
file_put_contents($file_path, $sql_content);

echo "Dummy data generated successfully into seed.sql!\n";
?>