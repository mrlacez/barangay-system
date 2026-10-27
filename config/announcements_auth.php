<?php

// Shared session security.
require_once __DIR__ . "/session.php";

// Session inactivity timeout: 15 minutes.
$timeoutSeconds = 15 * 60;

// Determine project base path.
$scriptName = str_replace(
    "\\",
    "/",
    (string) ($_SERVER["SCRIPT_NAME"] ?? "")
);

$basePath = preg_replace(
    "~/admin(?:/.*)?$~",
    "",
    $scriptName
);

$basePath = rtrim((string) $basePath, "/");

// Require login.
if (empty($_SESSION["user_id"])) {
    header("Location: " . $basePath . "/login.php");
    exit;
}

// Validate session activity.
$lastActivity = filter_var(
    $_SESSION["last_activity"] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $lastActivity === false ||
    $lastActivity === null ||
    $lastActivity <= 0 ||
    (time() - $lastActivity) > $timeoutSeconds
) {
    $_SESSION = [];

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

    session_destroy();

    header(
        "Location: " . $basePath . "/login.php?timeout=1"
    );
    exit;
}

// Allow Admin and Staff.
$currentRole = strtolower(
    trim((string) ($_SESSION["role"] ?? ""))
);

if (!in_array($currentRole, ["admin", "administrator", "staff"], true)) {
    http_response_code(403);
    exit("403 Forbidden: Wala kay permiso sa pag-access niini nga page.");
}

// Update activity after authentication.
$_SESSION["last_activity"] = time();

// Prevent caching of sensitive pages.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");