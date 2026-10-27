<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION["user_id"])) {
    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Unauthorized"
    ]);

    exit;
}

$currentUserId = (int) $_SESSION["user_id"];
$currentRole = $_SESSION["role"] ?? "";

if (
    $currentRole !== "admin" &&
    $currentRole !== "Administrator" &&
    $currentRole !== "Staff"
) {
    http_response_code(403);

    echo json_encode([
        "success" => false,
        "message" => "Forbidden"
    ]);

    exit;
}

require_once __DIR__ . "/config/database.php";


/*
|--------------------------------------------------------------------------
| Get unread notifications
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        title,
        message,
        reference_id,
        is_read,
        created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY id DESC
    LIMIT 10
");

if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to load notifications."
    ]);

    exit;
}

$stmt->bind_param(
    "i",
    $currentUserId
);

$stmt->execute();

$result = $stmt->get_result();

$notifications = [];

while ($row = $result->fetch_assoc()) {

    $notifications[] = [
        "id" => (int) $row["id"],
        "title" => $row["title"],
        "message" => $row["message"],
        "reference_id" => $row["reference_id"],
        "is_read" => (int) $row["is_read"],
        "created_at" => $row["created_at"]
    ];
}

$stmt->close();


/*
|--------------------------------------------------------------------------
| Count unread notifications
|--------------------------------------------------------------------------
*/

$countStmt = $conn->prepare("
    SELECT COUNT(*) AS unread_count
    FROM notifications
    WHERE user_id = ?
      AND is_read = 0
");

$unreadCount = 0;

if ($countStmt) {

    $countStmt->bind_param(
        "i",
        $currentUserId
    );

    $countStmt->execute();

    $countResult = $countStmt->get_result();

    if ($countResult) {

        $countRow = $countResult->fetch_assoc();

        $unreadCount = (int) (
            $countRow["unread_count"] ?? 0
        );
    }

    $countStmt->close();
}


echo json_encode([
    "success" => true,
    "unread_count" => $unreadCount,
    "notifications" => $notifications
]);

exit;