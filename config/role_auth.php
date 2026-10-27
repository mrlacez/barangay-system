
<?php
/**
 * Shared role authorization for Barangay Management System.
 * Role values are normalized to lowercase.
 */

require_once __DIR__ . "/session.php";

$timeoutSeconds = 15 * 60;

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

$basePath = preg_replace(
    "~/staff(?:/.*)?$~",
    "",
    (string) $basePath
);

$basePath = rtrim((string) $basePath, "/");

// 1. Kinahanglan naka-login.
if (empty($_SESSION["user_id"])) {
    header("Location: " . $basePath . "/login.php");
    exit;
}

// 2. Susiha ang session timeout.
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

// 3. I-normalize ang role.
$currentRole = strtolower(
    trim((string) ($_SESSION["role"] ?? ""))
);

if (!in_array(
    $currentRole,
    ["admin", "administrator", "staff"],
    true
)) {
    http_response_code(403);
    exit("403 Forbidden: Invalid user role.");
}

// 4. Update activity ug pugngi ang caching.
$_SESSION["last_activity"] = time();

header(
    "Cache-Control: no-store, no-cache, must-revalidate, max-age=0"
);
header("Pragma: no-cache");
header("Expires: 0");

/**
 * Admin-only action/page protection.
 */
function requireAdminRole(): void
{
    $role = strtolower(
        trim((string) ($_SESSION["role"] ?? ""))
    );

    if (!in_array(
        $role,
        ["admin", "administrator"],
        true
    )) {
        http_response_code(403);
        exit("403 Forbidden: Admin access required.");
    }
}
