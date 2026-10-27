<?php

    date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../config/database.php";

// Use Philippine Standard Time for this page and its DB connection.
date_default_timezone_set('Asia/Manila');

if (!$conn->query("SET time_zone = '+08:00'")) {
    error_log("Audit Logs timezone error: " . $conn->error);
}


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
| Administrator Only
|--------------------------------------------------------------------------
*/

$current_role = strtolower(trim((string) ($_SESSION["role"] ?? "")));

if (!in_array($current_role, ["admin", "administrator"], true)) {
    http_response_code(403);
    exit("Forbidden");
}

/*
|--------------------------------------------------------------------------
| No Cache
|--------------------------------------------------------------------------
*/

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");
$action = trim($_GET["action"] ?? "");
$module = trim($_GET["module"] ?? "");
$date_from = trim($_GET["date_from"] ?? "");
$date_to = trim($_GET["date_to"] ?? "");

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$recordsPerPage = 10;

$currentPage = isset($_GET["page"])
    ? (int) $_GET["page"]
    : 1;

if ($currentPage < 1) {
    $currentPage = 1;
}


/*
|--------------------------------------------------------------------------
| COUNT AUDIT LOGS
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*) AS total
    FROM audit_logs
    WHERE
        (
            ? = ''
            OR username LIKE CONCAT('%', ?, '%')
            OR description LIKE CONCAT('%', ?, '%')
        )
        AND (? = '' OR action = ?)
        AND (? = '' OR module = ?)
        AND (? = '' OR DATE(created_at) >= ?)
        AND (? = '' OR DATE(created_at) <= ?)
";

$countStmt = $conn->prepare($countSql);

$countStmt->bind_param(
    "sssssssssss",
    $search,
    $search,
    $search,
    $action,
    $action,
    $module,
    $module,
    $date_from,
    $date_from,
    $date_to,
    $date_to
);

$countStmt->execute();

$countResult = $countStmt->get_result();

$total_logs = (int) $countResult->fetch_assoc()["total"];

$countStmt->close();


/*
|--------------------------------------------------------------------------
| PAGINATION COMPUTATION
|--------------------------------------------------------------------------
*/

$totalPages = max(
    1,
    (int) ceil($total_logs / $recordsPerPage)
);

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $recordsPerPage;


/*
|--------------------------------------------------------------------------
| AUDIT LOG QUERY
|--------------------------------------------------------------------------
*/

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
    WHERE
        (
            ? = ''
            OR username LIKE CONCAT('%', ?, '%')
            OR description LIKE CONCAT('%', ?, '%')
        )
        AND (? = '' OR action = ?)
        AND (? = '' OR module = ?)
        AND (? = '' OR DATE(created_at) >= ?)
        AND (? = '' OR DATE(created_at) <= ?)
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "sssssssssssii",
    $search,
    $search,
    $search,
    $action,
    $action,
    $module,
    $module,
    $date_from,
    $date_from,
    $date_to,
    $date_to,
    $recordsPerPage,
    $offset
);

$stmt->execute();

$audit_result = $stmt->get_result();

?>

<?php include "../includes/header.php"; ?>

<div class="page-header">

    <div>
        <h1>Audit Logs</h1>

        <p>
            Monitor and review system activities performed by users.
        </p>
    </div>

    <div class="audit-header-actions">

        <a
            href="audit_logs_export.php"
            class="btn btn-primary">
            📥 Export CSV
        </a>

        <button
            type="button"
            class="btn btn-secondary"
            onclick="printAuditLogs()">
            🖨️ Print
        </button>

    </div>

</div>


<!-- ==========================================================
     AUDIT LOG RECORDS
========================================================== -->

<section class="content-card" id="audit-records">

    <div class="section-header">

        <div>
            <h2>Activity Records</h2>

            <p>
                Showing the latest system activities.
            </p>
        </div>

        <div class="audit-total">
            <?= $total_logs ?> record(s)
        </div>

    </div>


    <!-- ======================================================
         FILTERS
    ======================================================= -->

    <form method="GET" class="audit-filter-form">

        <div class="audit-filter-group">

            <label for="search">
                Search
            </label>

            <input
                type="text"
                id="search"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Username or description"
                maxlength="100">

        </div>


        <div class="audit-filter-group">

            <label for="action">
                Action
            </label>

            <select id="action" name="action">

                <option value="">
                    All Actions
                </option>

                <option value="LOGIN"
                    <?= $action === "LOGIN" ? "selected" : "" ?>>
                    LOGIN
                </option>

                <option value="LOGOUT"
                    <?= $action === "LOGOUT" ? "selected" : "" ?>>
                    LOGOUT
                </option>

                <option value="ADD"
                    <?= $action === "ADD" ? "selected" : "" ?>>
                    ADD
                </option>

                <option value="UPDATE"
                    <?= $action === "UPDATE" ? "selected" : "" ?>>
                    UPDATE
                </option>

                <option value="DELETE"
                    <?= $action === "DELETE" ? "selected" : "" ?>>
                    DELETE
                </option>

                <option value="CHANGE PASSWORD"
                    <?= $action === "CHANGE PASSWORD" ? "selected" : "" ?>>
                    CHANGE PASSWORD
                </option>

                <option value="PRINT" <?= $action === "PRINT" ? "selected" : "" ?>>
                    PRINT
                </option>

            </select>

        </div>


        <div class="audit-filter-group">

            <label for="module">
                Module
            </label>

            <select id="module" name="module">

                <option value="">
                    All Modules
                </option>

                <option value="Authentication"
                    <?= $module === "Authentication" ? "selected" : "" ?>>
                    Authentication
                </option>

                <option value="Residents"
                    <?= $module === "Residents" ? "selected" : "" ?>>
                    Residents
                </option>

                <option value="Officials"
                    <?= $module === "Officials" ? "selected" : "" ?>>
                    Officials
                </option>

                <option value="Certificates"
                    <?= $module === "Certificates" ? "selected" : "" ?>>
                    Certificates
                </option>

                <option value="Blotter"
                    <?= $module === "Blotter" ? "selected" : "" ?>>
                    Blotter
                </option>

                <option value="Announcements"
                    <?= $module === "Announcements" ? "selected" : "" ?>>
                    Announcements
                </option>

                <option value="User Management"
                    <?= $module === "User Management" ? "selected" : "" ?>>
                    User Management
                </option>

            </select>

        </div>


        <div class="audit-filter-group">

            <label for="date_from">
                From
            </label>

            <input
                type="date"
                id="date_from"
                name="date_from"
                value="<?= htmlspecialchars($date_from) ?>">

        </div>


        <div class="audit-filter-group">

            <label for="date_to">
                To
            </label>

            <input
                type="date"
                id="date_to"
                name="date_to"
                value="<?= htmlspecialchars($date_to) ?>">

        </div>


        <div class="audit-filter-actions">

            <button
                type="submit"
                class="btn btn-primary">
                Filter
            </button>

            <a
                href="audit_logs.php#audit-records"
                class="btn btn-light">
                Clear
            </a>

        </div>

    </form>


    <!-- ======================================================
         TABLE
    ======================================================= -->

    <div class="table-responsive">

        <table class="data-table audit-table">

            <thead>

                <tr>

                    <th>ID</th>

                    <th>User</th>

                    <th>Role</th>

                    <th>Action</th>

                    <th>Module</th>

                    <th>Record ID</th>

                    <th>Description</th>

                    <th>Date & Time</th>

                </tr>

            </thead>


            <tbody>

            <?php if ($audit_result->num_rows > 0): ?>

                <?php while ($log = $audit_result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?= (int)$log["id"] ?>
                        </td>


                        <td>

                            <strong>
                                <?= htmlspecialchars($log["username"] ?? "Unknown") ?>
                            </strong>

                        </td>


                        <td>

                            <?php
                            $display_role =
                                ($log["role"] === "admin")
                                ? "Administrator"
                                : $log["role"];
                            ?>

                            <span class="role-badge">
                                <?= htmlspecialchars($display_role ?? "") ?>
                            </span>

                        </td>


                        <td>

                            <?php
                            $action_class = "";

                            switch ($log["action"]) {

                                case "ADD":
                                    $action_class = "audit-add";
                                    break;

                                case "UPDATE":
                                    $action_class = "audit-update";
                                    break;

                                case "DELETE":
                                    $action_class = "audit-delete";
                                    break;

                                case "LOGIN":
                                    $action_class = "audit-login";
                                    break;

                                case "LOGOUT":
                                    $action_class = "audit-logout";
                                    break;

                                case "CHANGE PASSWORD":
                                    $action_class = "audit-password";
                                    break;

                                case "PRINT":
                                    $action_class = "audit-print";
                                    break;
                            }
                            ?>

                            <span class="audit-action <?= $action_class ?>">
                                <?= htmlspecialchars($log["action"]) ?>
                            </span>

                        </td>


                        <td>
                            <?= htmlspecialchars($log["module"]) ?>
                        </td>


                        <td>

                            <?php if ($log["record_id"] !== null): ?>

                                <?= (int)$log["record_id"] ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        <td class="audit-description">

                            <?= htmlspecialchars($log["description"]) ?>

                        </td>


                        <td>
                          
                            
<?php
try {
    $logTime = new DateTimeImmutable(
        $log['created_at'],
        new DateTimeZone('Asia/Manila')
    );

    echo htmlspecialchars(
        $logTime->format('M d, Y h:i A'),
        ENT_QUOTES,
        'UTF-8'
    );
} catch (Exception $e) {
    echo 'Invalid date';
}
?>

                            
                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="8"
                        class="empty-state">

                        No audit records found.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    <?php if ($total_logs > 0): ?>

        <div class="pagination-wrapper">

            <div class="pagination-info">

                <?php
                $startRecord = $offset + 1;

                $endRecord = min(
                    $offset + $recordsPerPage,
                    $total_logs
                );
                ?>

                Showing
                <?= $startRecord ?>
                –
                <?= $endRecord ?>
                of
                <?= $total_logs ?>
                records

            </div>


            
<?php if ($totalPages > 1): ?>
    <div class="pagination">

        <?php
        $startPage = max(1, $currentPage - 2);
        $endPage = min($totalPages, $currentPage + 2);

        $baseUrl = 'audit_logs.php?' . http_build_query([
            'search' => $search,
            'action' => $action,
            'module' => $module,
            'date_from' => $date_from,
            'date_to' => $date_to
        ]);
        ?>

        <?php if ($currentPage > 1): ?>
            <a class="page-link"
               href="<?= htmlspecialchars($baseUrl) ?>&page=<?= $currentPage - 1 ?>#audit-records">
                Previous
            </a>
        <?php endif; ?>

        <?php if ($startPage > 1): ?>
            <a class="page-link"
               href="<?= htmlspecialchars($baseUrl) ?>&page=1#audit-records">1</a>
            <?php if ($startPage > 2): ?>
                <span class="page-link">...</span>
            <?php endif; ?>
        <?php endif; ?>

        <?php for ($page = $startPage; $page <= $endPage; $page++): ?>
            <a class="page-link <?= $page === $currentPage ? 'active' : '' ?>"
               href="<?= htmlspecialchars($baseUrl) ?>&page=<?= $page ?>#audit-records">
                <?= $page ?>
            </a>
        <?php endfor; ?>

        <?php if ($endPage < $totalPages): ?>
            <?php if ($endPage < $totalPages - 1): ?>
                <span class="page-link">...</span>
            <?php endif; ?>
            <a class="page-link"
               href="<?= htmlspecialchars($baseUrl) ?>&page=<?= $totalPages ?>#audit-records">
                <?= $totalPages ?>
            </a>
        <?php endif; ?>

        <?php if ($currentPage < $totalPages): ?>
            <a class="page-link"
               href="<?= htmlspecialchars($baseUrl) ?>&page=<?= $currentPage + 1 ?>#audit-records">
                Next
            </a>
        <?php endif; ?>

    </div>
<?php endif; ?>


        </div>

    <?php endif; ?>

</section>


<style>

/* ==========================================================
   AUDIT LOGS
========================================================== */

#audit-records {
    background: #fff;
    border: 1px solid #e4e9f2;
    border-top: 5px solid #2563eb;
    border-radius: 14px;
    box-shadow: 0 5px 20px rgba(15, 23, 42, .07);
    margin-bottom: 26px;
    padding: 25px 30px;
}

#audit-records .section-header {
    align-items: flex-start;
    margin-bottom: 22px;
}

#audit-records .section-header h2 {
    color: #17233d;
    font-size: 22px;
    font-weight: 750;
    margin: 0 0 5px;
}

#audit-records .section-header p {
    color: #64748b;
    margin: 0;
}

.audit-total {
    padding: 8px 13px;
    border-radius: 20px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 12px;
    font-weight: 700;
}


/* ==========================================================
   FILTER FORM
========================================================== */

.audit-filter-form {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px;
    margin: 0 0 20px;
    padding: 0;
}

.audit-filter-group {
    display: flex;
    flex-direction: column;
    flex: 1 1 150px;
    gap: 7px;
    min-width: 0;
}

.audit-filter-group label {
    color: #26354d;
    font-size: 13px;
    font-weight: 650;
}

.audit-filter-group input,
.audit-filter-group select {
    background: #fff;
    border: 1px solid #d4dce8;
    border-radius: 9px;
    box-sizing: border-box;
    color: #182337;
    font: inherit;
    font-size: 14px;
    min-height: 44px;
    min-width: 0;
    outline: 0;
    padding: 10px 12px;
    width: 100%;
}

.audit-filter-group input:focus,
.audit-filter-group select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .14);
}

.audit-filter-actions {
    display: flex;
    flex: 0 0 auto;
    gap: 8px;
}

.audit-filter-actions .btn {
    align-items: center;
    border-radius: 9px;
    display: inline-flex;
    justify-content: center;
    min-height: 44px;
    padding: 9px 16px;
}


/* ==========================================================
   AUDIT TABLE
========================================================== */

.audit-table {
    min-width: 1050px;
    width: 100%;
}

#audit-records .table-responsive {
    border: 1px solid #e3eaf3;
    border-radius: 11px;
    overflow-x: auto;
}

#audit-records .audit-table th {
    background: #f1f5f9;
    border-bottom: 1px solid #dfe7f0;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    padding: 14px 12px;
    white-space: nowrap;
}

#audit-records .audit-table td {
    border-bottom: 1px solid #e8edf3;
    color: #334155;
    font-size: 13px;
    line-height: 1.45;
    padding: 13px 12px;
    vertical-align: middle;
}

#audit-records .audit-table tbody tr:nth-child(even) {
    background: #f8fafc;
}

#audit-records .audit-table tbody tr:hover {
    background: #f1f7ff;
}

.audit-description {
    min-width: 250px;
    max-width: 400px;
}


/* ==========================================================
   ACTION BADGES
========================================================== */

.audit-action {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.audit-add {
    background: #dcfce7;
    color: #15803d;
}

.audit-update {
    background: #dbeafe;
    color: #1d4ed8;
}

.audit-delete {
    background: #fee2e2;
    color: #b91c1c;
}

.audit-login {
    background: #ede9fe;
    color: #6d28d9;
}

.audit-logout {
    background: #f1f5f9;
    color: #475569;
}

.audit-password {
    background: #fef3c7;
    color: #b45309;
}

.audit-print {
    background: #d1fae5;
    color: #047857;
}


/* ==========================================================
   MOBILE
========================================================== */

@media (max-width: 768px) {

    #audit-records {
        padding: 20px 16px;
    }

    #audit-records .section-header {
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 12px;
    }

    .audit-filter-form {
        flex-direction: column;
        align-items: stretch;
        width: 100%;
    }

    .audit-filter-group input,
    .audit-filter-group select {
        width: 100%;
        box-sizing: border-box;
    }

    .audit-filter-actions {
        width: 100%;
    }

    .audit-filter-actions .btn {
        flex: 1;
        text-align: center;
    }

    .audit-table {
        min-width: 1050px;
    }

}




/* ==========================================================
   AUDIT HEADER ACTIONS
========================================================== */

.audit-header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}


/* ==========================================================
   PRINT
========================================================== */



@media print {
    @page {
        size: A4 landscape;
        margin: 10mm;
    }

    /* Reset the page for printing */
    html,
    body {
        width: 100% !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
        color: #000 !important;
        overflow: visible !important;
    }

    /* Hide all elements by default */
    body * {
        visibility: hidden !important;
    }

    /* Show the Audit Logs title and subtitle */
    .page-header,
    .page-header h1,
    .page-header p {
        visibility: visible !important;
    }

    .page-header {
        display: block !important;
        position: relative !important;
        width: 100% !important;
        margin: 0 0 15px !important;
        padding: 0 !important;
        background: #fff !important;
        border: none !important;
        box-shadow: none !important;
    }

    .page-header h1 {
        display: block !important;
        margin: 0 0 5px !important;
        padding: 0 !important;
        color: #17233d !important;
        font-size: 24px !important;
        font-weight: 700 !important;
    }

    .page-header p {
        display: block !important;
        margin: 0 !important;
        padding: 0 !important;
        color: #475569 !important;
        font-size: 12px !important;
    }

    /* Hide page actions */
    .audit-header-actions {
        display: none !important;
    }

    /* Show only the audit records container and its contents */
    #audit-records,
    #audit-records * {
        visibility: visible !important;
    }

    #audit-records {
        display: block !important;
        position: relative !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        background: #fff !important;
        overflow: visible !important;
    }

    /* Hide filters, record count and pagination */
    #audit-records .section-header,
    #audit-records .audit-filter-form,
    #audit-records .audit-total,
    #audit-records .pagination-wrapper,
    #audit-records .pagination,
    #audit-records .pagination-info {
        display: none !important;
    }

    /* Display the audit records table */
    #audit-records .table-responsive {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        border: none !important;
        border-radius: 0 !important;
        overflow: visible !important;
    }

    #audit-records .audit-table {
        display: table !important;
        width: 100% !important;
        min-width: 0 !important;
        margin: 0 !important;
        border-collapse: collapse !important;
        table-layout: auto !important;
        font-size: 9pt !important;
    }

    #audit-records .audit-table thead {
        display: table-header-group !important;
    }

    #audit-records .audit-table tbody {
        display: table-row-group !important;
    }

    #audit-records .audit-table tr {
        display: table-row !important;
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }

    #audit-records .audit-table th,
    #audit-records .audit-table td {
        display: table-cell !important;
        padding: 6px !important;
        color: #000 !important;
        background: #fff !important;
        border: 1px solid #cbd5e1 !important;
        font-size: 9pt !important;
        line-height: 1.3 !important;
        text-align: left !important;
        white-space: normal !important;
        overflow-wrap: anywhere !important;
    }

    #audit-records .audit-table th {
        font-weight: 700 !important;
        background: #f1f5f9 !important;
    }

    #audit-records .audit-action,
    #audit-records .role-badge {
        padding: 2px 4px !important;
        color: #000 !important;
        background: #fff !important;
        border: 1px solid #999 !important;
        box-shadow: none !important;
    }

    /* Hide the shared sidebar, navigation and footer */
    .sidebar,
    .navbar,
    .topbar,
    nav,
    footer,
    .footer,
    .online-status,
    .online-indicator,
    .status-indicator,
    .notification,
    .notification-bell,
    [class*="online"],
    [class*="Online"],
    [class*="status-widget"] {
        display: none !important;
        visibility: hidden !important;
    }

    /* Prevent unwanted page breaks inside the report */
    #audit-records,
    #audit-records .table-responsive {
        break-inside: auto !important;
        page-break-inside: auto !important;
    }

    a {
        color: #000 !important;
        text-decoration: none !important;
    }
}
    

/* Professional Audit Logs Pagination */
.pagination-wrapper {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 20px 0 4px;
    margin-top: 20px;
    border-top: 1px solid #e2e8f0;
}

.pagination-info {
    flex: 0 0 auto;
    color: #64748b;
    font-size: 13px;
    line-height: 1.6;
}

.pagination {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 7px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.pagination a,
.pagination button,
.pagination .page-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 12px;
    border: 1px solid #dbe3ef;
    border-radius: 9px;
    background: #fff;
    color: #334155;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: background-color .2s, border-color .2s, color .2s;
}

.pagination a:hover,
.pagination button:hover,
.pagination .page-link:hover {
    background: #eff6ff;
    border-color: #2563eb;
    color: #1d4ed8;
}

.pagination .active,
.pagination .active a,
.pagination .active .page-link,
.pagination a.active,
.pagination button.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.pagination .disabled,
.pagination .disabled a,
.pagination a.disabled,
.pagination button:disabled {
    opacity: .45;
    pointer-events: none;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .pagination-wrapper {
        flex-direction: column;
        align-items: flex-start;
    }

    .pagination {
        justify-content: flex-start;
        gap: 6px;
    }

    .pagination a,
    .pagination button,
    .pagination .page-link {
        min-width: 36px;
        height: 36px;
        padding: 0 9px;
    }
}


/* Professional compact pagination */
.pagination-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    width: 100%;
    padding: 20px 12px;
    margin-top: 18px;
    border-top: 1px solid #e2e8f0;
    box-sizing: border-box;
}

.pagination-info {
    width: 100%;
    text-align: center;
    color: #64748b;
    font-size: 13px;
    line-height: 1.5;
}

.pagination {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 7px;
    width: 100%;
    margin: 0;
    padding: 0;
    list-style: none;
}

.pagination a,
.pagination button,
.pagination .page-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 38px;
    height: 38px;
    padding: 0 10px;
    border: 1px solid #dbe3ef;
    border-radius: 9px;
    background: #fff;
    color: #334155;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    box-sizing: border-box;
    transition: background .2s ease, border-color .2s ease;
}

.pagination a:hover,
.pagination button:hover,
.pagination .page-link:hover {
    background: #eff6ff;
    border-color: #2563eb;
    color: #1d4ed8;
}

.pagination .active,
.pagination .active a,
.pagination .active .page-link,
.pagination a.active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.pagination .disabled,
.pagination .disabled a,
.pagination a.disabled,
.pagination button:disabled {
    opacity: .45;
    pointer-events: none;
}

@media (max-width: 768px) {
    .pagination {
        gap: 5px;
    }

    .pagination a,
    .pagination button,
    .pagination .page-link {
        min-width: 34px;
        height: 34px;
        padding: 0 7px;
        font-size: 12px;
    }
}

    
/* Professional Audit Logs Header Spacing */
.page-header {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 28px;
}

.page-header > div:first-child {
    width: 100%;
}

.page-header h1 {
    margin: 0 0 8px;
    line-height: 1.25;
}

.page-header p {
    margin: 0;
    line-height: 1.6;
}

.audit-header-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 2px;
}

.audit-header-actions .btn {
    margin: 0;
}
	
</style>

<script>

/* ==========================================================
   PRINT AUDIT LOGS
========================================================== */

function printAuditLogs() {
    window.print();
}

</script>


<?php

$stmt->close();

include "../includes/footer.php";

?>
