
<?php

date_default_timezone_set('Asia/Manila');

$host = 'YOUR_MYSQL_HOST';
$username = 'YOUR_MYSQL_USERNAME';
$password = 'YOUR_MYSQL_PASSWORD';
$database = 'YOUR_DATABASE_NAME';
$port = 3306;

mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database,
    $port
);

if ($conn->connect_error) {
    error_log('Database connection failed: ' . $conn->connect_error);
    die('Database connection failed. Please try again later.');
}

$conn->set_charset('utf8mb4');
