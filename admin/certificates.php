<?php
// Shared admin authentication, role check, and session timeout.
require_once __DIR__ . "/../config/admin_auth.php";

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/audit.php";


if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION["csrf_token"];
$message = "";
$messageType = "success";

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function redirectToRecords(
    string $search = "",
    int $page = 1,
    string $source = "all"
): void {
    if (!in_array($source, ["all", "online", "manual"], true)) {
        $source = "all";
    }

    header("Location: certificates.php?" . http_build_query([
        "search" => $search,
        "page" => max(1, $page),
        "source" => $source
    ]) . "#certificate-records");
    exit;
}

$certificateTypes = [
    "Barangay Certificate",
    "Certificate of Residency",
    "Certificate of Indigency"
];

$manualStatuses = [
    "Pending",
    "Approved",
    "Released",
    "Rejected"
];

$onlineStatuses = [
    "Pending",
    "Under Review",
    "Approved",
    "Rejected"
];

/* POST actions: online status, manual delete, manual add/edit. */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $submittedToken = (string)($_POST["csrf_token"] ?? "");

    if ($submittedToken === "" || !hash_equals($csrfToken, $submittedToken)) {
        $message = "Invalid security token. Please refresh the page and try again.";
        $messageType = "error";

    } elseif (isset($_POST["update_online_request"])) {
        $requestId = (int)($_POST["request_id"] ?? 0);
        $onlineStatus = trim((string)($_POST["online_status"] ?? ""));
        $adminRemarks = trim((string)($_POST["online_admin_remarks"] ?? ""));
        $search = trim((string)($_POST["search"] ?? ""));
        $page = max(1, (int)($_POST["page"] ?? 1));
        $source = (string)($_POST["source"] ?? "all");

        if (!in_array($source, ["all", "online", "manual"], true)) {
            $source = "all";
        }

        if (
            $requestId <= 0 ||
            !in_array($onlineStatus, $onlineStatuses, true)
        ) {
            $_SESSION["flash_message"] = "Invalid online request or status.";
            $_SESSION["flash_type"] = "error";
        } elseif (strlen($adminRemarks) > 5000) {
            $_SESSION["flash_message"] = "Admin remarks are too long.";
            $_SESSION["flash_type"] = "error";
        } else {
            $stmt = $conn->prepare("
                UPDATE service_requests
                SET status = ?,
                    admin_remarks = ?,
                    date_issued = CASE
                        WHEN ? = 'Approved' THEN COALESCE(date_issued, CURDATE())
                        ELSE NULL
                    END,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                  AND service_type IN (
                      'Barangay Certificate',
                      'Certificate of Residency',
                      'Certificate of Indigency'
                  )
            ");

            if ($stmt) {
                $stmt->bind_param(
                    "sssi",
                    $onlineStatus,
                    $adminRemarks,
                    $onlineStatus,
                    $requestId
                );

                if ($stmt->execute()) {
                    addAuditLog(
                        $conn,
                        "UPDATE",
                        "Certificates",
                        "Updated online certificate request #"
                            . $requestId
                            . " status to "
                            . $onlineStatus,
                        $requestId
                    );
                    $_SESSION["flash_message"] =
                        "Online certificate request updated successfully.";
                    $_SESSION["flash_type"] = "success";
                } else {
                    $_SESSION["flash_message"] =
                        "Unable to update online certificate request.";
                    $_SESSION["flash_type"] = "error";
                }

                $stmt->close();
            } else {
                $_SESSION["flash_message"] =
                    "Unable to prepare the online request update.";
                $_SESSION["flash_type"] = "error";
            }
        }

        redirectToRecords($search, $page, $source);

    
} elseif (isset($_POST["delete_online_request"])) {

    $deleteRequestId = (int)($_POST["online_request_id"] ?? 0);
    $search = trim((string)($_POST["search"] ?? ""));
    $page = max(1, (int)($_POST["page"] ?? 1));
    $source = (string)($_POST["source"] ?? "all");

    if (!in_array($source, ["all", "online", "manual"], true)) {
        $source = "all";
    }

    if ($deleteRequestId <= 0) {
        $_SESSION["flash_message"] = "Invalid online certificate request.";
        $_SESSION["flash_type"] = "error";
    } else {
        $stmt = $conn->prepare("
            DELETE FROM service_requests
            WHERE id = ?
              AND service_type IN (
                  'Barangay Certificate',
                  'Certificate of Residency',
                  'Certificate of Indigency'
              )
        ");

        if ($stmt) {
            $stmt->bind_param("i", $deleteRequestId);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                addAuditLog(
                    $conn,
                    "DELETE",
                    "Certificates",
                    "Deleted online certificate request #"
                        . $deleteRequestId,
                    $deleteRequestId
                );

                $_SESSION["flash_message"] =
                    "Online certificate request deleted successfully.";
                $_SESSION["flash_type"] = "success";
            } else {
                $_SESSION["flash_message"] =
                    "Online certificate request was not found or could not be deleted.";
                $_SESSION["flash_type"] = "error";
            }

            $stmt->close();
        } else {
            $_SESSION["flash_message"] =
                "Unable to prepare online request deletion.";
            $_SESSION["flash_type"] = "error";
        }
    }

    redirectToRecords($search, $page, $source);

} elseif (isset($_POST["delete_id"])) {

        $deleteId = (int)($_POST["delete_id"] ?? 0);

        if ($deleteId <= 0) {
            $message = "Invalid certificate record.";
            $messageType = "error";
        } else {
            /* Manual rows only. Online requests are not deleted here. */
            $stmt = $conn->prepare("DELETE FROM certificates WHERE id = ?");

            if ($stmt) {
                $stmt->bind_param("i", $deleteId);

                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    addAuditLog(
                        $conn,
                        "DELETE",
                        "Certificates",
                        "Deleted manual certificate record.",
                        $deleteId
                    );

                    $stmt->close();
                    $_SESSION["flash_message"] =
                        "Manual certificate record deleted successfully.";
                    $_SESSION["flash_type"] = "success";
                    redirectToRecords();
                }

                $message = "Manual certificate record was not found or could not be deleted.";
                $messageType = "error";
                $stmt->close();
            } else {
                $message = "Unable to prepare certificate deletion.";
                $messageType = "error";
            }
        }

    } else {
        $id = (int)($_POST["id"] ?? 0);

        $firstName = trim((string)($_POST["first_name"] ?? ""));
        $middleName = trim((string)($_POST["middle_name"] ?? ""));
        $lastName = trim((string)($_POST["last_name"] ?? ""));
        $suffix = trim((string)($_POST["suffix"] ?? ""));
        $birthDate = trim((string)($_POST["birth_date"] ?? ""));
        $gender = trim((string)($_POST["gender"] ?? ""));
        $civilStatus = trim((string)($_POST["civil_status"] ?? ""));
        $address = trim((string)($_POST["address"] ?? ""));
        $contactNumber = trim((string)($_POST["contact_number"] ?? ""));
        $email = trim((string)($_POST["email"] ?? ""));
        $certificateType = trim((string)($_POST["certificate_type"] ?? ""));
        $purpose = trim((string)($_POST["purpose"] ?? ""));
        $additionalDetails = trim((string)($_POST["additional_details"] ?? ""));
        $dateRequested = trim((string)($_POST["date_requested"] ?? ""));
        $status = trim((string)($_POST["status"] ?? "Pending"));

        $residentName = trim(
            $firstName . " " . $middleName . " " . $lastName . " " . $suffix
        );
        $residentName = preg_replace('/\s+/', ' ', $residentName);

        $dateIsValid = false;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRequested)) {
            [$year, $month, $day] =
                array_map("intval", explode("-", $dateRequested));
            $dateIsValid = checkdate($month, $day, $year);
        }

        $birthDateIsValid = true;
        if ($birthDate !== "") {
            $birthDateIsValid = false;
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
                [$birthYear, $birthMonth, $birthDay] =
                    array_map("intval", explode("-", $birthDate));
                $birthDateIsValid =
                    checkdate($birthMonth, $birthDay, $birthYear)
                    && $birthDate <= date("Y-m-d");
            }
        }

        $allowedGenders = ["Male", "Female", "Other"];
        $allowedCivilStatuses = ["Single", "Married", "Widowed", "Separated"];

        if (
            $firstName === "" ||
            $lastName === "" ||
            $gender === "" ||
            $civilStatus === "" ||
            $address === "" ||
            $contactNumber === "" ||
            $certificateType === "" ||
            $purpose === "" ||
            $dateRequested === ""
        ) {
            $message = "Please complete all required fields.";
            $messageType = "error";

        } elseif (!preg_match('/^09[0-9]{9}$/', $contactNumber)) {
            $message = "The contact number must start with 09 and contain exactly 11 digits.";
            $messageType = "error";

        } elseif (
            !preg_match("/^[A-Za-zÀ-ÿÑñ'., -]+$/u", $firstName) ||
            !preg_match("/^[A-Za-zÀ-ÿÑñ'., -]+$/u", $lastName) ||
            ($middleName !== "" &&
                !preg_match("/^[A-Za-zÀ-ÿÑñ'., -]+$/u", $middleName))
        ) {
            $message = "Please enter valid name fields.";
            $messageType = "error";

        } elseif (!in_array($certificateType, $certificateTypes, true)) {
            $message = "Please select a valid certificate type.";
            $messageType = "error";

        } elseif (!in_array($gender, $allowedGenders, true)) {
            $message = "Please select a valid gender.";
            $messageType = "error";

        } elseif (!in_array($civilStatus, $allowedCivilStatuses, true)) {
            $message = "Please select a valid civil status.";
            $messageType = "error";

        } elseif (
            strlen($address) > 255 ||
            strlen($email) > 150 ||
            ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) ||
            strlen($purpose) > 255 ||
            strlen($additionalDetails) > 5000
        ) {
            $message = "Please check the length and format of the information entered.";
            $messageType = "error";

        } elseif (
            !$dateIsValid ||
            $dateRequested > date("Y-m-d") ||
            !$birthDateIsValid
        ) {
            $message = "Please enter valid dates. Dates cannot be in the future.";
            $messageType = "error";

        } elseif (!in_array($status, $manualStatuses, true)) {
            $message = "Please select a valid status.";
            $messageType = "error";

        } else {
            $suffixValue = ($suffix === "" || $suffix === "None") ? null : $suffix;
            $middleNameValue = $middleName === "" ? null : $middleName;
            $birthDateValue = $birthDate === "" ? null : $birthDate;
            $emailValue = $email === "" ? null : $email;
            $isIssued = in_array($status, ["Approved", "Released"], true);
            $oldDateIssued = $editCertificate["date_issued"] ?? null;

            $dateIssued = $isIssued
                ? (!empty($oldDateIssued) ? $oldDateIssued : date("Y-m-d"))
                : null;

            if ($id > 0) {
                $stmt = $conn->prepare("
                    UPDATE certificates
                    SET resident_name = ?,
                        certificate_type = ?,
                        purpose = ?,
                        date_requested = ?,
                        date_issued = ?,
                        status = ?,
                        first_name = ?,
                        middle_name = ?,
                        last_name = ?,
                        suffix = ?,
                        birth_date = ?,
                        gender = ?,
                        civil_status = ?,
                        address = ?,
                        contact_number = ?,
                        email = ?,
                        additional_details = ?
                    WHERE id = ?
                ");

                if ($stmt) {
                    $stmt->bind_param(
                        "sssssssssssssssssi",
                        $residentName,
                        $certificateType,
                        $purpose,
                        $dateRequested,
                        $dateIssued,
                        $status,
                        $firstName,
                        $middleNameValue,
                        $lastName,
                        $suffixValue,
                        $birthDateValue,
                        $gender,
                        $civilStatus,
                        $address,
                        $contactNumber,
                        $emailValue,
                        $additionalDetails,
                        $id
                    );

                    if ($stmt->execute()) {
                        addAuditLog(
                            $conn,
                            "UPDATE",
                            "Certificates",
                            "Updated manual certificate record.",
                            $id
                        );
                        $stmt->close();
                        $_SESSION["flash_message"] =
                            "Certificate record updated successfully.";
                        $_SESSION["flash_type"] = "success";
                        redirectToRecords();
                    }

                    $message = "Unable to update certificate record.";
                    $messageType = "error";
                    $stmt->close();
                } else {
                    $message = "Unable to prepare certificate update.";
                    $messageType = "error";
                }

            } else {
                $stmt = $conn->prepare("
                    INSERT INTO certificates (
                        resident_name,
                        certificate_type,
                        purpose,
                        date_requested,
                        date_issued,
                        status,
                        first_name,
                        middle_name,
                        last_name,
                        suffix,
                        birth_date,
                        gender,
                        civil_status,
                        address,
                        contact_number,
                        email,
                        additional_details
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                if ($stmt) {
                    $stmt->bind_param(
                        "sssssssssssssssss",
                        $residentName,
                        $certificateType,
                        $purpose,
                        $dateRequested,
                        $dateIssued,
                        $status,
                        $firstName,
                        $middleNameValue,
                        $lastName,
                        $suffixValue,
                        $birthDateValue,
                        $gender,
                        $civilStatus,
                        $address,
                        $contactNumber,
                        $emailValue,
                        $additionalDetails
                    );

                    if ($stmt->execute()) {
                        $newId = (int)$stmt->insert_id;


                            /*
                             * Generate shared Certificate Reference No.
                             * Shared by ONLINE and MANUAL certificates.
                             *
                             * Example:
                             * CERT-2026-0001
                             * CERT-2026-0002
                             *
                             * The sequence automatically resets every new year.
                             */
                            $referenceYear = (int)date("Y");

                            $sequenceStmt = $conn->prepare("
                                INSERT INTO reference_sequences (
                                    reference_year,
                                    last_number
                                )
                                VALUES (?, LAST_INSERT_ID(1))
                                ON DUPLICATE KEY UPDATE
                                    last_number = LAST_INSERT_ID(last_number + 1)
                            ");

                            if (!$sequenceStmt) {
                                throw new Exception(
                                    "Unable to prepare certificate reference sequence."
                                );
                            }

                            $sequenceStmt->bind_param(
                                "i",
                                $referenceYear
                            );

                            if (!$sequenceStmt->execute()) {
                                $sequenceStmt->close();

                                throw new Exception(
                                    "Unable to generate certificate reference number."
                                );
                            }

                            /*
                             * Get the generated sequence number.
                             */
                            $sequenceResult = $conn->query("
                                SELECT LAST_INSERT_ID() AS sequence_number
                            ");

                            if (!$sequenceResult) {
                                $sequenceStmt->close();

                                throw new Exception(
                                    "Unable to retrieve certificate reference number."
                                );
                            }

                            $sequenceRow = $sequenceResult->fetch_assoc();

                            $nextReferenceNumber = (int)(
                                $sequenceRow["sequence_number"] ?? 0
                            );

                            $sequenceStmt->close();

                            if ($nextReferenceNumber <= 0) {
                                throw new Exception(
                                    "Invalid certificate reference sequence."
                                );
                            }

                            /*
                             * Final reference number.
                             *
                             * Example:
                             * CERT-2026-0001
                             */
                            $referenceNo = "CERT-"
                                . $referenceYear
                                . "-"
                                . str_pad(
                                    (string)$nextReferenceNumber,
                                    4,
                                    "0",
                                    STR_PAD_LEFT
                                );

                            /*
                             * Save reference number to the certificate.
                             */
                            $referenceStmt = $conn->prepare("
                                UPDATE certificates
                                SET reference_no = ?
                                WHERE id = ?
                            ");

                            if (!$referenceStmt) {
                                throw new Exception(
                                    "Unable to prepare certificate reference update."
                                );
                            }

                            $referenceStmt->bind_param(
                                "si",
                                $referenceNo,
                                $newId
                            );

                            if (!$referenceStmt->execute()) {
                                $referenceStmt->close();

                                throw new Exception(
                                    "Unable to save certificate reference number."
                                );
                            }

                            $referenceStmt->close();
                        $stmt->close();
                        $_SESSION["flash_message"] =
                            "Manual certificate record added successfully.";
                        $_SESSION["flash_type"] = "success";
                        redirectToRecords();
                    }

                    $message = "Unable to add certificate record.";
                    $messageType = "error";
                    $stmt->close();
                } else {
                    $message = "Unable to prepare certificate entry.";
                    $messageType = "error";
                }
            }
        }
    }
}

/* Flash message after a redirect. */
if (isset($_SESSION["flash_message"])) {
    $message = (string)$_SESSION["flash_message"];
    $messageType = (string)($_SESSION["flash_type"] ?? "success");
    unset($_SESSION["flash_message"], $_SESSION["flash_type"]);
}

/* Load a manual certificate for editing. */
$editCertificate = null;
if (isset($_GET["edit"])) {
    $editId = (int)$_GET["edit"];

    if ($editId > 0) {
        $stmt = $conn->prepare("
            SELECT *
            FROM certificates
            WHERE id = ?
            LIMIT 1
        ");

        if ($stmt) {
            $stmt->bind_param("i", $editId);
            $stmt->execute();
            $result = $stmt->get_result();
            $editCertificate = $result ? $result->fetch_assoc() : null;
            $stmt->close();
        }
    }
}

/* =========================================================
   UNIFIED CERTIFICATE RECORDS
   Manual certificates + Online certificate requests
   ========================================================= */

$search = trim((string)($_GET["search"] ?? ""));

$recordSource = trim((string)($_GET["source"] ?? "all"));

if (!in_array($recordSource, ["all", "manual", "online"], true)) {
    $recordSource = "all";
}

$recordsPerPage = 10;
$currentPage = max(1, (int)($_GET["page"] ?? 1));

$searchValue = "%" . $search . "%";


/*
 * MANUAL CERTIFICATES
 */
$manualSql = "
    SELECT
        'manual' AS record_source,
        id,
        reference_no,
        resident_name,
        certificate_type,
        purpose,
        date_requested,
        date_issued,
        status,
        NULL AS admin_remarks,
        first_name,
        middle_name,
        last_name,
        suffix,
        birth_date,
        gender,
        civil_status,
        address,
        contact_number,
        email,
        additional_details
    FROM certificates
";


/*
 * ONLINE CERTIFICATE REQUESTS
 */
$onlineSql = "
    SELECT
        'online' AS record_source,
        id,
        reference_no,
        full_name AS resident_name,
        service_type AS certificate_type,
        purpose,
        DATE(created_at) AS date_requested,
        date_issued,
        status,
        admin_remarks,
        first_name,
        middle_name,
        last_name,
        suffix,
        birth_date,
        gender,
        civil_status,
        address,
        contact_number,
        email,
        additional_details
    FROM service_requests
    WHERE service_type IN (
        'Barangay Certificate',
        'Certificate of Residency',
        'Certificate of Indigency'
    )
";


/*
 * Combine both sources
 */
$combinedSql = "
    ($manualSql)
    UNION ALL
    ($onlineSql)
";


/*
 * Filters
 */
$filterConditions = [];

if ($recordSource === "manual") {
    $filterConditions[] = "record_source = 'manual'";
} elseif ($recordSource === "online") {
    $filterConditions[] = "record_source = 'online'";
}


if ($search !== "") {
    $filterConditions[] = "
        (
            resident_name LIKE ?
            OR certificate_type LIKE ?
            OR purpose LIKE ?
            OR reference_no LIKE ?
        )
    ";
}


$filterSql = $filterConditions
    ? "WHERE " . implode(" AND ", $filterConditions)
    : "";


/*
 * COUNT
 */
$countSql = "
    SELECT COUNT(*) AS total
    FROM ($combinedSql) AS combined_records
    $filterSql
";

$countStmt = $conn->prepare($countSql);

if (!$countStmt) {
    die("Unable to load certificate records.");
}


if ($search !== "") {
    $countStmt->bind_param(
        "ssss",
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue
    );
}


if (!$countStmt->execute()) {
    $countStmt->close();
    die("Unable to load certificate records.");
}


$countResult = $countStmt->get_result();

$totalRecords = (int)(
    $countResult->fetch_assoc()["total"] ?? 0
);

$countStmt->close();


/*
 * PAGINATION
 */
$totalPages = max(
    1,
    (int)ceil($totalRecords / $recordsPerPage)
);

$currentPage = min(
    $currentPage,
    $totalPages
);

$offset = ($currentPage - 1) * $recordsPerPage;


/*
 * LOAD RECORDS
 */
$recordsSql = "
    SELECT *
    FROM ($combinedSql) AS combined_records
    $filterSql
    ORDER BY date_requested DESC, id DESC
    LIMIT ? OFFSET ?
";

$recordsStmt = $conn->prepare($recordsSql);

if (!$recordsStmt) {
    die("Unable to load certificate records.");
}


if ($search !== "") {

    $recordsStmt->bind_param(
        "ssssii",
        $searchValue,
        $searchValue,
        $searchValue,
        $searchValue,
        $recordsPerPage,
        $offset
    );

} else {

    $recordsStmt->bind_param(
        "ii",
        $recordsPerPage,
        $offset
    );
}


if (!$recordsStmt->execute()) {
    $recordsStmt->close();
    die("Unable to load certificate records.");
}


$recordsResult = $recordsStmt->get_result();

$certificateRows = [];

while ($record = $recordsResult->fetch_assoc()) {
    $certificateRows[] = $record;
}

$recordsStmt->close();

require_once "../includes/header.php";
?>

<style>
.source-badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}
.source-badge.online {
    color: #075985;
    background: #e0f2fe;
}
.source-badge.manual {
    color: #166534;
    background: #dcfce7;
}
.certificate-actions form {
    margin-top: 8px;
}
.certificate-actions select,
.certificate-actions textarea {
    display: block;
    width: 100%;
    margin: 6px 0;
}
.certificate-actions textarea {
    min-height: 55px;
}
.certificate-table details {
    margin-top: 6px;
}
.certificate-table details p {
    margin: 5px 0;
}

/* Certificate page UI improvements */
#certificate-form,
#certificate-records {
    border: 1px solid #e5eaf2;
    border-radius: 16px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
    margin-bottom: 24px;
}

#certificate-form {
    border-top: 5px solid #2563eb;
}

#certificate-records {
    border-top: 5px solid #2563eb;
}

#certificate-form .section-header,
#certificate-records .section-header {
    margin-bottom: 18px;
}

#certificate-form .section-header h2,
#certificate-records .section-header h2 {
    color: #14213d;
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 5px;
}

#certificate-form .section-header p,
#certificate-records .section-header p {
    color: #64748b;
    font-size: 14px;
}

/* Form fields */
#certificateForm .form-grid {
    column-gap: 22px;
    row-gap: 18px;
}

#certificateForm .form-group label {
    color: #26364b;
    display: block;
    font-size: 13px;
    font-weight: 650;
    margin-bottom: 7px;
}

#certificateForm input,
#certificateForm select,
#certificateForm textarea {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    box-sizing: border-box;
    color: #1e293b;
    font: inherit;
    font-size: 14px;
    min-height: 46px;
    padding: 10px 13px;
    transition: border-color .15s ease, box-shadow .15s ease;
    width: 100%;
}

#certificateForm textarea {
    min-height: 105px;
    resize: vertical;
}

#certificateForm input:focus,
#certificateForm select:focus,
#certificateForm textarea:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .13);
    outline: none;
}

#certificateForm .form-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}

#certificateForm .form-actions .btn {
    border-radius: 9px;
    min-height: 42px;
    padding: 10px 18px;
}

/* Search row */
#certificate-records .search-form {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 18px;
}

#certificate-records .search-form input {
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    box-sizing: border-box;
    font-size: 14px;
    min-height: 42px;
    max-width: 460px;
    padding: 9px 13px;
    width: 100%;
}

#certificate-records .search-form input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
    outline: none;
}

/* Records table */
#certificate-records .table-container {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow-x: auto;
}

#certificate-records table.certificate-table {
    border-collapse: separate;
    border-spacing: 0;
    min-width: 1250px;
    width: 100%;
}

#certificate-records .certificate-table thead th {
    background: #f1f5f9;
    border-bottom: 1px solid #dbe3ed;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .025em;
    padding: 14px 12px;
    text-align: left;
    white-space: nowrap;
}

#certificate-records .certificate-table tbody td {
    border-bottom: 1px solid #e8edf3;
    color: #334155;
    font-size: 13px;
    line-height: 1.45;
    padding: 14px 12px;
    vertical-align: top;
}

#certificate-records .certificate-table tbody tr:nth-child(even) {
    background: #fafcff;
}

#certificate-records .certificate-table tbody tr:hover {
    background: #f4f8ff;
}

#certificate-records .certificate-table tbody tr:last-child td {
    border-bottom: 0;
}

#certificate-records .certificate-table td:nth-child(1) {
    min-width: 125px;
    overflow-wrap: anywhere;
}

#certificate-records .certificate-table td:nth-child(2) {
    min-width: 125px;
}

#certificate-records .certificate-table td:nth-child(3) {
    min-width: 190px;
}

#certificate-records .certificate-table td:nth-child(4),
#certificate-records .certificate-table td:nth-child(5) {
    min-width: 145px;
}

#certificate-records .certificate-table td:nth-child(8) {
    min-width: 215px;
}

.source-badge {
    border: 1px solid transparent;
    padding: 6px 10px;
}

.source-badge.online {
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #1d4ed8;
}

.source-badge.manual {
    background: #f0fdf4;
    border-color: #bbf7d0;
    color: #15803d;
}

#certificate-records details {
    color: #475569;
    font-size: 12px;
    margin-top: 8px;
}

#certificate-records details summary {
    color: #2563eb;
    cursor: pointer;
    font-weight: 600;
}

#certificate-records details p {
    margin: 5px 0;
    overflow-wrap: anywhere;
}

/* Status and action controls */
#certificate-records .certificate-actions {
    min-width: 215px;
}

#certificate-records .certificate-actions > form {
    align-items: stretch;
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 9px;
    max-width: 210px;
}

#certificate-records .certificate-actions select,
#certificate-records .certificate-actions textarea {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-sizing: border-box;
    color: #334155;
    font: inherit;
    font-size: 13px;
    padding: 9px 10px;
    width: 100%;
}

#certificate-records .certificate-actions select {
    min-height: 39px;
}

#certificate-records .certificate-actions textarea {
    min-height: 72px;
    resize: vertical;
}

#certificate-records .certificate-actions select:focus,
#certificate-records .certificate-actions textarea:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
    outline: none;
}

#certificate-records .certificate-actions .btn {
    align-items: center;
    border-radius: 8px;
    display: inline-flex;
    justify-content: center;
    min-height: 37px;
    padding: 8px 12px;
    text-decoration: none;
    white-space: nowrap;
}

#certificate-records .certificate-actions > .btn,
#certificate-records .certificate-actions > form .btn {
    width: fit-content;
}

#certificate-records .certificate-actions .status-badge {
    display: inline-block;
    margin-bottom: 2px;
}

#certificate-records .pagination-wrapper {
    align-items: center;
    border-top: 1px solid #e2e8f0;
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    justify-content: space-between;
    margin-top: 18px;
    padding-top: 16px;
}

@media (max-width: 760px) {
    #certificateForm .form-grid {
        grid-template-columns: 1fr;
    }

    #certificate-records .search-form input {
        max-width: none;
    }

    #certificate-records .pagination-wrapper {
        align-items: flex-start;
        flex-direction: column;
    }
}

/* Keep wide records tables inside the Certificate Records card */
#certificate-records {
    box-sizing: border-box;
    max-width: 100%;
    min-width: 0;
    overflow: hidden;
}

#certificate-records .table-container {
    box-sizing: border-box;
    display: block;
    max-width: 100%;
    min-width: 0;
    overflow-x: auto !important;
    overflow-y: hidden;
    width: 100%;
    -webkit-overflow-scrolling: touch;
    scrollbar-color: #94a3b8 #e2e8f0;
    scrollbar-width: thin;
}

#certificate-records .table-container::-webkit-scrollbar {
    height: 10px;
}

#certificate-records .table-container::-webkit-scrollbar-track {
    background: #e2e8f0;
    border-radius: 8px;
}

#certificate-records .table-container::-webkit-scrollbar-thumb {
    background: #94a3b8;
    border-radius: 8px;
}

#certificate-records table.certificate-table {
    margin: 0;
    table-layout: auto;
}

#certificate-records .certificate-table th:last-child,
#certificate-records .certificate-table td:last-child {
    min-width: 220px;
}

#certificate-records .certificate-actions > form {
    max-width: 190px;
}

#certificate-records .certificate-actions .btn {
    white-space: nowrap;
}

/* Fix tall record rows and keep the table inside its card */
#certificate-records,
#certificate-records .table-container {
    box-sizing: border-box;
    height: auto !important;
    max-height: none !important;
    min-height: 0 !important;
}

#certificate-records {
    max-width: 100%;
    min-width: 0;
    overflow: hidden;
}

#certificate-records .table-container {
    display: block;
    max-width: 100%;
    min-width: 0;
    overflow-x: scroll !important;
    overflow-y: hidden;
    width: 100%;
    -webkit-overflow-scrolling: touch;
}

#certificate-records table.certificate-table {
    height: auto !important;
    margin: 0;
    min-height: 0 !important;
    table-layout: auto;
}

#certificate-records .certificate-table thead,
#certificate-records .certificate-table tbody,
#certificate-records .certificate-table tr,
#certificate-records .certificate-table th,
#certificate-records .certificate-table td {
    height: auto !important;
    max-height: none !important;
    min-height: 0 !important;
}

#certificate-records .certificate-table tbody td {
    padding: 14px 12px;
    vertical-align: top;
}

#certificate-records .certificate-actions > form {
    max-width: 190px;
}

#certificate-records .certificate-actions textarea {
    min-height: 72px;
    resize: vertical;
}

#certificate-records .table-container::-webkit-scrollbar {
    height: 12px;
}

#certificate-records .table-container::-webkit-scrollbar-track {
    background: #e2e8f0;
    border-radius: 8px;
}

#certificate-records .table-container::-webkit-scrollbar-thumb {
    background: #94a3b8;
    border-radius: 8px;
}

#certificate-records .table-container {
    display: block;
    max-width: 100%;
    overflow-x: auto !important;
    overflow-y: hidden;
    width: 100%;
}

#certificate-records table.certificate-table {
    height: auto !important;
}

#certificate-records .certificate-table tbody tr,
#certificate-records .certificate-table tbody td {
    height: auto !important;
    min-height: 0 !important;
}

#certificate-records .certificate-actions > form {
    gap: 5px;
    margin-top: 6px;
    max-width: 170px;
}

#certificate-records .certificate-actions select {
    min-height: 34px;
    padding: 6px 8px;
}

#certificate-records .certificate-actions textarea {
    min-height: 48px;
    padding: 7px 8px;
}

#certificate-records .certificate-actions .btn {
    min-height: 32px;
    padding: 6px 10px;
}

/* Compact table action cells; open controls only when needed */
#certificate-records .certificate-actions {
    min-width: 175px;
    vertical-align: top;
}

#certificate-records .record-actions {
    margin-top: 8px;
}

#certificate-records .record-actions summary {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    color: #1d4ed8;
    cursor: pointer;
    display: inline-block;
    font-size: 13px;
    font-weight: 650;
    list-style: none;
    padding: 8px 11px;
}

#certificate-records .record-actions summary::-webkit-details-marker {
    display: none;
}

#certificate-records .record-actions summary::before {
    content: "＋ ";
}

#certificate-records .record-actions[open] summary::before {
    content: "− ";
}

#certificate-records .record-actions form {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    gap: 9px;
    margin-top: 8px;
    max-width: 230px;
    padding: 12px;
}

#certificate-records .record-actions label {
    color: #475569;
    display: flex;
    flex-direction: column;
    font-size: 12px;
    font-weight: 600;
    gap: 5px;
}

#certificate-records .record-actions select,
#certificate-records .record-actions textarea {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    box-sizing: border-box;
    font: inherit;
    font-size: 13px;
    padding: 8px 9px;
    width: 100%;
}

#certificate-records .record-actions textarea {
    min-height: 65px;
    resize: vertical;
}

#certificate-records .manual-actions {
    align-items: flex-start;
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 8px;
}

#certificate-records .manual-actions form {
    margin: 0;
}

#certificate-records .manual-actions .btn,
#certificate-records .record-actions .btn {
    align-items: center;
    display: inline-flex;
    justify-content: center;
    min-height: 34px;
    white-space: nowrap;
}

/* Fit the certificate records table inside its card */
#certificate-records,
#certificate-records .table-container {
    box-sizing: border-box;
    max-width: 100%;
    min-width: 0;
}

#certificate-records .table-container {
    overflow-x: auto !important;
    overflow-y: hidden;
    width: 100%;
}

#certificate-records table.certificate-table {
    height: auto !important;
    min-width: 1100px;
    table-layout: fixed;
    width: 100% !important;
}

/* Keep the columns balanced */
#certificate-records .certificate-table th:nth-child(1),
#certificate-records .certificate-table td:nth-child(1) {
    width: 14%;
}

#certificate-records .certificate-table th:nth-child(2),
#certificate-records .certificate-table td:nth-child(2) {
    width: 12%;
}

#certificate-records .certificate-table th:nth-child(3),
#certificate-records .certificate-table td:nth-child(3) {
    width: 16%;
}

#certificate-records .certificate-table th:nth-child(4),
#certificate-records .certificate-table td:nth-child(4) {
    width: 14%;
}

#certificate-records .certificate-table th:nth-child(5),
#certificate-records .certificate-table td:nth-child(5) {
    width: 11%;
}

#certificate-records .certificate-table th:nth-child(6),
#certificate-records .certificate-table td:nth-child(6) {
    width: 11%;
}

#certificate-records .certificate-table th:nth-child(7),
#certificate-records .certificate-table td:nth-child(7) {
    width: 9%;
}

#certificate-records .certificate-table th:nth-child(8),
#certificate-records .certificate-table td:nth-child(8) {
    width: 13%;
}

/* Let long text wrap instead of widening the table */
#certificate-records .certificate-table th,
#certificate-records .certificate-table td {
    box-sizing: border-box;
    overflow-wrap: anywhere;
    padding: 10px 8px;
    vertical-align: top;
}

#certificate-records .certificate-table td:nth-child(1),
#certificate-records .certificate-table td:nth-child(2),
#certificate-records .certificate-table td:nth-child(3),
#certificate-records .certificate-table td:nth-child(4),
#certificate-records .certificate-table td:nth-child(5),
#certificate-records .certificate-table td:nth-child(6),
#certificate-records .certificate-table td:nth-child(7),
#certificate-records .certificate-table td:nth-child(8) {
    min-width: 0 !important;
}

#certificate-records .certificate-actions {
    min-width: 0 !important;
}

#certificate-records .certificate-actions > form,
#certificate-records .record-actions form {
    max-width: 100%;
}

/* Final certificate page polish */
#certificate-records .certificate-table {
    border-collapse: separate;
    border-spacing: 0;
    table-layout: fixed;
    width: 100% !important;
}

#certificate-records .certificate-table th,
#certificate-records .certificate-table td {
    overflow-wrap: break-word;
    padding: 12px 10px;
    vertical-align: middle;
}

#certificate-records .certificate-table thead th {
    background: #f1f5f9;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .02em;
    position: sticky;
    top: 0;
    z-index: 1;
}

#certificate-records .certificate-table tbody tr {
    transition: background-color .15s ease;
}

#certificate-records .certificate-table tbody tr:hover {
    background: #f8fbff;
}

/* Source labels */
#certificate-records .source-badge {
    border-radius: 999px;
    display: inline-flex;
    font-size: 12px;
    font-weight: 700;
    justify-content: center;
    line-height: 1.2;
    padding: 7px 10px;
    white-space: nowrap;
}

#certificate-records .source-badge.online {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1d4ed8;
}

#certificate-records .source-badge.manual {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #15803d;
}

/* Status and request actions */
#certificate-records .certificate-actions {
    min-width: 0 !important;
}

#certificate-records .certificate-actions .status-badge {
    border-radius: 999px;
    display: inline-flex;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 8px;
    padding: 6px 10px;
    white-space: nowrap;
}

#certificate-records .record-actions summary {
    align-items: center;
    border-radius: 8px;
    display: inline-flex;
    font-size: 12px;
    justify-content: center;
    padding: 8px 10px;
    white-space: nowrap;
}

#certificate-records .record-actions form {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 11px;
}

#certificate-records .manual-actions {
    align-items: center;
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-top: 6px;
}

#certificate-records .manual-actions form {
    margin: 0;
}

#certificate-records .manual-actions .btn,
#certificate-records .record-actions .btn {
    align-items: center;
    border-radius: 7px;
    display: inline-flex;
    font-size: 12px;
    font-weight: 650;
    justify-content: center;
    min-height: 34px;
    padding: 7px 10px;
    text-decoration: none;
    white-space: nowrap;
}

#certificate-records .manual-actions .btn-edit {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1d4ed8;
}

#certificate-records .manual-actions .btn-delete {
    background: #fff1f2;
    border: 1px solid #fecdd3;
    color: #be123c;
}

#certificate-records .manual-actions .btn-print,
#certificate-records .record-actions .btn-print {
    background: #059669;
    border: 1px solid #059669;
    color: #fff;
}

#certificate-records .certificate-table details > summary {
    color: #2563eb;
    cursor: pointer;
    font-size: 12px;
    font-weight: 650;
}

/* Keep narrow screens usable */
@media (max-width: 1100px) {
    #certificate-records .table-container {
        overflow-x: auto !important;
    }

    #certificate-records .certificate-table {
        min-width: 1100px;
    }
}

#certificate-records .search-form select {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    color: #334155;
    cursor: pointer;
    font: inherit;
    font-size: 14px;
    min-height: 42px;
    padding: 9px 36px 9px 13px;
}

#certificate-records .search-form select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
    outline: none;
}

/* Certificate Records: cleaner column widths and aligned actions */
#certificate-records .certificate-table {
    table-layout: fixed;
    width: 100% !important;
}

#certificate-records .certificate-table th,
#certificate-records .certificate-table td {
    box-sizing: border-box;
    padding: 11px 8px;
    vertical-align: middle;
}

#certificate-records .certificate-table th:nth-child(1),
#certificate-records .certificate-table td:nth-child(1) {
    width: 12%;
}

#certificate-records .certificate-table th:nth-child(2),
#certificate-records .certificate-table td:nth-child(2) {
    width: 11%;
}

#certificate-records .certificate-table th:nth-child(3),
#certificate-records .certificate-table td:nth-child(3) {
    width: 15%;
}

#certificate-records .certificate-table th:nth-child(4),
#certificate-records .certificate-table td:nth-child(4) {
    width: 14%;
}

#certificate-records .certificate-table th:nth-child(5),
#certificate-records .certificate-table td:nth-child(5) {
    width: 11%;
}

#certificate-records .certificate-table th:nth-child(6),
#certificate-records .certificate-table td:nth-child(6) {
    width: 10%;
}

#certificate-records .certificate-table th:nth-child(7),
#certificate-records .certificate-table td:nth-child(7) {
    width: 8%;
}

#certificate-records .certificate-table th:nth-child(8),
#certificate-records .certificate-table td:nth-child(8) {
    width: 19%;
}

#certificate-records .certificate-table tbody td {
    line-height: 1.4;
}

#certificate-records .manual-actions {
    align-items: center;
    display: flex;
    flex-wrap: nowrap;
    gap: 6px;
    margin-top: 8px;
}

#certificate-records .manual-actions .btn {
    font-size: 12px;
    min-height: 34px;
    padding: 7px 9px;
}

#certificate-records .manual-actions form {
    flex: 0 0 auto;
    margin: 0;
}

#certificate-records .certificate-table details > summary {
    cursor: pointer;
    white-space: nowrap;
}

#certificate-records .source-badge,
#certificate-records .certificate-actions .status-badge {
    white-space: nowrap;
}

@media (max-width: 1100px) {
    #certificate-records .table-container {
        overflow-x: auto !important;
    }

    #certificate-records .certificate-table {
        min-width: 1100px;
    }
}

.manage-request-trigger {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    color: #1d4ed8;
    cursor: pointer;
    padding: 8px 11px;
}

.manage-request-link {
    display: inline-block;
    text-decoration: none;
}

.manage-request-dialog {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    margin: 0;

    border: 0;
    border-radius: 14px;
    box-shadow: 0 20px 60px rgba(15, 23, 42, .3);
    max-width: 460px;
    max-height: calc(100vh - 32px);
    overflow-y: auto;
    padding: 22px;
    width: calc(100% - 32px);
}

.manage-request-dialog::backdrop {
    background: rgba(15, 23, 42, .6);
}

.manage-request-header {
    align-items: center;
    display: flex;
    justify-content: space-between;
    margin-bottom: 18px;
}

.manage-request-header h2 {
    color: #172554;
    font-size: 20px;
    margin: 0;
}

.manage-request-close {
    background: #f1f5f9;
    border: 0;
    border-radius: 8px;
    cursor: pointer;
    font-size: 22px;
    height: 36px;
    width: 36px;
}

.manage-request-dialog form {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.manage-request-dialog label {
    color: #475569;
    display: flex;
    flex-direction: column;
    font-size: 13px;
    font-weight: 600;
    gap: 6px;
}

.manage-request-dialog select,
.manage-request-dialog textarea {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    box-sizing: border-box;
    font: inherit;
    padding: 10px;
    width: 100%;
}

.manage-request-dialog textarea {
    min-height: 100px;
    resize: vertical;
}

.manage-request-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 9px;
    justify-content: flex-end;
}

.manage-request-cancel {
    background: #f1f5f9;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    cursor: pointer;
    padding: 9px 13px;
}

.delete-confirm-overlay[hidden] {
    display: none;
}

.delete-confirm-overlay {
    position: fixed;
    inset: 0;
    z-index: 21000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, .6);
}

.delete-confirm-box {
    width: 100%;
    max-width: 400px;
    padding: 24px;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 20px 60px rgba(15, 23, 42, .3);
    text-align: center;
}

#cancelDeleteButton,
#confirmDeleteButton {
    margin: 8px 4px 0;
    padding: 10px 20px;
    border: 0;
    border-radius: 8px;
    cursor: pointer;
}

#cancelDeleteButton {
    background: #e2e8f0;
}

#confirmDeleteButton {
    background: #dc2626;
    color: #fff;
}

.delete-confirm-icon {
    background: #fef3c7;
    border-radius: 50%;
    font-size: 30px;
    height: 72px;
    line-height: 72px;
    margin: 0 auto 18px;
    width: 72px;
}

.delete-confirm-box h3 {
    color: #1e293b;
    font-size: 24px;
    margin-bottom: 10px;
}

.delete-confirm-box p {
    color: #64748b;
    margin-bottom: 24px;
}

/* Update button - disabled until something changes */
#certificateSubmitButton:disabled {
    background: #2563eb !important;
    border-color: #2563eb !important;
    color: #fff !important;
    cursor: not-allowed !important;
    opacity: 0.45;
    box-shadow: none !important;
    transform: none !important;
    filter: none !important;
}

/* Update button - enabled when there are changes */
#certificateSubmitButton:not(:disabled) {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
    cursor: pointer;
    opacity: 1;
}

/* Slight hover effect only when enabled */
#certificateSubmitButton:not(:disabled):hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
}
    

/* Manual Entry: align Print, Edit and Delete side by side */
#certificate-records .manual-actions {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 6px !important;
    margin-top: 8px !important;
}

#certificate-records .manual-actions form {
    display: inline-flex !important;
    flex: 0 0 auto !important;
    width: auto !important;
    margin: 0 !important;
    padding: 0 !important;
}

#certificate-records .manual-actions .btn {
    display: inline-flex !important;
    flex: 0 0 auto !important;
    align-items: center;
    justify-content: center;
    min-height: 34px !important;
    margin: 0 !important;
    padding: 7px 10px !important;
    font-size: 12px !important;
    white-space: nowrap !important;
}    


/* FINAL FIX: prevent certificate details from overlapping */

#certificate-records,
#certificate-records .table-container {
    box-sizing: border-box;
    width: 100%;
    max-width: 100%;
    min-width: 0;
}

#certificate-records .table-container {
    overflow-x: hidden !important;
}

#certificate-records table.certificate-table {
    width: 100% !important;
    min-width: 0 !important;
    table-layout: fixed !important;
    border-collapse: separate;
    border-spacing: 0;
}

/* Balanced column widths: total 100% */
#certificate-records .certificate-table th:nth-child(1),
#certificate-records .certificate-table td:nth-child(1) {
    width: 14%;
}

#certificate-records .certificate-table th:nth-child(2),
#certificate-records .certificate-table td:nth-child(2) {
    width: 10%;
}

#certificate-records .certificate-table th:nth-child(3),
#certificate-records .certificate-table td:nth-child(3) {
    width: 15%;
}

#certificate-records .certificate-table th:nth-child(4),
#certificate-records .certificate-table td:nth-child(4) {
    width: 13%;
}

#certificate-records .certificate-table th:nth-child(5),
#certificate-records .certificate-table td:nth-child(5) {
    width: 10%;
}

#certificate-records .certificate-table th:nth-child(6),
#certificate-records .certificate-table td:nth-child(6) {
    width: 10%;
}

#certificate-records .certificate-table th:nth-child(7),
#certificate-records .certificate-table td:nth-child(7) {
    width: 10%;
}

#certificate-records .certificate-table th:nth-child(8),
#certificate-records .certificate-table td:nth-child(8) {
    width: 18%;
}

/* Prevent content from entering neighboring columns */
#certificate-records .certificate-table th,
#certificate-records .certificate-table td {
    box-sizing: border-box;
    min-width: 0 !important;
    padding: 10px 8px;
    vertical-align: middle;
    overflow-wrap: anywhere;
}

/* Reference number must wrap inside its own column */
#certificate-records .reference-number {
    display: block;
    white-space: normal !important;
    overflow-wrap: anywhere;
}

/* Source badge must fit inside its column */
#certificate-records .source-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    max-width: 100%;
    padding: 5px 7px;
    font-size: 11px;
    line-height: 1.3;
    white-space: normal !important;
}

/* Applicant details remain within their column */
#certificate-records .certificate-table td:nth-child(3) strong,
#certificate-records .certificate-table td:nth-child(3)
.details-popup-trigger {
    max-width: 100%;
    overflow-wrap: anywhere;
}

#certificate-records .certificate-table td:nth-child(3)
.details-popup-trigger {
    display: block;
    margin-top: 4px;
    white-space: normal;
    text-align: left;
}

/* Status and online request buttons */
#certificate-records td.certificate-actions {
    min-width: 0 !important;
    vertical-align: middle;
}

#certificate-records td.certificate-actions > .status-badge {
    display: block;
    width: fit-content;
    max-width: 100%;
    margin: 0 0 7px;
    white-space: normal;
}

#certificate-records .online-primary-actions {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: wrap !important;
    align-items: center !important;
    gap: 6px !important;
    width: 100%;
    margin: 0 !important;
}

#certificate-records .online-primary-actions > a,
#certificate-records .online-primary-actions > form {
    display: inline-flex !important;
    flex: 0 0 auto !important;
    align-items: center;
    width: auto !important;
    max-width: 100%;
    margin: 0 !important;
}

#certificate-records .online-primary-actions > form {
    flex-direction: row !important;
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
}

#certificate-records .online-primary-actions .btn,
#certificate-records .manual-actions .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    min-height: 32px;
    padding: 6px 8px !important;
    margin: 0 !important;
    font-size: 11px !important;
    white-space: nowrap !important;
}

#certificate-records .certificate-actions > .btn-print {
    display: inline-flex;
    margin-top: 5px;
}

/* Manual Print, Edit and Delete stay together */
#certificate-records .manual-actions {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    gap: 5px !important;
    max-width: 100%;
    margin-top: 6px;
}

#certificate-records .manual-actions > form {
    display: inline-flex !important;
    flex: 0 0 auto !important;
    width: auto !important;
    margin: 0 !important;
    padding: 0 !important;
}

/* Mobile/tablet: preserve details instead of clipping them */
@media (max-width: 1100px) {
    #certificate-records .table-container {
        overflow-x: auto !important;
    }

    #certificate-records table.certificate-table {
        min-width: 1100px !important;
    }
}    
    
/* FINAL FIX: Align Print, Edit, and Delete buttons */
#certificate-records .manual-actions {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 6px !important;
    width: 100%;
    margin-top: 8px !important;
}

#certificate-records .manual-actions > a,
#certificate-records .manual-actions > form {
    display: inline-flex !important;
    flex: 0 0 auto !important;
    align-items: center !important;
    margin: 0 !important;
    padding: 0 !important;
}

#certificate-records .manual-actions .btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-sizing: border-box !important;
    min-height: 34px !important;
    margin: 0 !important;
    padding: 7px 10px !important;
    white-space: nowrap !important;
    line-height: 1.3 !important;
}    
    
</style>

<div class="topbar">
    <div>
        <h1>Barangay Certificates</h1>
        <p>Manage certificate requests and releases</p>
    </div>

    <div class="admin-profile">
        <div class="admin-avatar">
            <?= h(strtoupper(substr((string)($_SESSION["username"] ?? "A"), 0, 1))) ?>
        </div>
        <div>
            <strong><?= h($_SESSION["username"] ?? "Admin") ?></strong>
            <small>
                <?= h(
                    ($_SESSION["role"] ?? "") === "admin"
                        ? "Administrator"
                        : ($_SESSION["role"] ?? "User")
                ) ?>
            </small>
        </div>
    </div>
</div>

<?php if ($message !== ""): ?>
    <div class="crud-popup-overlay" id="crudPopup">
        <div class="crud-popup-box <?= $messageType === "error" ? "error" : "success" ?>">
            <div class="crud-popup-icon">
                <?= $messageType === "error" ? "!" : "✓" ?>
            </div>

            <h3>
                <?= $messageType === "error" ? "Error!" : "Success!" ?>
            </h3>

            <p><?= h($message) ?></p>

            <button type="button" class="crud-popup-button" onclick="closeCrudPopup()">
                OK
            </button>
        </div>
    </div>
<?php endif; ?>


<!-- FORM FIRST -->
<div class="content-section" id="certificate-form">
    <div class="section-header">
        <div>
            <h2><?= $editCertificate ? "Edit Manual Certificate" : "Add Manual Certificate" ?></h2>
            <p>Use the same applicant details as the online service request form.</p>
        </div>
    </div>

    <form method="POST" class="resident-form" id="certificateForm" data-auto-capitalize>
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">

        <?php if ($editCertificate): ?>
            <input type="hidden" name="id" value="<?= (int)$editCertificate["id"] ?>">
        <?php endif; ?>

        <div class="form-grid">
            <div class="form-group">
                <label for="certificate_type">Service Requested *</label>
                <select id="certificate_type" name="certificate_type" required>
                    <option value="">Select service</option>
                    <?php foreach ($certificateTypes as $type): ?>
                        <option
                            value="<?= h($type) ?>"
                            <?= ($editCertificate["certificate_type"] ?? "") === $type ? "selected" : "" ?>
                        ><?= h($type) ?></option>
                    <?php endforeach; ?>
                </select>

            </div>

            <div class="form-group">
                <label for="first_name">First Name *</label>
                <input id="first_name" name="first_name" type="text" maxlength="100" required
                    value="<?= h($editCertificate["first_name"] ?? "") ?>">
            </div>

            <div class="form-group">
                <label for="middle_name">Middle Name</label>
                <input id="middle_name" name="middle_name" type="text" maxlength="100"
                    value="<?= h($editCertificate["middle_name"] ?? "") ?>">
            </div>

            <div class="form-group">
                <label for="last_name">Last Name *</label>
                <input id="last_name" name="last_name" type="text" maxlength="100" required
                    value="<?= h($editCertificate["last_name"] ?? "") ?>">
            </div>

            <div class="form-group">
                <label for="suffix">Suffix</label>
                <?php $selectedSuffix = $editCertificate["suffix"] ?? ""; ?>
                <select id="suffix" name="suffix">
                    <option value="" <?= $selectedSuffix === "" ? "selected" : "" ?>>None</option>
                    <?php foreach (["Jr.", "Sr.", "II", "III", "IV"] as $suffixOption): ?>
                        <option value="<?= h($suffixOption) ?>"
                            <?= $selectedSuffix === $suffixOption ? "selected" : "" ?>>
                            <?= h($suffixOption) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="birth_date">Birth Date</label>
                <input id="birth_date" name="birth_date" type="date"
                    max="<?= h(date("Y-m-d")) ?>"
                    value="<?= h($editCertificate["birth_date"] ?? "") ?>">
            </div>

            <div class="form-group">
                <label for="gender">Gender *</label>
                <select id="gender" name="gender" required>
                    <option value="">Select Gender</option>
                    <?php foreach (["Male", "Female", "Other"] as $option): ?>
                        <option value="<?= h($option) ?>"
                            <?= ($editCertificate["gender"] ?? "") === $option ? "selected" : "" ?>>
                            <?= h($option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="civil_status">Civil Status *</label>
                <select id="civil_status" name="civil_status" required>
                    <option value="">Select Civil Status</option>
                    <?php foreach (["Single", "Married", "Widowed", "Separated"] as $option): ?>
                        <option value="<?= h($option) ?>"
                            <?= ($editCertificate["civil_status"] ?? "") === $option ? "selected" : "" ?>>
                            <?= h($option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="address">Complete Address *</label>
                <input id="address" name="address" type="text" maxlength="255" required
                    placeholder="House No., Purok, Barangay"
                    value="<?= h($editCertificate["address"] ?? "") ?>">
            </div>

            <div class="form-group">
                <label for="contact_number">Contact Number *</label>
                <input
                    id="contact_number"
                    name="contact_number"
                    type="tel"
                    inputmode="numeric"
                    pattern="09[0-9]{9}"
                    minlength="11"
                    maxlength="11"
                    required
                    placeholder="09XXXXXXXXX"
                    title="The contact number must start with 09 and contain exactly 11 digits."
                    value="<?= h($editCertificate["contact_number"] ?? "") ?>"
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input id="email" name="email" type="email" maxlength="150"
                    placeholder="Optional"
                    value="<?= h($editCertificate["email"] ?? "") ?>">
            </div>

            <div class="form-group">
                <label for="purpose">Purpose *</label>
                <input id="purpose" name="purpose" type="text" maxlength="255" required
                    placeholder="Example: Employment requirement"
                    value="<?= h($editCertificate["purpose"] ?? "") ?>">
            </div>

            <div class="form-group">
                <label for="date_requested">Date Requested *</label>
                <input id="date_requested" name="date_requested" type="date"
                    max="<?= h(date("Y-m-d")) ?>" required
                    value="<?= h($editCertificate["date_requested"] ?? date("Y-m-d")) ?>">
            </div>

            <div class="form-group">
                <label for="status">Status *</label>
                <select id="status" name="status" required>
                    <?php foreach ($manualStatuses as $manualStatus): ?>
                        <option value="<?= h($manualStatus) ?>"
                            <?= ($editCertificate["status"] ?? "Pending") === $manualStatus ? "selected" : "" ?>>
                            <?= h($manualStatus) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label for="additional_details">Additional Details</label>
                <textarea id="additional_details" name="additional_details"
                    maxlength="5000"
                    placeholder="Enter additional information if necessary"><?= h($editCertificate["additional_details"] ?? "") ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button
                type="submit"
                class="btn btn-primary"
                id="certificateSubmitButton"
                <?= $editCertificate ? "disabled" : "" ?>
            >
                <?= $editCertificate ? "Update Certificate" : "Add Manual Certificate" ?>
            </button>

            <?php if ($editCertificate): ?>
                <a href="certificates.php#certificate-records" class="btn btn-secondary">Cancel Edit</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- CERTIFICATE RECORDS UNDER THE FORM -->
<div class="content-section" id="certificate-records">
    <div class="section-header">
        <div>
            <h2>Certificate Records</h2>
            <p>
                View and manage all certificate transactions, including manual entries and online requests.
            </p>
        </div>
        <a class="btn btn-primary" href="service_requests.php?search=Certificate#service-request-records">
            📋 Manage Online Certificate Requests
        </a>
    </div>

    <form
        method="GET"
        action="certificates.php#certificate-records"
        class="search-form"
    >

        <input
            type="text"
            name="search"
            placeholder="Search reference no., applicant, certificate type, or purpose"
            value="<?= h($search) ?>"
        >

        <select name="source">
            <option value="all"
                <?= $recordSource === "all" ? "selected" : "" ?>>
                All Sources
            </option>

            <option value="manual"
                <?= $recordSource === "manual" ? "selected" : "" ?>>
                Manual Entry
            </option>

            <option value="online"
                <?= $recordSource === "online" ? "selected" : "" ?>>
                Online Request
            </option>
        </select>

        <button
            type="submit"
            class="btn btn-primary"
        >
            Search
        </button>

        <?php if ($search !== "" || $recordSource !== "all"): ?>

            <a
                href="certificates.php#certificate-records"
                class="btn btn-secondary"
            >
                Clear
            </a>

        <?php endif; ?>

    </form>

    <div class="table-container">
        <table class="certificate-table">
            <thead>
                <tr>
                    <th>Reference No.</th>
                    <th>Source</th>
                    <th>Resident / Applicant</th>
                    <th>Certificate Type</th>
                    <th>Purpose</th>
                    <th>Date Requested</th>
                    <th>Date Issued</th>
                    <th>Status / Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (count($certificateRows) > 0): ?>
                <?php foreach ($certificateRows as $row): ?>
                    <?php $isOnline = ($row["record_source"] === "online"); ?>
                    <tr>
                        <td>
                            <strong class="reference-number">
                                <?= h($row["reference_no"] ?? "—") ?>
                            </strong>
                        </td>

                        <td>
                            <?php if ($isOnline): ?>
                                <span class="source-badge online">Online Request</span>
                            <?php else: ?>
                                <span class="source-badge manual">Manual Entry</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <strong><?= h($row["resident_name"] ?? "") ?></strong>
                            <button
                                type="button"
                                class="details-popup-trigger"
                                data-details="<?= h(json_encode([
                                    "First name" => $row["first_name"] ?? "—",
                                    "Middle name" => $row["middle_name"] ?? "—",
                                    "Last name" => $row["last_name"] ?? "—",
                                    "Suffix" => $row["suffix"] ?? "—",
                                    "Birth date" => $row["birth_date"] ?? "—",
                                    "Gender" => $row["gender"] ?? "—",
                                    "Civil status" => $row["civil_status"] ?? "—",
                                    "Address" => $row["address"] ?? "—",
                                    "Contact number" => $row["contact_number"] ?? "—",
                                    "Email" => $row["email"] ?? "—",
                                    "Additional details" => $row["additional_details"] ?? "—"
                                ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: "{}") ?>"
                            >
                                View applicant details
                            </button>
                        </td>

                        <td><?= h($row["certificate_type"] ?? "") ?></td>
                        <td><?= h($row["purpose"] ?? "") ?></td>
                        <td><?= h($row["date_requested"] ?? "—") ?></td>
                        <td>
                            <?= !empty($row["date_issued"])
                                ? h($row["date_issued"])
                                : "—"
                            ?>
                        </td>

                        <td class="certificate-actions">
                            <span class="status-badge status-<?= h(
                                strtolower(preg_replace(
                                    "/[^a-zA-Z0-9]+/",
                                    "-",
                                    $row["status"] ?? "pending"
                                ))
                            ) ?>">
                                <?= h($row["status"] ?? "Pending") ?>
                            </span>

                            
<?php if ($isOnline): ?>

<div class="online-primary-actions">

    <a
        class="manage-request-trigger manage-request-link"
        href="service_requests.php?<?= h(http_build_query([
            "search" => (string)($row["reference_no"] ?? ""),
            "focus" => (string)($row["reference_no"] ?? "")
        ])) ?>#service-request-records"
    >Manage Request</a>

    <?php if (($row["status"] ?? "") === "Approved"): ?>
        <a
            class="btn btn-print"
            href="certificate_print.php?request_id=<?= (int)$row["id"] ?>"
        >🖨️ Print Certificate</a>
    <?php endif; ?>

    <form method="POST" class="delete-confirm-form">
        <input type="hidden" name="csrf_token"
               value="<?= h($csrfToken) ?>">
        <input type="hidden" name="delete_online_request"
               value="1">
        <input type="hidden" name="online_request_id"
               value="<?= (int)$row["id"] ?>">
        <input type="hidden" name="search"
               value="<?= h($search) ?>">
        <input type="hidden" name="page"
               value="<?= (int)$currentPage ?>">
        <input type="hidden" name="source"
               value="<?= h($recordSource) ?>">

        <button type="submit" class="btn btn-delete">
            🗑️ Delete
        </button>
    </form>

</div>

<?php else: ?>

                                <div class="manual-actions">
                                    <?php if (in_array($row["status"], ["Approved", "Released"], true)): ?>
                                        <a
                                            class="btn btn-print"
                                            href="certificate_print.php?id=<?= (int)$row["id"] ?>"
                                        >🖨️ Print</a>
                                    <?php endif; ?>

                                    <a
                                        class="btn btn-edit"
                                        href="certificates.php?edit=<?= (int)$row["id"] ?>#certificate-form"
                                    >Edit</a>

                                    <form method="POST" class="delete-confirm-form">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                        <input type="hidden" name="delete_id" value="<?= (int)$row["id"] ?>">
                                        <button type="submit" class="btn btn-delete">Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align:center;">No certificate records found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalRecords > 0): ?>
        <div class="pagination-wrapper">
            <div class="pagination-info">
                Showing <?= $offset + 1 ?>–<?= min($offset + $recordsPerPage, $totalRecords) ?>
                of <?= $totalRecords ?> records
            </div>

            <div class="pagination">
                <?php if ($currentPage > 1): ?>
                    <a class="page-link"
                        href="certificates.php?<?= h(http_build_query([
                            "search" => $search,
                            "page" => $currentPage - 1,
                            "source" => $recordSource
                        ])) ?>#certificate-records">Previous</a>
                <?php endif; ?>

                <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                    <a class="page-link <?= $page === $currentPage ? "active" : "" ?>"
                        href="certificates.php?<?= h(http_build_query([
                            "search" => $search,
                            "page" => $page,
                            "source" => $recordSource
                        ])) ?>#certificate-records"><?= $page ?></a>
                <?php endfor; ?>

                <?php if ($currentPage < $totalPages): ?>
                    <a class="page-link"
                        href="certificates.php?<?= h(http_build_query([
                            "search" => $search,
                            "page" => $currentPage + 1,
                            "source" => $recordSource
                        ])) ?>#certificate-records">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.details-popup-trigger {
    background: transparent;
    border: 0;
    color: #2563eb;
    cursor: pointer;
    display: inline-block;
    font: inherit;
    font-size: 12px;
    font-weight: 650;
    margin-top: 7px;
    padding: 0;
    text-align: left;
}

.details-popup-trigger:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

.applicant-modal-overlay {
    align-items: center;
    background: rgba(15, 23, 42, .58);
    display: none;
    inset: 0;
    justify-content: center;
    padding: 20px;
    position: fixed;
    z-index: 10000;
}

.applicant-modal-overlay.is-open {
    display: flex;
}

.applicant-modal {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 24px 70px rgba(15, 23, 42, .25);
    max-height: min(85vh, 760px);
    max-width: 700px;
    overflow-y: auto;
    width: 100%;
}

.applicant-modal-header {
    align-items: flex-start;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    padding: 20px 24px;
}

.applicant-modal-header h2 {
    color: #172554;
    font-size: 20px;
    margin: 0;
}

.applicant-modal-header p {
    color: #64748b;
    font-size: 13px;
    margin: 5px 0 0;
}

.applicant-modal-close {
    align-items: center;
    background: #f1f5f9;
    border: 0;
    border-radius: 8px;
    color: #475569;
    cursor: pointer;
    display: inline-flex;
    font-size: 20px;
    height: 36px;
    justify-content: center;
    width: 36px;
}

.applicant-modal-close:hover {
    background: #e2e8f0;
}

.applicant-modal-body {
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    padding: 20px 24px 24px;
}

.applicant-detail-item {
    background: #f8fafc;
    border: 1px solid #e8edf3;
    border-radius: 10px;
    min-width: 0;
    padding: 11px 13px;
}

.applicant-detail-item.is-wide {
    grid-column: 1 / -1;
}

.applicant-detail-label {
    color: #64748b;
    display: block;
    font-size: 11px;
    font-weight: 700;
    margin-bottom: 5px;
    text-transform: uppercase;
}

.applicant-detail-value {
    color: #1e293b;
    display: block;
    font-size: 14px;
    overflow-wrap: anywhere;
    white-space: pre-wrap;
}

@media (max-width: 600px) {
    .applicant-modal-overlay {
        padding: 12px;
    }

    .applicant-modal-header,
    .applicant-modal-body {
        padding-left: 16px;
        padding-right: 16px;
    }

    .applicant-modal-body {
        grid-template-columns: 1fr;
    }

    .applicant-detail-item.is-wide {
        grid-column: auto;
    }
}

.crud-popup-overlay {
    align-items: center;
    background: rgba(15, 23, 42, .6);
    display: flex;
    inset: 0;
    justify-content: center;
    padding: 20px;
    position: fixed;
    z-index: 20000;
}

.crud-popup-box {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(15, 23, 42, .3);
    max-width: 400px;
    padding: 28px;
    text-align: center;
    width: 100%;
}

.crud-popup-icon {
    align-items: center;
    border-radius: 50%;
    display: flex;
    font-size: 28px;
    font-weight: 700;
    height: 56px;
    justify-content: center;
    margin: 0 auto 12px;
    width: 56px;
}

.crud-popup-box.success .crud-popup-icon {
    background: #dcfce7;
    color: #16a34a;
}

.crud-popup-box.error .crud-popup-icon {
    background: #fee2e2;
    color: #dc2626;
}

.crud-popup-box h3 {
    margin: 0 0 8px;
}

.crud-popup-box p {
    color: #475569;
    margin: 0 0 20px;
}

.crud-popup-button {
    background: #2563eb;
    border: 0;
    border-radius: 8px;
    color: #fff;
    cursor: pointer;
    padding: 10px 24px;
}


.reference-number {
    color: #1d4ed8;
    font-size: 13px;
    font-weight: 750;
    letter-spacing: .01em;
    white-space: nowrap;
}
    

#certificate-records .certificate-actions > form.delete-confirm-form {
    margin-top: 8px;
}

#certificate-records .certificate-actions .btn-delete {
    background: #fff1f2;
    border: 1px solid #fecdd3;
    border-radius: 7px;
    color: #be123c;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 650;
    min-height: 34px;
    padding: 7px 10px;
}


/* ===== COMPACT CERTIFICATE RECORDS LAYOUT ===== */
#certificate-records {
    box-sizing: border-box;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    padding: 18px 20px;
}

#certificate-records .section-header {
    gap: 12px;
    margin-bottom: 12px;
}

#certificate-records .section-header h2 {
    font-size: 21px;
    margin-bottom: 3px;
}

#certificate-records .section-header p {
    font-size: 12px;
    margin: 0;
}

#certificate-records .section-header > a {
    flex-shrink: 0;
    font-size: 12px;
    padding: 9px 12px;
}

#certificate-records .search-form {
    display: flex;
    align-items: center;
    flex-wrap: nowrap;
    gap: 8px;
    margin-bottom: 12px;
}

#certificate-records .search-form input {
    flex: 1 1 420px;
    width: auto;
    min-width: 100px;
    max-width: none;
    min-height: 38px;
    font-size: 13px;
    padding: 8px 11px;
}

#certificate-records .search-form select,
#certificate-records .search-form .btn {
    min-height: 38px;
    font-size: 13px;
    padding: 8px 12px;
    flex-shrink: 0;
}

#certificate-records .table-container {
    width: 100%;
    max-width: 100%;
    min-width: 0;
    overflow-x: hidden !important;
    overflow-y: visible;
    border-radius: 9px;
}

#certificate-records table.certificate-table {
    width: 100% !important;
    min-width: 0 !important;
    table-layout: fixed;
    border-collapse: collapse;
    font-size: 12px;
}

#certificate-records .certificate-table th,
#certificate-records .certificate-table td {
    box-sizing: border-box;
    padding: 7px 6px;
    font-size: 11px;
    line-height: 1.3;
    vertical-align: middle;
    overflow-wrap: anywhere;
}

#certificate-records .certificate-table thead th {
    padding-top: 10px;
    padding-bottom: 10px;
    white-space: normal;
}

#certificate-records .certificate-table tbody tr {
    height: auto;
}

#certificate-records .certificate-table td:nth-child(1) {
    width: 13%;
}

#certificate-records .certificate-table td:nth-child(2) {
    width: 11%;
}

#certificate-records .certificate-table td:nth-child(3) {
    width: 16%;
}

#certificate-records .certificate-table td:nth-child(4) {
    width: 14%;
}

#certificate-records .certificate-table td:nth-child(5) {
    width: 12%;
}

#certificate-records .certificate-table td:nth-child(6) {
    width: 10%;
}

#certificate-records .certificate-table td:nth-child(7) {
    width: 8%;
}

#certificate-records .certificate-table td:nth-child(8) {
    width: 16%;
}

#certificate-records .source-badge,
#certificate-records .certificate-actions .status-badge {
    display: inline-flex;
    max-width: 100%;
    padding: 4px 6px;
    font-size: 10px;
    line-height: 1.25;
    white-space: normal;
    overflow-wrap: anywhere;
}

#certificate-records .details-popup-trigger {
    display: block;
    margin-top: 4px;
    padding: 0;
    border: 0;
    background: transparent;
    color: #2563eb;
    font-size: 10px;
    line-height: 1.3;
    text-align: left;
    cursor: pointer;
}

#certificate-records .certificate-actions {
    min-width: 0 !important;
}

#certificate-records .certificate-actions > form,
#certificate-records .record-actions form {
    max-width: 100%;
    min-width: 0;
}

#certificate-records .record-actions {
    margin-top: 4px;
}

#certificate-records .record-actions summary,
#certificate-records .manual-actions .btn,
#certificate-records .record-actions .btn {
    min-height: 28px;
    padding: 5px 7px;
    font-size: 10px;
    white-space: normal;
    line-height: 1.2;
}

#certificate-records .record-actions form {
    gap: 6px;
    padding: 8px;
}

#certificate-records .record-actions label {
    font-size: 11px;
    gap: 3px;
}

#certificate-records .record-actions select,
#certificate-records .record-actions textarea {
    box-sizing: border-box;
    width: 100%;
    min-width: 0;
    padding: 6px;
    font-size: 11px;
}

#certificate-records .record-actions textarea {
    min-height: 45px;
}

#certificate-records .manual-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 4px;
}

#certificate-records .manual-actions form {
    margin: 0;
}

#certificate-records .pagination-wrapper {
    margin-top: 10px;
    padding-top: 10px;
    gap: 8px;
    font-size: 12px;
}

/* Keep the table usable on smaller screens */
@media (max-width: 1050px) {
    #certificate-records .search-form {
        flex-wrap: wrap;
    }

    #certificate-records .table-container {
        overflow-x: auto !important;
    }

    #certificate-records table.certificate-table {
        min-width: 850px !important;
    }
}

@media (max-width: 600px) {
    #certificate-records {
        padding: 12px;
    }

    #certificate-records .section-header {
        align-items: flex-start;
        flex-direction: column;
    }

    #certificate-records .search-form input {
        flex-basis: 100%;
    }
}
    

/* ==========================================
   CERTIFICATE RECORDS - LAYOUT FIX
   Prevent overlapping and clipping
========================================== */

#certificate-records {
    box-sizing: border-box;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    overflow: visible;
}

#certificate-records .section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
}

/* Search controls */
#certificate-records .search-form {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin-bottom: 16px;
}

#certificate-records .search-form input {
    flex: 1 1 350px;
    width: auto;
    min-width: 0;
    max-width: 100%;
    box-sizing: border-box;
}

#certificate-records .search-form select {
    flex: 0 1 180px;
    min-width: 130px;
    box-sizing: border-box;
}

/* Table wrapper: allow horizontal scrolling when necessary */
#certificate-records .table-container {
    display: block;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    overflow-x: auto !important;
    overflow-y: visible;
    box-sizing: border-box;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

/* Let the browser calculate appropriate column widths */
#certificate-records table.certificate-table {
    width: 100% !important;
    min-width: 1120px;
    table-layout: auto !important;
    border-collapse: separate;
    border-spacing: 0;
    margin: 0;
}

/* Table headings */
#certificate-records .certificate-table thead th {
    padding: 12px 10px;
    background: #f1f5f9;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.4;
    text-align: left;
    vertical-align: middle;
    white-space: normal;
    overflow-wrap: normal;
}

/* Record cells */
#certificate-records .certificate-table tbody td {
    padding: 12px 10px;
    font-size: 12px;
    line-height: 1.5;
    vertical-align: middle;
    white-space: normal;
    overflow-wrap: break-word;
    word-break: normal;
    height: auto !important;
    min-height: 0 !important;
    max-height: none !important;
}

/* Reference number: preserve the full text */
#certificate-records .certificate-table th:first-child,
#certificate-records .certificate-table td:first-child {
    min-width: 145px;
    overflow-wrap: normal;
    word-break: normal;
}

/* Source badge */
#certificate-records .source-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    max-width: 100%;
    box-sizing: border-box;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    line-height: 1.3;
    white-space: nowrap;
}

/* Applicant information */
#certificate-records .certificate-table td:nth-child(3) {
    min-width: 165px;
}

#certificate-records .certificate-table td:nth-child(3) a,
#certificate-records .certificate-table td:nth-child(3) button {
    max-width: 100%;
}

/* Dates */
#certificate-records .certificate-table th:nth-child(6),
#certificate-records .certificate-table td:nth-child(6),
#certificate-records .certificate-table th:nth-child(7),
#certificate-records .certificate-table td:nth-child(7) {
    min-width: 105px;
    white-space: nowrap;
}

/* Status and action column */
#certificate-records .certificate-table th:last-child,
#certificate-records .certificate-table td:last-child {
    min-width: 185px;
}

#certificate-records .certificate-actions {
    min-width: 0 !important;
    width: auto;
    vertical-align: middle;
}

#certificate-records .certificate-actions > a,
#certificate-records .certificate-actions > button {
    max-width: 100%;
    box-sizing: border-box;
}

#certificate-records .certificate-actions > form,
#certificate-records .certificate-actions .delete-confirm-form {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
    width: 100%;
    max-width: 190px;
    margin-top: 6px;
    box-sizing: border-box;
}

/* Buttons */
#certificate-records .certificate-actions .btn,
#certificate-records .manual-actions .btn {
    box-sizing: border-box;
    max-width: 100%;
    min-height: 32px;
    padding: 7px 9px;
    font-size: 11px;
    line-height: 1.3;
    white-space: normal;
    overflow-wrap: normal;
    text-align: center;
}

/* Print button */
#certificate-records .btn-print {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-top: 5px;
}

/* Delete button */
#certificate-records .btn-delete {
    background: #fff1f2;
    border: 1px solid #fecdd3;
    border-radius: 7px;
    color: #be123c;
    cursor: pointer;
}

/* Keep expanded request controls within their cell */
#certificate-records .record-actions {
    max-width: 100%;
    margin-top: 6px;
}

#certificate-records .record-actions form {
    box-sizing: border-box;
    width: 100%;
    max-width: 240px;
}

#certificate-records .record-actions select,
#certificate-records .record-actions textarea {
    box-sizing: border-box;
    width: 100%;
    max-width: 100%;
}

/* Applicant details must not cover neighboring cells */
#certificate-records .certificate-table details {
    position: relative;
    max-width: 100%;
    overflow-wrap: anywhere;
}

#certificate-records .certificate-table details[open] {
    background: #f8fafc;
    padding: 6px;
    border-radius: 6px;
}

#certificate-records .certificate-table details summary {
    cursor: pointer;
    color: #2563eb;
    font-size: 11px;
}

/* Smaller screens */
@media (max-width: 1100px) {
    #certificate-records .table-container {
        overflow-x: auto !important;
    }

    #certificate-records table.certificate-table {
        min-width: 1120px;
    }
}

@media (max-width: 600px) {
    #certificate-records .section-header {
        align-items: flex-start;
        flex-direction: column;
    }

    #certificate-records .search-form {
        align-items: stretch;
        flex-direction: column;
    }

    #certificate-records .search-form input,
    #certificate-records .search-form select,
    #certificate-records .search-form button {
        width: 100%;
        max-width: 100%;
    }
}
    

/* ===== ONLINE CERTIFICATE ACTIONS: SIDE BY SIDE ===== */

#certificate-records td.certificate-actions {
    min-width: 0 !important;
    vertical-align: middle;
}

/* Status stays on its own line */
#certificate-records td.certificate-actions .status-badge {
    display: table;
    margin-bottom: 8px;
}

/* Manage Request and Delete beside each other */
#certificate-records td.certificate-actions > .manage-request-link,
#certificate-records td.certificate-actions > form.delete-confirm-form {
    display: inline-flex !important;
    vertical-align: middle;
    align-items: center;
    margin: 0 5px 5px 0 !important;
    max-width: 100%;
}

#certificate-records td.certificate-actions > form.delete-confirm-form {
    width: auto !important;
    max-width: none !important;
    flex-direction: row !important;
    gap: 0 !important;
}

#certificate-records td.certificate-actions .manage-request-link,
#certificate-records td.certificate-actions .btn-delete {
    box-sizing: border-box;
    min-height: 34px;
    padding: 7px 10px;
    font-size: 12px;
    white-space: nowrap;
}

/* Print stays compact on approved records */
#certificate-records td.certificate-actions > .btn-print {
    display: table;
    margin: 3px 0 6px;
    min-height: 32px;
    padding: 6px 9px;
    font-size: 12px;
}

/* ===== FIT TABLE TO DESKTOP WIDTH ===== */

#certificate-records .table-container {
    width: 100%;
    max-width: 100%;
    overflow-x: hidden !important;
}

#certificate-records table.certificate-table {
    width: 100% !important;
    min-width: 0 !important;
    table-layout: fixed !important;
}

/* Balance the eight columns */
#certificate-records .certificate-table th:nth-child(1),
#certificate-records .certificate-table td:nth-child(1) {
    width: 13%;
}

#certificate-records .certificate-table th:nth-child(2),
#certificate-records .certificate-table td:nth-child(2) {
    width: 11%;
}

#certificate-records .certificate-table th:nth-child(3),
#certificate-records .certificate-table td:nth-child(3) {
    width: 15%;
}

#certificate-records .certificate-table th:nth-child(4),
#certificate-records .certificate-table td:nth-child(4) {
    width: 13%;
}

#certificate-records .certificate-table th:nth-child(5),
#certificate-records .certificate-table td:nth-child(5) {
    width: 11%;
}

#certificate-records .certificate-table th:nth-child(6),
#certificate-records .certificate-table td:nth-child(6) {
    width: 11%;
}

#certificate-records .certificate-table th:nth-child(7),
#certificate-records .certificate-table td:nth-child(7) {
    width: 9%;
}

#certificate-records .certificate-table th:nth-child(8),
#certificate-records .certificate-table td:nth-child(8) {
    width: 17%;
}

/* Prevent text from overlapping neighboring columns */
#certificate-records .certificate-table th,
#certificate-records .certificate-table td {
    box-sizing: border-box;
    padding: 9px 6px;
    overflow-wrap: anywhere;
    word-break: normal;
    vertical-align: middle;
}

#certificate-records .source-badge {
    max-width: 100%;
    white-space: normal;
    overflow-wrap: normal;
}

/* Small screens can scroll horizontally rather than clip details */
@media (max-width: 1100px) {
    #certificate-records .table-container {
        overflow-x: auto !important;
    }

    #certificate-records table.certificate-table {
        min-width: 1050px !important;
    }
}


/* FINAL OVERRIDE: online request action buttons */
#certificate-records .certificate-actions .online-primary-actions {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 6px !important;
    width: 100%;
    max-width: 100%;
    margin: 5px 0 !important;
}

#certificate-records .certificate-actions
.online-primary-actions > .manage-request-link,
#certificate-records .certificate-actions
.online-primary-actions > form.delete-confirm-form {
    display: inline-flex !important;
    flex: 0 0 auto !important;
    align-items: center;
    width: auto !important;
    max-width: none !important;
    margin: 0 !important;
}

#certificate-records .certificate-actions
.online-primary-actions > form.delete-confirm-form {
    flex-direction: row !important;
    gap: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
}

#certificate-records .online-primary-actions .manage-request-link,
#certificate-records .online-primary-actions .btn-delete {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 7px 9px !important;
    margin: 0 !important;
    font-size: 11px !important;
    white-space: nowrap !important;
    overflow-wrap: normal !important;
}

#certificate-records .certificate-actions > .btn-print {
    display: inline-flex;
    margin-top: 5px;
}

/* Desktop: fit the table within its available width */
@media (min-width: 1101px) {
    #certificate-records .table-container {
        width: 100%;
        max-width: 100%;
        overflow-x: hidden !important;
    }

    #certificate-records table.certificate-table {
        width: 100% !important;
        min-width: 0 !important;
        table-layout: fixed !important;
    }
}

/* Align online certificate action buttons */
#certificate-records .online-primary-actions {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 8px !important;
    width: 100%;
    margin: 6px 0 !important;
}

#certificate-records .online-primary-actions > a,
#certificate-records .online-primary-actions > form {
    display: inline-flex !important;
    flex: 0 0 auto !important;
    align-items: center !important;
    width: auto !important;
    margin: 0 !important;
}

#certificate-records .online-primary-actions > form {
    flex-direction: row !important;
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
}

#certificate-records .online-primary-actions .btn,
#certificate-records .online-primary-actions .manage-request-link {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-sizing: border-box !important;
    min-height: 40px !important;
    margin: 0 !important;
    padding: 9px 13px !important;
    white-space: nowrap !important;
    line-height: 1.3 !important;
}       



/* FINAL DESKTOP FIT: keep all certificate columns visible without horizontal scrolling */
@media (min-width: 1101px) {
    #certificate-records,
    #certificate-records .table-container {
        box-sizing: border-box !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
        overflow-x: hidden !important;
    }

    #certificate-records table.certificate-table {
        width: 100% !important;
        min-width: 0 !important;
        table-layout: fixed !important;
        border-spacing: 0;
    }

    #certificate-records .certificate-table th,
    #certificate-records .certificate-table td {
        box-sizing: border-box !important;
        min-width: 0 !important;
        padding: 9px 7px !important;
        font-size: 11px !important;
        line-height: 1.35 !important;
        overflow-wrap: anywhere !important;
        vertical-align: middle !important;
    }

    /* Column widths total 100%; reserve enough room for action controls. */
    #certificate-records .certificate-table th:nth-child(1),
    #certificate-records .certificate-table td:nth-child(1) { width: 12% !important; }
    #certificate-records .certificate-table th:nth-child(2),
    #certificate-records .certificate-table td:nth-child(2) { width: 10% !important; }
    #certificate-records .certificate-table th:nth-child(3),
    #certificate-records .certificate-table td:nth-child(3) { width: 15% !important; }
    #certificate-records .certificate-table th:nth-child(4),
    #certificate-records .certificate-table td:nth-child(4) { width: 12% !important; }
    #certificate-records .certificate-table th:nth-child(5),
    #certificate-records .certificate-table td:nth-child(5) { width: 9% !important; }
    #certificate-records .certificate-table th:nth-child(6),
    #certificate-records .certificate-table td:nth-child(6) { width: 11% !important; }
    #certificate-records .certificate-table th:nth-child(7),
    #certificate-records .certificate-table td:nth-child(7) { width: 9% !important; }
    #certificate-records .certificate-table th:nth-child(8),
    #certificate-records .certificate-table td:nth-child(8) { width: 22% !important; }

    #certificate-records .source-badge,
    #certificate-records .certificate-actions .status-badge {
        max-width: 100% !important;
        white-space: normal !important;
        overflow-wrap: anywhere !important;
        font-size: 10px !important;
        padding: 5px 6px !important;
    }

    /* Wrap action buttons neatly inside the last column instead of clipping them. */
    #certificate-records .online-primary-actions,
    #certificate-records .manual-actions {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: wrap !important;
        align-items: center !important;
        gap: 5px !important;
        width: 100% !important;
        max-width: 100% !important;
        margin: 6px 0 0 !important;
    }

    #certificate-records .online-primary-actions > a,
    #certificate-records .online-primary-actions > form,
    #certificate-records .manual-actions > a,
    #certificate-records .manual-actions > form {
        display: inline-flex !important;
        flex: 0 1 auto !important;
        min-width: 0 !important;
        max-width: 100% !important;
        margin: 0 !important;
    }

    #certificate-records .online-primary-actions > form {
        padding: 0 !important;
        border: 0 !important;
        background: transparent !important;
    }

    #certificate-records .online-primary-actions .btn,
    #certificate-records .online-primary-actions .manage-request-link,
    #certificate-records .manual-actions .btn {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-sizing: border-box !important;
        min-height: 30px !important;
        max-width: 100% !important;
        padding: 6px 7px !important;
        font-size: 10px !important;
        line-height: 1.25 !important;
        text-align: center !important;
        white-space: normal !important;
        overflow-wrap: anywhere !important;
    }

    #certificate-records .certificate-actions > form,
    #certificate-records .certificate-actions .delete-confirm-form {
        width: auto !important;
        max-width: 100% !important;
        margin: 0 !important;
    }

    #certificate-records .certificate-actions .details-popup-trigger {
        max-width: 100% !important;
        white-space: normal !important;
        overflow-wrap: anywhere !important;
    }
}

/* On narrower screens, keep the full table readable with touch scrolling. */
@media (max-width: 1100px) {
    #certificate-records .table-container {
        max-width: 100% !important;
        overflow-x: auto !important;
        -webkit-overflow-scrolling: touch;
    }
    #certificate-records table.certificate-table {
        min-width: 1050px !important;
    }
}

</style>

<div
    class="applicant-modal-overlay"
    id="applicantDetailsModal"
    aria-hidden="true"
>
    <section
        class="applicant-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="applicantModalTitle"
    >
        <header class="applicant-modal-header">
            <div>
                <h2 id="applicantModalTitle">Applicant Details</h2>
                <p>Information submitted with this certificate request.</p>
            </div>

            <button
                type="button"
                class="applicant-modal-close"
                id="closeApplicantModal"
                aria-label="Close"
            >&times;</button>
        </header>

        <div class="applicant-modal-body" id="applicantDetailsContent"></div>
    </section>
</div>

<div class="delete-confirm-overlay" id="deleteConfirmPopup" hidden>
    <div class="delete-confirm-box" role="dialog" aria-modal="true">
        <div class="delete-confirm-icon">⚠️</div>

        <h3>Confirm Delete</h3>
        <p>Are you sure you want to delete this certificate record?</p>

        <div class="delete-confirm-buttons">
            <button type="button" id="cancelDeleteButton">Cancel</button>
            <button type="button" id="confirmDeleteButton">Delete</button>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById("applicantDetailsModal");
    const content = document.getElementById("applicantDetailsContent");
    const closeButton = document.getElementById("closeApplicantModal");

    if (!modal || !content || !closeButton) return;

    function closeModal() {
        modal.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");
        content.replaceChildren();
    }

    document.addEventListener("click", function (event) {
        const trigger = event.target.closest(".details-popup-trigger");

        if (trigger) {
            let details = {};

            try {
                details = JSON.parse(trigger.dataset.details || "{}");
            } catch (error) {
                details = {};
            }

            content.replaceChildren();

            Object.entries(details).forEach(function ([label, value]) {
                const item = document.createElement("div");
                const labelElement = document.createElement("span");
                const valueElement = document.createElement("span");

                item.className = "applicant-detail-item";
                labelElement.className = "applicant-detail-label";
                valueElement.className = "applicant-detail-value";

                if (label === "Additional details") {
                    item.classList.add("is-wide");
                }

                labelElement.textContent = label;
                valueElement.textContent =
                    value === null || value === "" ? "—" : String(value);

                item.append(labelElement, valueElement);
                content.appendChild(item);
            });

            modal.classList.add("is-open");
            modal.setAttribute("aria-hidden", "false");
            closeButton.focus();
            return;
        }

        if (event.target === modal || event.target === closeButton) {
            closeModal();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && modal.classList.contains("is-open")) {
            closeModal();
        }
    });
})();
</script>

<script src="../assets/js/auto-capitalize.js" defer></script>

<script>
document.addEventListener("click", function (event) {
    const openButton = event.target.closest(".manage-request-trigger");

    if (openButton) {
        const dialog = document.getElementById(openButton.dataset.dialog);
        if (dialog) dialog.showModal();
        return;
    }

    const closeButton = event.target.closest(
        ".manage-request-close, .manage-request-cancel"
    );

    if (closeButton) {
        const dialog = closeButton.closest("dialog");
        if (dialog) dialog.close();
    }
});

document.querySelectorAll(".manage-request-dialog").forEach(function (dialog) {
    dialog.addEventListener("click", function (event) {
        if (event.target === dialog) dialog.close();
    });
});
</script>

<script>
function closeCrudPopup() {
    const popup = document.getElementById("crudPopup");
    if (popup) popup.remove();
}

if (document.getElementById("crudPopup")) {
    setTimeout(closeCrudPopup, 3000);
}
</script>

<script>
let deleteFormToSubmit = null;
const deletePopup = document.getElementById("deleteConfirmPopup");

document.querySelectorAll(".delete-confirm-form").forEach(function (form) {
    form.addEventListener("submit", function (event) {
        event.preventDefault();
        deleteFormToSubmit = form;
        deletePopup.hidden = false;
    });
});

document.getElementById("cancelDeleteButton").addEventListener("click", function () {
    deletePopup.hidden = true;
    deleteFormToSubmit = null;
});

document.getElementById("confirmDeleteButton").addEventListener("click", function () {
    if (deleteFormToSubmit) {
        deleteFormToSubmit.submit();
    }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("certificateForm");
    const submitButton = document.getElementById("certificateSubmitButton");

    // Only apply this behavior when editing an existing certificate.
    const isEditing = <?= $editCertificate ? "true" : "false" ?>;

    if (!form || !submitButton || !isEditing) {
        return;
    }

    /*
     * Fields that can be edited.
     * We compare their actual values against the values
     * loaded when the Edit page opened.
     */
    const fields = form.querySelectorAll(
        "input:not([type='hidden']), select, textarea"
    );

    // Store original values.
    const originalValues = {};

    fields.forEach(function (field) {
        originalValues[field.name] = field.value;
    });

    function hasChanges() {
        for (const field of fields) {
            const originalValue = originalValues[field.name] ?? "";
            const currentValue = field.value;

            if (currentValue !== originalValue) {
                return true;
            }
        }

        return false;
    }

    function updateButtonState() {
        const changed = hasChanges();

        submitButton.disabled = !changed;

        if (changed) {
            submitButton.title = "Save the changes";
        } else {
            submitButton.title = "No changes to update";
        }
    }

    // Check whenever the user changes or types something.
    fields.forEach(function (field) {
        field.addEventListener("input", updateButtonState);
        field.addEventListener("change", updateButtonState);
    });

    // Initial state: disabled because nothing has changed.
    updateButtonState();
});
</script>

<?php
require_once "../includes/footer.php";
?>
