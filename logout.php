
<?php

require_once __DIR__ . "/config/session.php";
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/config/audit.php";

// I-record ang logout sa dili pa tangtangon ang session data.
if (isset($_SESSION["user_id"])) {
    addAuditLog(
        $conn,
        "LOGOUT",
        "Authentication",
        "User logged out.",
        $_SESSION["user_id"]
    );
}

// Kuhaa ang tanang session data.
$_SESSION = [];

// I-expire ang session cookie gamit ang parehas nga parameters.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(session_name(), "", [
        "expires" => time() - 42000,
        "path" => $params["path"],
        "domain" => $params["domain"],
        "secure" => $params["secure"],
        "httponly" => $params["httponly"],
        "samesite" => $params["samesite"] ?? "Lax"
    ]);
}

// I-destroy ang server-side session.
session_destroy();

// Ayaw tugoti ang browser nga magpakita pag-usab sa sensitibong page cache.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

header("Location: login.php");
exit;
