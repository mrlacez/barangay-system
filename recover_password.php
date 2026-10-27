<?php
declare(strict_types=1);

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/audit.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['recovery_csrf'])) {
    $_SESSION['recovery_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['recovery_csrf'];
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    $username = trim((string)($_POST['username'] ?? ''));
    $code = strtoupper(trim((string)($_POST['recovery_code'] ?? '')));
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    if ($token === '' || !hash_equals($csrf, $token)) {
        $message = 'Invalid security token. Refresh the page.';
    } elseif (
        $username === '' || strlen($username) > 50 ||
        strlen($code) > 100
    ) {
        $message = 'Invalid username or recovery code.';
    } elseif (strlen($newPassword) < 12 || strlen($newPassword) > 4096) {
        $message = 'Use a password with at least 12 characters.';
    } elseif (!hash_equals($newPassword, $confirmPassword)) {
        $message = 'The new passwords do not match.';
    } else {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ipKey = hash('sha256', 'recovery|' . $ip);

        // Rate limit by IP using the existing login_rate_limits table.
        $limitStmt = $conn->prepare(
            "SELECT attempts, window_started_at, locked_until
             FROM login_rate_limits WHERE scope_key = ? LIMIT 1"
        );

        $locked = true; // Fail closed if the limit check fails.

        if ($limitStmt) {
            $limitStmt->bind_param('s', $ipKey);
            $limitStmt->execute();
            $limit = $limitStmt->get_result()->fetch_assoc();
            $limitStmt->close();

            $locked = false;
            if ($limit) {
                $lockTime = !empty($limit['locked_until'])
                    ? strtotime((string)$limit['locked_until'])
                    : false;
                $windowStart = strtotime(
                    (string)$limit['window_started_at']
                );

                if ($lockTime !== false && $lockTime > time()) {
                    $locked = true;
                } elseif (
                    $windowStart !== false &&
                    $windowStart > time() - 900 &&
                    (int)$limit['attempts'] >= 5
                ) {
                    $locked = true;
                }
            }
        }

        if ($locked) {
            $message = 'Recovery is temporarily unavailable. Try again later.';
        } else {
            $stmt = $conn->prepare(
                "SELECT u.id, u.role, u.status,
                        r.id AS recovery_id, r.code_hash, r.used_at
                 FROM users u
                 INNER JOIN admin_recovery_codes r ON r.user_id = u.id
                 WHERE u.username = ?
                 LIMIT 1"
            );

            $record = null;
            if ($stmt) {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $record = $stmt->get_result()->fetch_assoc();
                $stmt->close();
            }

            $valid = $record
                && in_array(
                    strtolower(trim((string)$record['role'])),
                    ['admin', 'administrator'],
                    true
                )
                && trim((string)$record['status']) === 'Active'
                && empty($record['used_at'])
                && password_verify($code, (string)$record['code_hash']);

            if (!$valid) {
                // Record failed attempt. Five attempts in 15 minutes locks IP.
                $rate = $conn->prepare(
                    "INSERT INTO login_rate_limits
                        (scope_key, attempts, window_started_at, locked_until)
                     VALUES (?, 1, NOW(), NULL)
                     ON DUPLICATE KEY UPDATE
                       locked_until = CASE
                         WHEN window_started_at <=
                              DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                           THEN NULL
                         WHEN attempts + 1 >= 5
                           THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                         ELSE locked_until
                       END,
                       attempts = CASE
                         WHEN window_started_at <=
                              DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                           THEN 1
                         ELSE attempts + 1
                       END,
                       window_started_at = CASE
                         WHEN window_started_at <=
                              DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                           THEN NOW()
                         ELSE window_started_at
                       END"
                );

                if ($rate) {
                    $rate->bind_param('s', $ipKey);
                    $rate->execute();
                    $rate->close();
                }

                $message =
                    'Invalid username or recovery code, or the code was used.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $uid = (int)$record['id'];
                $rid = (int)$record['recovery_id'];

                try {
                    $conn->begin_transaction();

                    // Lock recovery record so simultaneous requests cannot reuse it.
                    $lock = $conn->prepare(
                        "SELECT code_hash, used_at
                         FROM admin_recovery_codes
                         WHERE id = ? FOR UPDATE"
                    );
                    $lock->bind_param('i', $rid);
                    $lock->execute();
                    $fresh = $lock->get_result()->fetch_assoc();
                    $lock->close();

                    if (
                        !$fresh ||
                        !empty($fresh['used_at']) ||
                        !password_verify($code, (string)$fresh['code_hash'])
                    ) {
                        throw new RuntimeException('Recovery code is no longer valid.');
                    }

                    $update = $conn->prepare(
                        "UPDATE users
                         SET password = ?
                         WHERE id = ? AND status = 'Active'
                           AND LOWER(role) IN ('admin', 'administrator')"
                    );
                    $update->bind_param('si', $newHash, $uid);
                    $update->execute();

                    if ($update->affected_rows !== 1) {
                        $update->close();
                        throw new RuntimeException('Password update failed.');
                    }
                    $update->close();

                    $consume = $conn->prepare(
                        "UPDATE admin_recovery_codes
                         SET used_at = NOW()
                         WHERE id = ? AND used_at IS NULL"
                    );
                    $consume->bind_param('i', $rid);
                    $consume->execute();

                    if ($consume->affected_rows !== 1) {
                        $consume->close();
                        throw new RuntimeException('Recovery code consumption failed.');
                    }
                    $consume->close();

                    $conn->commit();

                    addAuditLog(
                        $conn,
                        'PASSWORD_RECOVERY',
                        'Authentication',
                        'Admin password reset using backup recovery code.',
                        $uid
                    );

                    $success = true;
                    $message =
                        'Password reset successful. The recovery code cannot be reused.';
                    unset($_SESSION['recovery_csrf']);
                } catch (Throwable $e) {
                    $conn->rollback();
                    error_log('Password recovery failed: ' . $e->getMessage());
                    $message =
                        'Recovery failed. Please try again or contact the system administrator.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Password Recovery</title>
<style>
body{font-family:Arial,sans-serif;background:#f3f6fb;padding:24px;color:#172033}
main{max-width:620px;margin:30px auto;background:#fff;padding:28px;border-radius:12px}
label{display:block;font-weight:bold;margin:16px 0 7px}
input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #ccd3df;border-radius:7px}
button{margin-top:18px;padding:12px 18px;border:0;border-radius:7px;background:#174ea6;color:white;font-weight:bold}
.notice{padding:12px;background:#eef5ff;border-radius:7px;margin:16px 0;line-height:1.5}
a{color:#174ea6}
</style>
</head>
<body>
<main>
<h2>Admin Password Recovery</h2>
<p>Use your saved backup recovery code.</p>

<?php if ($message !== ''): ?>
<div class="notice">
<?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<?php if ($success): ?>
<p><a href="login.php">Return to Login</a></p>
<?php else: ?>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf_token"
 value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

<label for="username">Admin Username</label>
<input id="username" name="username" autocomplete="username"
 maxlength="50" required>

<label for="recovery_code">Backup Recovery Code</label>
<input id="recovery_code" name="recovery_code" autocomplete="off"
 maxlength="100" required>

<label for="new_password">New Password (at least 12 characters)</label>
<input id="new_password" name="new_password" type="password"
 autocomplete="new-password" minlength="12" maxlength="4096" required>

<label for="confirm_password">Confirm New Password</label>
<input id="confirm_password" name="confirm_password" type="password"
 autocomplete="new-password" minlength="12" maxlength="4096" required>

<button type="submit">Reset Admin Password</button>
</form>
<?php endif; ?>

<p><a href="login.php">Back to Login</a></p>
</main>
</body>
</html>