<?php
/* =========================================================
   COMMUNITY ASSISTANCE - ADMIN
   =========================================================

   ONLINE SERVICE REQUEST
            ↓
   service_requests table
            ↓
   admin/community_assistance.php

   This page:
   - Shows Community Assistance requests
   - Shows the form even when there are 0 records
   - Loads online request data into the admin form
   - Allows admin/staff to update the request
   ========================================================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../config/database.php";


/* =========================================================
   AUTHORIZATION
   ========================================================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

$role = strtolower(trim((string)($_SESSION["role"] ?? "")));
$isAdmin = in_array($role, ["admin", "administrator"], true);
$isStaff = ($role === "staff");

if (!$isAdmin && !$isStaff) {
    http_response_code(403);
    exit("403 Forbidden: Wala kay permiso sa pag-access niini nga page.");
}


/* =========================================================
   HELPER
   ========================================================= */

function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}


/* =========================================================
   CSRF TOKEN
   ========================================================= */

if (empty($_SESSION["community_assistance_csrf"])) {
    $_SESSION["community_assistance_csrf"] =
        bin2hex(random_bytes(32));
}

$csrfToken =
    $_SESSION["community_assistance_csrf"];


/* =========================================================
   STATUS OPTIONS
   ========================================================= */

$allowedStatuses = [
    "Pending",
    "Under Review",
    "Approved",
    "Rejected",
    "Ready for Pickup",
    "Completed"
];

/* =========================================================
   ASSISTANCE TYPES
   ========================================================= */

$assistanceTypes = [
    "Medical Assistance",
    "Financial Assistance",
    "Food Assistance",
    "Educational Assistance",
    "Burial/Funeral Assistance",
    "Emergency Assistance",
    "PWD Assistance",
    "Senior Citizen Assistance",
    "Solo Parent Assistance",
    "Livelihood Assistance",
    "Other"
];


/* =========================================================
   FLASH MESSAGES
   ========================================================= */

$success =
    $_SESSION["community_assistance_success"] ?? "";

$error =
    $_SESSION["community_assistance_error"] ?? "";

$flashFormData =
    $_SESSION["community_assistance_form"] ?? null;

unset(
    $_SESSION["community_assistance_success"],
    $_SESSION["community_assistance_error"],
    $_SESSION["community_assistance_form"]
);


/* =========================================================
   SAVE / UPDATE / DELETE REQUEST
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (
        !hash_equals(
            $_SESSION["community_assistance_csrf"],
            $token
        )
    ) {

        $_SESSION["community_assistance_error"] =
            "Invalid security token. Please try again.";

        header("Location: community_assistance.php");
        exit;
    }


    /*
     * STAFF PERMISSIONS:
     * Staff may update only the status and remarks of an existing request.
     * Creating, deleting, or editing resident details is Admin-only.
     */
    if (!$isAdmin) {
        if (isset($_POST["delete_community_request"])) {
            http_response_code(403);
            exit("403 Forbidden: Admin access required to delete requests.");
        }

        if (!isset($_POST["update_community_request"])) {
            http_response_code(403);
            exit("403 Forbidden: Action not permitted.");
        }

        $staffRequestId = (int)($_POST["id"] ?? 0);
        $staffStatus = trim((string)($_POST["status"] ?? ""));
        $staffRemarks = trim((string)($_POST["admin_remarks"] ?? ""));

        if ($staffRequestId <= 0) {
            $_SESSION["community_assistance_error"] =
                "Staff can update only an existing request.";
        } elseif (!in_array($staffStatus, $allowedStatuses, true)) {
            $_SESSION["community_assistance_error"] =
                "Invalid request status.";
        } elseif (strlen($staffRemarks) > 1000) {
            $_SESSION["community_assistance_error"] =
                "Remarks must not exceed 1000 characters.";
        } else {
            $staffUpdate = $conn->prepare("
                UPDATE service_requests
                SET status = ?,
                    admin_remarks = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
                  AND service_type = 'Community Assistance'
                LIMIT 1
            ");

            if (!$staffUpdate) {
                $_SESSION["community_assistance_error"] =
                    "Unable to prepare the request update.";
            } else {
                $staffUpdate->bind_param(
                    "ssi",
                    $staffStatus,
                    $staffRemarks,
                    $staffRequestId
                );

                if ($staffUpdate->execute() && $staffUpdate->affected_rows >= 0) {
                    $_SESSION["community_assistance_success"] =
                        "Request status and remarks updated successfully.";
                } else {
                    $_SESSION["community_assistance_error"] =
                        "Unable to update the request.";
                }

                $staffUpdate->close();
            }
        }

        header(
            "Location: community_assistance.php?id="
            . $staffRequestId
            . "#community-request-form"
        );
        exit;
    }

    /* =====================================================
       DELETE REQUEST (ADMIN ONLY)
       ===================================================== */

    if (isset($_POST["delete_community_request"])) {

        $id = (int)($_POST["id"] ?? 0);

        if ($id <= 0) {

            $_SESSION["community_assistance_error"] =
                "Invalid request selected.";

        } else {

            $delete = $conn->prepare("
                DELETE FROM service_requests
                WHERE id = ?
                  AND service_type = 'Community Assistance'
                LIMIT 1
            ");

            if (!$delete) {

                $_SESSION["community_assistance_error"] =
                    "Unable to prepare delete request.";

            } else {

                $delete->bind_param("i", $id);

                if ($delete->execute()) {

                    if ($delete->affected_rows > 0) {

                        $_SESSION["community_assistance_success"] =
                            "Community Assistance request deleted successfully.";

                    } else {

                        $_SESSION["community_assistance_error"] =
                            "Community Assistance request not found.";
                    }

                } else {

                    $_SESSION["community_assistance_error"] =
                        "Unable to delete the request.";
                }

                $delete->close();
            }
        }

        header("Location: community_assistance.php");
        exit;
    }


    /* =====================================================
       SAVE / UPDATE
       ===================================================== */

    if (isset($_POST["update_community_request"])) {

        $id =
            (int)($_POST["id"] ?? 0);

        $assistanceType =
            trim($_POST["assistance_type"] ?? "");

        $firstName =
            trim($_POST["first_name"] ?? "");

        $middleName =
            trim($_POST["middle_name"] ?? "");

        $lastName =
            trim($_POST["last_name"] ?? "");

        $suffix =
            trim($_POST["suffix"] ?? "");

        $birthDate =
            trim($_POST["birth_date"] ?? "");

        $gender =
            trim($_POST["gender"] ?? "");

        $civilStatus =
            trim($_POST["civil_status"] ?? "");

        $address =
            trim($_POST["address"] ?? "");

        $contactNumber =
            trim($_POST["contact_number"] ?? "");

        $email =
            trim($_POST["email"] ?? "");

        $purpose =
            trim($_POST["purpose"] ?? "");

        $additionalDetails =
            trim($_POST["additional_details"] ?? "");

        $status =
            trim($_POST["status"] ?? "Pending");

        $adminRemarks =
            trim($_POST["admin_remarks"] ?? "");


        /* =================================================
           FULL NAME
           ================================================= */

        $fullName = trim(
            preg_replace(
                "/\s+/",
                " ",
                "$firstName $middleName $lastName $suffix"
            )
        );


        /* =================================================
           VALIDATION
           ================================================= */

        $error = "";


        if ($assistanceType === "") {

            $error =
                "Please select a type of assistance.";

        } elseif (!in_array($assistanceType, $assistanceTypes, true)) {

            $error =
                "Please select a valid type of assistance.";

        } elseif (
            $firstName === "" ||
            $lastName === ""
        ) {

            $error =
                "First name and last name are required.";

        } elseif (
            !preg_match(
                "/^[A-Za-zÀ-ÿÑñ' -]+$/u",
                $firstName
            )
        ) {

            $error =
                "First name must contain letters only.";

        } elseif (
            $middleName !== "" &&
            !preg_match(
                "/^[A-Za-zÀ-ÿÑñ' -]+$/u",
                $middleName
            )
        ) {

            $error =
                "Middle name must contain letters only.";

        } elseif (
            !preg_match(
                "/^[A-Za-zÀ-ÿÑñ' -]+$/u",
                $lastName
            )
        ) {

            $error =
                "Last name must contain letters only.";

        } elseif (
            $address === "" ||
            strlen($address) < 5
        ) {

            $error =
                "Complete address must be at least 5 characters.";

        } elseif (
            !preg_match(
                "/^09[0-9]{9}$/",
                $contactNumber
            )
        ) {

            $error =
                "Contact number must be a valid 11-digit Philippine mobile number.";

        } elseif (
            !in_array(
                $gender,
                ["Male", "Female"],
                true
            )
        ) {

            $error =
                "Please select a valid gender.";

        } elseif (
            !in_array(
                $civilStatus,
                [
                    "Single",
                    "Married",
                    "Widowed",
                    "Separated"
                ],
                true
            )
        ) {

            $error =
                "Please select a valid civil status.";

        } elseif (
            strlen($purpose) < 3
        ) {

            $error =
                "Purpose must be at least 3 characters.";

        } elseif (
            !in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {

            $error =
                "Invalid request status.";

        } elseif (
            $email !== "" &&
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $error =
                "Please enter a valid email address.";
        }


        /* =================================================
           IF EXISTING REQUEST → VERIFY
           ================================================= */

        if (
            $error === "" &&
            $id > 0
        ) {

            $check = $conn->prepare("
                SELECT id
                FROM service_requests
                WHERE id = ?
                  AND service_type = 'Community Assistance'
                LIMIT 1
            ");

            if (!$check) {

                $error =
                    "Unable to verify the request.";

            } else {

                $check->bind_param(
                    "i",
                    $id
                );

                $check->execute();

                $checkResult =
                    $check->get_result();

                if (
                    !$checkResult ||
                    $checkResult->num_rows === 0
                ) {

                    $error =
                        "Community Assistance request not found.";
                }

                $check->close();
            }
        }


        /* =================================================
           SAVE
           ================================================= */

        if ($error === "") {


            /* =============================================
               NEW MANUAL REQUEST
               ============================================= */

            if ($id <= 0) {

                $temporaryReference =
                    "TMP-" .
                    bin2hex(random_bytes(8));


                $serviceType = "Community Assistance";

                $insert = $conn->prepare("
                    INSERT INTO service_requests (
                        reference_no,
                        service_type,
                        request_source,
                        assistance_type,
                        full_name,
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
                        purpose,
                        additional_details,
                        status,
                        admin_remarks
                    )
                    VALUES (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        NULLIF(?, ''),
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");


                if (!$insert) {

                    $error =
                        "Unable to prepare the new request.";

                } else {

                    $requestSource = "Manual Entry";

                    $insert->bind_param(
                        "sssssssssssssssssss",
                        $temporaryReference,
                        $serviceType,
                        $requestSource,
                        $assistanceType,
                        $fullName,
                        $firstName,
                        $middleName,
                        $lastName,
                        $suffix,
                        $birthDate,
                        $gender,
                        $civilStatus,
                        $address,
                        $contactNumber,
                        $email,
                        $purpose,
                        $additionalDetails,
                        $status,
                        $adminRemarks
                    );


                    if (!$insert->execute()) {

                        $error =
                            "Unable to save the Community Assistance request.";

                    } else {

                        $newId =
                            $conn->insert_id;


                        /*
                         * Generate a Community Assistance reference number.
                         * The auto-increment database ID keeps the serial unique.
                         * Example: CA-2026-0001
                         */
                        $referenceNo =
                            "CA-" .
                            date("Y") .
                            "-" .
                            str_pad(
                                (string)$newId,
                                4,
                                "0",
                                STR_PAD_LEFT
                            );


                        $updateReference =
                            $conn->prepare("
                                UPDATE service_requests
                                SET reference_no = ?
                                WHERE id = ?
                            ");


                        if ($updateReference) {

                            $updateReference->bind_param(
                                "si",
                                $referenceNo,
                                $newId
                            );

                            $updateReference->execute();

                            $updateReference->close();
                        }


                        $success =
                            "Community Assistance request saved successfully.";


                        $id = $newId;
                    }


                    $insert->close();
                }


            /* =============================================
               EXISTING REQUEST → UPDATE
               ============================================= */

            } else {

                $update = $conn->prepare("
                    UPDATE service_requests SET
                        assistance_type = ?,
                        full_name = ?,
                        first_name = ?,
                        middle_name = ?,
                        last_name = ?,
                        suffix = ?,
                        birth_date = NULLIF(?, ''),
                        gender = ?,
                        civil_status = ?,
                        address = ?,
                        contact_number = ?,
                        email = ?,
                        purpose = ?,
                        additional_details = ?,
                        status = ?,
                        admin_remarks = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                      AND service_type = 'Community Assistance'
                ");


                if (!$update) {

                    $error =
                        "Unable to prepare the update.";

                } else {

                    $update->bind_param(
                        "ssssssssssssssssi",
                        $assistanceType,
                        $fullName,
                        $firstName,
                        $middleName,
                        $lastName,
                        $suffix,
                        $birthDate,
                        $gender,
                        $civilStatus,
                        $address,
                        $contactNumber,
                        $email,
                        $purpose,
                        $additionalDetails,
                        $status,
                        $adminRemarks,
                        $id
                    );


                    if (!$update->execute()) {

                        $error =
                            "Unable to update the Community Assistance request.";

                    } else {

                        $success =
                            "Community Assistance request updated successfully.";
                    }


                    $update->close();
                }
            }
        }


        /* =================================================
           REDIRECT
           ================================================= */

        if ($error !== "") {

            $_SESSION["community_assistance_error"] =
                $error;

            $_SESSION["community_assistance_form"] = [
                "id" => $id,
                "reference_no" => "",
                "service_type" => "Community Assistance",
                "assistance_type" => $assistanceType,
                "full_name" => $fullName,
                "first_name" => $firstName,
                "middle_name" => $middleName,
                "last_name" => $lastName,
                "suffix" => $suffix,
                "birth_date" => $birthDate,
                "gender" => $gender,
                "civil_status" => $civilStatus,
                "address" => $address,
                "contact_number" => $contactNumber,
                "email" => $email,
                "purpose" => $purpose,
                "additional_details" => $additionalDetails,
                "status" => $status,
                "admin_remarks" => $adminRemarks,
                "created_at" => ""
            ];

            header(
                "Location: community_assistance.php?id="
                . $id
                . "#community-request-form"
            );

        } else {

            $_SESSION["community_assistance_success"] =
                $success;

            header(
                "Location: community_assistance.php?id="
                . $id
                . "#community-request-form"
            );
        }

        exit;
    }
}




/* =========================================================
   FILTERS
   ========================================================= */

$search =
    trim($_GET["search"] ?? "");

$statusFilter =
    trim($_GET["status"] ?? "");

if (
    $statusFilter !== "" &&
    !in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {
    $statusFilter = "";
}

$assistanceFilter =
    trim($_GET["assistance_type"] ?? "");

if (
    $assistanceFilter !== "" &&
    !in_array(
        $assistanceFilter,
        $assistanceTypes,
        true
    )
) {
    $assistanceFilter = "";
}


/* =========================================================
   SELECTED REQUEST
   ========================================================= */

$selectedId =
    (int)($_GET["id"] ?? 0);

$selectedRequest = null;


/* =========================================================
   DEFAULT EMPTY FORM
   ========================================================= */

$formData = [
    "id" => 0,
    "reference_no" => "",
    "service_type" => "Community Assistance",
    "assistance_type" => "",
    "full_name" => "",
    "first_name" => "",
    "middle_name" => "",
    "last_name" => "",
    "suffix" => "",
    "birth_date" => "",
    "gender" => "",
    "civil_status" => "",
    "address" => "",
    "contact_number" => "",
    "email" => "",
    "purpose" => "",
    "additional_details" => "",
    "status" => "Pending",
    "admin_remarks" => "",
    "created_at" => ""
];


/* =========================================================
   LOAD SELECTED REQUEST
   ========================================================= */

if ($selectedId > 0) {

    $selected = $conn->prepare("
        SELECT
            id,
            reference_no,
            service_type,
            assistance_type,
            full_name,
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
            purpose,
            additional_details,
            status,
            admin_remarks,
            created_at,
            updated_at
        FROM service_requests
        WHERE id = ?
          AND service_type = 'Community Assistance'
        LIMIT 1
    ");

    if ($selected) {

        $selected->bind_param(
            "i",
            $selectedId
        );

        $selected->execute();

        $selectedResult =
            $selected->get_result();

        if (
            $selectedResult &&
            $selectedResult->num_rows > 0
        ) {

            $selectedRequest =
                $selectedResult->fetch_assoc();

            $formData =
                array_merge(
                    $formData,
                    $selectedRequest
                );
        }

        $selected->close();
    }
}


/* =========================================================
   RESTORE FORM DATA AFTER VALIDATION ERROR
   ========================================================= */

if (is_array($flashFormData)) {

    $flashFormId = (int)($flashFormData["id"] ?? 0);

    if ($flashFormId === $selectedId) {
        $formData = array_merge(
            $formData,
            $flashFormData
        );
    }
}


/* =========================================================
   PAGINATION
   ========================================================= */

$recordsPerPage = 10;

$currentPage =
    max(
        1,
        (int)($_GET["page"] ?? 1)
    );


/* =========================================================
   COUNT REQUESTS
   ========================================================= */

$countSql = "
    SELECT COUNT(*) AS total
    FROM service_requests
    WHERE service_type = 'Community Assistance'
";

$countTypes = "";

$countParams = [];


if ($search !== "") {

    $countSql .= "
        AND (
            reference_no LIKE ?
            OR full_name LIKE ?
            OR contact_number LIKE ?
            OR purpose LIKE ?
        )
    ";

    $q =
        "%" . $search . "%";

    $countTypes =
        "ssss";

    $countParams = [
        $q,
        $q,
        $q,
        $q
    ];
}


if ($statusFilter !== "") {

    $countSql .= "
        AND status = ?
    ";

    $countTypes .= "s";

    $countParams[] =
        $statusFilter;
}

if ($assistanceFilter !== "") {

    $countSql .= "
        AND assistance_type = ?
    ";

    $countTypes .= "s";

    $countParams[] =
        $assistanceFilter;
}


$countStmt =
    $conn->prepare($countSql);

if (!$countStmt) {

    die(
        "Database error while counting requests."
    );
}


if (!empty($countParams)) {

    $countStmt->bind_param(
        $countTypes,
        ...$countParams
    );
}

$countStmt->execute();

$countResult =
    $countStmt->get_result();

$totalRecords =
    (int)(
        $countResult->fetch_assoc()["total"]
        ?? 0
    );

$countStmt->close();


$totalPages =
    max(
        1,
        (int)ceil(
            $totalRecords /
            $recordsPerPage
        )
    );

$currentPage =
    min(
        $currentPage,
        $totalPages
    );

$offset =
    ($currentPage - 1) *
    $recordsPerPage;


/* =========================================================
   GET REQUESTS
   ========================================================= */

$sql = "
    SELECT
        id,
        reference_no,
        request_source,
        full_name,
        assistance_type,
        contact_number,
        email,
        purpose,
        status,
        created_at
    FROM service_requests
    WHERE service_type = 'Community Assistance'
";

$types = "";

$params = [];


if ($search !== "") {

    $sql .= "
        AND (
            reference_no LIKE ?
            OR full_name LIKE ?
            OR contact_number LIKE ?
            OR purpose LIKE ?
        )
    ";

    $q =
        "%" . $search . "%";

    $types .= "ssss";

    $params[] = $q;
    $params[] = $q;
    $params[] = $q;
    $params[] = $q;
}


if ($statusFilter !== "") {

    $sql .= "
        AND status = ?
    ";

    $types .= "s";

    $params[] =
        $statusFilter;
}

if ($assistanceFilter !== "") {

    $sql .= "
        AND assistance_type = ?
    ";

    $types .= "s";

    $params[] =
        $assistanceFilter;
}


$sql .= "
    ORDER BY id DESC
    LIMIT ?
    OFFSET ?
";

$types .= "ii";

$params[] =
    $recordsPerPage;

$params[] =
    $offset;


$stmt =
    $conn->prepare($sql);

if (!$stmt) {

    die(
        "Database error while loading requests."
    );
}

$stmt->bind_param(
    $types,
    ...$params
);

$stmt->execute();

$result =
    $stmt->get_result();


/* =========================================================
   PAGE URL
   ========================================================= */

function pageUrl(
    int $page,
    string $search,
    string $status,
    string $assistanceType = ""
): string {

    return
        "community_assistance.php"
        . "?search="
        . urlencode($search)
        . "&status="
        . urlencode($status)
        . "&assistance_type="
        . urlencode($assistanceType)
        . "&page="
        . $page
        . "#community-request-records";
}


/* =========================================================
   HEADER
   ========================================================= */

include "../includes/header.php";

?>


<style>

/* =========================================================
   PAGE
   ========================================================= */

.community-page {
    padding: 24px;
    max-width: 1500px;
    margin: auto;
}


/* =========================================================
   HERO
   ========================================================= */

.community-hero {
    background:
        linear-gradient(
            135deg,
            #1d4ed8,
            #2563eb
        );

    color: #fff;

    border-radius: 18px;

    padding: 24px 28px;

    margin-bottom: 20px;

    box-shadow:
        0 12px 30px
        rgba(
            37,
            99,
            235,
            .16
        );
}

.community-hero h1 {
    margin: 0 0 6px;

    font-size: 26px;
}

.community-hero p {
    margin: 0;

    opacity: .9;

    font-size: 13px;
}


/* =========================================================
   CARD
   ========================================================= */

.community-card {
    background: #fff;

    border:
        1px solid
        #e5e7eb;

    border-radius: 16px;

    box-shadow:
        0 8px 25px
        rgba(
            15,
            23,
            42,
            .06
        );

    margin-bottom: 20px;

    overflow: hidden;
}

.community-card-header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    padding: 20px 22px;

    border-bottom:
        1px solid
        #e5e7eb;
}

.community-card-header h2 {
    margin: 0;

    color: #0f172a;

    font-size: 19px;
}

.community-card-header p {
    margin: 5px 0 0;

    color: #64748b;

    font-size: 12px;
}


/* =========================================================
   BADGE
   ========================================================= */

.community-service-badge {
    display: inline-flex;

    align-items: center;

    gap: 6px;

    border-radius: 999px;

    padding: 7px 11px;

    font-size: 11px;

    font-weight: 700;

    white-space: nowrap;

    background: #f3e8ff;

    color: #7e22ce;

    border:
        1px solid
        #d8b4fe;
}

.community-service-badge::before {
    content: "🏘️";
}


/* =========================================================
   ALERTS
   ========================================================= */

.alert {
    padding: 12px 15px;

    border-radius: 10px;

    margin-bottom: 15px;

    font-size: 13px;
}

.alert-success {
    background: #ecfdf5;

    color: #047857;

    border:
        1px solid
        #a7f3d0;
}

.alert-error {
    background: #fef2f2;

    color: #b91c1c;

    border:
        1px solid
        #fecaca;
}


/* =========================================================
   FILTER
   ========================================================= */

.community-filter {
    display: grid;

    grid-template-columns:
        1.5fr
        1fr
        auto
        auto;

    gap: 10px;

    padding: 18px 22px;

    border-bottom:
        1px solid
        #e5e7eb;
}

.community-filter input,
.community-filter select,
.community-form input,
.community-form select,
.community-form textarea {

    width: 100%;

    box-sizing: border-box;

    border:
        1px solid
        #cbd5e1;

    border-radius: 8px;

    background: #fff;

    padding: 10px 11px;

    font-size: 13px;

    color: #0f172a;

    outline: none;
}

.community-filter input:focus,
.community-filter select:focus,
.community-form input:focus,
.community-form select:focus,
.community-form textarea:focus {

    border-color: #2563eb;

    box-shadow:
        0 0 0 3px
        rgba(
            37,
            99,
            235,
            .1
        );
}


/* =========================================================
   BUTTONS
   ========================================================= */

.btn {
    display: inline-flex;

    align-items: center;

    justify-content: center;

    border: 0;

    border-radius: 8px;

    padding: 10px 15px;

    font-size: 12px;

    font-weight: 700;

    text-decoration: none;

    cursor: pointer;
}

.btn-primary {
    background: #2563eb;

    color: #fff;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-secondary {
    background: #f1f5f9;

    color: #334155;
}

.btn-secondary:hover {
    background: #e2e8f0;
}

.btn-view {
    background: #eff6ff;

    color: #1d4ed8;

    border:
        1px solid
        #bfdbfe;
}

.btn-view:hover {
    background: #dbeafe;
}

.btn-disabled {
    opacity: .45;

    cursor: not-allowed;
}


/* =========================================================
   TABLE
   ========================================================= */

.community-table-wrap {
    overflow-x: auto;
}

.community-table {
    width: 100%;

    border-collapse:
        collapse;
}

.community-table th {

    background: #f8fafc;

    color: #64748b;

    text-transform: uppercase;

    font-size: 10px;

    letter-spacing: .04em;

    text-align: left;

    padding: 12px 16px;

    border-bottom:
        1px solid
        #e2e8f0;
}

.community-table td {

    padding: 14px 16px;

    border-bottom:
        1px solid
        #eef2f7;

    font-size: 12px;

    color: #334155;

    vertical-align: middle;
}

.community-table tr:hover {
    background: #f8fbff;
}

.ref {
    font-weight: 800;

    color: #1d4ed8;
}

.name {
    font-weight: 700;

    color: #0f172a;
}

.muted {
    color: #94a3b8;

    font-size: 11px;
}


/* =========================================================
   STATUS
   ========================================================= */

.status {

    display: inline-flex;

    padding: 5px 9px;

    border-radius: 999px;

    font-size: 10px;

    font-weight: 800;
}

.status-pending {
    background: #fff7ed;

    color: #c2410c;
}

.status-review {
    background: #eff6ff;

    color: #1d4ed8;
}

.status-approved {
    background: #ecfdf5;

    color: #047857;
}

.status-rejected {
    background: #fef2f2;

    color: #b91c1c;
}

.status-ready {
    background: #f5f3ff;

    color: #6d28d9;
}

.status-completed {
    background: #ecfeff;

    color: #0e7490;
}


/* =========================================================
   EMPTY
   ========================================================= */

.empty {
    text-align: center;

    padding: 45px 20px;

    color: #64748b;

    font-size: 13px;
}


/* =========================================================
   PAGINATION
   ========================================================= */

.pagination {

    display: flex;

    justify-content: center;

    gap: 6px;

    padding: 18px;
}

.page-link {

    display: inline-flex;

    min-width: 34px;

    height: 34px;

    align-items: center;

    justify-content: center;

    border:
        1px solid
        #dbe3ee;

    border-radius: 8px;

    color: #334155;

    text-decoration: none;

    font-size: 11px;
}

.page-link.active {

    background: #2563eb;

    color: #fff;

    border-color: #2563eb;
}


/* =========================================================
   FORM
   ========================================================= */

.community-form-wrap {
    padding: 22px;
}

.community-selected {

    display: flex;

    justify-content: space-between;

    gap: 15px;

    align-items: center;

    background: #f8fafc;

    border:
        1px solid
        #e2e8f0;

    border-radius: 12px;

    padding: 14px 16px;

    margin-bottom: 18px;
}

.community-selected strong {

    display: block;

    color: #0f172a;

    font-size: 15px;
}

.community-selected span {

    color: #64748b;

    font-size: 11px;
}


/* =========================================================
   FORM SECTIONS
   ========================================================= */

.community-form-section {

    border:
        1px solid
        #e5e7eb;

    border-radius: 12px;

    padding: 18px;

    margin-bottom: 15px;
}

.community-form-section-title {

    font-size: 14px;

    color: #1d4ed8;

    margin: 0 0 15px;

    border-left:
        3px solid
        #2563eb;

    padding-left: 9px;
}

.community-form-grid {

    display: grid;

    grid-template-columns:
        repeat(
            2,
            minmax(
                0,
                1fr
            )
        );

    gap: 14px;
}

.community-form-group.full {
    grid-column: 1 / -1;
}

.community-form-group label {

    display: block;

    font-size: 11px;

    font-weight: 700;

    color: #334155;

    margin-bottom: 6px;
}

.required {
    color: #dc2626;
}

.field-hint {

    display: block;

    color: #64748b;

    font-size: 10px;

    margin-top: 5px;
}

.community-form textarea {

    min-height: 105px;

    resize: vertical;
}


/* =========================================================
   EMPTY FORM NOTICE
   ========================================================= */

.no-selection {

    background: #eff6ff;

    border:
        1px solid
        #bfdbfe;

    color: #1e40af;

    border-radius: 10px;

    padding: 12px 14px;

    margin-bottom: 18px;

    font-size: 12px;
}


/* =========================================================
   FORM ACTIONS
   ========================================================= */

.community-form-actions {

    display: flex;

    justify-content: flex-end;

    gap: 10px;

    margin-top: 18px;
}


/* =========================================================
   PROFESSIONAL UI ENHANCEMENTS
   ========================================================= */

.community-hero-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
}

.community-eyebrow {
    margin-bottom: 7px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .12em;
    text-transform: uppercase;
    opacity: .78;
}

.community-hero-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.community-total-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 9px 13px;
    border: 1px solid rgba(255,255,255,.24);
    border-radius: 999px;
    background: rgba(255,255,255,.12);
    backdrop-filter: blur(8px);
}

.community-total-pill strong {
    font-size: 15px;
}

.community-total-pill span {
    font-size: 10px;
    opacity: .86;
}

.btn-hero {
    background: #fff;
    color: #1d4ed8;
    box-shadow: 0 6px 18px rgba(15,23,42,.14);
}

.btn-hero:hover {
    background: #eff6ff;
    color: #1e40af;
}

.btn-danger {
    background: #dc2626;
    color: #fff;
}

.btn-danger:hover {
    background: #b91c1c;
}

.btn-danger-soft {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.btn-danger-soft:hover {
    background: #fee2e2;
}

.btn:focus-visible,
.page-link:focus-visible,
.community-form input:focus-visible,
.community-form select:focus-visible,
.community-form textarea:focus-visible,
.community-filter input:focus-visible,
.community-filter select:focus-visible {
    outline: 3px solid rgba(37,99,235,.22);
    outline-offset: 2px;
}

.community-form input[readonly] {
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
}

.community-form-section {
    background: linear-gradient(180deg, #fff 0%, #fcfdff 100%);
}

.request-source-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 6px 9px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
    border: 1px solid transparent;
}

.source-online {
    color: #1d4ed8;
    background: #eff6ff;
    border-color: #bfdbfe;
}

.source-manual {
    color: #047857;
    background: #ecfdf5;
    border-color: #a7f3d0;
}

.assistance-type-badge {
    display: inline-flex;
    align-items: center;
    max-width: 220px;
    padding: 5px 9px;
    border-radius: 999px;
    background: #f5f3ff;
    color: #6d28d9;
    border: 1px solid #ddd6fe;
    font-size: 10px;
    font-weight: 800;
}

.community-table td:last-child {
    min-width: 190px;
}

.community-table td:last-child .btn {
    min-height: 34px;
}

.community-table tbody tr {
    transition: background .15s ease;
}

.alert {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* =========================================================
   PROFESSIONAL ADMIN POLISH
   ========================================================= */

.community-page {
    color: #0f172a;
}

.community-hero {
    position: relative;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,.12);
}

.community-hero::after {
    content: "";
    position: absolute;
    width: 240px;
    height: 240px;
    right: -80px;
    top: -90px;
    border-radius: 50%;
    background: rgba(255,255,255,.08);
    pointer-events: none;
}

.community-hero-content {
    position: relative;
    z-index: 1;
}

.community-card {
    border-color: #e2e8f0;
}

.community-card-header {
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
}

.community-filter {
    grid-template-columns: minmax(260px, 1.7fr) minmax(180px, 1fr) minmax(150px, .8fr) auto;
    align-items: end;
    background: #fbfdff;
}

.filter-field label {
    display: block;
    margin-bottom: 6px;
    color: #475569;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .03em;
    text-transform: uppercase;
}

.filter-control {
    position: relative;
}

.filter-control > span {
    position: absolute;
    left: 11px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    font-size: 16px;
    pointer-events: none;
}

.filter-control input {
    padding-left: 34px;
}

.filter-actions {
    display: flex;
    gap: 8px;
}

.community-table thead th {
    position: sticky;
    top: 0;
    z-index: 1;
}

.community-table tbody tr.is-selected {
    background: #eff6ff;
    box-shadow: inset 3px 0 0 #2563eb;
}

.community-table td:nth-child(5) {
    max-width: 260px;
}

.community-table td:nth-child(5) {
    white-space: normal;
}

.table-actions {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 6px;
    flex-wrap: nowrap;
    white-space: nowrap;
}

/* Keep the delete form inline with the View / Edit link. */
.table-actions form {
    display: inline-flex !important;
    align-items: center;
    width: auto !important;
    margin: 0 !important;
    padding: 0;
    flex: 0 0 auto;
}

.table-actions .btn {
    min-width: 82px;
    margin: 0;
}

.request-reference {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.request-reference .ref {
    font-size: 12px;
}

.request-date {
    white-space: nowrap;
}

.community-form-section-title {
    display: flex;
    align-items: center;
    gap: 8px;
}

.community-form-section-title::before {
    content: "";
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #2563eb;
    box-shadow: 0 0 0 4px #dbeafe;
}

.community-form-actions {
    position: sticky;
    bottom: 0;
    z-index: 5;
    padding: 13px;
    margin: 18px -22px -22px;
    border-top: 1px solid #e2e8f0;
    background: rgba(255,255,255,.94);
    backdrop-filter: blur(10px);
}

.community-form-actions .btn {
    min-width: 130px;
}

.community-selected {
    border-color: #bfdbfe;
    background: linear-gradient(135deg, #eff6ff, #f8fafc);
}

.community-selected .request-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 7px;
}

.meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 8px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid #dbeafe;
    color: #475569;
    font-size: 10px;
    font-weight: 700;
}

.empty-state-icon {
    width: 52px;
    height: 52px;
    margin: 0 auto 10px;
    display: grid;
    place-items: center;
    border-radius: 16px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 22px;
}

.empty-state-title {
    display: block;
    margin-bottom: 4px;
    color: #0f172a;
    font-weight: 800;
}

.empty-state-text {
    display: block;
    color: #64748b;
}

.pagination {
    align-items: center;
    flex-wrap: wrap;
}

.page-summary {
    margin-right: 8px;
    color: #64748b;
    font-size: 11px;
}

@media (max-width: 1050px) {
    .community-filter {
        grid-template-columns: 1fr 1fr;
    }

    .filter-search {
        grid-column: 1 / -1;
    }

    .filter-actions {
        grid-column: 1 / -1;
        justify-content: flex-end;
    }
}

/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 800px) {

    .community-filter {
        grid-template-columns: 1fr;
    }

    .filter-search,
    .filter-actions {
        grid-column: auto;
    }

    .filter-actions {
        justify-content: stretch;
    }

    .filter-actions .btn {
        flex: 1;
    }

    .community-form-actions {
        position: static;
        margin: 18px -22px -22px;
    }

    .community-form-actions .btn {
        min-width: 0;
        flex: 1;
    }

    .community-table {
        min-width: 1050px;
    }

    .community-filter,
    .community-form-grid {

        grid-template-columns: 1fr;
    }

    .community-form-group.full {
        grid-column: auto;
    }

    .community-card-header,
    .community-selected,
    .community-hero-content {

        align-items: flex-start;

        flex-direction: column;
    }

    .community-hero-actions {
        width: 100%;
        flex-wrap: wrap;
    }

    .community-hero-actions .btn-hero {
        flex: 1;
    }

    .community-page {
        padding: 12px;
    }
}

/* =========================================================
   SUCCESS POPUP
   ========================================================= */

.success-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 99999;

    display: flex;
    align-items: center;
    justify-content: center;

    padding: 20px;

    background: rgba(15, 23, 42, .55);

    backdrop-filter: blur(5px);

    animation: successOverlayIn .2s ease;
}

.success-modal {
    position: relative;

    width: 100%;
    max-width: 420px;

    padding: 32px 28px 28px;

    background: #ffffff;

    border-radius: 20px;

    text-align: center;

    box-shadow:
        0 25px 70px rgba(15, 23, 42, .25);

    animation: successModalIn .25s ease;
}

.success-modal-close {
    position: absolute;

    top: 12px;
    right: 14px;

    width: 32px;
    height: 32px;

    border: 0;
    border-radius: 50%;

    background: #f1f5f9;

    color: #64748b;

    font-size: 22px;
    line-height: 1;

    cursor: pointer;
}

.success-modal-close:hover {
    background: #e2e8f0;
    color: #0f172a;
}

.success-icon {
    width: 70px;
    height: 70px;

    margin: 0 auto 16px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;

    background: #dcfce7;

    color: #16a34a;

    font-size: 38px;
    font-weight: 800;

    border: 6px solid #f0fdf4;
}

.success-modal h3 {
    margin: 0 0 8px;

    color: #0f172a;

    font-size: 22px;
    font-weight: 800;
}

.success-modal p {
    margin: 0 auto 22px;

    max-width: 340px;

    color: #64748b;

    font-size: 13px;

    line-height: 1.6;
}

.success-modal-button {
    width: 100%;

    border: 0;
    border-radius: 10px;

    padding: 12px 18px;

    background: #2563eb;

    color: #ffffff;

    font-size: 13px;
    font-weight: 800;

    cursor: pointer;

    transition: .15s ease;
}

.success-modal-button:hover {
    background: #1d4ed8;

    transform: translateY(-1px);
}

@keyframes successOverlayIn {
    from {
        opacity: 0;
    }

    to {
        opacity: 1;
    }
}

@keyframes successModalIn {
    from {
        opacity: 0;
        transform: translateY(15px) scale(.96);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}


#saveCommunityButton:disabled {
    opacity: 0.45;
    cursor: not-allowed;
    pointer-events: none;
}




/* =========================================================
   DELETE CONFIRMATION MODAL
   ========================================================= */
.delete-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 100000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, .48);
    backdrop-filter: blur(4px);
}
.delete-modal-overlay.is-open {
    display: flex;
}
.delete-confirm-modal {
    width: 100%;
    max-width: 420px;
    box-sizing: border-box;
    padding: 32px 28px;
    border-radius: 16px;
    background: #fff;
    text-align: center;
    box-shadow: 0 24px 65px rgba(15, 23, 42, .28);
    animation: deleteModalIn .18s ease-out;
}
.delete-modal-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 62px;
    height: 62px;
    margin: 0 auto 16px;
    border-radius: 50%;
    background: #fef3c7;
    font-size: 30px;
}
.delete-confirm-modal h2 {
    margin: 0 0 10px;
    color: #1e293b;
    font-size: 22px;
    line-height: 1.3;
}
.delete-confirm-modal p {
    margin: 0 auto 25px;
    color: #64748b;
    font-size: 14px;
    line-height: 1.6;
}
.delete-modal-actions {
    display: flex;
    justify-content: center;
    gap: 12px;
}
.delete-modal-actions button {
    min-width: 102px;
    min-height: 40px;
    padding: 10px 18px;
    border: 0;
    border-radius: 9px;
    font: inherit;
    font-size: 13px;
    font-weight: 800;
    cursor: pointer;
    transition: background .15s ease, transform .15s ease;
}
.delete-modal-cancel {
    background: #e2e8f0;
    color: #334155;
}
.delete-modal-cancel:hover {
    background: #cbd5e1;
}
.delete-modal-confirm {
    background: #dc2626;
    color: #fff;
}
.delete-modal-confirm:hover {
    background: #b91c1c;
}
.delete-modal-actions button:focus-visible {
    outline: 3px solid rgba(37, 99, 235, .3);
    outline-offset: 2px;
}
@keyframes deleteModalIn {
    from { opacity: 0; transform: translateY(8px) scale(.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
@media (max-width: 480px) {
    .delete-confirm-modal { padding: 28px 20px 22px; }
    .delete-modal-actions { gap: 9px; }
    .delete-modal-actions button { flex: 1; }
}

</style>


<div class="community-page">


    <!-- =====================================================
         HERO
    ====================================================== -->

    <div class="community-hero">

        <div class="community-hero-content">

            <div>
                <div class="community-eyebrow">
                    BARANGAY SERVICES · ADMIN PORTAL
                </div>

                <h1>
                    🏘️ Community Assistance
                </h1>

                <p>
                    Manage, process, and track Community Assistance requests
                    from online submissions and in-person encoding.
                </p>
            </div>

            <div class="community-hero-actions">

                <div class="community-total-pill">
                    <strong><?= number_format($totalRecords) ?></strong>
                    <span>Total Requests</span>
                </div>

                <?php if ($isAdmin): ?>
                    <a
                        href="community_assistance.php#community-request-form"
                        class="btn btn-hero"
                    >
                        ＋ New Request
                    </a>
                <?php endif; ?>

            </div>

        </div>

    </div>


    <!-- =====================================================
         ALERTS
    ====================================================== -->

    <?php if ($success !== ""): ?>

        <div class="success-modal-overlay" id="successModal">

            <div class="success-modal">

                <button
                    type="button"
                    class="success-modal-close"
                    onclick="closeSuccessModal()"
                    aria-label="Close"
                >
                    ×
                </button>

                <div class="success-icon">
                    ✓
                </div>

                <h3>
                    Success!
                </h3>

                <p>
                    <?= e($success) ?>
                </p>

                <button
                    type="button"
                    class="success-modal-button"
                    onclick="closeSuccessModal()"
                >
                    OK
                </button>

            </div>

        </div>

    <?php endif; ?>


    <?php if ($error !== ""): ?>

        <div class="alert alert-error">

            ⚠️
            <?= e($error) ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ADMIN FORM
         ALWAYS VISIBLE
    ====================================================== -->

    <section
        class="community-card"
        id="community-request-form"
    >

        <div class="community-card-header">

            <div>

                <h2>
                    Community Assistance Request Form
                </h2>

                <p>
                    Create a new request manually or review and update
                    information submitted by the resident.
                </p>

            </div>

            <span class="community-service-badge">
                Community Assistance
            </span>

        </div>


        <div class="community-form-wrap">


            <?php if (!$selectedRequest): ?>

                <div class="no-selection">

                    ℹ️
                    <strong>New manual request.</strong>

                    Fill out the form below to encode a Community Assistance
                    request manually. Existing requests can be opened from
                    the records table for editing.

                </div>

            <?php else: ?>

                <!-- SELECTED REQUEST -->

                <?php if ($isStaff): ?>
                    <div class="no-selection">
                        <strong>Staff access:</strong>
                        You may update the request status and remarks only.
                        Changes to resident details, creating requests, and deleting requests are restricted to Admin.
                    </div>
                <?php endif; ?>

                <div class="community-selected">

                    <div>
                        <strong><?= e($formData["full_name"] ?: "Community Assistance Request") ?></strong>

                        <div class="request-meta">
                            <span class="meta-chip">
                                <span aria-hidden="true">#</span>
                                <?= e($formData["reference_no"] ?: "New Request") ?>
                            </span>

                            <span class="meta-chip">
                                <span aria-hidden="true">●</span>
                                <?= e($formData["status"] ?: "Pending") ?>
                            </span>

                            <?php if (!empty($formData["assistance_type"])): ?>
                                <span class="meta-chip">
                                    <?= e($formData["assistance_type"]) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <span class="muted">
                        Submitted:
                        <?= !empty($formData["created_at"])
                            ? e(date("M d, Y h:i A", strtotime($formData["created_at"])))
                            : "Not yet submitted" ?>
                    </span>

                </div>

            <?php endif; ?>


            <!-- =================================================
                 FORM
            ================================================== -->

            <form
                method="POST"
                class="community-form"
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken) ?>"
                >


                <input
                    type="hidden"
                    name="id"
                    value="<?= (int)$formData["id"] ?>"
                >


                <!-- =================================================
                     SERVICE DETAILS
                ================================================== -->

                <div class="community-form-section">

                    <h3 class="community-form-section-title">
                        Service Details
                    </h3>


                    <div class="community-form-grid">

                        <div
                            class="community-form-group full"
                        >

                            <label>
                                Service Requested
                            </label>

                            <input
                                type="text"
                                value="Community Assistance"
                                readonly
                            >

                        </div>

                        <div class="community-form-group">

                            <label for="assistance_type">
                                Type of Assistance
                                <span class="required">*</span>
                            </label>

                            <select
                                id="assistance_type"
                                name="assistance_type"
                                required

                            >

                                <option value="">
                                    Select Assistance Type
                                </option>

                                <option value="Medical Assistance"
                                    <?= ($formData["assistance_type"] ?? "") === "Medical Assistance" ? "selected" : "" ?>>
                                    Medical Assistance
                                </option>

                                <option value="Financial Assistance"
                                    <?= ($formData["assistance_type"] ?? "") === "Financial Assistance" ? "selected" : "" ?>>
                                    Financial Assistance
                                </option>

                                <option value="Food Assistance"
                                    <?= ($formData["assistance_type"] ?? "") === "Food Assistance" ? "selected" : "" ?>>
                                    Food Assistance
                                </option>

                                <option value="Educational Assistance"
                                    <?= ($formData["assistance_type"] ?? "") === "Educational Assistance" ? "selected" : "" ?>>
                                    Educational Assistance
                                </option>

                                <option value="Burial/Funeral Assistance"
                                    <?= ($formData["assistance_type"] ?? "") === "Burial/Funeral Assistance" ? "selected" : "" ?>>
                                    Burial/Funeral Assistance
                                </option>

                                <option value="Emergency Assistance"
                                    <?= ($formData["assistance_type"] ?? "") === "Emergency Assistance" ? "selected" : "" ?>>
                                    Emergency Assistance
                                </option>

                                <option value="PWD Assistance"
                                    <?= ($formData["assistance_type"] ?? "") === "PWD Assistance" ? "selected" : "" ?>>
                                    PWD Assistance
                                </option>

                                <option value="Senior Citizen Assistance"
                                    <?= ($formData["assistance_type"] ?? "") === "Senior Citizen Assistance" ? "selected" : "" ?>>
                                    Senior Citizen Assistance
                                </option>

                                <option value="Solo Parent Assistance"
                                    <?= ($formData["assistance_type"] ?? "") === "Solo Parent Assistance" ? "selected" : "" ?>>
                                    Solo Parent Assistance
                                </option>

                                <option value="Livelihood Assistance"
                                    <?= ($formData["assistance_type"] ?? "") === "Livelihood Assistance" ? "selected" : "" ?>>
                                    Livelihood Assistance
                                </option>

                                <option value="Other"
                                    <?= ($formData["assistance_type"] ?? "") === "Other" ? "selected" : "" ?>>
                                    Other
                                </option>

                            </select>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     RESIDENT INFORMATION
                ================================================== -->

                <div class="community-form-section">

                    <h3 class="community-form-section-title">
                        Resident Information
                    </h3>


                    <div class="community-form-grid">


                        <!-- FIRST NAME -->

                        <div class="community-form-group">

                            <label for="first_name">

                                First Name

                                <span class="required">
                                    *
                                </span>

                            </label>

                            <input
                                type="text"
                                id="first_name"
                                name="first_name"
                                maxlength="50"
                                pattern="[A-Za-zÀ-ÿÑñ' -]+"
                                required
                                value="<?= e(
                                    $formData["first_name"]
                                ) ?>"
                                placeholder="Enter first name"

                            >

                        </div>


                        <!-- MIDDLE NAME -->

                        <div class="community-form-group">

                            <label for="middle_name">

                                Middle Name

                            </label>

                            <input
                                type="text"
                                id="middle_name"
                                name="middle_name"
                                maxlength="50"
                                pattern="[A-Za-zÀ-ÿÑñ' -]+"
                                value="<?= e(
                                    $formData["middle_name"]
                                ) ?>"
                                placeholder="Enter middle name"

                            >

                        </div>


                        <!-- LAST NAME -->

                        <div class="community-form-group">

                            <label for="last_name">

                                Last Name

                                <span class="required">
                                    *
                                </span>

                            </label>

                            <input
                                type="text"
                                id="last_name"
                                name="last_name"
                                maxlength="50"
                                pattern="[A-Za-zÀ-ÿÑñ' -]+"
                                required
                                value="<?= e(
                                    $formData["last_name"]
                                ) ?>"
                                placeholder="Enter last name"

                            >

                        </div>


                        <!-- SUFFIX -->

                        <div class="community-form-group">

                            <label for="suffix">

                                Suffix

                            </label>

                            <select
                                id="suffix"
                                name="suffix"

                            >

                                <?php

                                $suffixOptions = [
                                    "",
                                    "Jr.",
                                    "Sr.",
                                    "II",
                                    "III",
                                    "IV",
                                    "V"
                                ];

                                ?>

                                <?php foreach (
                                    $suffixOptions
                                    as $suffixOption
                                ): ?>

                                    <option
                                        value="<?= e(
                                            $suffixOption
                                        ) ?>"
                                        <?= (
                                            $formData["suffix"]
                                            ?? ""
                                        ) === $suffixOption
                                            ? "selected"
                                            : "" ?>
                                    >

                                        <?= $suffixOption === ""
                                            ? "None"
                                            : e($suffixOption) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- BIRTH DATE -->

                        <div class="community-form-group">

                            <label for="birth_date">

                                Birth Date

                            </label>

                            <input
                                type="date"
                                id="birth_date"
                                name="birth_date"
                                max="<?= date("Y-m-d") ?>"
                                value="<?= e(
                                    $formData["birth_date"]
                                ) ?>"

                            >

                        </div>


                        <!-- GENDER -->

                        <div class="community-form-group">

                            <label for="gender">

                                Gender

                                <span class="required">
                                    *
                                </span>

                            </label>

                            <select
                                id="gender"
                                name="gender"
                                required

                            >

                                <option
                                    value=""
                                    disabled
                                    <?= empty(
                                        $formData["gender"]
                                    )
                                        ? "selected"
                                        : "" ?>
                                >
                                    Select Gender
                                </option>

                                <option
                                    value="Male"
                                    <?= (
                                        $formData["gender"]
                                        ?? ""
                                    ) === "Male"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Male
                                </option>

                                <option
                                    value="Female"
                                    <?= (
                                        $formData["gender"]
                                        ?? ""
                                    ) === "Female"
                                        ? "selected"
                                        : "" ?>
                                >
                                    Female
                                </option>

                            </select>

                        </div>


                        <!-- CIVIL STATUS -->

                        <div class="community-form-group">

                            <label for="civil_status">

                                Civil Status

                                <span class="required">
                                    *
                                </span>

                            </label>

                            <select
                                id="civil_status"
                                name="civil_status"
                                required

                            >

                                <option
                                    value=""
                                    disabled
                                    <?= empty(
                                        $formData["civil_status"]
                                    )
                                        ? "selected"
                                        : "" ?>
                                >
                                    Select Civil Status
                                </option>

                                <?php

                                $civilStatuses = [
                                    "Single",
                                    "Married",
                                    "Widowed",
                                    "Separated"
                                ];

                                ?>

                                <?php foreach (
                                    $civilStatuses
                                    as $civil
                                ): ?>

                                    <option
                                        value="<?= e($civil) ?>"
                                        <?= (
                                            $formData[
                                                "civil_status"
                                            ] ?? ""
                                        ) === $civil
                                            ? "selected"
                                            : "" ?>
                                    >
                                        <?= e($civil) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- ADDRESS -->

                        <div class="community-form-group">

                            <label for="address">

                                Complete Address

                                <span class="required">
                                    *
                                </span>

                            </label>

                            <input
                                type="text"
                                id="address"
                                name="address"
                                minlength="5"
                                maxlength="255"
                                required
                                value="<?= e(
                                    $formData["address"]
                                ) ?>"
                                placeholder="House No., Purok, Barangay"

                            >

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     CONTACT INFORMATION
                ================================================== -->

                <div class="community-form-section">

                    <h3 class="community-form-section-title">
                        Contact Information
                    </h3>


                    <div class="community-form-grid">


                        <!-- CONTACT -->

                        <div class="community-form-group">

                            <label for="contact_number">

                                Contact Number

                                <span class="required">
                                    *
                                </span>

                            </label>

                            <input
                                type="tel"
                                id="contact_number"
                                name="contact_number"
                                maxlength="11"
                                minlength="11"
                                pattern="09[0-9]{9}"
                                inputmode="numeric"
                                required
                                value="<?= e(
                                    $formData["contact_number"]
                                ) ?>"
                                placeholder="09XXXXXXXXX"

                            >

                            <small class="field-hint">

                                Use an active Philippine
                                mobile number for updates.

                            </small>

                        </div>


                        <!-- EMAIL -->

                        <div class="community-form-group">

                            <label for="email">

                                Email Address

                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                maxlength="150"
                                value="<?= e(
                                    $formData["email"]
                                ) ?>"
                                placeholder="Optional"

                            >

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     REQUEST DETAILS
                ================================================== -->

                <div class="community-form-section">

                    <h3 class="community-form-section-title">
                        Request Details
                    </h3>


                    <div class="community-form-grid">


                        <!-- PURPOSE -->

                        <div
                            class="community-form-group full"
                        >

                            <label for="purpose">

                                Purpose

                                <span class="required">
                                    *
                                </span>

                            </label>

                            <input
                                type="text"
                                id="purpose"
                                name="purpose"
                                minlength="3"
                                maxlength="255"
                                required
                                value="<?= e(
                                    $formData["purpose"]
                                ) ?>"
                                placeholder="Example: Medical assistance"

                            >

                        </div>


                        <!-- ADDITIONAL DETAILS -->

                        <div
                            class="community-form-group full"
                        >

                            <label for="additional_details">

                                Additional Details

                            </label>

                            <textarea
                                id="additional_details"
                                name="additional_details"
                                maxlength="2000"
                                placeholder="Enter additional information if necessary"

                            ><?= e(
                                $formData[
                                    "additional_details"
                                ]
                            ) ?></textarea>

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     ADMIN PROCESSING
                ================================================== -->

                <div class="community-form-section">

                    <h3 class="community-form-section-title">
                        Admin Processing
                    </h3>


                    <div class="community-form-grid">


                        <!-- STATUS -->

                        <div class="community-form-group">

                            <label for="status">

                                Request Status

                                <span class="required">
                                    *
                                </span>

                            </label>

                            <select
                                id="status"
                                name="status"
                                required

                            >

                                <?php foreach (
                                    $allowedStatuses
                                    as $item
                                ): ?>

                                    <option
                                        value="<?= e($item) ?>"
                                        <?= (
                                            $formData["status"]
                                            ?? "Pending"
                                        ) === $item
                                            ? "selected"
                                            : "" ?>
                                    >

                                        <?= e($item) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <!-- REMARKS -->

                        <div class="community-form-group">

                            <label for="admin_remarks">

                                Admin Remarks

                            </label>

                            <input
                                type="text"
                                id="admin_remarks"
                                name="admin_remarks"
                                maxlength="1000"
                                value="<?= e(
                                    $formData["admin_remarks"]
                                ) ?>"
                                placeholder="Enter remarks for this request..."

                            >

                        </div>

                    </div>

                </div>


                <!-- =================================================
                     ACTIONS
                ================================================== -->

                <div class="community-form-actions">

                    <a
                        href="community_assistance.php"
                        class="btn btn-secondary"
                    >
                        ↻ Clear / Reset
                    </a>


                    <?php if ($isAdmin && $selectedRequest): ?>

                        <button
                            type="submit"
                            form="deleteRequestForm"
                            name="delete_community_request"
                            value="1"
                            class="btn btn-danger"
                        >
                            🗑️ Delete
                        </button>

                    <?php endif; ?>


                    <button
                        type="submit"
                        name="update_community_request"
                        value="1"
                        id="saveCommunityButton"
                        class="btn btn-primary"
                        <?= $selectedRequest ? "disabled" : "" ?>
                    >
                        💾 <?= $selectedRequest
                            ? "Save Changes"
                            : "Save Request" ?>
                    </button>

                </div>

            </form>

            <?php if ($selectedRequest): ?>

                <form
                    id="deleteRequestForm"
                    method="POST"
                >
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($csrfToken) ?>"
                    >
                    <input
                        type="hidden"
                        name="id"
                        value="<?= (int)$formData["id"] ?>"
                    >
                    <input
                        type="hidden"
                        name="delete_community_request"
                        value="1"
                    >
                </form>

            <?php endif; ?>

        </div>

    </section>




    <!-- =====================================================
         REQUEST LIST
    ====================================================== -->

    <section
        class="community-card"
        id="community-request-records"
    >

        <div class="community-card-header">

            <div>

                <h2>
                    Community Assistance Requests
                </h2>

                <p>
                    Requests submitted from the public
                    Online Service Request form.
                </p>

            </div>

            <span class="community-service-badge">
                Community Assistance
            </span>

        </div>


        <!-- SEARCH -->

        <form
            method="GET"
            class="community-filter"
            aria-label="Filter Community Assistance requests"
        >

            <div class="filter-field filter-search">
                <label for="request-search">Search</label>
                <div class="filter-control">
                    <span aria-hidden="true">⌕</span>
                    <input
                        id="request-search"
                        type="search"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Reference, resident, contact, or purpose..."
                    >
                </div>
            </div>

            <div class="filter-field">
                <label for="request-type-filter">Assistance Type</label>
                <select id="request-type-filter" name="assistance_type">
                    <option value="">All Assistance Types</option>
                    <?php foreach ($assistanceTypes as $item): ?>
                        <option
                            value="<?= e($item) ?>"
                            <?= $assistanceFilter === $item ? "selected" : "" ?>
                        >
                            <?= e($item) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-field">
                <label for="request-status-filter">Status</label>
                <select id="request-status-filter" name="status">
                    <option value="">All Status</option>
                    <?php foreach ($allowedStatuses as $item): ?>
                        <option
                            value="<?= e($item) ?>"
                            <?= $statusFilter === $item ? "selected" : "" ?>
                        >
                            <?= e($item) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <span aria-hidden="true">⌕</span> Search
                </button>
                <a href="community_assistance.php#community-request-records" class="btn btn-secondary">
                    Reset
                </a>
            </div>

        </form>


        <!-- TABLE -->

        <div class="community-table-wrap">

            <table class="community-table">

                <thead>

                    <tr>

                        <th>
                            Reference No.
                        </th>

                        <th>
                            Resident
                        </th>

                        <th>
                            Source
                        </th>

                        <th>
                            Assistance Type
                        </th>

                        <th>
                            Contact
                        </th>

                        <th>
                            Purpose
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Date Submitted
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if ($result->num_rows > 0): ?>

                    <?php while (
                        $row =
                        $result->fetch_assoc()
                    ): ?>

                        <?php

                        $statusClass =
                            match (
                                $row["status"]
                            ) {

                                "Pending" =>
                                    "status-pending",

                                "Under Review" =>
                                    "status-review",

                                "Approved" =>
                                    "status-approved",

                                "Rejected" =>
                                    "status-rejected",

                                "Ready for Pickup" =>
                                    "status-ready",

                                "Completed" =>
                                    "status-completed",

                                default =>
                                    ""
                            };

                        ?>

                        <tr class="<?= (int)$row["id"] === $selectedId ? "is-selected" : "" ?>">

                            <td>
                                <div class="request-reference">
                                    <span class="ref">

                                <?= e($row["reference_no"]) ?>
                                    </span>
                                    <span class="muted">ID #<?= (int)$row["id"] ?></span>
                                </div>
                            </td>


                            <td>

                                <div class="name">

                                    <?= e(
                                        $row["full_name"]
                                    ) ?>

                                </div>

                                <div class="muted">

                                    <?= e(
                                        $row["email"]
                                        ?: "No email"
                                    ) ?>

                                </div>

                            </td>

                            <td>
                                <?php
                                $requestSource = trim((string)($row["request_source"] ?? ""));
                                if ($requestSource === "") {
                                    $requestSource = "Online Request";
                                }
                                $sourceIsManual = strcasecmp($requestSource, "Manual Entry") === 0;
                                ?>
                                <span class="request-source-badge <?= $sourceIsManual ? "source-manual" : "source-online" ?>">
                                    <span aria-hidden="true"><?= $sourceIsManual ? "✍️" : "🌐" ?></span>
                                    <?= e($requestSource) ?>
                                </span>
                            </td>

                            <td>

                                <span class="assistance-type-badge">
                                    <?= e(
                                        $row["assistance_type"]
                                        ?: "Not specified"
                                    ) ?>
                                </span>

                            </td>


                            <td>

                                <?= e(
                                    $row["contact_number"]
                                ) ?>

                            </td>


                            <td>

                                <?= e(
                                    $row["purpose"]
                                ) ?>

                            </td>


                            <td>

                                <span
                                    class="status <?= $statusClass ?>"
                                >

                                    <?= e(
                                        $row["status"]
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= e(
                                    date(
                                        "M d, Y h:i A",
                                        strtotime(
                                            $row["created_at"]
                                        )
                                    )
                                ) ?>

                            </td>


                            <td>

                                <div class="table-actions">

                                    <a
                                        class="btn btn-view"
                                        href="community_assistance.php?id=<?= (int)$row["id"] ?>#community-request-form"
                                    >
                                        View / Edit
                                    </a>


                                    <?php if ($isAdmin): ?>
                                        <form
                                            method="POST"
                                            style="display:inline;"
                                        >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($csrfToken) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$row["id"] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="delete_community_request"
                                                value="1"
                                                class="btn btn-danger-soft"
                                                aria-label="Delete Community Assistance request"
                                            >
                                                🗑️ Delete
                                            </button>

                                        </form>
                                    <?php endif; ?>

                                </div>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="8" class="empty">
                            <div class="empty-state-icon" aria-hidden="true">⌕</div>
                            <span class="empty-state-title">No requests found</span>
                            <span class="empty-state-text">
                                Try adjusting your search or filters, or create a new request.
                            </span>
                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <!-- PAGINATION -->



            <div class="pagination">

                <span class="page-summary">
                    Page <?= $currentPage ?> of <?= $totalPages ?>
                    · <?= number_format($totalRecords) ?> request<?= $totalRecords === 1 ? "" : "s" ?>
                </span>

                <?php if ($currentPage > 1): ?>

                    <a
                        class="page-link"
                        href="<?= e(
                            pageUrl(
                                $currentPage - 1,
                                $search,
                                $statusFilter,
                                $assistanceFilter
                            )
                        ) ?>"
                    >
                        Previous
                    </a>

                <?php endif; ?>


                <?php
                    $pageStart = max(1, $currentPage - 2);
                    $pageEnd = min($totalPages, $currentPage + 2);
                ?>

                <?php if ($pageStart > 1): ?>
                    <a class="page-link"
                       href="<?= e(pageUrl(1, $search, $statusFilter, $assistanceFilter)) ?>">1</a>
                    <?php if ($pageStart > 2): ?>
                        <span class="page-link" aria-hidden="true">…</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($p = $pageStart; $p <= $pageEnd; $p++): ?>
                    <a
                        class="page-link <?= $p === $currentPage ? "active" : "" ?>"
                        href="<?= e(pageUrl($p, $search, $statusFilter, $assistanceFilter)) ?>"
                        <?= $p === $currentPage ? 'aria-current="page"' : "" ?>
                    >
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($pageEnd < $totalPages): ?>
                    <?php if ($pageEnd < $totalPages - 1): ?>
                        <span class="page-link" aria-hidden="true">…</span>
                    <?php endif; ?>
                    <a class="page-link"
                       href="<?= e(pageUrl($totalPages, $search, $statusFilter, $assistanceFilter)) ?>">
                        <?= $totalPages ?>
                    </a>
                <?php endif; ?>


                <?php if (
                    $currentPage < $totalPages
                ): ?>

                    <a
                        class="page-link"
                        href="<?= e(
                            pageUrl(
                                $currentPage + 1,
                                $search,
                                $statusFilter,
                                $assistanceFilter
                            )
                        ) ?>"
                    >
                        Next
                    </a>

                <?php endif; ?>

            </div>



    </section>


</div>

<!-- Custom delete confirmation dialog -->
<div class="delete-modal-overlay" id="deleteConfirmModal" aria-hidden="true">
    <div class="delete-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmTitle" aria-describedby="deleteConfirmMessage">
        <div class="delete-modal-icon" aria-hidden="true">⚠️</div>
        <h2 id="deleteConfirmTitle">Confirm Delete</h2>
        <p id="deleteConfirmMessage">Are you sure you want to delete this Community Assistance request? This action cannot be undone.</p>
        <div class="delete-modal-actions">
            <button type="button" class="delete-modal-cancel" id="cancelDeleteButton">Cancel</button>
            <button type="button" class="delete-modal-confirm" id="confirmDeleteButton">Delete</button>
        </div>
    </div>
</div>


<?php

$stmt->close();

?>


<script>
document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("deleteConfirmModal");
    const cancelButton = document.getElementById("cancelDeleteButton");
    const confirmButton = document.getElementById("confirmDeleteButton");
    if (!modal || !cancelButton || !confirmButton) return;

    let pendingDeleteForm = null;
    let previousFocus = null;

    function openDeleteModal(form) {
        pendingDeleteForm = form;
        previousFocus = document.activeElement;
        modal.classList.add("is-open");
        modal.setAttribute("aria-hidden", "false");
        document.body.style.overflow = "hidden";
        cancelButton.focus();
    }

    function closeDeleteModal() {
        modal.classList.remove("is-open");
        modal.setAttribute("aria-hidden", "true");
        document.body.style.overflow = "";
        pendingDeleteForm = null;
        if (previousFocus && typeof previousFocus.focus === "function") {
            previousFocus.focus();
        }
    }

    document.querySelectorAll("form").forEach(function (form) {
        if (!form.querySelector('[name="delete_community_request"]')) return;
        form.addEventListener("submit", function (event) {
            event.preventDefault();
            openDeleteModal(form);
        });
    });

    cancelButton.addEventListener("click", closeDeleteModal);

    confirmButton.addEventListener("click", function () {
        if (!pendingDeleteForm) return;
        const form = pendingDeleteForm;
        confirmButton.disabled = true;
        confirmButton.textContent = "Deleting...";
        // Native submit sends the existing CSRF token and request ID without reopening the modal.
        HTMLFormElement.prototype.submit.call(form);
    });

    modal.addEventListener("click", function (event) {
        if (event.target === modal) closeDeleteModal();
    });

    document.addEventListener("keydown", function (event) {
        if (!modal.classList.contains("is-open")) return;
        if (event.key === "Escape") {
            closeDeleteModal();
        } else if (event.key === "Tab") {
            const focusable = [cancelButton, confirmButton];
            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const capitalizeFields = [
        "first_name",
        "middle_name",
        "last_name"
    ];

    capitalizeFields.forEach(function (fieldId) {

        const input = document.getElementById(fieldId);

        if (!input) return;

        input.addEventListener("input", function () {

            if (this.value.length > 0) {
                this.value =
                    this.value.charAt(0).toUpperCase() +
                    this.value.slice(1);
            }

        });

    });

});
</script>

<script>

function closeSuccessModal() {

    const modal =
        document.getElementById("successModal");

    if (!modal) return;

    modal.style.opacity = "0";

    setTimeout(function () {

        modal.remove();

    }, 180);
}


/* Close when clicking outside the popup */

document.addEventListener("click", function (event) {

    const modal =
        document.getElementById("successModal");

    if (
        modal &&
        event.target === modal
    ) {
        closeSuccessModal();
    }

});


/* Automatically close after 4 seconds */

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const modal =
            document.getElementById("successModal");

        if (!modal) return;

        setTimeout(function () {

            closeSuccessModal();

        }, 4000);

    }
);

</script>


<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.querySelector(
        '#community-request-form form'
    );

    const saveButton = document.getElementById(
        'saveCommunityButton'
    );

    if (!form || !saveButton) return;

    // Ayaw i-disable ang Save Request kung bag-ong request.
    const isEditing = new URLSearchParams(
        window.location.search
    ).has('id');

    if (!isEditing) {
        saveButton.disabled = false;
        return;
    }

    // Kuhaa ang original values sa form pag-abli sa Edit.
    const fields = Array.from(form.querySelectorAll(
        'input:not([type="hidden"]):not([readonly]), select, textarea'
    ));

    function getValue(field) {
        if (field.type === 'checkbox' ||
            field.type === 'radio') {
            return field.checked;
        }

        return field.value;
    }

    const originalValues = new Map();

    fields.forEach(function (field) {
        originalValues.set(field, getValue(field));
    });

    function checkChanges() {
        const hasChanges = fields.some(function (field) {
            return getValue(field) !== originalValues.get(field);
        });

        saveButton.disabled = !hasChanges;
        saveButton.classList.toggle(
            'btn-disabled',
            !hasChanges
        );
    }

    fields.forEach(function (field) {
        field.addEventListener('input', checkChanges);
        field.addEventListener('change', checkChanges);
    });

    checkChanges();
});
</script>


<?php

include "../includes/footer.php";

?>
