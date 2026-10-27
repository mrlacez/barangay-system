
<?php

function addAuditLog(
    $conn,
    $action,
    $module,
    $description,
    $record_id = null
) {
    if (!($conn instanceof mysqli) || $conn->connect_error) {
        error_log("Audit log failed: invalid database connection.");
        return false;
    }

    $user_id = isset($_SESSION["user_id"])
        ? (int) $_SESSION["user_id"]
        : null;

    $username = $_SESSION["username"] ?? "Unknown";
    $role = $_SESSION["role"] ?? "Unknown";

    // Generate Philippine local time explicitly.
    $created_at = (new DateTimeImmutable(
        'now',
        new DateTimeZone('Asia/Manila')
    ))->format('Y-m-d H:i:s');

    $sql = "INSERT INTO audit_logs
        (user_id, username, role, action, module, record_id, description, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log("Audit log prepare failed: " . $conn->error);
        return false;
    }

    $stmt->bind_param(
        "issssiss",
        $user_id,
        $username,
        $role,
        $action,
        $module,
        $record_id,
        $description,
        $created_at
    );

    $result = $stmt->execute();

    if (!$result) {
        error_log("Audit log insert failed: " . $stmt->error);
    }

    $stmt->close();

    return $result;
}
