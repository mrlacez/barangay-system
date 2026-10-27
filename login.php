
<?php

require_once __DIR__ . "/config/session.php";
require_once "config/database.php";
require_once "config/audit.php";

/*
|--------------------------------------------------------------------------
| Prevent browser caching
|--------------------------------------------------------------------------
*/
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

/*
|--------------------------------------------------------------------------
| If already logged in, go directly to dashboard
|--------------------------------------------------------------------------
*/
if (isset($_SESSION["user_id"])) {
    $existingRole = strtolower(
        trim((string) ($_SESSION["role"] ?? ""))
    );

    if (in_array($existingRole, ["admin", "administrator"], true)) {
        header("Location: admin/dashboard.php");
        exit;
    }

    if ($existingRole === "staff") {
        header("Location: staff/dashboard.php");
        exit;
    }

    $_SESSION = [];
    session_destroy();
}

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/
if (empty($_SESSION["login_csrf_token"])) {
    $_SESSION["login_csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["login_csrf_token"];
$error = "";

/**
 * Checks whether a login key is currently locked.
 * Expired attempt windows are reset atomically by recordFailedLogin().
 */
function getLoginLimit(
    mysqli $conn,
    string $key,
    int $maxAttempts,
    int $windowSeconds = 900
): array {
    $stmt = $conn->prepare(
        "SELECT attempts, window_started_at, locked_until
         FROM login_rate_limits
         WHERE scope_key = ?
         LIMIT 1"
    );

    if (!$stmt) {
        throw new RuntimeException("Rate-limit check failed.");
    }

    $stmt->bind_param("s", $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ["locked" => false, "remaining" => 0];
    }

    $now = time();
    $windowStart = strtotime($row["window_started_at"]);
    $lockedUntil = !empty($row["locked_until"])
        ? strtotime($row["locked_until"])
        : 0;

    // Active lockout takes priority.
    if ($lockedUntil !== false && $lockedUntil > $now) {
        return [
            "locked" => true,
            "remaining" => $lockedUntil - $now
        ];
    }

    // Do not reset the database counter here.
    // The atomic failed-login statement handles expired windows.
    if (
        $windowStart === false ||
        $windowStart <= $now - $windowSeconds
    ) {
        return ["locked" => false, "remaining" => 0];
    }

    return [
        "locked" => (int) $row["attempts"] >= $maxAttempts,
        "remaining" => 0
    ];
}


/**
 * Atomically records a failed login.
 * Concurrent requests cannot overwrite each other's attempt counts.
 */
function recordFailedLogin(
    mysqli $conn,
    string $key,
    int $maxAttempts,
    int $windowSeconds = 900
): void {
    $stmt = $conn->prepare(
        "INSERT INTO login_rate_limits
            (scope_key, attempts, window_started_at, locked_until)
         VALUES (?, 1, NOW(), NULL)
         ON DUPLICATE KEY UPDATE
            locked_until = CASE
                WHEN window_started_at <= DATE_SUB(
                    NOW(), INTERVAL ? SECOND
                ) THEN NULL
                WHEN attempts + 1 >= ?
                    THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                ELSE locked_until
            END,
            attempts = CASE
                WHEN window_started_at <= DATE_SUB(
                    NOW(), INTERVAL ? SECOND
                ) THEN 1
                ELSE attempts + 1
            END,
            window_started_at = CASE
                WHEN window_started_at <= DATE_SUB(
                    NOW(), INTERVAL ? SECOND
                ) THEN NOW()
                ELSE window_started_at
            END"
    );

    if (!$stmt) {
        throw new RuntimeException("Rate-limit update failed.");
    }

    $stmt->bind_param(
        "siiii",
        $key,
        $windowSeconds,
        $maxAttempts,
        $windowSeconds,
        $windowSeconds
    );

    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException("Rate-limit save failed.");
    }

    $stmt->close();
}

/** Remove attempt counters after successful authentication. */
function clearLoginLimits(
    mysqli $conn,
    string $ipKey,
    string $pairKey
): void {
    $stmt = $conn->prepare(
        "DELETE FROM login_rate_limits WHERE scope_key IN (?, ?)"
    );

    if (!$stmt) {
        throw new RuntimeException("Unable to clear login limits.");
    }

    $stmt->bind_param("ss", $ipKey, $pairKey);
    $stmt->execute();
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| LOGIN PROCESS
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submitted_token = $_POST["csrf_token"] ?? "";

    if (
        $submitted_token === "" ||
        !hash_equals($_SESSION["login_csrf_token"], $submitted_token)
    ) {
        $error = "Invalid security token. Please refresh the page and try again.";
    } else {
        $username = trim((string) ($_POST["username"] ?? ""));
        $password = (string) ($_POST["password"] ?? "");
        $clientIp = $_SERVER["REMOTE_ADDR"] ?? "unknown";

        // Hash identifiers so raw IP addresses and usernames are not stored.
        $ipKey = hash("sha256", "ip|" . $clientIp);

        $pairKey = hash(
            "sha256",
            "pair|" . $clientIp . "|" . strtolower($username)
        );

        try {
            // Check limits BEFORE querying the users table.
            $ipLimit = getLoginLimit($conn, $ipKey, 20);
            $pairLimit = getLoginLimit($conn, $pairKey, 5);

            if ($ipLimit["locked"] || $pairLimit["locked"]) {
                $error = "Invalid username or password. Please wait before trying again.";
            } elseif ($username === "" || $password === "") {
                recordFailedLogin($conn, $ipKey, 20);
                recordFailedLogin($conn, $pairKey, 5);

                $error = "Invalid username or password.";
            } elseif (strlen($username) > 50) {
                recordFailedLogin($conn, $ipKey, 20);
                recordFailedLogin($conn, $pairKey, 5);

                $error = "Invalid username or password.";
            } else {
                $stmt = $conn->prepare(
                    "SELECT id, username, password, role, status
                     FROM users
                     WHERE username = ?
                     LIMIT 1"
                );

                if (!$stmt) {
                    throw new RuntimeException("Unable to prepare login query.");
                }

                $stmt->bind_param("s", $username);
                $stmt->execute();

                $result = $stmt->get_result();

                $user = $result->num_rows === 1
                    ? $result->fetch_assoc()
                    : null;

                $stmt->close();

                // Use one generic error for nonexistent usernames,
                // wrong passwords, and inactive accounts.
                $validCredentials = $user !== null
                    && trim((string) ($user["status"] ?? "")) === "Active"
                    && password_verify(
                        $password,
                        (string) $user["password"]
                    );

                if (!$validCredentials) {
                    recordFailedLogin($conn, $ipKey, 20);
                    recordFailedLogin($conn, $pairKey, 5);

                    $error = "Invalid username or password.";
                } else {
                    // Clear both counters after successful authentication.
                    clearLoginLimits($conn, $ipKey, $pairKey);

                    // Prevent session fixation.
                    session_regenerate_id(true);

                    $_SESSION["user_id"] = $user["id"];
                    $_SESSION["username"] = $user["username"];
                    $_SESSION["role"] = $user["role"];
                    $_SESSION["last_activity"] = time();

                    addAuditLog(
                        $conn,
                        "LOGIN",
                        "Authentication",
                        "User successfully logged in.",
                        $user["id"]
                    );

                    unset($_SESSION["login_csrf_token"]);


                    $role = strtolower(trim((string) $user["role"]));

                    if (in_array($role, ["admin", "administrator"], true)) {
                        header("Location: admin/dashboard.php");
                        exit;
                    }

                    if ($role === "staff") {
                        header("Location: admin/dashboard.php");
                        exit;
                    }

                    $_SESSION = [];
                    session_destroy();

                    header("Location: login.php");
                    exit;

                }
            }
        } catch (Throwable $e) {
            error_log("Login/rate-limit error: " . $e->getMessage());

            $error = "Login is temporarily unavailable. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Barangay Management System - Login</title>

    <link rel="stylesheet"
          href="/barangay-system/assets/css/style.css?v=6">
</head>

<body>

<div class="login-page">

    <!-- LEFT BRANDING PANEL -->
    <section class="login-brand">

        <div class="brand-content">

            <div class="brand-building">
                <svg viewBox="0 0 100 100"
                     xmlns="http://www.w3.org/2000/svg">

                    <path d="M10 35 L50 10 L90 35 Z"
                          fill="currentColor"
                          opacity="0.35"/>

                    <rect x="18" y="35" width="64" height="8"
                          rx="2" fill="currentColor" opacity="0.35"/>

                    <rect x="22" y="43" width="10" height="38"
                          rx="2" fill="currentColor" opacity="0.35"/>

                    <rect x="38" y="43" width="10" height="38"
                          rx="2" fill="currentColor" opacity="0.35"/>

                    <rect x="54" y="43" width="10" height="38"
                          rx="2" fill="currentColor" opacity="0.35"/>

                    <rect x="70" y="43" width="10" height="38"
                          rx="2" fill="currentColor" opacity="0.35"/>

                    <rect x="15" y="81" width="70" height="7"
                          rx="2" fill="currentColor" opacity="0.35"/>
                </svg>
            </div>

            <h1>
                BARANGAY
                <span>MANAGEMENT SYSTEM</span>
            </h1>

            <p class="brand-tagline">
                A more efficient and accountable barangay
                <br>
                for a better community.
            </p>

            <div class="brand-features">

                <div class="brand-feature">
                    <div class="feature-icon">👥</div>
                    <span>Serve<br>Residents</span>
                </div>

                <div class="brand-feature">
                    <div class="feature-icon">📄</div>
                    <span>Manage<br>Records</span>
                </div>

                <div class="brand-feature">
                    <div class="feature-icon">🛡️</div>
                    <span>Build a<br>Stronger Barangay</span>
                </div>

            </div>
        </div>

        <div class="wave wave-one"></div>
        <div class="wave wave-two"></div>
        <div class="wave wave-three"></div>

    </section>

    <!-- RIGHT LOGIN PANEL -->
    <section class="login-panel">

        <div class="login-box">

            <!-- LOGO -->
            <div class="login-logo">
                <svg viewBox="0 0 100 100"
                     xmlns="http://www.w3.org/2000/svg">

                    <path d="M10 35 L50 10 L90 35 Z" fill="white"/>

                    <rect x="18" y="35" width="64" height="8"
                          rx="2" fill="white"/>

                    <rect x="22" y="43" width="10" height="38"
                          rx="2" fill="white"/>

                    <rect x="38" y="43" width="10" height="38"
                          rx="2" fill="white"/>

                    <rect x="54" y="43" width="10" height="38"
                          rx="2" fill="white"/>

                    <rect x="70" y="43" width="10" height="38"
                          rx="2" fill="white"/>

                    <rect x="15" y="81" width="70" height="7"
                          rx="2" fill="white"/>
                </svg>
            </div>

            <h2>Barangay Management System</h2>

            <p class="login-subtitle">Authorized User Login</p>

            <!-- ERROR POP-UP -->
            <?php if ($error !== ""): ?>
                <div class="login-error-modal" id="loginErrorModal">

                    <div class="login-error-box">

                        <div class="login-error-icon">!</div>

                        <h3>Login Failed</h3>

                        <p>
                            <?= htmlspecialchars(
                                $error,
                                ENT_QUOTES | ENT_SUBSTITUTE,
                                "UTF-8"
                            ) ?>
                        </p>

                        <button
                            type="button"
                            class="login-error-button"
                            onclick="closeLoginError()">
                            OK
                        </button>

                    </div>
                </div>
            <?php endif; ?>

            <!-- LOGIN FORM -->
            <form method="POST" autocomplete="off">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $csrf_token,
                        ENT_QUOTES | ENT_SUBSTITUTE,
                        "UTF-8"
                    ) ?>">

                <!-- USERNAME -->
                <div class="login-form-group">

                    <label for="username">Username</label>

                    <div class="input-wrapper">

                        <span class="input-icon">
                            <svg viewBox="0 0 24 24"
                                 xmlns="http://www.w3.org/2000/svg">

                                <circle
                                    cx="12" cy="8" r="4"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"/>

                                <path
                                    d="M4 21c0-4 3.5-6 8-6s8 2 8 6"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"/>
                            </svg>
                        </span>

                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Enter username"
                            autocomplete="username"
                            required>
                    </div>
                </div>

                <!-- PASSWORD -->
                <div class="login-form-group">

                    <label for="password">Password</label>

                    <div class="input-wrapper">

                        <span class="input-icon">
                            <svg viewBox="0 0 24 24"
                                 xmlns="http://www.w3.org/2000/svg">

                                <rect
                                    x="5" y="10" width="14" height="10"
                                    rx="2"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"/>

                                <path
                                    d="M8 10V7a4 4 0 0 1 8 0v3"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"/>
                            </svg>
                        </span>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter password"
                            autocomplete="current-password"
                            required>

                        <button
                            type="button"
                            class="password-toggle"
                            onclick="togglePassword()"
                            aria-label="Show password">

                            <svg id="eyeIcon"
                                 viewBox="0 0 24 24"
                                 xmlns="http://www.w3.org/2000/svg">

                                <path
                                    d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"/>

                                <circle
                                    cx="12" cy="12" r="2.5"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"/>
                            </svg>
                        </button>

                    </div>
                </div>

                <!-- LOGIN BUTTON -->
                <button type="submit" class="login-button">
                    <span>Login</span>
                    <span class="login-arrow">→</span>
                </button>

            </form>

            <p style="text-align:center; margin-top:15px;">
                <a href="recover_password.php"
                   style="color:#174ea6; text-decoration:none;">
                    Forgot Password?
                </a>
            </p>

            <!-- DIVIDER -->
            <div class="login-divider">
                <span></span>
                <small>or</small>
                <span></span>
            </div>

            <!-- SECURITY NOTICE -->
            <div class="security-notice">

                <div class="security-icon">
                    <svg viewBox="0 0 24 24"
                         xmlns="http://www.w3.org/2000/svg">

                        <path
                            d="M12 3l8 3v6c0 5-3.5 8-8 10-4.5-2-8-5-8-10V6l8-3Z"
                            fill="currentColor"/>

                        <path
                            d="M9 12l2 2 4-4"
                            fill="none"
                            stroke="white"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"/>
                    </svg>
                </div>

                <div>
                    <strong>Authorized personnel only.</strong>
                    <span>Please login to continue.</span>
                </div>

            </div>

            <!-- FOOTER -->
            <div class="login-footer">

                <span>
                    &copy; <?= date("Y") ?> Barangay Management System
                </span>

                <span>All Rights Reserved</span>

            </div>

        </div>
    </section>

</div>

<script>
function togglePassword() {

    const password = document.getElementById("password");
    const eyeIcon = document.getElementById("eyeIcon");

    if (password.type === "password") {

        password.type = "text";

        eyeIcon.innerHTML = `
            <path
                d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"
                fill="none"
                stroke="currentColor"
                stroke-width="2"/>

            <path
                d="M4 4l16 16"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"/>
        `;

    } else {

        password.type = "password";

        eyeIcon.innerHTML = `
            <path
                d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"
                fill="none"
                stroke="currentColor"
                stroke-width="2"/>

            <circle
                cx="12" cy="12" r="2.5"
                fill="none"
                stroke="currentColor"
                stroke-width="2"/>
        `;
    }
}

function closeLoginError() {

    const modal = document.getElementById("loginErrorModal");

    if (modal) {
        modal.remove();
    }
}
</script>

</body>
</html>