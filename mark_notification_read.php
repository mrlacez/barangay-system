<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");


/*
|--------------------------------------------------------------------------
| Require Login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {

    http_response_code(401);

    echo json_encode([
        "success" => false,
        "message" => "Unauthorized"
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Require Administrator or Staff
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Get Notification ID
|--------------------------------------------------------------------------
*/

$notificationId = isset($_POST["notification_id"])
    ? (int) $_POST["notification_id"]
    : 0;


if ($notificationId <= 0) {

    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Invalid notification ID."
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/config/database.php";


/*
|--------------------------------------------------------------------------
| Mark Notification as Read
|--------------------------------------------------------------------------
|
| Important:
| The notification must belong to the currently logged-in user.
|
*/

$stmt = $conn->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE id = ?
      AND user_id = ?
");


if (!$stmt) {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to update notification."
    ]);

    exit;
}


$stmt->bind_param(
    "ii",
    $notificationId,
    $currentUserId
);


$stmt->execute();


if ($stmt->affected_rows >= 0) {

    echo json_encode([
        "success" => true,
        "message" => "Notification marked as read."
    ]);

} else {

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Unable to mark notification as read."
    ]);
}


$stmt->close();

exit;