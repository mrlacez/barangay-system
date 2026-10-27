<?php

require_once __DIR__ . "/../config/announcements_auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/audit.php";

// UI permission only; the conversion POST handler also enforces this server-side.
$canConvertBlotter = in_array(
    strtolower(trim((string) ($_SESSION["role"] ?? ""))),
    ["admin", "administrator"],
    true
);

/* =========================
   NO CACHE
========================= */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

/* =========================
   CSRF TOKEN
========================= */

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];



/* =========================
   UPDATE REQUEST
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_request"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        $_SESSION["error_message"] = "Invalid security token.";
        header("Location: service_requests.php");
        exit;
    }

    $id = (int) ($_POST["id"] ?? 0);
    $status = trim($_POST["status"] ?? "");
    $admin_remarks = trim($_POST["admin_remarks"] ?? "");

    $allowed_statuses = [
        "Pending",
        "Under Review",
        "Approved",
        "Rejected",
        "Ready for Pickup",
        "Completed"
    ];

    if ($id <= 0) {
        $_SESSION["error_message"] = "Invalid request.";
        header("Location: service_requests.php");
        exit;
    }

    if (!in_array($status, $allowed_statuses, true)) {
        $_SESSION["error_message"] = "Invalid request status.";
        header("Location: service_requests.php");
        exit;
    }

    if (mb_strlen($admin_remarks) > 5000) {
        $_SESSION["error_message"] = "Remarks are too long.";
        header("Location: service_requests.php");
        exit;
    }

    /* Get current request */
    $check_stmt = $conn->prepare("
        SELECT reference_no, status
        FROM service_requests
        WHERE id = ?
        LIMIT 1
    ");

    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();

    $check_result = $check_stmt->get_result();
    $request = $check_result->fetch_assoc();

    $check_stmt->close();

    if (!$request) {
        $_SESSION["error_message"] = "Service request not found.";
        header("Location: service_requests.php");
        exit;
    }

    /* Update */
    $stmt = $conn->prepare("
        UPDATE service_requests
        SET
            status = ?,
            admin_remarks = ?,
            date_issued = CASE
                WHEN service_type IN (
                    'Barangay Certificate',
                    'Certificate of Residency',
                    'Certificate of Indigency'
                ) AND ? = 'Approved'
                THEN COALESCE(date_issued, CURDATE())
                ELSE date_issued
            END
        WHERE id = ?
    ");

    $stmt->bind_param(
        "sssi",
        $status,
        $admin_remarks,
        $status,
        $id
    );

    if ($stmt->execute()) {

        if ($request["status"] !== $status) {
            $audit_description =
                "Updated service request {$request["reference_no"]}: status changed from {$request["status"]} to {$status}.";
        } else {
            $audit_description =
                "Updated service request {$request["reference_no"]}: admin remarks updated.";
        }

        addAuditLog(
            $conn,
            "UPDATE",
            "Service Requests",
            $audit_description,
            $id
        );

        $_SESSION["success_message"] =
            "Service request {$request["reference_no"]} updated successfully.";

    } else {

        $_SESSION["error_message"] =
            "Failed to update the service request.";
    }

    $stmt->close();

    header("Location: service_requests.php");
    exit;
}

/* =========================
   CONVERT BLOTTER REQUEST
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["convert_blotter"])
) {

    // Only Admin/Administrator may convert a request into an official blotter record.
    // Staff can still access this page and update service request status/remarks.
    if (
        !in_array(
            strtolower(trim((string) ($_SESSION["role"] ?? ""))),
            ["admin", "administrator"],
            true
        )
    ) {
        http_response_code(403);
        exit("403 Forbidden: Admin access required.");
    }

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals(
            $_SESSION["csrf_token"],
            $_POST["csrf_token"]
        )
    ) {
        $_SESSION["error_message"] =
            "Invalid security token.";

        header("Location: service_requests.php");
        exit;
    }

    $id = (int) ($_POST["id"] ?? 0);

    if ($id <= 0) {
        $_SESSION["error_message"] =
            "Invalid service request.";

        header("Location: service_requests.php");
        exit;
    }

    try {

        $conn->begin_transaction();

        /*
         * Get the service request and lock it
         * to prevent duplicate conversion.
         */
        $checkStmt = $conn->prepare("
            SELECT
                id,
                reference_no,
                service_type,
                full_name,
                respondent,
                incident,
                incident_date,
                blotter_remarks,
                blotter_id
            FROM service_requests
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $checkStmt->bind_param(
            "i",
            $id
        );

        $checkStmt->execute();

        $result = $checkStmt->get_result();

        $request = $result->fetch_assoc();

        $checkStmt->close();


        if (!$request) {
            throw new Exception(
                "Service request not found."
            );
        }


        /*
         * Only Blotter Assistance can be converted.
         */
        if (
            $request["service_type"] !==
            "Blotter Assistance"
        ) {
            throw new Exception(
                "Only Blotter Assistance requests can be converted."
            );
        }


        /*
         * Prevent duplicate conversion.
         */
        if (
            !empty($request["blotter_id"])
        ) {
            throw new Exception(
                "This request has already been converted to a blotter record."
            );
        }


        /*
         * Incident is required.
         */
        if (
            trim((string) $request["incident"]) === ""
        ) {
            throw new Exception(
                "The incident information is required before conversion."
            );
        }


        /*
         * Insert into the official blotter table.
         */
        $insertStmt = $conn->prepare("
            INSERT INTO blotter (
                complainant,
                respondent,
                incident,
                incident_date,
                status,
                remarks
            )
            VALUES (
                ?,
                ?,
                ?,
                NULLIF(?, ''),
                'Pending',
                ?
            )
        ");

        $incidentDate =
            (string) ($request["incident_date"] ?? "");

        $blotterRemarks =
            (string) ($request["blotter_remarks"] ?? "");

        $insertStmt->bind_param(
            "sssss",
            $request["full_name"],
            $request["respondent"],
            $request["incident"],
            $incidentDate,
            $blotterRemarks
        );

        if (!$insertStmt->execute()) {
            throw new Exception(
                "Unable to create the blotter record."
            );
        }

        $blotterId =
            $insertStmt->insert_id;

        $insertStmt->close();


        /*
         * Link the service request
         * to the newly created blotter record.
         */
        $updateStmt = $conn->prepare("
            UPDATE service_requests
            SET
                blotter_id = ?,
                status = 'Completed'
            WHERE id = ?
              AND blotter_id IS NULL
        ");

        $updateStmt->bind_param(
            "ii",
            $blotterId,
            $id
        );

        if (!$updateStmt->execute()) {
            throw new Exception(
                "Unable to link the service request to the blotter record."
            );
        }

        if ($updateStmt->affected_rows !== 1) {
            throw new Exception(
                "This request may have already been converted."
            );
        }

        $updateStmt->close();


        /*
         * Audit logs
         */
        addAuditLog(
            $conn,
            "ADD",
            "Blotter",
            "Converted service request {$request["reference_no"]} into blotter record #{$blotterId}.",
            $blotterId
        );

        addAuditLog(
            $conn,
            "UPDATE",
            "Service Requests",
            "Converted service request {$request["reference_no"]} into blotter record #{$blotterId}.",
            $id
        );


        $conn->commit();


        $_SESSION["success_message"] =
            "Blotter Assistance request {$request["reference_no"]} was successfully converted to Blotter Record #{$blotterId}.";

    } catch (Throwable $e) {

        $conn->rollback();

        $_SESSION["error_message"] =
            $e->getMessage();
    }


    header("Location: service_requests.php");
    exit;
}

/* =========================
   FLASH MESSAGE
========================= */

$success = "";

if (isset($_SESSION["success_message"])) {
    $success = $_SESSION["success_message"];
    unset($_SESSION["success_message"]);
}

$error = "";

if (isset($_SESSION["error_message"])) {
    $error = $_SESSION["error_message"];
    unset($_SESSION["error_message"]);
}



/* =========================
   SEARCH
========================= */

$search = trim($_GET["search"] ?? "");
$status_filter = trim($_GET["status"] ?? "");
$service_filter = trim($_GET["service"] ?? "");
$focusReference = trim($_GET["focus"] ?? "");


/* =========================
   ALLOWED SERVICES
========================= */

$allowed_services = [
    "Barangay Certificate",
    "Certificate of Residency",
    "Certificate of Indigency",
    "Blotter Assistance",
    "Community Assistance"
];

if (
    $service_filter !== "" &&
    !in_array($service_filter, $allowed_services, true)
) {
    $service_filter = "";
}

$allowed_statuses = [
    "Pending",
    "Under Review",
    "Approved",
    "Rejected",
    "Ready for Pickup",
    "Completed"
];

if ($status_filter !== "" && !in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = "";
}

/* =========================
   PAGINATION
========================= */

$recordsPerPage = 10;

$currentPage = isset($_GET["page"])
    ? max(1, (int) $_GET["page"])
    : 1;

/* =========================
   COUNT RECORDS
========================= */

$countSql = "
    SELECT COUNT(*) AS total
    FROM service_requests
    WHERE 1=1
";

$countTypes = "";
$countParams = [];

if ($search !== "") {
    $countSql .= "
        AND (
            reference_no LIKE ?
            OR full_name LIKE ?
            OR service_type LIKE ?
            OR contact_number LIKE ?
        )
    ";

    $searchParam = "%" . $search . "%";

    $countTypes .= "ssss";

    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
    $countParams[] = $searchParam;
}

if ($status_filter !== "") {
    $countSql .= " AND status = ?";
    $countTypes .= "s";
    $countParams[] = $status_filter;
}

if ($service_filter !== "") {
    $countSql .= " AND service_type = ?";
    $countTypes .= "s";
    $countParams[] = $service_filter;
}

$countStmt = $conn->prepare($countSql);

if (!empty($countParams)) {
    $countStmt->bind_param(
        $countTypes,
        ...$countParams
    );
}

$countStmt->execute();

$countResult = $countStmt->get_result();
$totalRecords = (int) ($countResult->fetch_assoc()["total"] ?? 0);

$countStmt->close();

$totalPages = max(
    1,
    (int) ceil($totalRecords / $recordsPerPage)
);

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $recordsPerPage;

/* =========================
   GET REQUESTS
========================= */

$sql = "
    SELECT
        id,
        reference_no,
        service_type,
        full_name,
        first_name,
        middle_name,
        last_name,
        suffix,
        birth_date,
        gender,
        civil_status,
        respondent,
        incident,
        incident_date,
        blotter_remarks,
        blotter_id,
        address,
        contact_number,
        email,
        purpose,
        additional_details,
        status,
        admin_remarks,
        created_at,
        updated_at
    FROM service_requests
    WHERE 1=1
";

$types = "";
$params = [];

if ($search !== "") {

    $sql .= "
        AND (
            reference_no LIKE ?
            OR full_name LIKE ?
            OR service_type LIKE ?
            OR contact_number LIKE ?
        )
    ";

    $searchParam = "%" . $search . "%";

    $types .= "ssss";

    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($status_filter !== "") {
    $sql .= " AND status = ?";
    $types .= "s";
    $params[] = $status_filter;
}

if ($service_filter !== "") {
    $sql .= " AND service_type = ?";
    $types .= "s";
    $params[] = $service_filter;
}

$sql .= "
    ORDER BY id DESC
    LIMIT ? OFFSET ?
";

$types .= "ii";

$params[] = $recordsPerPage;
$params[] = $offset;

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    $types,
    ...$params
);

$stmt->execute();

$result = $stmt->get_result();

/* =========================
   PAGE
========================= */

include "../includes/header.php";

?>

<section class="page-header">

    <div>
        <h1>📋 Service Requests</h1>

        <p>
            Manage online certificates and blotter assistance requests here. Convert blotter assistance to an official Blotter Record when appropriate.
        </p>
    </div>

</section>


<section class="content-section service-request-records" id="service-request-records">

    <div class="section-header">

        <div>
            <h2>Service Request Records</h2>

            <p>
                Total Records:
                <strong><?= number_format($totalRecords) ?></strong>
            </p>
        </div>

    </div>


    <!-- SEARCH -->

    <form method="GET" class="search-filter-bar" role="search">

        <input
            type="text"
            name="search"
            value="<?= htmlspecialchars(
                $search,
                ENT_QUOTES,
                "UTF-8"
            ) ?>"
            placeholder="Search reference no., name, service, or contact..."
        >

        <!-- SERVICE FILTER -->
        <select name="service">

            <option value="">
                All Services
            </option>

            <?php foreach ($allowed_services as $service): ?>

                <option
                    value="<?= htmlspecialchars(
                        $service,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                    <?= $service_filter === $service
                        ? "selected"
                        : "" ?>
                >
                    <?= htmlspecialchars(
                        $service,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </option>

            <?php endforeach; ?>

        </select>


        <!-- STATUS FILTER -->
        <select name="status">

            <option value="">
                All Status
            </option>

            <?php
            $statuses = [
                "Pending",
                "Under Review",
                "Approved",
                "Rejected",
                "Ready for Pickup",
                "Completed"
            ];
            ?>

            <?php foreach ($statuses as $status): ?>

                <option
                    value="<?= htmlspecialchars(
                        $status,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                    <?= $status_filter === $status
                        ? "selected"
                        : "" ?>
                >
                    <?= htmlspecialchars(
                        $status,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </option>

            <?php endforeach; ?>

        </select>


        <button
            type="submit"
            class="btn-primary"
        >
            🔎 Search
        </button>


        <a
            href="service_requests.php"
            class="btn-secondary"
        >
            Reset
        </a>

    </form>


    <!-- TABLE -->

    <div class="table-wrapper">

        <table class="data-table service-requests-table">

            <thead>

                <tr>

                    <th>#</th>

                    <th>Reference No.</th>

                    <th>Resident</th>

                    <th>Service</th>

                    <th>Contact</th>

                    <th>Status</th>

                    <th>Date Submitted</th>

                    <th>Action</th>

                </tr>

            </thead>

            <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php $counter = $offset + 1; ?>

                    <?php while ($row = $result->fetch_assoc()): ?>

                        <?php

                        $statusClass = match ($row["status"]) {

                            "Pending" => "status-pending",

                            "Under Review" => "status-review",

                            "Approved" => "status-approved",

                            "Rejected" => "status-rejected",

                            "Ready for Pickup" => "status-ready",

                            "Completed" => "status-completed",

                            default => "status-default"
                        };

                        ?>

                        <tr>

                            <td>
                                <?= $counter++ ?>
                            </td>

                            <td>
                                <strong>
                                    <?= htmlspecialchars(
                                        $row["reference_no"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </strong>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["full_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>

                                <?php

                                $serviceClass = match ($row["service_type"]) {

                                    "Community Assistance"
                                        => "service-community",

                                    "Blotter Assistance"
                                        => "service-blotter",

                                    "Barangay Certificate",
                                    "Certificate of Residency",
                                    "Certificate of Indigency"
                                        => "service-certificate",

                                    default
                                        => "service-default"
                                };

                                ?>

                                <span class="service-badge <?= $serviceClass ?>">

                                    <?= htmlspecialchars(
                                        $row["service_type"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row["contact_number"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </td>

                            <td>

                                <span class="status-badge <?= $statusClass ?>">

                                    <?= htmlspecialchars(
                                        $row["status"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>

                                </span>

                            </td>

                            <td>

                                <?= date(
                                    "M d, Y h:i A",
                                    strtotime($row["created_at"])
                                ) ?>

                            </td>

                            <td>

                                <button
                                    type="button"
                                    class="btn-small btn-view"
                                    data-reference="<?= htmlspecialchars($row["reference_no"], ENT_QUOTES, "UTF-8") ?>"
                                    onclick='openRequestModal(
                                        <?= json_encode(
                                            $row,
                                            JSON_HEX_TAG |
                                            JSON_HEX_APOS |
                                            JSON_HEX_QUOT |
                                            JSON_HEX_AMP
                                        ) ?>
                                    )'
                                >
                                    👁 View
                                </button>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="8"
                            class="empty-state"
                        >

                            📋 No service requests found.

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>


    <!-- PAGINATION -->

    <?php if ($totalPages > 1): ?>

        <div class="pagination-wrapper">

            <div class="pagination-info">

                Showing
                <strong><?= $totalRecords > 0 ? $offset + 1 : 0 ?></strong>
                –
                <strong>
                    <?= min(
                        $offset + $recordsPerPage,
                        $totalRecords
                    ) ?>
                </strong>

                of

                <strong><?= $totalRecords ?></strong>
                records

            </div>


            <div class="pagination">

                <?php if ($currentPage > 1): ?>

                    <a
                        href="service_requests.php?search=<?= urlencode($search) ?>&service=<?= urlencode($service_filter) ?>&status=<?= urlencode($status_filter) ?>&page=<?= $currentPage - 1 ?>#service-request-records"
                        class="page-link"
                    >
                        Previous
                    </a>

                <?php endif; ?>


                <?php for ($page = 1; $page <= $totalPages; $page++): ?>

                    <a
                        href="service_requests.php?search=<?= urlencode($search) ?>&service=<?= urlencode($service_filter) ?>&status=<?= urlencode($status_filter) ?>&page=<?= $page ?>#service-request-records"
                        class="page-link <?= $page === $currentPage ? "active" : "" ?>"
                    >
                        <?= $page ?>
                    </a>

                <?php endfor; ?>


                <?php if ($currentPage < $totalPages): ?>

                    <a
                        href="service_requests.php?search=<?= urlencode($search) ?>&service=<?= urlencode($service_filter) ?>&status=<?= urlencode($status_filter) ?>&page=<?= $currentPage + 1 ?>#service-request-records"
                        class="page-link"
                    >
                        Next
                    </a>

                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>

</section>


<!-- =========================
     VIEW / UPDATE MODAL
========================= -->

<div
    id="requestModal"
    class="modal-overlay"
    style="display:none;"
>

    <div class="modal-box service-request-modal">

        <div class="modal-header">

            <h2>📋 Service Request Details</h2>

            <button
                type="button"
                class="modal-close"
                onclick="closeRequestModal()"
            >
                &times;
            </button>

        </div>


        <div class="modal-body">

            <div class="request-details">

                <div class="detail-item">

                    <span>Reference Number</span>

                    <strong id="modalReference"></strong>

                </div>

                <div class="detail-item">

                    <span>Service Requested</span>

                    <strong id="modalService"></strong>

                </div>

                <div class="detail-item">

                    <span id="modalNameLabel">Full Name</span>

                    <strong id="modalName"></strong>

                </div>

                <div class="detail-item">

                    <span>First Name</span>

                    <strong id="modalFirstName"></strong>

                </div>

                <div class="detail-item">

                    <span>Middle Name</span>

                    <strong id="modalMiddleName"></strong>

                </div>

                <div class="detail-item">

                    <span>Last Name</span>

                    <strong id="modalLastName"></strong>

                </div>

                <div class="detail-item">

                    <span>Suffix</span>

                    <strong id="modalSuffix"></strong>

                </div>

                <div class="detail-item">

                    <span>Birth Date</span>

                    <strong id="modalBirthDate"></strong>

                </div>

                <div class="detail-item">

                    <span>Gender</span>

                    <strong id="modalGender"></strong>

                </div>

                <div class="detail-item">

                    <span>Civil Status</span>

                    <strong id="modalCivilStatus"></strong>

                </div>


                <div
                    class="detail-item blotter-only-detail"
                    id="modalRespondentBox"
                    style="display:none;"
                >

                    <span>Respondent</span>

                    <strong id="modalRespondent"></strong>

                </div>


                <div
                    class="detail-item blotter-only-detail"
                    id="modalIncidentBox"
                    style="display:none;"
                >

                    <span>Incident</span>

                    <strong id="modalIncident"></strong>

                </div>


                <div
                    class="detail-item blotter-only-detail"
                    id="modalIncidentDateBox"
                    style="display:none;"
                >

                    <span>Incident Date</span>

                    <strong id="modalIncidentDate"></strong>

                </div>


                <div
                    class="detail-item blotter-only-detail"
                    id="modalBlotterRemarksBox"
                    style="display:none;"
                >

                    <span>Remarks</span>

                    <strong id="modalBlotterRemarks"></strong>

                </div>

                <div class="detail-item">

                    <span>Address</span>

                    <strong id="modalAddress"></strong>

                </div>

                <div class="detail-item">

                    <span>Contact Number</span>

                    <strong id="modalContact"></strong>

                </div>

                <div class="detail-item">

                    <span>Email</span>

                    <strong id="modalEmail"></strong>

                </div>

                <div
                    class="detail-item regular-only-detail"
                    id="modalPurposeBox"
                >

                    <span>Purpose</span>

                    <strong id="modalPurpose"></strong>

                </div>

                <div
                    class="detail-item regular-only-detail"
                    id="modalAdditionalBox"
                >

                    <span>Additional Details</span>

                    <strong id="modalAdditional"></strong>

                </div>

                <div class="detail-item">

                    <span>Date Submitted</span>

                    <strong id="modalCreated"></strong>

                </div>

            </div>


            <hr>


            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $csrf_token,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="update_request"
                    value="1"
                >

                <input
                    type="hidden"
                    name="id"
                    id="modalId"
                    value=""
                >


                <div class="form-group">

                    <label>
                        Request Status
                    </label>

                    <select
                        name="status"
                        id="modalStatus"
                        required
                    >

                        <?php foreach ($statuses as $status): ?>

                            <option value="<?= htmlspecialchars(
                                $status,
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>">

                                <?= htmlspecialchars(
                                    $status,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Admin Remarks
                    </label>

                    <textarea
                        name="admin_remarks"
                        id="modalRemarks"
                        rows="5"
                        placeholder="Enter remarks or instructions for the resident..."
                    ></textarea>

                </div>


                <div class="modal-actions">

                    <a
                        href="#"
                        id="modalPrintCertificate"
                        class="btn-print"
                        style="display:none;"
                        target="_blank"
                        rel="noopener"
                    >
                        🖨️ Print Certificate
                    </a>

                    <button
                        type="button"
                        id="convertBlotterButton"
                        class="btn-convert-blotter"
                        style="display:none;"
                        onclick="return showConvertBlotterPopup(event)"
                    >
                        🟠 Convert to Blotter Record
                    </button>

                    <button
                        type="button"
                        class="btn-secondary"
                        onclick="closeRequestModal()"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn-primary"
                    >
                        💾 Update Request
                    </button>

                </div>

            </form>

        <form
            method="POST"
            id="convertBlotterForm"
            style="display:none;"
        >

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrf_token,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <input
                type="hidden"
                name="convert_blotter"
                value="1"
            >

            <input
                type="hidden"
                name="id"
                id="convertBlotterId"
                value=""
            >

        </form>



    </div>
</div>

</div>

<!-- =========================
     CONVERT BLOTTER CONFIRMATION
========================= -->

<div id="convertConfirmPopup" class="convert-confirm-overlay" role="dialog" aria-modal="true" aria-labelledby="convertConfirmTitle" style="display:none;position:fixed;inset:0;z-index:2147483647;background:rgba(15,23,42,.65);align-items:center;justify-content:center;padding:20px;box-sizing:border-box;">

    <div class="convert-confirm-box" style="width:100%;max-width:430px;background:#fff;border-radius:14px;padding:28px;text-align:center;box-shadow:0 20px 50px rgba(0,0,0,.3);box-sizing:border-box;">

        <div class="convert-confirm-icon" style="width:55px;height:55px;margin:0 auto 15px;border-radius:50%;background:#fff7ed;display:flex;align-items:center;justify-content:center;font-size:28px;">
            🟠
        </div>

        <h3 id="convertConfirmTitle" style="margin:0 0 10px;color:#172554;font-size:20px;">Convert to Blotter Record?</h3>

        <p style="margin:8px 0;color:#475569;font-size:14px;line-height:1.5;">
            Convert this Blotter Assistance request into an official
            Blotter Record?
        </p>

        <p class="convert-confirm-note" style="margin:8px 0;color:#64748b;font-size:13px;line-height:1.5;">
            This action will create a new record in the Blotter Records module.
        </p>

        <div class="convert-confirm-actions" style="display:flex;justify-content:center;gap:10px;margin-top:22px;flex-wrap:wrap;">

            <button
                type="button"
                class="btn-confirm-cancel"
                style="border:0;border-radius:7px;padding:10px 18px;font-weight:600;cursor:pointer;background:#e5e7eb;color:#374151;"
                onclick="closeConvertBlotterPopup()"
            >
                Cancel
            </button>

            <button
                type="button"
                class="btn-confirm-ok"
                style="border:0;border-radius:7px;padding:10px 18px;font-weight:600;cursor:pointer;background:#f59e0b;color:#fff;"
                onclick="submitBlotterConversion()"
            >
                ✓ Convert
            </button>

        </div>

    </div>

</div>


<!-- =========================
     SUCCESS / ERROR POPUP
========================= -->

<?php if ($success !== ""): ?>

    <div
        id="successPopup"
        class="custom-popup success-popup"
        style="
            display:flex !important;
            position:fixed !important;
            top:25px !important;
            right:25px !important;
            z-index:99999999 !important;
        "
    >

        <div class="popup-icon">
            ✓
        </div>

        <div>

            <strong>Success</strong>

            <p>
                <?= htmlspecialchars(
                    $success,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </p>

        </div>

        <button
            type="button"
            onclick="closePopup('successPopup')"
        >
            &times;
        </button>

    </div>

<?php endif; ?>


<?php if ($error !== ""): ?>

    <div id="errorPopup" class="custom-popup error-popup">

        <div class="popup-icon">
            !
        </div>

        <div>

            <strong>Error</strong>

            <p>
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </p>

        </div>

        <button
            type="button"
            onclick="closePopup('errorPopup')"
        >
            &times;
        </button>

    </div>

<?php endif; ?>


<style>
.page-header {
    align-items: flex-start;
    display: flex;
    justify-content: space-between;
    margin: 0 0 24px;
}

.page-header h1 {
    color: #172554;
    font-size: clamp(24px, 3vw, 32px);
    line-height: 1.2;
    margin: 0 0 7px;
}

.page-header p { color: #64748b; margin: 0; }

.service-request-records {
    border: 1px solid #e8edf3;
    border-top: 5px solid #2563eb;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(15, 23, 42, .06);
    padding: 24px;
}

.service-request-records .section-header {
    align-items: center;
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
}

.service-request-records .section-header h2 { color: #172554; font-size: 20px; margin: 0 0 5px; }
.service-request-records .section-header p { color: #64748b; margin: 0; }

.search-filter-bar {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 0 0 18px;
}

.search-filter-bar input,
.search-filter-bar select {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    color: #1e293b;
    font: inherit;
    height: 42px;
    padding: 0 12px;
}

.search-filter-bar input { flex: 1 1 330px; min-width: 220px; }
.search-filter-bar select { min-width: 160px; }
.search-filter-bar input:focus, .search-filter-bar select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, .12); outline: 0; }
.search-filter-bar .btn-primary, .search-filter-bar .btn-secondary { align-items: center; display: inline-flex; height: 42px; justify-content: center; margin: 0; padding: 0 17px; text-decoration: none; }

.service-request-records .table-wrapper {
    border: 1px solid #e5eaf1;
    border-radius: 10px;
    overflow-x: auto;
}

.service-request-records .data-table {
    border-collapse: separate;
    border-spacing: 0;
    min-width: 940px;
    width: 100%;
}

.service-request-records .data-table thead th {
    background: #f8fafc;
    border-bottom: 1px solid #e2e8f0;
    color: #475569;
    font-size: 12px;
    font-weight: 700;
    padding: 14px 12px;
    text-align: left;
    white-space: nowrap;
}

.service-request-records .data-table tbody td {
    border-bottom: 1px solid #edf1f5;
    color: #334155;
    font-size: 13px;
    line-height: 1.45;
    padding: 14px 12px;
    vertical-align: middle;
}

.service-request-records .data-table tbody tr:last-child td { border-bottom: 0; }
.service-request-records .data-table tbody tr:hover { background: #f8fbff; }
.service-request-records .data-table td:nth-child(2) strong { color: #172554; white-space: nowrap; }
.service-request-records .data-table td:nth-child(3) { font-weight: 600; }
.service-request-records .data-table .empty-state { color: #64748b; padding: 36px 16px; text-align: center; }

.service-request-records .btn-view {
    background: #2563eb;
    border: 0;
    border-radius: 8px;
    color: #fff;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
    padding: 9px 13px;
    transition: background .15s ease, transform .15s ease;
}

.service-request-records .btn-view:hover { background: #1d4ed8; transform: translateY(-1px); }
.service-request-records .pagination-wrapper { align-items: center; border-top: 1px solid #e8edf3; display: flex; flex-wrap: wrap; gap: 14px; justify-content: space-between; margin-top: 18px; padding-top: 17px; }
.service-request-records .pagination-info { color: #64748b; font-size: 13px; }
.service-request-records .pagination { display: flex; flex-wrap: wrap; gap: 6px; }
.service-request-records .page-link { align-items: center; border: 1px solid #dbe3ed; border-radius: 7px; color: #334155; display: inline-flex; font-size: 13px; min-height: 36px; padding: 0 12px; text-decoration: none; }
.service-request-records .page-link:hover { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
.service-request-records .page-link.active { background: #2563eb; border-color: #2563eb; color: #fff; }

@media (max-width: 700px) {
    .page-header { margin-bottom: 18px; }
    .service-request-records { padding: 16px; }
    .search-filter-bar { align-items: stretch; }
    .search-filter-bar input, .search-filter-bar select, .search-filter-bar .btn-primary, .search-filter-bar .btn-secondary { flex: 1 1 100%; width: 100%; }
}

.service-request-modal {

/* =========================
   SUCCESS / ERROR POPUP
========================= */

.custom-popup {
    position: fixed !important;
    top: 25px !important;
    right: 25px !important;

    z-index: 9999999 !important;

    min-width: 360px;
    max-width: 450px;

    display: flex !important;
    align-items: flex-start;
    gap: 12px;

    padding: 16px 18px;

    background: #ffffff !important;
    color: #111827 !important;

    border-radius: 12px;

    box-shadow: 0 15px 40px rgba(15, 23, 42, 0.25);

    opacity: 1 !important;
    visibility: visible !important;

    animation: popupSlideIn 0.3s ease;
}

.custom-popup .popup-icon {
    width: 36px;
    height: 36px;

    display: flex;
    align-items: center;
    justify-content: center;

    flex-shrink: 0;

    border-radius: 50%;

    font-size: 20px;
    font-weight: 700;
}

.custom-popup strong {
    display: block;
    margin-bottom: 3px;

    font-size: 15px;
}

.custom-popup p {
    margin: 0;

    font-size: 13px;
    line-height: 1.5;

    color: #475569;
}

.custom-popup > button {
    margin-left: auto;

    border: none;
    background: transparent;

    font-size: 22px;
    line-height: 1;

    color: #64748b;

    cursor: pointer;
}

.custom-popup > button:hover {
    color: #111827;
}


/* SUCCESS */

.success-popup {
    border-left: 5px solid #16a34a;
}

.success-popup .popup-icon {
    background: #dcfce7;
    color: #16a34a;
}

.success-popup strong {
    color: #166534;
}


/* ERROR */

.error-popup {
    border-left: 5px solid #dc2626;
}

.error-popup .popup-icon {
    background: #fee2e2;
    color: #dc2626;
}

.error-popup strong {
    color: #991b1b;
}


/* ANIMATION */

@keyframes popupSlideIn {

    from {
        opacity: 0;
        transform: translateX(30px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }

}


/* MOBILE */

@media (max-width: 600px) {

    .custom-popup {
        top: 15px;
        left: 15px;
        right: 15px;

        min-width: auto;
        max-width: none;
    }

}

/* =========================
   CONVERT BLOTTER CONFIRMATION
========================= */

.convert-confirm-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 2147483647;
    background: rgba(15, 23, 42, 0.65);
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.convert-confirm-box {
    width: 100%;
    max-width: 430px;
    background: #ffffff;
    border-radius: 14px;
    padding: 28px;
    text-align: center;
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.30);
    animation: convertPopupIn 0.2s ease;
}

.convert-confirm-icon {
    width: 55px;
    height: 55px;
    margin: 0 auto 15px;
    border-radius: 50%;
    background: #fff7ed;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.convert-confirm-box h3 {
    margin: 0 0 10px;
    color: #172554;
    font-size: 20px;
}

.convert-confirm-box p {
    margin: 8px 0;
    color: #475569;
    font-size: 14px;
    line-height: 1.5;
}

.convert-confirm-note {
    font-size: 13px !important;
    color: #64748b !important;
}

.convert-confirm-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 22px;
}

.btn-confirm-cancel,
.btn-confirm-ok {
    border: none;
    border-radius: 7px;
    padding: 10px 18px;
    font-weight: 600;
    cursor: pointer;
}

.btn-confirm-cancel {
    background: #e5e7eb;
    color: #374151;
}

.btn-confirm-cancel:hover {
    background: #d1d5db;
}

.btn-confirm-ok {
    background: #f59e0b;
    color: #ffffff;
}

.btn-confirm-ok:hover {
    background: #d97706;
}

@keyframes convertPopupIn {
    from {
        opacity: 0;
        transform: translateY(-15px) scale(0.98);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}


/* =========================
   SERVICE BADGES
========================= */

.service-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;

    padding: 6px 10px;

    border-radius: 999px;

    font-size: 11px;
    font-weight: 700;

    line-height: 1.2;

    white-space: nowrap;

    border: 1px solid transparent;
}


/* COMMUNITY ASSISTANCE */

.service-community {
    background: #f3e8ff;
    color: #7e22ce;
    border-color: #d8b4fe;
}

.service-community::before {
    content: "🏘️";
}


/* BLOTTER ASSISTANCE */

.service-blotter {
    background: #fff7ed;
    color: #c2410c;
    border-color: #fed7aa;
}

.service-blotter::before {
    content: "⚖️";
}


/* CERTIFICATES */

.service-certificate {
    background: #eff6ff;
    color: #1d4ed8;
    border-color: #bfdbfe;
}

.service-certificate::before {
    content: "📄";
}


/* OTHER */

.service-default {
    background: #f1f5f9;
    color: #475569;
    border-color: #cbd5e1;
}

</style>


<style>

.service-request-modal {
    max-width: 850px;
    width: 95%;
}


/* =========================
   SERVICE REQUEST MODAL
========================= */

.modal-overlay {
    position: fixed;
    inset: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.65);

    display: flex;
    align-items: center;
    justify-content: center;

    z-index: 9999;

    padding: 20px;

    overflow-y: auto;
}

.modal-box {
    background: #ffffff;
    border-radius: 14px;

    width: 100%;
    max-width: 850px;

    max-height: 90vh;
    overflow-y: auto;

    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.25);

    animation: modalFadeIn 0.2s ease;
}

@keyframes modalFadeIn {

    from {
        opacity: 0;
        transform: translateY(-20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }

}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 18px 22px;

    border-bottom: 1px solid #e5e7eb;
}

.modal-header h2 {
    margin: 0;

    color: #172554;

    font-size: 20px;
}

.modal-close {
    border: none;

    background: transparent;

    font-size: 28px;

    color: #64748b;

    cursor: pointer;

    line-height: 1;
}

.modal-close:hover {
    color: #dc2626;
}

.modal-body {
    padding: 22px;
}

.modal-body hr {
    border: none;

    border-top: 1px solid #e5e7eb;

    margin: 20px 0;
}

.modal-actions {
    display: flex;

    justify-content: flex-end;

    gap: 10px;

    margin-top: 15px;
}

.modal-actions button {
    cursor: pointer;
}

.modal-actions .btn-print {
    align-items: center;
    border-radius: 8px;
    display: inline-flex;
    font-weight: 700;
    padding: 10px 14px;
    text-decoration: none;
}

@media (max-width: 700px) {

    .modal-overlay {
        padding: 10px;
    }

    .modal-box {
        max-height: 95vh;
    }

    .modal-header {
        padding: 15px;
    }

    .modal-body {
        padding: 15px;
    }

}

.request-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.detail-item {
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px;
}

.detail-item span {
    display: block;
    font-size: 12px;
    color: #64748b;
    margin-bottom: 5px;
}

.detail-item strong {
    color: #172554;
    line-height: 1.5;
    word-break: break-word;
}

.status-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-review {
    background: #dbeafe;
    color: #1e40af;
}

.status-approved {
    background: #dcfce7;
    color: #166534;
}

.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}

.status-ready {
    background: #e0e7ff;
    color: #3730a3;
}

.status-completed {
    background: #d1fae5;
    color: #065f46;
}

.status-default {
    background: #e5e7eb;
    color: #374151;
}

.btn-view {
    background: #2563eb;
    color: white;
    border: none;
    padding: 7px 12px;
    border-radius: 6px;
    cursor: pointer;
}

.btn-convert-blotter {
    background: #f59e0b;
    color: white;
    border: none;
    padding: 8px 13px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

.btn-convert-blotter:hover {
    background: #d97706;
}

.btn-view:hover {
    background: #1d4ed8;
}

@media (max-width: 700px) {

    .request-details {
        grid-template-columns: 1fr;
    }

}

</style>


<script>

function openRequestModal(request) {

    document.getElementById("modalId").value =
        request.id || "";

    const printCertificateLink =
        document.getElementById("modalPrintCertificate");
    const certificateTypes = [
        "Barangay Certificate",
        "Certificate of Residency",
        "Certificate of Indigency"
    ];
    if (
        printCertificateLink &&
        certificateTypes.includes(request.service_type) &&
        request.status === "Approved"
    ) {
        printCertificateLink.href =
            "certificate_print.php?request_id=" + encodeURIComponent(request.id);
        printCertificateLink.style.display = "inline-flex";
    } else if (printCertificateLink) {
        printCertificateLink.style.display = "none";
        printCertificateLink.href = "#";
    }

            const convertButton =
                document.getElementById("convertBlotterButton");

            const convertId =
                document.getElementById("convertBlotterId");

            if (convertId) {
                convertId.value = request.id || "";
            }

            const canConvertBlotter = <?= $canConvertBlotter ? "true" : "false" ?>;

            if (
                convertButton &&
                canConvertBlotter &&
                request.service_type === "Blotter Assistance" &&
                !request.blotter_id
            ) {
                convertButton.style.display = "inline-block";
            } else if (convertButton) {
                convertButton.style.display = "none";
            }

    document.getElementById("modalReference").textContent =
        request.reference_no || "";

    document.getElementById("modalService").textContent =
        request.service_type || "";

    document.getElementById("modalName").textContent =
        request.full_name || "";

        document.getElementById("modalFirstName").textContent =
            request.first_name || "Not provided";

        document.getElementById("modalMiddleName").textContent =
            request.middle_name || "Not provided";

        document.getElementById("modalLastName").textContent =
            request.last_name || "Not provided";

        document.getElementById("modalSuffix").textContent =
            request.suffix || "None";

        document.getElementById("modalBirthDate").textContent =
            request.birth_date || "Not provided";

        document.getElementById("modalGender").textContent =
            request.gender || "Not provided";

        document.getElementById("modalCivilStatus").textContent =
            request.civil_status || "Not provided";

        const isBlotter =
            request.service_type === "Blotter Assistance";

            document.getElementById("modalPurposeBox").style.display =
                isBlotter ? "none" : "block";

            document.getElementById("modalAdditionalBox").style.display =
                isBlotter ? "none" : "block";


        document.getElementById("modalNameLabel").textContent =
            isBlotter ? "Complainant" : "Full Name";


        document.getElementById("modalRespondentBox").style.display =
            isBlotter ? "block" : "none";


        document.getElementById("modalIncidentBox").style.display =
            isBlotter ? "block" : "none";


        document.getElementById("modalIncidentDateBox").style.display =
            isBlotter ? "block" : "none";


        document.getElementById("modalBlotterRemarksBox").style.display =
            isBlotter ? "block" : "none";


        if (isBlotter) {

            document.getElementById("modalRespondent").textContent =
                request.respondent || "Not provided";


            document.getElementById("modalIncident").textContent =
                request.incident || "Not provided";


            document.getElementById("modalIncidentDate").textContent =
                request.incident_date || "Not provided";


            document.getElementById("modalBlotterRemarks").textContent =
                request.blotter_remarks || "None";

        }

    document.getElementById("modalAddress").textContent =
        request.address || "";

    document.getElementById("modalContact").textContent =
        request.contact_number || "";

    document.getElementById("modalEmail").textContent =
        request.email || "Not provided";

    document.getElementById("modalPurpose").textContent =
        request.purpose || "";

    document.getElementById("modalAdditional").textContent =
        request.additional_details || "None";

    if (request.created_at) {
        const date = new Date(request.created_at.replace(" ", "T"));

        document.getElementById("modalCreated").textContent =
            date.toLocaleDateString("en-US", {
                month: "long",
                day: "numeric",
                year: "numeric"
            }) +
            " • " +
            date.toLocaleTimeString("en-US", {
                hour: "numeric",
                minute: "2-digit"
            });
    } else {
        document.getElementById("modalCreated").textContent = "";
    }

    document.getElementById("modalStatus").value =
        request.status || "Pending";

    document.getElementById("modalRemarks").value =
        request.admin_remarks || "";

    document.getElementById("requestModal").style.display =
        "flex";
}

function showConvertBlotterPopup(event) {

    if (event) {
        event.preventDefault();
    }

    const popup = document.getElementById("convertConfirmPopup");
    const requestId = document.getElementById("convertBlotterId");

    if (popup && requestId && requestId.value) {
        popup.style.display = "flex";
        popup.style.position = "fixed";
        popup.style.inset = "0";
        popup.style.zIndex = "2147483647";
        popup.style.width = "100vw";
        popup.style.height = "100vh";
    }

    return false;
}

function closeRequestModal() {

    document.getElementById("requestModal").style.display =
        "none";
}


window.addEventListener("click", function(event) {

    const modal =
        document.getElementById("requestModal");

    if (event.target === modal) {
        closeRequestModal();
    }

});


function closePopup(id) {

    const popup =
        document.getElementById(id);

    if (popup) {
        popup.remove();
    }

}

window.addEventListener("DOMContentLoaded", function () {

    const successPopup =
        document.getElementById("successPopup");

    if (successPopup) {
        successPopup.style.display = "flex";
        successPopup.style.visibility = "visible";
        successPopup.style.opacity = "1";
        successPopup.style.zIndex = "99999999";
    }

});

setTimeout(function() {

    const success =
        document.getElementById("successPopup");

    const error =
        document.getElementById("errorPopup");

    if (success) {
        success.remove();
    }

    if (error) {
        error.remove();
    }

}, 5000);



function closeConvertBlotterPopup() {

    const popup = document.getElementById("convertConfirmPopup");

    if (popup) {
        popup.style.display = "none";
    }
}


function submitBlotterConversion() {

    const popup = document.getElementById("convertConfirmPopup");
    const form = document.getElementById("convertBlotterForm");
    const requestId = document.getElementById("convertBlotterId");

    if (!form || !requestId || !requestId.value) {
        return;
    }

    if (popup) popup.style.display = "none";
    form.submit();
}

const focusReference = <?= json_encode($focusReference, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
if (focusReference) {
    const matchingRequestButton = Array.from(
        document.querySelectorAll(".btn-view[data-reference]")
    ).find(button => button.dataset.reference === focusReference);

    if (matchingRequestButton) {
        matchingRequestButton.click();
    }
}

</script>





<?php

$stmt->close();

include "../includes/footer.php";

?>

