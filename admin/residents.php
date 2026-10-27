
<?php

require_once __DIR__ . "/../config/admin_auth.php";



/* =========================
   CSRF PROTECTION
========================= */

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(
        random_bytes(32)
    );
}

$csrf_token = $_SESSION["csrf_token"];

require_once "../config/database.php";
require_once "../config/audit.php";

$message = "";
$messageType = "";

/* =========================
   FLASH MESSAGE
========================= */

if (isset($_SESSION["flash_message"])) {
    $message = $_SESSION["flash_message"];
    $messageType = $_SESSION["flash_type"] ?? "success";

    unset($_SESSION["flash_message"]);
    unset($_SESSION["flash_type"]);
}

/* =========================
   DELETE RESIDENT
   POST + CSRF PROTECTED
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_id"])
) {

    $deleteId = (int) $_POST["delete_id"];

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        $submittedToken === "" ||
        !hash_equals($csrf_token, $submittedToken)
    ) {

        $message = "Invalid security token. Please try again.";
        $messageType = "error";

    } elseif ($deleteId <= 0) {

        $message = "Invalid resident record.";
        $messageType = "error";

    } else {

        $stmt = $conn->prepare(
            "DELETE FROM residents WHERE id = ?"
        );

        $stmt->bind_param(
            "i",
            $deleteId
        );

        if ($stmt->execute()) {

            addAuditLog(
                $conn,
                "DELETE",
                "Residents",
                "Deleted resident record.",
                $deleteId
            );

            $_SESSION["flash_message"] = "Resident deleted successfully.";
            $_SESSION["flash_type"] = "success";

            header("Location: residents.php#resident-records");
            exit;
        } else {

            $message = "Unable to delete resident.";
            $messageType = "error";
        }

        $stmt->close();
    }
}


/* =========================
   ADD / UPDATE RESIDENT
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    !isset($_POST["delete_id"])
) {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        $submittedToken === "" ||
        !hash_equals($csrf_token, $submittedToken)
    ) {

        $message = "Invalid security token. Please refresh the page and try again.";
        $messageType = "error";

    } else {

        $id = isset($_POST["id"])
            ? (int) $_POST["id"]
            : 0;

    $first_name = trim($_POST["first_name"] ?? "");
    $middle_name = trim($_POST["middle_name"] ?? "");
    $last_name = trim($_POST["last_name"] ?? "");
    $suffix = trim($_POST["suffix"] ?? "");
    $birth_date = $_POST["birth_date"] ?? "";
    $gender = $_POST["gender"] ?? "";
    $civil_status = $_POST["civil_status"] ?? "";
    $address = trim($_POST["address"] ?? "");
    $contact_number = trim($_POST["contact_number"] ?? "");
    $email = trim($_POST["email"] ?? "");

    $allowedSuffixes = ["", "Jr.", "Sr.", "II", "III", "IV", "V"];
    $addressLength = function_exists("mb_strlen") ? mb_strlen($address, "UTF-8") : strlen($address);

    if ($email !== "" && (strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
        $message = "Please enter a valid email address.";
        $messageType = "error";
    } elseif ($first_name === "" || $last_name === "" || $birth_date === "" || $address === "") {
        $message = "Please complete all required fields.";
        $messageType = "error";
    } elseif (!preg_match("/^[A-Za-zÀ-ÿÑñ' -]+$/u", $first_name)) {
        $message = "First name must contain letters only.";
        $messageType = "error";
    } elseif ($middle_name !== "" && !preg_match("/^[A-Za-zÀ-ÿÑñ' -]+$/u", $middle_name)) {
        $message = "Middle name must contain letters only.";
        $messageType = "error";
    } elseif (!preg_match("/^[A-Za-zÀ-ÿÑñ' -]+$/u", $last_name)) {
        $message = "Last name must contain letters only.";
        $messageType = "error";
    } elseif (!in_array($suffix, $allowedSuffixes, true)) {
        $message = "Please select a valid suffix.";
        $messageType = "error";
    } elseif ($contact_number !== "" && (
        !preg_match("/^09[0-9]{9}$/", $contact_number) ||
        preg_match('/^(.)\\1{10}$/', $contact_number) ||
        preg_match('/^(.)\\1{6,}$/', $contact_number)
    )) {
        $message = "Please enter a valid contact number that starts with 09 and contains exactly 11 digits.";
        $messageType = "error";
    } elseif ($addressLength < 5 || $addressLength > 255) {
        $message = "Address must be between 5 and 255 characters.";
        $messageType = "error";
    } elseif (
        $birth_date !== "" &&
        (
            !preg_match("/^\\d{4}-\\d{2}-\\d{2}$/", $birth_date) ||
            ($birthDateObject = DateTime::createFromFormat("!Y-m-d", $birth_date)) === false ||
            $birthDateObject->format("Y-m-d") !== $birth_date ||
            $birth_date > date("Y-m-d")
        )
    ) {
        $message = "Please enter a valid birth date that is not in the future.";
        $messageType = "error";
    } elseif (!in_array($gender, ["Male", "Female"], true)) {
        $message = "Please select a valid gender.";
        $messageType = "error";
    } elseif (!in_array($civil_status, ["Single", "Married", "Widowed", "Separated"], true)) {
        $message = "Please select a valid civil status.";
        $messageType = "error";
    } else {
        if ($contact_number !== "") {
            $checkStmt = $conn->prepare(
                "SELECT id FROM residents WHERE contact_number = ? AND id != ? LIMIT 1"
            );

            if (!$checkStmt) {
                $message = "Unable to verify the contact number.";
                $messageType = "error";
            } else {
                $checkStmt->bind_param("si", $contact_number, $id);

                if (!$checkStmt->execute()) {
                    $message = "Unable to verify the contact number.";
                    $messageType = "error";
                } else {
                    $checkResult = $checkStmt->get_result();
                    if ($checkResult->num_rows > 0) {
                        $message = "This contact number is already registered.";
                        $messageType = "error";
                    }
                }

                $checkStmt->close();
            }
        }
    }
                /* =========================
                   SAVE ONLY IF VALID
                ========================= */

                if ($message === "") {

                    /* =========================
                       UPDATE
                    ========================= */

                    if ($id > 0) {

                        $stmt = $conn->prepare(
                            "UPDATE residents
                             SET first_name = ?,
                                 middle_name = ?,
                                 last_name = ?,
                                 suffix = ?,
                                 birth_date = NULLIF(?, ''),
                                 gender = ?,
                                 civil_status = ?,
                                 address = ?,
                                 contact_number = ?,
                                 email = NULLIF(?, '')
                             WHERE id = ?"
                        );

                        $stmt->bind_param(
                            "ssssssssssi",
                            $first_name,
                            $middle_name,
                            $last_name,
                            $suffix,
                            $birth_date,
                            $gender,
                            $civil_status,
                            $address,
                            $contact_number,
                            $email,
                            $id
                        );

                        if ($stmt->execute()) {

                            addAuditLog(
                                $conn,
                                "UPDATE",
                                "Residents",
                                "Updated resident information.",
                                $id
                            );

                            $_SESSION["flash_message"] = "Resident updated successfully.";
                            $_SESSION["flash_type"] = "success";

                            header("Location: residents.php#resident-records");
                            exit;
                        } else {
                            $message = "Unable to update resident.";
                            $messageType = "error";
                        }

                        $stmt->close();

                    } else {

                        /* =========================
                           INSERT
                        ========================= */

                        $stmt = $conn->prepare(
                            "INSERT INTO residents
                            (
                                first_name,
                                middle_name,
                                last_name,
                                suffix,
                                birth_date,
                                gender,
                                civil_status,
                                address,
                                contact_number,
                                email
                            )
                            VALUES (?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, NULLIF(?, ''))"
                            );

                        $stmt->bind_param(
                            "ssssssssss",
                            $first_name,
                            $middle_name,
                            $last_name,
                            $suffix,
                            $birth_date,
                            $gender,
                            $civil_status,
                            $address,
                            $contact_number,
                            $email
                        );

                        if ($stmt->execute()) {

                            $resident_id = $stmt->insert_id;

                            addAuditLog(
                                $conn,
                                "ADD",
                                "Residents",
                                "Added a new resident.",
                                $resident_id
                            );

                            $_SESSION["flash_message"] = "Resident added successfully.";
                            $_SESSION["flash_type"] = "success";

                            header("Location: residents.php#resident-records");
                            exit;
                        } else {
                            $message = "Unable to add resident.";
                            $messageType = "error";
                        }

                        $stmt->close();
                    }
                }
}
}


/* =========================
   SEARCH + PAGINATION
========================= */

$search = trim($_GET["search"] ?? "");

/*
|--------------------------------------------------------------------------
| Pagination Settings
|--------------------------------------------------------------------------
*/

$recordsPerPage = 10;

$currentPage = isset($_GET["page"])
    ? (int) $_GET["page"]
    : 1;

if ($currentPage < 1) {
    $currentPage = 1;
}


/* Resident Records contains residents entered by admin only. */
$searchValue = "%" . $search . "%";
$residentWhere = "";

if ($search !== "") {
    $residentWhere = "WHERE first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR address LIKE ? OR contact_number LIKE ? OR email LIKE ?";
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM residents $residentWhere");
    $countStmt->bind_param("ssssss", $searchValue, $searchValue, $searchValue, $searchValue, $searchValue, $searchValue);
} else {
    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM residents");
}

$countStmt->execute();
$totalRecords = (int)($countStmt->get_result()->fetch_assoc()["total"] ?? 0);
$countStmt->close();

$totalPages = max(1, (int)ceil($totalRecords / $recordsPerPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $recordsPerPage;

$residentSelect = "SELECT
    id,
    NULL AS reference_no,
    NULL AS service_type,
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
    NULL AS purpose,
    NULL AS additional_details,
    NULL AS respondent,
    NULL AS incident,
    NULL AS incident_date,
    NULL AS blotter_remarks,
    'Registered' AS status,
    NULL AS admin_remarks,
    NULL AS created_at
    FROM residents $residentWhere
    ORDER BY last_name ASC, first_name ASC
    LIMIT ? OFFSET ?";
$stmt = $conn->prepare($residentSelect);
if ($search !== "") {
    $stmt->bind_param("ssssssii", $searchValue, $searchValue, $searchValue, $searchValue, $searchValue, $searchValue, $recordsPerPage, $offset);
} else {
    $stmt->bind_param("ii", $recordsPerPage, $offset);
}
$stmt->execute();
$residents = $stmt->get_result();


/* =========================
   EDIT RESIDENT
========================= */

$editResident = null;

if (isset($_GET["edit"])) {

    $editId = (int) $_GET["edit"];

    if ($editId > 0) {

        $stmt = $conn->prepare(
            "SELECT *
             FROM residents
             WHERE id = ?"
        );

        $stmt->bind_param("i", $editId);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $editResident = $result->fetch_assoc();
        }

        $stmt->close();
    }
}

$residentFormData = $editResident ?? [];
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST["delete_id"])) {
    $residentFormData = array_merge($residentFormData, $_POST);
}
$residentEditableFields = [
    "first_name", "middle_name", "last_name", "suffix", "birth_date",
    "gender", "civil_status", "address", "contact_number", "email"
];
$residentOriginalValues = [];
if ($editResident) {
    foreach ($residentEditableFields as $field) {
        $residentOriginalValues[$field] = (string) ($editResident[$field] ?? "");
    }
}


/* =========================
   SHARED HEADER
   Includes the SAME sidebar
   used by Certificates
========================= */

require_once "../includes/header.php";

?>

<style>
/* Match the public online form layout and control styling. */
.content-section:has(#residentForm) {
    background: #fff;
    border: 1px solid #e4e9f2;
    border-top: 5px solid #2563eb;
    border-radius: 14px;
    box-shadow: 0 5px 20px rgba(15, 23, 42, .07);
    padding: clamp(23px, 3vw, 35px);
    margin-bottom: 26px;
}
.content-section:has(#residentForm) .section-header {
    margin-bottom: 28px;
}
.content-section:has(#residentForm) .section-header h2 {
    color: #17233d;
    font-size: clamp(1.25rem, 2vw, 1.65rem);
    font-weight: 750;
    letter-spacing: -.025em;
    margin: 0 0 6px;
}
.content-section:has(#residentForm) .section-header p {
    color: #64748b;
    margin: 0;
    line-height: 1.55;
}
#residentForm {
    background: transparent;
    border: 0;
    padding: 0;
    scroll-margin-top: 24px;
}
#residentForm .form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    align-items: start;
}
#residentForm .form-group { min-width: 0; margin: 0; }
#residentForm .required { color: #dc2626; }
#residentForm .form-group.full-width { grid-column: 1 / -1; }
#residentForm label {
    display: block;
    color: #26354d;
    font-size: .9rem;
    font-weight: 650;
    margin: 0 0 8px;
}
#residentForm input:not([type="hidden"]),
#residentForm select {
    box-sizing: border-box;
    display: block;
    width: 100%;
    min-width: 0;
    min-height: 46px;
    padding: 11px 13px;
    border: 1px solid #d4dce8;
    border-radius: 9px;
    background: #fff;
    color: #182337;
    font: inherit;
    font-size: 14px;
    line-height: 1.4;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .025);
    transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
}
#residentForm input::placeholder { color: #94a3b8; }
#residentForm input:focus,
#residentForm select:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
}
#residentForm .resident-field-invalid { border-color: #dc2626 !important; box-shadow: 0 0 0 3px rgba(220, 38, 38, .12) !important; }
#residentForm .resident-field-error { color: #b91c1c; font-size: 12px; font-weight: 600; line-height: 1.4; margin: 7px 0 0; }
#residentForm .form-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1px solid #e3eaf3;
    padding-top: 20px;
    margin-top: 20px;
}
#residentForm .form-actions .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 10px 20px;
    border-radius: 9px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
}
#residentForm .form-actions .btn-secondary { background: #f1f5f9; border: 1px solid #e2e8f0; color: #334155; }
#residentForm .form-actions .btn-primary { background: #2563eb; border: 1px solid #2563eb; color: #fff; }
#residentForm .form-actions .btn:hover { transform: translateY(-1px); filter: brightness(.98); }
#residentForm .form-actions .btn-primary { box-shadow: 0 4px 10px rgba(37, 99, 235, .18); }
#residentForm .form-actions .btn-primary:disabled { cursor: not-allowed; filter: grayscale(.2); opacity: .55; transform: none; box-shadow: none; }
@media (max-width: 900px) {
    #residentForm .form-grid { grid-template-columns: minmax(0, 1fr); gap: 17px; }
    #residentForm .form-group.full-width { grid-column: 1; }
    #residentForm .form-actions { justify-content: stretch; }
    #residentForm .form-actions .btn { flex: 1 1 140px; }
}
@media (max-width: 900px) {
    #resident-records { min-width: 0; padding: 22px 18px; }
    #resident-records .table-container { max-width: 100%; }
    #resident-records table { min-width: 1200px; }
    #resident-records .resident-list-header .search-form { width: 100%; }
    #resident-records .resident-list-header .search-form input { flex: 1 1 100%; width: 100%; }
    #resident-records .resident-list-header .search-form button,
    #resident-records .resident-list-header .search-form a { flex: 0 1 auto; }
}
@media (max-width: 700px) {
    body:has(#residentForm) .sidebar { width: 64px !important; }
    body:has(#residentForm) .sidebar-header { justify-content: center; gap: 0; padding: 12px 6px; }
    body:has(#residentForm) .sidebar-header > div:not(.barangay-logo) { display: none; }
    body:has(#residentForm) .sidebar-menu { padding: 12px 6px 90px; }
    body:has(#residentForm) .sidebar-menu a,
    body:has(#residentForm) .sidebar-bottom a { justify-content: center; gap: 0; padding: 12px 4px; }
    body:has(#residentForm) .sidebar-menu a > span:not(.menu-icon),
    body:has(#residentForm) .sidebar-bottom a > span:not(.menu-icon) { display: none; }
    body:has(#residentForm) .sidebar-menu a .menu-icon,
    body:has(#residentForm) .sidebar-bottom a .menu-icon { flex: 0 0 24px; width: 24px; }
    body:has(#residentForm) .sidebar-bottom { width: 64px !important; padding: 12px 6px; }
    body:has(#residentForm) .main-content { box-sizing: border-box; margin-left: 64px !important; min-width: 0; padding: 16px 12px; width: calc(100% - 64px) !important; }
    body:has(#residentForm) .topbar { align-items: flex-start; flex-wrap: wrap; gap: 12px; }
    #resident-records { padding: 18px 14px; }
    #residentForm .form-actions .btn { flex: 1 1 100%; }
}
@media (max-width: 380px) {
    #resident-records .resident-list-header h2 { font-size: 20px; }
    #resident-records .search-form button, #resident-records .search-form a { flex: 1 1 100%; }
}
@media (prefers-reduced-motion: reduce) {
    #residentForm input, #residentForm select, #residentForm .form-actions .btn { transition: none; }
}

.source-badge {
    border-radius: 999px;
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.3;
    padding: 5px 9px;
    white-space: nowrap;
}
.source-badge.online { background: #dbeafe; color: #1d4ed8; }
.source-badge.manual { background: #f1f5f9; color: #475569; }
#resident-records {
    border: 1px solid #e8edf3;
    border-top: 5px solid #2563eb;
    border-radius: 15px;
    box-shadow: 0 4px 18px rgba(15, 23, 42, .06);
    padding: 25px 30px;
}
#resident-records .resident-list-header { align-items: flex-start; display: block; margin-bottom: 22px; }
#resident-records .resident-list-header h2 { color: #172554; font-size: 22px; margin: 0 0 5px; }
#resident-records .resident-list-header p { color: #64748b; margin: 0; }
#resident-records .search-form { align-items: center; display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }
#resident-records .search-form input { background: #fff; border: 1px solid #cbd5e1; border-radius: 9px; box-sizing: border-box; color: #182337; flex: 0 1 575px; font: inherit; min-height: 46px; min-width: min(100%, 260px); padding: 11px 14px; }
#resident-records .search-form input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, .14); outline: 0; }
#resident-records .search-form button, #resident-records .search-form a { align-items: center; border: 1px solid transparent; border-radius: 9px; display: inline-flex; font: inherit; font-size: 14px; font-weight: 700; justify-content: center; min-height: 44px; padding: 0 18px; text-decoration: none; }
#resident-records .search-form button { background: #2563eb; color: #fff; cursor: pointer; }
#resident-records .search-form a { background: #f1f5f9; border-color: #e2e8f0; color: #334155; }
#resident-records .search-form button:hover, #resident-records .search-form a:hover { filter: brightness(.97); }
#resident-records .table-container { border: 1px solid #e3eaf3; border-radius: 11px; overflow-x: auto; }
#resident-records table { border-collapse: separate; border-spacing: 0; min-width: 0; table-layout: fixed; width: 100%; }
#resident-records table th { background: #f1f5f9; border-bottom: 1px solid #dfe7f0; color: #334155; font-size: 12px; font-weight: 700; padding: 14px 12px; text-align: left; white-space: nowrap; }
#resident-records table td { border-bottom: 1px solid #e8edf3; color: #334155; font-size: 13px; line-height: 1.45; overflow-wrap: anywhere; padding: 12px; vertical-align: middle; }
#resident-records table tbody tr:nth-child(even) { background: #f8fafc; }
#resident-records table tbody tr:hover { background: #f1f7ff; }
#resident-records table tbody tr:last-child td { border-bottom: 0; }
#resident-records table th:nth-child(1), #resident-records table td:nth-child(1) { width: 9%; }
#resident-records table th:nth-child(2), #resident-records table td:nth-child(2) { width: 20%; }
#resident-records table th:nth-child(3), #resident-records table td:nth-child(3) { width: 8%; }
#resident-records table th:nth-child(4), #resident-records table td:nth-child(4) { width: 10%; }
#resident-records table th:nth-child(5), #resident-records table td:nth-child(5) { width: 9%; }
#resident-records table th:nth-child(6), #resident-records table td:nth-child(6) { width: 12%; }
#resident-records table th:nth-child(7), #resident-records table td:nth-child(7) { width: 13%; }
#resident-records table th:nth-child(8), #resident-records table td:nth-child(8) { width: 19%; }
#resident-records table td:nth-child(1) { font-size: 12px; white-space: nowrap; }
#resident-records table td:nth-child(2) strong { color: #172554; display: block; }
.resident-status-badge { border-radius: 999px; display: inline-block; font-size: 11px; font-weight: 700; padding: 4px 8px; white-space: nowrap; }
.resident-status-badge.status-pending { background: #fef3c7; color: #92400e; }
.resident-status-badge.status-under-review, .resident-status-badge.status-ready-for-pickup { background: #dbeafe; color: #1d4ed8; }
.resident-status-badge.status-approved, .resident-status-badge.status-completed { background: #dcfce7; color: #166534; }
.resident-status-badge.status-rejected { background: #fee2e2; color: #b91c1c; }
.resident-status-badge.status-registered { background: #f1f5f9; color: #475569; }
#resident-records .table-actions { align-items: center; display: flex; flex-direction: row; flex-wrap: nowrap; gap: 5px; white-space: nowrap; }
#resident-records .manual-actions { align-items: center; display: inline-flex; flex-wrap: nowrap; gap: 4px; }
#resident-records .manual-actions .btn-small { align-items: center; border: 1px solid transparent; border-radius: 7px; box-sizing: border-box; display: inline-flex; font-size: 11px; font-weight: 700; justify-content: center; line-height: 1.2; min-height: 28px; padding: 5px 6px; text-decoration: none; }
#resident-records .manual-actions .btn-small.edit { background: #dbeafe; color: #1d4ed8; }
#resident-records .manual-actions .btn-small.delete { background: #fff1f2; border-color: #fecdd3; color: #be123c; cursor: pointer; }
#resident-records .manual-actions .btn-small:hover { filter: brightness(.97); }
#resident-records .table-actions form { display: inline-flex; margin: 0; }
#resident-records .table-actions .btn-small { white-space: nowrap; }
@media (max-width: 760px) {
    #resident-records { padding: 18px 16px; }
    #resident-records table { min-width: 1200px; }
    .resident-list-header .search-form { width: 100%; }
    .resident-list-header .search-form input { flex: 1 1 100%; width: 100%; }
    .resident-list-header .search-form button, .resident-list-header .search-form a { flex: 1 1 110px; }
}
.manage-online-request-trigger { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; color: #1d4ed8; cursor: pointer; font: inherit; font-size: 12px; min-height: 34px; padding: 7px 10px; }
.manage-online-request-trigger:hover { background: #dbeafe; }
.resident-manage-dialog {
    left: 50%;
    margin: 0;
    border: 0;
    border-radius: 16px;
    box-shadow: 0 24px 70px rgba(15, 23, 42, .28);
    max-height: min(88vh, 760px);
    max-width: 580px;
    padding: 0;
    position: fixed;
    top: 50%;
    transform: translate(-50%, -50%);
    width: calc(100% - 32px);
}
.resident-manage-dialog::backdrop { background: rgba(15, 23, 42, .58); }
.resident-manage-header { align-items: center; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; padding: 20px 24px; }
.resident-manage-header h2 { color: #172554; font-size: 21px; margin: 0; }
.resident-manage-close { background: #f1f5f9; border: 0; border-radius: 8px; cursor: pointer; font-size: 22px; height: 38px; width: 38px; }
.resident-manage-form { padding: 20px 24px 24px; }
.resident-manage-form label { color: #475569; display: block; font-size: 13px; font-weight: 700; margin: 0 0 8px; }
.resident-manage-form select, .resident-manage-form textarea { background: #fff; border: 1px solid #cbd5e1; border-radius: 9px; box-sizing: border-box; color: #334155; font: inherit; margin-bottom: 20px; padding: 11px 13px; width: 100%; }
.resident-manage-form textarea { min-height: 85px; resize: vertical; }
.resident-manage-actions { align-items: center; display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-end; }
.resident-manage-actions button, .resident-manage-actions a { border: 0; border-radius: 8px; cursor: pointer; font: inherit; font-size: 13px; font-weight: 700; padding: 11px 15px; text-decoration: none; }
.resident-manage-cancel { background: #f1f5f9; color: #334155; }
.resident-manage-save { background: #2563eb; color: #fff; }
.resident-manage-print { background: #059669; color: #fff; }
@media (max-width: 600px) {
    .resident-manage-header { padding: 17px; }
    .resident-manage-form { padding: 17px; }
    .resident-manage-actions { align-items: stretch; flex-direction: column-reverse; }
    .resident-manage-actions button, .resident-manage-actions a { text-align: center; }
}
</style>


<!-- =========================
     TOP BAR
========================= -->

<header class="topbar">

    <div>

        <h1>
            Residents
        </h1>

        <p>
            Manage barangay resident records
        </p>

    </div>


    <div class="admin-profile">

        <div class="admin-avatar">

            <?= strtoupper(
                substr(
                    $_SESSION["username"],
                    0,
                    1
                )
            ) ?>

        </div>


        <div>

            <strong>
                <?= htmlspecialchars(
                    $_SESSION["username"]
                ) ?>
            </strong>

            <small>
                <?= htmlspecialchars(
                    ($_SESSION["role"] ?? "") === "admin"
                        ? "Administrator"
                        : ($_SESSION["role"] ?? "User")
                ) ?>
            </small>

        </div>

    </div>

</header>


<!-- =========================
     MESSAGE
========================= -->

<?php if ($message !== ""): ?>

    <?php
    $errorFocusField = "";
    if ($messageType === "error") {
        $errorFieldMap = [
            "email" => "email",
            "contact" => "contact_number",
            "address" => "address",
            "birth date" => "birth_date",
            "first name" => "first_name",
            "middle name" => "middle_name",
            "last name" => "last_name",
            "suffix" => "suffix",
            "gender" => "gender",
            "civil status" => "civil_status"
        ];
        foreach ($errorFieldMap as $messagePart => $fieldName) {
            if (stripos($message, $messagePart) !== false) {
                $errorFocusField = $fieldName;
                break;
            }
        }
        if ($errorFocusField === "" && !isset($_POST["delete_id"])) {
            foreach (["first_name", "last_name", "birth_date", "address"] as $requiredField) {
                if (trim((string)($_POST[$requiredField] ?? "")) === "") {
                    $errorFocusField = $requiredField;
                    break;
                }
            }
        }
    }
    ?>

    <div class="crud-popup-overlay" id="crudPopup" data-return-to-form="<?= $messageType === "error" ? "true" : "false" ?>" data-focus-field="<?= htmlspecialchars($errorFocusField, ENT_QUOTES, "UTF-8") ?>" data-error-message="<?= $messageType === "error" ? htmlspecialchars($message, ENT_QUOTES, "UTF-8") : "" ?>">

        <div class="crud-popup-box <?= $messageType ?>">

            <div class="crud-popup-icon">
                <?= $messageType === "success" ? "✓" : "!" ?>
            </div>

            <h3>
                <?= $messageType === "success" ? "Success!" : "Error!" ?>
            </h3>

            <p>
                <?= htmlspecialchars($message) ?>
            </p>

            <button
                type="button"
                class="crud-popup-button"
                onclick="closeCrudPopup()"
            >
                <?= $messageType === "error" ? "Go to Add Resident Form" : "OK" ?>
            </button>

        </div>

    </div>

<?php endif; ?>


<!-- =========================
     RESIDENT FORM
========================= -->

<section class="content-section">

    <div class="section-header">

        <div>

            <h2>

                <?= $editResident
                    ? "Edit Resident"
                    : "Add New Resident"
                ?>

            </h2>

            <p>
                <?= $editResident
                    ? "Update the resident's personal and contact information."
                    : "Enter a resident's personal and contact information for the barangay records."
                ?>
            </p>

        </div>

    </div>


    <form
        method="POST"
        class="resident-form"
        id="residentForm"
        <?= $editResident
            ? 'data-original-values="' . htmlspecialchars(json_encode($residentOriginalValues), ENT_QUOTES, "UTF-8") . '"'
            : ""
        ?>
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($csrf_token) ?>"
        >

        <?php if ($editResident): ?>
            <input
                type="hidden"
                name="id"
                value="<?= (int) $editResident["id"] ?>"
            >
        <?php endif; ?>

        <div class="form-grid">


            <!-- FIRST NAME -->

            <div class="form-group">

                <label for="first_name">
                    First Name <span class="required">*</span>
                </label>

                <input
                    type="text"
                    id="first_name"
                    name="first_name"
                    required
                    maxlength="50"
                    pattern="[A-Za-zÀ-ÿÑñ' -]+"
                    title="Please enter letters only."
                    value="<?= htmlspecialchars(
                        $residentFormData["first_name"] ?? ""
                    ) ?>"
                >

            </div>


            <!-- MIDDLE NAME -->

            <div class="form-group">

                <label for="middle_name">
                    Middle Name
                </label>

                <input
                    type="text"
                    id="middle_name"
                    name="middle_name"
                    maxlength="50"
                    pattern="[A-Za-zÀ-ÿÑñ' -]+"
                    title="Please enter letters only."
                    value="<?= htmlspecialchars(
                        $residentFormData["middle_name"] ?? ""
                    ) ?>"
                >

            </div>


            <!-- LAST NAME -->

            <div class="form-group">

                <label for="last_name">
                    Last Name <span class="required">*</span>
                </label>

                <input
                    type="text"
                    id="last_name"
                    name="last_name"
                    required
                    maxlength="50"
                    pattern="[A-Za-zÀ-ÿÑñ' -]+"
                    title="Please enter letters only."
                    value="<?= htmlspecialchars(
                        $residentFormData["last_name"] ?? ""
                    ) ?>"
                >

            </div>


            <!-- SUFFIX -->

            <div class="form-group">

                <label for="suffix">
                    Suffix
                </label>

                <select id="suffix" name="suffix">

                    <option value="">
                        None
                    </option>

                    <?php

                    $suffixes = [
                        "Jr.",
                        "Sr.",
                        "II",
                        "III",
                        "IV",
                        "V"
                    ];

                    foreach ($suffixes as $value):

                    ?>

                        <option
                            value="<?= $value ?>"
                            <?= (
                                ($residentFormData["suffix"] ?? "")
                                === $value
                            )
                                ? "selected"
                                : ""
                            ?>
                        >

                            <?= $value ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- BIRTH DATE -->

            <div class="form-group">

                <label for="birth_date">
                    Birth Date <span class="required">*</span>
                </label>

                <input
                    type="date"
                    id="birth_date"
                    name="birth_date"
                    required
                    max="<?= date('Y-m-d') ?>"
                    value="<?= htmlspecialchars(
                        $residentFormData["birth_date"] ?? ""
                    ) ?>"
                >

            </div>


            <!-- GENDER -->

            <div class="form-group">

                <label for="gender">
                    Gender
                </label>

                <select id="gender" name="gender" required>

                    <option value="" disabled
                        <?= empty($residentFormData["gender"] ?? "")
                            ? "selected"
                            : ""
                        ?>
                    >
                        Select Gender
                    </option>


                    <option
                        value="Male"
                        <?= (
                            ($residentFormData["gender"] ?? "")
                            === "Male"
                        )
                            ? "selected"
                            : ""
                        ?>
                    >
                        Male
                    </option>


                    <option
                        value="Female"
                        <?= (
                            ($residentFormData["gender"] ?? "")
                            === "Female"
                        )
                            ? "selected"
                            : ""
                        ?>
                    >
                        Female
                    </option>

                </select>

            </div>


            <!-- CIVIL STATUS -->

            <div class="form-group">

                <label for="civil_status">
                    Civil Status
                </label>

                <select id="civil_status" name="civil_status" required>

                    <option value="" disabled
                        <?= empty($residentFormData["civil_status"] ?? "")
                            ? "selected"
                            : ""
                        ?>
                    >
                        Select Civil Status
                    </option>

                    <?php

                    $statuses = [
                        "Single",
                        "Married",
                        "Widowed",
                        "Separated"
                    ];

                    foreach ($statuses as $status):

                    ?>

                        <option
                            value="<?= htmlspecialchars($status) ?>"
                            <?= (
                                ($residentFormData["civil_status"] ?? "")
                                === $status
                            )
                                ? "selected"
                                : ""
                            ?>
                        >
                            <?= htmlspecialchars($status) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- ADDRESS -->
            <div class="form-group">
                <label for="address">Complete Address <span class="required">*</span></label>
                <input
                    type="text"
                    id="address"
                    name="address"
                    minlength="5"
                    maxlength="255"
                    autocomplete="street-address"
                    required
                    placeholder="House No., Purok, Barangay"
                    value="<?= htmlspecialchars($residentFormData["address"] ?? "", ENT_QUOTES, "UTF-8") ?>"
                >
            </div>

            <!-- CONTACT NUMBER -->
            <div class="form-group">
                <label for="contact_number">Contact Number</label>
                <input
                    type="tel"
                    id="contact_number"
                    name="contact_number"
                    minlength="11"
                    maxlength="11"
                    pattern="09[0-9]{9}"
                    inputmode="numeric"
                    autocomplete="tel"
                    placeholder="09XXXXXXXXX"
                    title="Enter a valid contact number that starts with 09 and has exactly 11 digits."
                    value="<?= htmlspecialchars($residentFormData["contact_number"] ?? "", ENT_QUOTES, "UTF-8") ?>"
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="150"
                    autocomplete="email"
                    placeholder="Optional"
                    value="<?= htmlspecialchars(
                        $residentFormData["email"] ?? "",
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >
            </div>

        </div>


        <!-- FORM BUTTONS -->

        <div class="form-actions">


            <?php if ($editResident): ?>

                <a
                    href="residents.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>


                <button
                    type="submit"
                    class="btn btn-primary"
                    id="updateResidentButton"
                >
                    Update Resident
                </button>


            <?php else: ?>

                <button
                    type="reset"
                    class="btn btn-secondary"
                >
                    Clear
                </button>


                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Save Resident
                </button>

            <?php endif; ?>


        </div>

    </form>

</section>


<!-- =========================
     RESIDENT LIST
========================= -->

<section class="content-section" id="resident-records">

    <div class="section-header resident-list-header">

        <div>

            <h2>
                Resident Records
            </h2>

            <p>
                Resident records entered and managed by barangay staff.
            </p>

        </div>


        <!-- SEARCH -->

        <form
            method="GET"
            action="residents.php#resident-records"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                aria-label="Search resident records"
                placeholder="Search resident name, contact, address, or email..."
                value="<?= htmlspecialchars($search, ENT_QUOTES, "UTF-8") ?>"
            >

            <button type="submit">
                Search
            </button>


            <?php if ($search !== ""): ?>

                <a href="residents.php#resident-records">
                    Clear
                </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- =========================
         TABLE
    ========================= -->

    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>
                        Resident ID
                    </th>

                    <th>Resident / Applicant</th>

                    <th>Gender</th>
                    <th>Birth Date</th>
                    <th>Civil Status</th>
                    <th>Address</th>
                    <th>Contact</th>
                    <th>Status / Actions</th>

                </tr>

            </thead>


            <tbody>


            <?php if (
                $residents &&
                $residents->num_rows > 0
            ): ?>


                <?php while (
                    $resident = $residents->fetch_assoc()
                ): ?>

                    <?php
                    $residentFullName = implode(" ", array_filter([
                        trim((string)($resident["first_name"] ?? "")),
                        trim((string)($resident["middle_name"] ?? "")),
                        trim((string)($resident["last_name"] ?? "")),
                        trim((string)($resident["suffix"] ?? ""))
                    ], static fn($part) => $part !== ""));
                    ?>


                    <tr>


                        <!-- ID -->

                        <td>

                            RES-<?= str_pad((string)(int)$resident["id"], 6, "0", STR_PAD_LEFT) ?>

                        </td>

                        <!-- FULL NAME -->

                        <td>

                            <strong>

                                <?= htmlspecialchars($residentFullName, ENT_QUOTES, "UTF-8") ?>

                            </strong>

                        </td>

                        <!-- GENDER -->

                        <td>

                            <?= htmlspecialchars(
                                $resident["gender"]
                            ) ?>

                        </td>


                        <!-- BIRTH DATE -->

                        <td>

                            <?= htmlspecialchars(
                                $resident["birth_date"]
                            ) ?>

                        </td>


                        <!-- CIVIL STATUS -->

                        <td>

                            <?= htmlspecialchars(
                                $resident["civil_status"]
                            ) ?>

                        </td>


                        <!-- ADDRESS -->

                        <td>

                            <?= htmlspecialchars(
                                $resident["address"]
                            ) ?>

                        </td>


                        <!-- CONTACT -->

                        <td>

                            <?= htmlspecialchars(
                                $resident["contact_number"]
                            ) ?>

                        </td>

                        <!-- ACTION -->

                        <td>

                            <div class="table-actions">
                                <span class="resident-status-badge status-registered">Registered</span>
                                <div class="manual-actions">
                                    <a href="residents.php?edit=<?= (int)$resident["id"] ?>" class="btn-small edit">Edit</a>
                                    <form method="POST" onsubmit="return showDeletePopup(this);">
                                        <input type="hidden" name="delete_id" value="<?= (int)$resident["id"] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8") ?>">
                                        <button type="submit" class="btn-small delete">Delete</button>
                                    </form>
                                </div>
                            </div>

                        </td>


                    </tr>


                <?php endwhile; ?>


            <?php else: ?>


                <tr>

                    <td
                        colspan="8"
                        class="empty-table"
                    >

                        No resident records found.

                    </td>

                </tr>


            <?php endif; ?>


            </tbody>

        </table>

    </div>

<!-- =========================
     PAGINATION
========================= -->

<?php if ($totalRecords > 0): ?>

    <?php
    $startRecord = $offset + 1;
    $endRecord = min(
        $offset + $recordsPerPage,
        $totalRecords
    );
    ?>

    <div class="pagination-wrapper">

        <div class="pagination-info">

            Showing
            <strong><?= $startRecord ?></strong>
            –
            <strong><?= $endRecord ?></strong>
            of
            <strong><?= $totalRecords ?></strong>
            records

        </div>


        <div class="pagination">

            <?php if ($currentPage > 1): ?>

                <a
                    href="residents.php?<?= http_build_query([
                        "search" => $search,
                        "page" => $currentPage - 1
                    ]) ?>#resident-records"
                    class="page-link"
                >
                    Previous
                </a>

            <?php endif; ?>


            <?php for (
                $page = 1;
                $page <= $totalPages;
                $page++
            ): ?>

                <a
                    href="residents.php?<?= http_build_query([
                        "search" => $search,
                        "page" => $page
                    ]) ?>#resident-records"
                    class="page-link <?= $page === $currentPage ? "active" : "" ?>"
                >
                    <?= $page ?>
                </a>

            <?php endfor; ?>


            <?php if ($currentPage < $totalPages): ?>

                <a
                    href="residents.php?<?= http_build_query([
                        "search" => $search,
                        "page" => $currentPage + 1
                    ]) ?>#resident-records"
                    class="page-link"
                >
                    Next
                </a>

            <?php endif; ?>

        </div>

    </div>

<?php endif; ?>

</section>

<!-- DELETE CONFIRMATION POPUP -->

<div class="delete-popup-overlay" id="deletePopup">

    <div class="delete-popup-box">

        <div class="delete-popup-icon">
            ⚠️
        </div>

        <h3>Confirm Delete</h3>

        <p>
            Are you sure you want to delete this resident?
        </p>

        <div class="delete-popup-actions">

            <button
                type="button"
                class="delete-cancel-button"
                onclick="closeDeletePopup()"
            >
                Cancel
            </button>

            <button
                type="button"
                class="delete-confirm-button"
                onclick="confirmDelete()"
            >
                Delete
            </button>

        </div>

    </div>

</div>

<script>
document.querySelectorAll('#residentForm input[name="first_name"], #residentForm input[name="middle_name"], #residentForm input[name="last_name"]').forEach(function (nameInput) {
    function capitalizeFirstLetter() {
        if (this.value.length > 0) {
            this.value = this.value.charAt(0).toLocaleUpperCase() + this.value.slice(1);
        }
    }

    capitalizeFirstLetter.call(nameInput);
    nameInput.addEventListener("input", capitalizeFirstLetter);
});

const residentPhoneInput = document.getElementById("contact_number");
if (residentPhoneInput) {
    residentPhoneInput.addEventListener("input", function () {
        this.value = this.value.replace(/\D/g, "").slice(0, 11);
    });
}

const residentEditForm = document.getElementById("residentForm");
const updateResidentButton = document.getElementById("updateResidentButton");
if (residentEditForm && updateResidentButton && residentEditForm.dataset.originalValues) {
    const originalValues = JSON.parse(residentEditForm.dataset.originalValues);
    const editableControls = Array.from(residentEditForm.elements).filter(function (control) {
        return control.name && Object.prototype.hasOwnProperty.call(originalValues, control.name);
    });

    function updateResidentButtonState() {
        const hasChanges = editableControls.some(function (control) {
            const currentValue = String(control.value ?? "");
            const originalValue = String(originalValues[control.name] ?? "");
            const isNameField = ["first_name", "middle_name", "last_name"].includes(control.name);
            return isNameField
                ? currentValue.toLocaleLowerCase() !== originalValue.toLocaleLowerCase()
                : currentValue !== originalValue;
        });
        updateResidentButton.disabled = !hasChanges;
        updateResidentButton.title = hasChanges
            ? "Save the changes made to this resident."
            : "Make a change to enable updating this resident.";
    }

    editableControls.forEach(function (control) {
        control.addEventListener("input", updateResidentButtonState);
        control.addEventListener("change", updateResidentButtonState);
    });
    updateResidentButtonState();
}


function closeCrudPopup() {

    const popup = document.getElementById("crudPopup");

    if (popup) {
        const returnToForm = popup.dataset.returnToForm === "true";
        const focusField = popup.dataset.focusField;
        const errorMessage = popup.dataset.errorMessage;
        popup.remove();

        if (returnToForm) {
            const form = document.getElementById("residentForm");
            if (form) {
                form.scrollIntoView({ behavior: "smooth", block: "start" });
                const target = (focusField && form.elements.namedItem(focusField))
                    || form.querySelector(":invalid")
                    || form.elements.namedItem("first_name");
                if (target && typeof target.focus === "function") {
                    if (focusField && errorMessage) {
                        target.classList.add("resident-field-invalid");
                        target.setAttribute("aria-invalid", "true");
                        const inlineError = document.createElement("p");
                        inlineError.className = "resident-field-error";
                        inlineError.id = "resident-error-" + focusField;
                        inlineError.textContent = errorMessage;
                        target.insertAdjacentElement("afterend", inlineError);
                        target.setAttribute("aria-describedby", inlineError.id);

                        const clearInlineError = function () {
                            target.classList.remove("resident-field-invalid");
                            target.removeAttribute("aria-invalid");
                            target.removeAttribute("aria-describedby");
                            inlineError.remove();
                            target.removeEventListener("input", clearInlineError);
                            target.removeEventListener("change", clearInlineError);
                        };
                        target.addEventListener("input", clearInlineError);
                        target.addEventListener("change", clearInlineError);
                    }
                    window.setTimeout(function () {
                        target.focus({ preventScroll: true });
                    }, 300);
                }
            }
        }
    }

}

<?php if ($messageType !== "error"): ?>
setTimeout(function () {

    closeCrudPopup();

}, 3000);
<?php endif; ?>


let deleteForm = null;

function showDeletePopup(form) {

    deleteForm = form;

    const popup = document.getElementById("deletePopup");

    if (popup) {
        popup.style.display = "flex";
    }

    return false;
}

function closeDeletePopup() {

    const popup = document.getElementById("deletePopup");

    if (popup) {
        popup.style.display = "none";
    }

    deleteForm = null;
}

function confirmDelete() {

    if (deleteForm) {
        deleteForm.submit();
    }

}

</script>


<?php

/* =========================
   SHARED FOOTER
========================= */

require_once "../includes/footer.php";

?>
