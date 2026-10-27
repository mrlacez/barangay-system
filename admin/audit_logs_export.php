
<?php

require_once __DIR__ . "/../config/session.php";
require_once __DIR__ . "/../config/admin_auth.php";
require_once __DIR__ . "/../config/database.php";


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Admin-Only Authorization
|--------------------------------------------------------------------------
*/

$current_role = strtolower(
    trim((string) ($_SESSION["role"] ?? ""))
);

if (!in_array($current_role, ["admin", "administrator"], true)) {
    http_response_code(403);
    exit("Forbidden");
}

/*
|--------------------------------------------------------------------------
| Prevent Caching
|--------------------------------------------------------------------------
*/

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

/*
|--------------------------------------------------------------------------
| CSV Formula Injection Protection
|--------------------------------------------------------------------------
*/



function safeCsvValue($value): string
{
    $value = (string) ($value ?? "");

    if (preg_match('/^[\x00-\x20]*[=+\-@]/', $value)) {
        return "'" . $value;
    }

    return $value;
}



/*
|--------------------------------------------------------------------------
| Fetch Audit Logs
|--------------------------------------------------------------------------
*/

try {
    $sql = "
        SELECT
            id,
            user_id,
            username,
            role,
            action,
            module,
            record_id,
            description,
            created_at
        FROM audit_logs
        ORDER BY id DESC
    ";

    $result = $conn->query($sql);

    if ($result === false) {
        throw new RuntimeException("Audit log query failed.");
    }
} catch (Throwable $e) {
    error_log("Audit log CSV export failed: " . $e->getMessage());

    http_response_code(500);
    exit("Unable to export audit logs at this time.");
}

/*
|--------------------------------------------------------------------------
| Open Output Stream
|--------------------------------------------------------------------------
*/

$output = @fopen("php://output", "w");

if ($output === false) {
    error_log("Audit log CSV export failed: cannot open output stream.");

    $result->free();

    http_response_code(500);
    exit("Unable to create the CSV export.");
}

/*
|--------------------------------------------------------------------------
| CSV Response Headers
|--------------------------------------------------------------------------
*/

header("Content-Type: text/csv; charset=UTF-8");
header('Content-Disposition: attachment; filename="audit_logs.csv"');
header("X-Content-Type-Options: nosniff");

/*
 * UTF-8 BOM helps Microsoft Excel display UTF-8 text correctly.
 */
fwrite($output, "\xEF\xBB\xBF");

/*
|--------------------------------------------------------------------------
| CSV Column Headers
|--------------------------------------------------------------------------
*/

fputcsv($output, [
    "ID",
    "User ID",
    "Username",
    "Role",
    "Action",
    "Module",
    "Record ID",
    "Description",
    "Date & Time"
]);

/*
|--------------------------------------------------------------------------
| Export Records
|--------------------------------------------------------------------------
*/

try {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            (int) $row["id"],
            $row["user_id"] === null ? "" : (int) $row["user_id"],
            safeCsvValue($row["username"]),
            safeCsvValue($row["role"]),
            safeCsvValue($row["action"]),
            safeCsvValue($row["module"]),
            $row["record_id"] === null ? "" : (int) $row["record_id"],
            safeCsvValue($row["description"]),
            safeCsvValue($row["created_at"])
        ]);
    }
} catch (Throwable $e) {
    error_log("Audit log CSV export output failed: " . $e->getMessage());
} finally {
    $result->free();
    fclose($output);
}

exit;
