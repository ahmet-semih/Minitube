<?php
// Author: Ahmet Semih Gümus
// install.php - Creates the database, tables, and fills them with seed data.

$servername = "localhost";
$username = "root";
$password = "mysql";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Reset any previous install so seed data is loaded cleanly.
if ($conn->query("DROP DATABASE IF EXISTS ahmetsemih_gumus") === FALSE) {
    die("Error dropping database: " . $conn->error);
}

// Create database
$sql = "CREATE DATABASE ahmetsemih_gumus";

if ($conn->query($sql) === FALSE) {
    die("Error creating database: " . $conn->error);
}

// Select database
mysqli_select_db($conn, 'ahmetsemih_gumus');

// Set charset to support Turkish and other special characters
$conn->set_charset("utf8mb4");

// Drop tables first in reverse dependency order so the installer can be rerun safely.
$dropTables = [
    "DROP TABLE IF EXISTS COMMENTS",
    "DROP TABLE IF EXISTS SUBSCRIPTIONS",
    "DROP TABLE IF EXISTS VIDEOS",
    "DROP TABLE IF EXISTS CHANNELS",
    "DROP TABLE IF EXISTS USERS"
];

foreach ($dropTables as $dropSql) {
    if ($conn->query($dropSql) === FALSE) {
        die("Error dropping table: " . $conn->error);
    }
}

// Create tables
$tables = [
    "CREATE TABLE USERS (
        user_id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        user_image VARCHAR(1000),
        full_name VARCHAR(255),
        email VARCHAR(255),
        country VARCHAR(255),
        joined_on DATE,
        bio TEXT
    )",
    "CREATE TABLE CHANNELS (
        channel_id INT PRIMARY KEY AUTO_INCREMENT,
        owner_id INT UNIQUE,
        channel_image VARCHAR(1000),
        name VARCHAR(255) NOT NULL,
        description TEXT,
        created_on DATE,
        category VARCHAR(255),
        FOREIGN KEY (owner_id) REFERENCES USERS(user_id) ON DELETE CASCADE
    )",
    "CREATE TABLE VIDEOS (
        video_id INT PRIMARY KEY AUTO_INCREMENT,
        channel_id INT,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        url VARCHAR(1000),
        duration_seconds INT,
        uploaded_at DATETIME,
        view_count INT DEFAULT 0,
        like_count INT DEFAULT 0,
        FOREIGN KEY (channel_id) REFERENCES CHANNELS(channel_id) ON DELETE CASCADE
    )",
    "CREATE TABLE SUBSCRIPTIONS (
        subscription_id INT PRIMARY KEY AUTO_INCREMENT,
        subscriber_id INT,
        channel_id INT,
        subscribed_at DATETIME,
        FOREIGN KEY (subscriber_id) REFERENCES USERS(user_id) ON DELETE CASCADE,
        FOREIGN KEY (channel_id) REFERENCES CHANNELS(channel_id) ON DELETE CASCADE
    )",
    "CREATE TABLE COMMENTS (
        comment_id INT PRIMARY KEY AUTO_INCREMENT,
        video_id INT,
        user_id INT,
        parent_comment_id INT,
        body TEXT NOT NULL,
        posted_at DATETIME,
        FOREIGN KEY (video_id) REFERENCES VIDEOS(video_id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES USERS(user_id) ON DELETE CASCADE,
        FOREIGN KEY (parent_comment_id) REFERENCES COMMENTS(comment_id) ON DELETE CASCADE
    )"
];

foreach ($tables as $sql) {
    if ($conn->query($sql) === FALSE) {
        die("Error creating table: " . $conn->error);
    }
}

// Fill tables using seed.sql
$seed_file = __DIR__ . '/../data/seed.sql';
if (!file_exists($seed_file)) {
    die("Error: seed.sql not found at $seed_file. Run generate_data.php first.");
}

$seed_sql = file_get_contents($seed_file);
if ($seed_sql === false || trim($seed_sql) === '') {
    die("Error: seed.sql is empty or could not be read.");
}

// Use multi_query to execute the entire seed file at once
if ($conn->multi_query($seed_sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
} else {
    die("Seed error: " . $conn->error);
}

// Check for any error that occurred during multi_query execution
if ($conn->errno) {
    die("Seed error after multi_query: " . $conn->error);
}

$conn->close();
header('Location: ../php/login.php');
exit;
?>