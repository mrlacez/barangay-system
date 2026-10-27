
<?php

// Shared protection for all admin pages and admin endpoints.
require_once __DIR__ . "/session.php";

$timeoutSeconds = 15 * 60; // 15 minutes

// Determine the project root from the requested /admin/ URL.
$scriptName = str_replace(
    "\\",
    "/",
    (string) ($_SERVER["SCRIPT_NAME"] ?? "")
);

$basePath = preg_replace(
    "~\/admin(?:\/.*)?$~",
    "",
    $scriptName
);

$basePath = rtrim((string) $basePath, "/");

// Require an authenticated user.
if (empty($_SESSION["user_id"])) {
    header("Location: " . $basePath . "/login.php");
    exit;
}

// Expire sessions that have no valid activity timestamp
// or have been inactive for longer than 15 minutes.
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

    header("Location: " . $basePath . "/login.php?timeout=1");
    exit;
}




/*
 * Allow Admin and Administrator on all admin pages.
 * Allow Staff on selected admin pages only.
 */
$role = strtolower(
    trim((string) ($_SESSION["role"] ?? ""))
);

$scriptName = str_replace(
    "\\",
    "/",
    (string) ($_SERVER["SCRIPT_NAME"] ?? "")
);

$isAllowedStaffPage = preg_match(
    '~/admin/(dashboard|residents|officials|certificates|blotter|reports|announcements|service_request|community_assistance)\.php$~i',
    $scriptName
) === 1;

$isAdmin = in_array(
    $role,
    ["admin", "administrator"],
    true
);

$isStaffAllowed = (
    $role === "staff" &&
    $isAllowedStaffPage
);

if (!$isAdmin && !$isStaffAllowed) {
    http_response_code(403);
    exit("403 Forbidden: You are not authorized to access this page.");
}



// Record activity only after authentication and role checks pass.
$_SESSION["last_activity"] = time();

// Prevent sensitive pages from being cached.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

