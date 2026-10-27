<?php
declare(strict_types=1);

// Use the same security guard as the Admin Dashboard.
require_once __DIR__ . '/../config/admin_auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/audit.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));

if (!in_array($role, ['admin', 'administrator'], true)) {
    http_response_code(403);
    exit('403 Forbidden: Admin access only.');
}

$userId = (int)($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(403);
    exit('Access denied.');
}

$userId = (int)$_SESSION['user_id'];
$message = '';
$shownCode = $_SESSION['recovery_code_once'] ?? null;
unset($_SESSION['recovery_code_once']);

if (empty($_SESSION['recovery_setup_csrf'])) {
    $_SESSION['recovery_setup_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['recovery_setup_csrf'];

$stmt = $conn->prepare(
    "SELECT id FROM admin_recovery_codes WHERE user_id = ? LIMIT 1"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    $currentPassword = (string)($_POST['current_password'] ?? '');

    if ($token === '' || !hash_equals($csrf, $token)) {
        $message = 'Invalid security token. Refresh the page.';
    } elseif ($existing) {
        $message = 'Recovery code already exists. It cannot be replaced here.';
    } else {
        $stmt = $conn->prepare(
            "SELECT password, role, status
             FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $valid = $account
            && in_array(
                strtolower(trim((string)$account['role'])),
                ['admin', 'administrator'],
                true
            )
            && trim((string)$account['status']) === 'Active'
            && password_verify(
                $currentPassword,
                (string)$account['password']
            );

        if (!$valid) {
            $message = 'Current password is incorrect.';
        } else {
            // 128-bit random code; display only once.
            $rawCode = strtoupper(bin2hex(random_bytes(16)));
            $recoveryCode = implode('-', str_split($rawCode, 8));
            $codeHash = password_hash($recoveryCode, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO admin_recovery_codes (user_id, code_hash)
                 VALUES (?, ?)"
            );

            if (!$stmt) {
                $message = 'Could not create recovery code.';
            } else {
                $stmt->bind_param('is', $userId, $codeHash);

                if ($stmt->execute()) {
                    $_SESSION['recovery_code_once'] = $recoveryCode;

                    addAuditLog(
                        $conn,
                        'RECOVERY_CODE_CREATED',
                        'Authentication',
                        'Admin created a backup recovery code.',
                        $userId
                    );

                    $stmt->close();
                    header('Location: recovery_setup.php');
                    exit;
                }

                $stmt->close();
                $message = 'Recovery code already exists or setup failed.';
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
<title>Admin Recovery Setup</title>
<style>
body{font-family:Arial,sans-serif;background:#f3f6fb;padding:24px;color:#172033}
main{max-width:620px;margin:30px auto;background:#fff;padding:28px;border-radius:12px}
label{display:block;font-weight:bold;margin:18px 0 7px}
input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #ccd3df;border-radius:7px}
button{margin-top:16px;padding:12px 18px;border:0;border-radius:7px;background:#174ea6;color:white;font-weight:bold}
.notice{padding:12px;background:#fff4d6;border-radius:7px;margin:16px 0}
.code{padding:16px;background:#eaf7ec;border:1px solid #acd9b4;border-radius:7px;font: bold 20px monospace;overflow-wrap:anywhere}
a{color:#174ea6}
</style>
</head>
<body>
<main>
<h2>Admin Backup Recovery Code</h2>
<p>Generate a backup code for your Admin account. Save it securely.</p>

<?php if ($message !== ''): ?>
<div class="notice">
<?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<?php if (is_string($shownCode)): ?>
    <h3>Save this code now</h3>
    <div class="code">
    <?= htmlspecialchars($shownCode, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <p>This code will not be shown again. Keep it offline and private.</p>
    <a href="dashboard.php">Return to Dashboard</a>

<?php elseif ($existing): ?>
    <div class="notice">
    A recovery code already exists. For safety, it cannot be displayed
    or replaced on this page.
    </div>
    <a href="dashboard.php">Return to Dashboard</a>

<?php else: ?>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf_token"
 value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

<label for="current_password">Current Admin Password</label>
<input id="current_password" name="current_password"
 type="password" autocomplete="current-password" required>

<button type="submit">Generate Recovery Code</button>
</form>
<?php endif; ?>
</main>
</body>
</html>