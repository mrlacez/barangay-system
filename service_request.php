<?php

require_once "config/database.php";

$error = "";

/* =========================
   ALLOWED SERVICES
========================= */

$allowedServices = [
    "Barangay Certificate",
    "Certificate of Residency",
    "Certificate of Indigency",
    "Blotter Assistance",
    "Community Assistance"
];

$serviceType = trim($_GET["service"] ?? $_POST["service_type"] ?? "");

if (!in_array($serviceType, $allowedServices, true)) {
    $serviceType = "";
}

/* =========================
   INPUT HELPERS
========================= */

function textLength(string $value): int
{
    return function_exists("mb_strlen")
        ? mb_strlen($value, "UTF-8")
        : strlen($value);
}

function cleanSingleLine(string $value): string
{
    $value = str_replace(["\\r", "\\n", "\\t"], " ", $value);
    $value = preg_replace('/\\s{2,}/u', ' ', trim($value));
    return $value ?? "";
}

function cleanMultiLine(string $value): string
{
    $value = str_replace(["\\r\\n", "\\r"], "\\n", $value);
    $value = preg_replace('/[ \\t]+/u', ' ', $value);
    $value = preg_replace('/\\n{3,}/u', "\\n\\n", trim($value));
    return $value ?? "";
}

/* =========================
   CSRF TOKEN
========================= */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION["service_request_csrf"])) {
    $_SESSION["service_request_csrf"] = bin2hex(random_bytes(32));
}

/* =========================
   SUBMIT REQUEST
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrfToken = $_POST["csrf_token"] ?? "";

    if (
        empty($csrfToken) ||
        !hash_equals($_SESSION["service_request_csrf"], $csrfToken)
    ) {
        $error = "Invalid request. Please refresh the page and try again.";
    }

    $serviceType = trim($_POST["service_type"] ?? "");

    $assistanceType = cleanSingleLine((string)($_POST["assistance_type"] ?? ""));

    $firstName = cleanSingleLine((string)($_POST["first_name"] ?? ""));
    $middleName = cleanSingleLine((string)($_POST["middle_name"] ?? ""));
    $lastName = cleanSingleLine((string)($_POST["last_name"] ?? ""));
    $suffix = cleanSingleLine((string)($_POST["suffix"] ?? ""));
    $birthDate = cleanSingleLine((string)($_POST["birth_date"] ?? ""));
    $gender = cleanSingleLine((string)($_POST["gender"] ?? ""));
    $civilStatus = cleanSingleLine((string)($_POST["civil_status"] ?? ""));

    $address = cleanSingleLine((string)($_POST["address"] ?? ""));
    $contactNumber = cleanSingleLine((string)($_POST["contact_number"] ?? ""));
    $email = cleanSingleLine((string)($_POST["email"] ?? ""));

    $purpose = cleanSingleLine((string)($_POST["purpose"] ?? ""));
    $additionalDetails = cleanMultiLine((string)($_POST["additional_details"] ?? ""));

    $respondent = cleanSingleLine((string)($_POST["respondent"] ?? ""));
    $incident = cleanSingleLine((string)($_POST["incident"] ?? ""));
    $incidentDate = cleanSingleLine((string)($_POST["incident_date"] ?? ""));
    $blotterRemarks = cleanMultiLine((string)($_POST["blotter_remarks"] ?? ""));

    /* Build full name for database compatibility */
    $fullName = trim(
        preg_replace(
            '/\s+/u',
            ' ',
            implode(' ', array_filter([
                $firstName,
                $middleName,
                $lastName,
                $suffix
            ]))
        )
    );

    if ($serviceType === "Blotter Assistance") {
        $purpose = "Blotter Assistance";
    }

    /* Honeypot field: normal users should never fill this. */
    $website = trim((string)($_POST["website"] ?? ""));

    if (
        !$error &&
        $website !== ""
    ) {
        $error = "Unable to submit the request. Please try again.";
    }

    if (
        !$error &&
        !in_array($serviceType, $allowedServices, true)
    ) {
        $error = "Please select a valid barangay service.";
    }

    if (
        !$error &&
        $serviceType === "Community Assistance" &&
        $assistanceType === ""
    ) {
        $error = "Please select a type of assistance.";
    }

    if (
        !$error &&
        (
            $firstName === "" ||
            $lastName === "" ||
            $address === "" ||
            $contactNumber === "" ||
            $gender === "" ||
            $civilStatus === "" ||
            (
                $serviceType !== "Blotter Assistance" &&
                $purpose === ""
            )
        )
    ) {
        $error = "Please complete all required fields.";
    }

    /* Length restrictions */
    if (!$error && textLength($fullName) < 2) {
        $error = "Please enter your complete name.";
    }

    if (!$error && textLength($fullName) > 150) {
        $error = "Full name is too long.";
    }

    if (!$error && !preg_match("/^[\\p{L}\\p{M}][\\p{L}\\p{M} .'\\-]{1,149}$/u", $fullName)) {
        $error = "Please enter a valid full name.";
    }

    if (!$error && textLength($address) < 5) {
        $error = "Please enter your complete address.";
    }

    if (!$error && textLength($address) > 255) {
        $error = "Address is too long.";
    }

    if (!$error && textLength($contactNumber) > 30) {
        $error = "Contact number is too long.";
    }

    if (
        !$error &&
        !preg_match('/^09[0-9]{9}$/', $contactNumber)
    ) {
        $error = "Contact number must be exactly 11 digits and start with 09.";
    }

    if (
        !$error &&
        preg_match('/^(\d)\1{10}$/', $contactNumber)
    ) {
        $error = "Please enter a valid contact number.";
    }

    /* Reject obviously fake/repeated contact numbers. */
    if (
        !$error &&
        preg_match('/^(.)\\1{6,}$/', preg_replace('/\\D+/', '', $contactNumber))
    ) {
        $error = "Please enter a valid contact number.";
    }

    if (
        !$error &&
        $email !== "" &&
        (
            textLength($email) > 150 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)
        )
    ) {
        $error = "Please enter a valid email address.";
    }


    if (!$error && !preg_match("/^[A-Za-zÀ-ÿÑñ' -]+$/u", $firstName)) {
        $error = "First name must contain letters only.";
    }

    if (
        !$error &&
        $middleName !== "" &&
        !preg_match("/^[A-Za-zÀ-ÿÑñ' -]+$/u", $middleName)
    ) {
        $error = "Middle name must contain letters only.";
    }

    if (!$error && !preg_match("/^[A-Za-zÀ-ÿÑñ' -]+$/u", $lastName)) {
        $error = "Last name must contain letters only.";
    }

    $allowedSuffixes = ["", "Jr.", "Sr.", "II", "III", "IV", "V"];

    if (!$error && !in_array($suffix, $allowedSuffixes, true)) {
        $error = "Please select a valid suffix.";
    }

    $allowedGenders = ["Male", "Female"];

    if (!$error && !in_array($gender, $allowedGenders, true)) {
        $error = "Please select a valid gender.";
    }

    $allowedCivilStatuses = [
        "Single",
        "Married",
        "Widowed",
        "Separated"
    ];

    if (!$error && !in_array($civilStatus, $allowedCivilStatuses, true)) {
        $error = "Please select a valid civil status.";
    }

    if (!$error && $birthDate !== "") {

        $birthDateObject = DateTime::createFromFormat(
            "Y-m-d",
            $birthDate
        );

        if (
            !$birthDateObject ||
            $birthDateObject->format("Y-m-d") !== $birthDate
        ) {
            $error = "Please enter a valid birth date.";
        } elseif ($birthDate > date("Y-m-d")) {
            $error = "Birth date cannot be in the future.";
        }
    }





    if (
        !$error &&
        $serviceType !== "Blotter Assistance" &&
        textLength($purpose) < 3
    ) {
        $error = "Please provide a clear purpose for the request.";
    }

    if (
        !$error &&
        $serviceType !== "Blotter Assistance" &&
        textLength($purpose) > 255
    ) {
        $error = "Purpose is too long.";
    }

    if (
        !$error &&
        textLength($additionalDetails) > 2000
    ) {
        $error = "Additional details are too long. Please keep them within 2,000 characters.";
    }




    if (!$error && $serviceType === "Blotter Assistance") {

        if ($incident === "") {
            $error = "Please provide the incident details.";
        }

        if (!$error && textLength($incident) < 5) {
            $error = "Please provide a clear description of the incident.";
        }

        if (!$error && textLength($incident) > 500) {
            $error = "Incident details are too long.";
        }

        if (!$error && textLength($respondent) > 150) {
            $error = "Respondent name is too long.";
        }

        if (!$error && $incidentDate !== "") {

            $dateObject = DateTime::createFromFormat(
                "Y-m-d",
                $incidentDate
            );

            if (
                !$dateObject ||
                $dateObject->format("Y-m-d") !== $incidentDate
            ) {
                $error = "Please enter a valid incident date.";
            } elseif ($incidentDate > date("Y-m-d")) {
                $error = "Incident date cannot be in the future.";
            }
        }

        if (!$error && textLength($blotterRemarks) > 2000) {
            $error = "Blotter remarks are too long. Please keep them within 2,000 characters.";
        }
    }

    /* Reject obvious repeated-character/spam input. */
    if (
        !$error &&
        (
            preg_match('/^(.)\\1{7,}$/u', preg_replace('/\\s+/u', '', $fullName)) ||
            preg_match('/^(.)\\1{19,}$/u', preg_replace('/\\s+/u', '', $purpose))
        )
    ) {
        $error = "Please provide meaningful information.";
    }

    /* Basic public-form rate limit: one submission per session per 60 seconds. */
    if (
        !$error &&
        isset($_SESSION["service_request_last_submit_at"]) &&
        (time() - (int)$_SESSION["service_request_last_submit_at"]) < 60
    ) {
        $error = "Please wait a moment before submitting another request.";
    }

    /* Prevent rapid duplicate requests for the same contact and service. */
    if (!$error) {
        $duplicateStmt = $conn->prepare("
            SELECT reference_no
            FROM service_requests
            WHERE contact_number = ?
              AND service_type = ?
              AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            ORDER BY id DESC
            LIMIT 1
        ");

        if ($duplicateStmt) {
            $duplicateStmt->bind_param(
                "ss",
                $contactNumber,
                $serviceType
            );
            $duplicateStmt->execute();
            $duplicateResult = $duplicateStmt->get_result();

            if ($duplicateResult && $duplicateResult->num_rows > 0) {
                $duplicateRow = $duplicateResult->fetch_assoc();
                $error = "A similar request was already submitted recently. Reference No.: " .
                    htmlspecialchars(
                        $duplicateRow["reference_no"],
                        ENT_QUOTES,
                        "UTF-8"
                    );
            }

            $duplicateStmt->close();
        }
    }

    /* =========================
       SAVE REQUEST
    ========================= */

    if (!$error) {

        try {

            $conn->begin_transaction();

            /*
             * Temporary unique reference.
             * After INSERT we use the database ID
             * to create a cleaner final reference number.
             */

            $temporaryReference =
                "TMP-" .
                bin2hex(random_bytes(8));

            if ($serviceType === "Blotter Assistance") {

                $stmt = $conn->prepare("
                    INSERT INTO service_requests (
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
                        address,
                        contact_number,
                        email,
                        purpose,
                        additional_details,
                        request_source,
                        status
                    )
                    VALUES (
                        ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?,
                        ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, ?, 'Online Request', 'Pending'
                    )
                ");

                $stmt->bind_param(
                    "sssssssssssssssssss",
                    $temporaryReference,
                    $serviceType,
                    $fullName,
                    $firstName,
                    $middleName,
                    $lastName,
                    $suffix,
                    $birthDate,
                    $gender,
                    $civilStatus,
                    $respondent,
                    $incident,
                    $incidentDate,
                    $blotterRemarks,
                    $address,
                    $contactNumber,
                    $email,
                    $purpose,
                    $additionalDetails
                );

            } else {

                $stmt = $conn->prepare("
                    INSERT INTO service_requests (
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
                        request_source,
                        status
                    )
                    VALUES (
                        ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?, ?, ?, ?, ?, ?, 'Online Request', 'Pending'
                    )
                ");

                if (!$stmt) {
                    throw new Exception("Unable to prepare service request.");
                }

                $stmt->bind_param(
                    "ssssssssssssssss",
                    $temporaryReference,
                    $serviceType,
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
                    $additionalDetails
                );
            }

            $stmt->execute();

            $requestId = $conn->insert_id;

            /*
             * Generate yearly certificate reference number.
             * Example:
             * CERT-2026-0001
             * CERT-2026-0002
             *
             * The numbering resets to 0001 every new year.
             */

            $referenceYear = (int) date("Y");

            /*
             * Atomically create/update the yearly sequence.
             * LAST_INSERT_ID() gives us the new sequence number.
             */
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
                    "Unable to prepare reference sequence."
                );
            }

            $sequenceStmt->bind_param(
                "i",
                $referenceYear
            );

            if (!$sequenceStmt->execute()) {
                $sequenceStmt->close();

                throw new Exception(
                    "Unable to generate reference number."
                );
            }

            $sequenceStmt->close();

            /*
             * Get the newly generated sequence number.
             */
            $sequenceResult = $conn->query("
                SELECT LAST_INSERT_ID() AS sequence_number
            ");

            if (!$sequenceResult) {
                throw new Exception(
                    "Unable to retrieve reference sequence."
                );
            }

            $sequenceRow = $sequenceResult->fetch_assoc();

            $sequenceNumber = (int) (
                $sequenceRow["sequence_number"] ?? 0
            );

            if ($sequenceNumber <= 0) {
                throw new Exception(
                    "Invalid reference sequence."
                );
            }

            /*
             * Final reference number.
             *
             * Example:
             * CERT-2026-0001
             */
            $referenceNo =
                "CERT-" .
                $referenceYear .
                "-" .
                str_pad(
                    (string) $sequenceNumber,
                    4,
                    "0",
                    STR_PAD_LEFT
                );

            $updateStmt = $conn->prepare("
                UPDATE service_requests
                SET reference_no = ?
                WHERE id = ?
            ");

            $updateStmt->bind_param(
                "si",
                $referenceNo,
                $requestId
            );

            $updateStmt->execute();

            /*
             * CREATE NOTIFICATION FOR ACTIVE ADMINISTRATORS AND STAFF
             */
            $notificationTitle = "New Service Request";

            $notificationMessage =
                $fullName .
                " submitted a " .
                $serviceType .
                " request. Reference No.: " .
                $referenceNo;

            $notificationStmt = $conn->prepare("
                INSERT INTO notifications (
                    user_id,
                    title,
                    message,
                    type,
                    reference_id
                )
                SELECT
                    id,
                    ?,
                    ?,
                    'service_request',
                    ?
                FROM users
                WHERE status = 'Active'
                  AND (
                      role = 'Administrator'
                      OR role = 'admin'
                      OR role = 'Staff'
                  )
            ");

            if (!$notificationStmt) {
                throw new Exception("Unable to prepare notification.");
            }

            $notificationStmt->bind_param(
                "ssi",
                $notificationTitle,
                $notificationMessage,
                $requestId
            );

            $notificationStmt->execute();
            $notificationStmt->close();

            $conn->commit();

            $_SESSION["service_request_last_submit_at"] = time();

            $stmt->close();
            $updateStmt->close();

            /*
             * Prevent duplicate submission
             * when refreshing the browser.
             */

            unset($_SESSION["service_request_csrf"]);

            header(
                "Location: service_request_success.php?ref=" .
                urlencode($referenceNo) .
                "&service=" .
                urlencode($serviceType)
            );

            exit;

        } catch (Throwable $e) {

            $conn->rollback();

            $error =
                "Unable to submit your request. Please try again.";

        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Online Service Request - Barangay</title>


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f1f5f9;

            color: #1f2937;
        }

        /* =========================
           HEADER
        ========================= */

        /* Shared navbar styles are defined below to match index.php. */



        /* =========================
           PAGE
        ========================= */

        .page-container {
            max-width: 940px;

            margin: 40px auto;

            padding: 0 20px;
        }

        .back-link {
            display: inline-block;

            margin-bottom: 18px;

            color: #2563eb;

            font-weight: bold;

            text-decoration: none;
        }

        .request-card {
            background: white;

            border: 1px solid #e2e8f0;
            border-radius: 18px;

            padding: 36px;

            box-shadow:
                0 5px 20px rgba(0, 0, 0, .07);

            border-top: 5px solid #2563eb;
        }

        .request-header {
            margin-bottom: 28px;
            padding-bottom: 22px;
            border-bottom: 1px solid #e2e8f0;
        }

        .request-header h2 {
            margin: 0 0 8px;

            color: #172554;

            font-size: 28px;
        }

        .request-header p {
            margin: 0;

            color: #64748b;

            line-height: 1.6;
        }

        .request-header-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
        }

        .request-header-copy { min-width: 0; }

        .track-link {
            flex: 0 0 auto;
            color: #1d4ed8;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 9px;
            padding: 10px 13px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }

        .track-link:hover { background: #dbeafe; }

        .form-section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 28px 0 17px;
            color: #172554;
            font-size: 16px;
        }

        .form-section-title::before {
            content: "";
            width: 4px;
            height: 18px;
            border-radius: 4px;
            background: #2563eb;
        }

        .field-hint {
            display: block;
            margin-top: 6px;
            color: #64748b;
            font-size: 12px;
        }

        /* =========================
           FORM
        ========================= */

        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 18px;
        }

        label {
            display: block;

            margin-bottom: 7px;

            font-size: 14px;

            font-weight: bold;

            color: #334155;
        }

        .required {
            color: #dc2626;
        }

        input,
        select,
        textarea {
            width: 100%;

            padding: 12px 13px;

            border: 1px solid #cbd5e1;

            border-radius: 8px;

            font-family: inherit;

            font-size: 14px;

            outline: none;

            background: white;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 3px rgba(37, 99, 235, .10);
        }

        input:hover, select:hover, textarea:hover { border-color: #94a3b8; }

        textarea {
            min-height: 105px;

            resize: vertical;
        }

        .service-display {
            padding: 14px;

            background: #eff6ff;

            border: 1px solid #bfdbfe;

            border-radius: 8px;

            color: #1e40af;

            font-weight: bold;
        }

        /* =========================
           ERROR
        ========================= */

        .error-message {
            margin-bottom: 22px;

            padding: 13px 15px;

            border-radius: 8px;

            background: #fef2f2;

            border: 1px solid #fecaca;

            color: #b91c1c;

            font-size: 14px;
        }

        .error-message:focus { outline: 3px solid rgba(220, 38, 38, .18); }

        /* =========================
           BUTTON
        ========================= */

        .submit-button {
            width: 100%;

            border: 0;

            border-radius: 10px;

            padding: 14px;

            background: linear-gradient(115deg, #1d4ed8, #2563eb);

            color: white;

            font-weight: bold;

            font-size: 15px;

            cursor: pointer;
        }

        .submit-button:hover {
            background: #1e40af;
            transform: translateY(-1px);
        }

        .privacy-note {
            margin-top: 16px;

            padding: 13px;

            background: #f8fafc;

            border-radius: 8px;

            color: #64748b;

            font-size: 13px;

            line-height: 1.5;
        }

        .submit-help {
            margin: 10px 0 0;
            color: #64748b;
            font-size: 12px;
            text-align: center;
        }

        /* =========================
           FOOTER
        ========================= */

        /* =========================
           FOOTER — MATCH INDEX.PHP
        ========================= */
        footer {
            width: 100%;
            min-height: 44px;
            margin-top: auto;
            padding: 10px 16px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: linear-gradient(
                135deg,
                #0b1e3d,
                #102a56
            );

            color: #cbd5e1;
            font-size: 12px;
            line-height: normal;

            border-top: 1px solid rgba(255, 255, 255, 0.05);
            flex-shrink: 0;
        }

        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 650px) {

            .header-content {
                gap: 15px;
            }

            .brand h1 {
                font-size: 18px;
            }

            .brand p {
                font-size: 11px;
            }

            .page-container {
                margin-top: 25px;

                padding: 0 15px;
            }

            .request-card {
                padding: 22px 18px;
                border-radius: 14px;
            }

            .request-header-top { flex-direction: column; }
            .request-header h2 { font-size: 24px; }

            .form-row {
                grid-template-columns: 1fr;

                gap: 0;
            }

        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition: none !important; }
        }

        .submit-button:disabled {
            background: #94a3b8;
            cursor: not-allowed;
            opacity: 0.75;
        }

        .submit-button:disabled:hover {
            background: #94a3b8;
        }


/* NAVBAR: match index.php exactly on desktop; keep matching responsive behavior */
.main-header {
    width: 100%;
    height: 88px;
    background: linear-gradient(135deg, #123873 0%, #16448f 48%, #0f3d82 100%);
    box-shadow: 0 5px 22px rgba(15, 23, 42, .18);
    position: relative;
    z-index: 1000;
}

.main-nav {
    width: min(1540px, calc(100% - 55px));
    height: 88px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    gap: 25px;
}

.main-nav .brand {
    display: flex;
    align-items: center;
    gap: 13px;
    min-width: 315px;
    flex-shrink: 0;
    margin: 0;
    padding: 0;
    color: #fff;
}

.main-nav .brand-logo {
    width: 66px;
    height: 66px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: #fff;
    border-radius: 14px;
    color: #1768e8;
    font-size: 34px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, .18);
}

.main-nav .brand-text { min-width: 0; }

.main-nav .brand-text h1 {
    margin: 0;
    color: #fff;
    font-size: 23px;
    line-height: 1.1;
    font-weight: 900;
    letter-spacing: .3px;
}

.main-nav .brand-text span {
    display: block;
    margin-top: 5px;
    color: rgba(255, 255, 255, .86);
    font-size: 13px;
    white-space: nowrap;
}

.main-nav .main-nav-links {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.main-nav .nav-item {
    position: relative;
    height: 46px;
    padding: 0 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border-radius: 12px;
    color: #fff;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    white-space: nowrap;
    transition: background .2s ease, transform .2s ease, box-shadow .2s ease;
}

.main-nav .nav-item:hover {
    background: rgba(255, 255, 255, .14);
    transform: translateY(-1px);
}

.main-nav .nav-item.active {
    background: linear-gradient(135deg, #3b91f7, #1473ed);
    box-shadow: 0 7px 18px rgba(0, 0, 0, .16);
}

.main-nav .nav-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    line-height: 1;
}

/* Match the announcement notification indicator used by index.php */
.main-nav .announcement-nav {
    position: relative;
}
.main-nav .notification-dot {
    position: absolute;
    top: 6px;
    right: 7px;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #ef4444;
    border: 2px solid #17458b;
    box-shadow: 0 0 0 1px rgba(255, 255, 255, .08);
}

.main-nav .nav-right {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-shrink: 0;
}

.main-nav .nav-search {
    width: 282px;
    height: 43px;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 0 15px;
    border-radius: 24px;
    background: rgba(255, 255, 255, .13);
    border: 1px solid rgba(255, 255, 255, .08);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .04);
    flex-shrink: 0;
}

.main-nav .nav-search span {
    flex: 0 0 auto;
    color: #fff;
    font-size: 22px;
    line-height: 1;
}

.main-nav .nav-search input {
    width: 100%;
    min-width: 0;
    border: 0;
    outline: none;
    background: transparent;
    color: #fff;
    font-size: 13px;
    padding: 0;
    box-shadow: none;
}

.main-nav .nav-search input::placeholder { color: rgba(255, 255, 255, .75); }
.main-nav .nav-search input:hover { border-color: transparent; }

.main-nav .admin-menu {
    display: flex;
    align-items: center;
    gap: 9px;
    color: #fff;
    cursor: pointer;
    white-space: nowrap;
    user-select: none;
}

.main-nav .admin-avatar {
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #eaf2ff;
    color: #214c8d;
    font-size: 21px;
    border: 2px solid rgba(255, 255, 255, .55);
    box-shadow: 0 3px 10px rgba(0, 0, 0, .10);
    flex-shrink: 0;
}

.main-nav .admin-name strong {
    color: #fff;
    font-size: 14px;
    font-weight: 800;
}

.main-nav .admin-arrow {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 16px;
    height: 16px;
    margin-left: 1px;
    color: #fff;
    font-size: 11px;
    line-height: 1;
    flex-shrink: 0;
    opacity: .9;
    vertical-align: middle;
}

.main-nav .admin-arrow i { display: block; line-height: 1; }

@media (min-width: 1001px) and (max-width: 1200px) {
    .main-nav { width: calc(100% - 30px); gap: 14px; }
    .main-nav .brand { min-width: 0; }
    .main-nav .nav-search { width: 200px; }
    .main-nav .nav-item { padding: 0 10px; }
}

@media (min-width: 701px) and (max-width: 1000px) {
    .main-header { height: auto; min-height: 88px; }
    .main-nav {
        width: calc(100% - 24px);
        height: auto;
        min-height: 88px;
        padding: 10px 0;
        flex-wrap: wrap;
        gap: 8px;
    }
    .main-nav .brand { order: 1; flex: 1 1 auto; min-width: 0; gap: 8px; }
    .main-nav .brand-logo { width: 48px; height: 48px; font-size: 25px; }
    .main-nav .brand-text h1 { font-size: 18px; }
    .main-nav .brand-text span { display: none; }
    .main-nav .nav-right { order: 2; flex: 0 0 auto; gap: 8px; }
    .main-nav .nav-search { display: none; }
    .main-nav .admin-avatar { width: 36px; height: 36px; }
    .main-nav .main-nav-links {
        order: 3; flex: 0 0 100%; width: 100%; min-width: 0;
        justify-content: flex-start; flex-wrap: wrap; gap: 5px; overflow: visible;
    }
    .main-nav .nav-item { height: 38px; padding: 0 9px; gap: 5px; font-size: 11px; }
}

@media (max-width: 700px) {
    .main-header { height: auto; min-height: 0; }
    .main-nav {
        width: calc(100% - 20px);
        height: auto;
        min-height: 0;
        padding: 10px 0;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }
    .main-nav .brand { order: 1; flex: 0 0 100%; width: 100%; min-width: 0; gap: 9px; }
    .main-nav .brand-logo { width: 46px; height: 46px; font-size: 24px; }
    .main-nav .brand-text h1 { font-size: 17px; }
    .main-nav .brand-text span { display: block; font-size: 11px; white-space: normal; }
    .main-nav .main-nav-links {
        order: 2; width: 100%; min-width: 0; flex: 1 1 100%;
        display: flex; justify-content: flex-start; gap: 5px;
        overflow-x: auto; overflow-y: hidden; white-space: nowrap;
        padding: 2px 0 6px; scrollbar-width: thin; -webkit-overflow-scrolling: touch;
    }
    .main-nav .main-nav-links .nav-item { flex: 0 0 auto; height: 40px; padding: 0 10px; gap: 6px; font-size: 12px; }
    .main-nav .main-nav-links .nav-icon { flex: 0 0 auto; font-size: 15px; }
    .main-nav .nav-right { display: none; }
}


/* Fix Services grid icon in the navbar */
.main-nav .services-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}

.main-nav .services-icon svg {
    display: block;
    width: 18px;
    height: 18px;
    fill: currentColor;
}




/* ==========================================================
   BONGDO — MODERN ONLINE SERVICE REQUEST UI
   Visual refresh based on the supplied reference screenshot.
   Existing PHP processing and form field names are preserved.
========================================================== */
:root {
    --brand-900: #0b2f68;
    --brand-800: #10448f;
    --brand-700: #075fe8;
    --brand-600: #0878f9;
    --ink: #102858;
    --muted: #647997;
    --line: #dce7f5;
    --surface: rgba(255,255,255,.94);
    --soft-blue: #eef6ff;
}
html { min-height: 100%; scroll-behavior: smooth; }
body {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background:
      radial-gradient(ellipse at 10% 22%, rgba(90,160,255,.16), transparent 32%),
      radial-gradient(ellipse at 90% 75%, rgba(97,170,255,.12), transparent 35%),
      linear-gradient(135deg, #f4f8ff 0%, #eaf3ff 48%, #f7faff 100%);
    color: var(--ink);
    font-family: Inter, "Segoe UI", Arial, sans-serif;
}
.page-container {
    width: min(1480px, calc(100% - 64px));
    max-width: none;
    margin: 22px auto 30px;
    padding: 0;
    flex: 1 0 auto;
}
.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 14px 18px;
    color: #075fe8;
    font-size: 15px;
    font-weight: 800;
    text-decoration: none;
    transition: transform .18s ease, color .18s ease;
}
.back-link:hover { color: #0349b8; transform: translateX(-2px); }
.request-layout {
    display: grid;
    grid-template-columns: 324px minmax(0, 1fr);
    align-items: stretch;
    gap: 18px;
}
.request-sidebar {
    position: relative;
    overflow: hidden;
    min-width: 0;
    padding: 26px 22px;
    border: 1px solid rgba(255,255,255,.9);
    border-radius: 22px;
    background: linear-gradient(160deg, rgba(239,247,255,.96), rgba(255,255,255,.82) 48%, rgba(231,241,255,.95));
    box-shadow: 0 14px 40px rgba(34,91,165,.10), inset 0 1px 0 #fff;
}
.request-sidebar::before,
.request-sidebar::after {
    content: ""; position: absolute; z-index: 0; pointer-events: none;
    border-radius: 50%; background: linear-gradient(135deg, rgba(56,133,255,.12), rgba(255,255,255,.02));
}
.request-sidebar::before { width: 360px; height: 220px; left: -120px; bottom: -110px; transform: rotate(-18deg); }
.request-sidebar::after { width: 240px; height: 160px; right: -120px; bottom: 30px; }
.sidebar-inner { position: relative; z-index: 1; }

/* Online Service Request — Document with Pencil Icon */
.sidebar-art {
    width: 132px;
    height: 112px;
    margin: 4px auto 18px;
    display: grid;
    place-items: center;
    position: relative;
    color: #0877f9;
    font-size: 68px;
    filter: drop-shadow(0 10px 12px rgba(31, 104, 210, .16));
}

.sidebar-art::before {
    content: "";
    position: absolute;
    width: 104px;
    height: 104px;
    border-radius: 28px;
    background: linear-gradient(145deg, #ffffff, #d9eaff);
    box-shadow: 0 8px 25px rgba(26, 99, 194, .16);
    transform: rotate(-7deg);
}

.sidebar-art i {
    position: relative;
    z-index: 1;
    color: #0877f9;
    font-size: 68px;
    line-height: 1;
}

.request-sidebar h1 { margin: 0 0 12px; color: #09275e; font-size: 29px; line-height: 1.08; letter-spacing: -.8px; font-weight: 900; }
.request-sidebar .sidebar-description { margin: 0; color: #536b8f; font-size: 14px; line-height: 1.55; }
.sidebar-divider { height: 1px; margin: 18px 0; background: #d4e2f5; }
.progress-list { display: grid; gap: 8px; }
.progress-step { display: grid; grid-template-columns: 48px 25px minmax(0,1fr); align-items: center; gap: 9px; padding: 10px 8px; border-radius: 13px; color: #4f6688; }
.progress-step.active { background: linear-gradient(110deg,#e1efff,#d5e8ff); color: #075fe8; box-shadow: 0 5px 16px rgba(31,111,231,.09); position: relative; }
.progress-step.active::before { content:""; position:absolute; left:0; top:9px; bottom:9px; width:3px; border-radius:4px; background:#0878f9; }
.step-icon { width: 44px; height: 44px; display:grid; place-items:center; border-radius:50%; background:#e0e9f5; color:#637b9e; font-size:18px; }
.progress-step.active .step-icon { background:linear-gradient(145deg,#1186ff,#0757df); color:white; box-shadow:0 5px 12px rgba(0,94,229,.22); }
.step-number { width:23px; height:23px; display:grid; place-items:center; border-radius:50%; background:#647b9c; color:white; font-size:12px; font-weight:800; }
.progress-step.active .step-number { background:#0969ec; }
.step-copy strong { display:block; font-size:13px; line-height:1.3; font-weight:800; }
.step-copy span { display:block; margin-top:3px; font-size:11.5px; line-height:1.35; }
.reminder-card { display:flex; align-items:flex-start; gap:11px; margin-top:18px; padding:13px; border:1px solid #b7d5ff; border-radius:13px; background:rgba(222,237,255,.82); color:#43658f; }
.reminder-icon { flex:0 0 32px; width:32px; height:32px; display:grid; place-items:center; border-radius:50%; background:#0874f5; color:white; font-size:15px; }
.reminder-card strong { display:block; margin:0 0 4px; color:#075fe8; font-size:12px; }
.reminder-card p { margin:0; font-size:11.5px; line-height:1.5; }
.request-card {
    min-width: 0;
    padding: 20px 23px 12px;
    border: 1px solid rgba(255,255,255,.94);
    border-radius: 22px;
    background: var(--surface);
    box-shadow: 0 14px 42px rgba(31,83,145,.12), inset 0 1px 0 #fff;
    border-top: 1px solid rgba(255,255,255,.94);
}
.request-header { margin: 0 0 17px; padding: 0 2px 0; border: 0; }
.request-header-top { display:flex; align-items:center; justify-content:space-between; gap:18px; }
.request-header-copy { display:flex; align-items:flex-start; gap:12px; min-width:0; }
.request-header h2 { margin:0 0 4px; color:#0a2457; font-size:25px; letter-spacing:-.7px; line-height:1.2; font-weight:900; }
.request-header p { margin:0; color:#607796; font-size:12.5px; line-height:1.5; }
.track-link { padding:9px 13px; border-radius:11px; white-space:nowrap; font-size:12px; color:#075fe8; background:#f1f7ff; border-color:#c5ddff; }
.track-link:hover { background:#e2efff; }
.request-card form { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); column-gap:16px; row-gap:0; }
.form-section-title {
    grid-column:1 / -1;
    display:flex; align-items:center; gap:12px;
    min-height:52px; margin:0; padding:10px 14px;
    border:1px solid #e0edff; border-bottom:0; border-radius:15px 15px 0 0;
    background:linear-gradient(100deg,#eaf4ff,#f5f9ff);
    color:#102858; font-size:15px; line-height:1.25; font-weight:850;
}
.form-section-title::before { content:"\f15c"; width:38px; height:38px; display:grid; place-items:center; flex:0 0 38px; border-radius:50%; background:#d8eaff; color:#0873f5; font-family:"Font Awesome 6 Free"; font-weight:900; font-size:17px; }
.form-section-title:nth-of-type(2) { background:linear-gradient(100deg,#f0efff,#f8f7ff); border-color:#e8e5ff; }
.form-section-title:nth-of-type(2)::before { content:"\f2bd"; color:#6655ee; background:#e4e0ff; }
.form-section-title:nth-of-type(3) { background:linear-gradient(100deg,#e6faf3,#f4fffb); border-color:#d9f4e9; }
.form-section-title:nth-of-type(3)::before { content:"\f095"; color:#08a85a; background:#d5f8e7; }
.form-section-title:nth-of-type(4) { background:linear-gradient(100deg,#eeedff,#f8f7ff); border-color:#e6e3ff; }
.form-section-title:nth-of-type(4)::before { content:"\f15c"; color:#6551e8; background:#dfdcff; }
.form-group { min-width:0; margin:0; padding:7px 9px 9px; background:#fbfdff; border-left:1px solid #e0edff; border-right:1px solid #e0edff; }
.form-row { display:contents; }
.form-row .form-group { grid-column:span 2; }
.request-card form > .form-group { grid-column:span 3; }
.request-card form > .form-section-title + .form-group { grid-column:1 / -1; }
.request-card form > .form-section-title:nth-of-type(2) ~ .form-row .form-group { grid-column:span 2; }
.request-card form > .form-section-title:nth-of-type(3) ~ .form-row .form-group { grid-column:span 3; }
label { margin-bottom:6px; color:#243c62; font-size:12px; font-weight:800; }
input, select, textarea {
    min-width:0; width:100%; padding:10px 12px;
    border:1px solid #d2deec; border-radius:10px;
    background:#fff; color:#172b4d; font:500 13px/1.35 Inter,"Segoe UI",Arial,sans-serif;
    box-shadow:inset 0 1px 2px rgba(15,40,80,.025);
    transition:border-color .18s ease, box-shadow .18s ease, background .18s ease;
}
input::placeholder, textarea::placeholder { color:#8191a8; opacity:1; }
input:hover, select:hover, textarea:hover { border-color:#a9c7ef; }
input:focus, select:focus, textarea:focus { border-color:#398cff; box-shadow:0 0 0 3px rgba(31,125,255,.12); background:#fff; }
textarea { min-height:74px; resize:vertical; }
.service-display { padding:11px 13px; border:1px solid #c8e0ff; border-radius:10px; background:#fff; color:#18365f; font-size:13px; }
#assistanceTypeGroup { grid-column:1 / -1 !important; }
#blotterFields { grid-column:1 / -1; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0 16px; padding:0; border:1px solid #e0edff; background:#fbfdff; }
#blotterFields .form-group { border:0; }
#blotterFields .form-group:nth-child(2), #blotterFields .form-group:nth-child(4) { grid-column:1 / -1; }
#purposeGroup, #additionalDetailsGroup { border-bottom:1px solid #e0edff; }
#purposeGroup { border-left:1px solid #e0edff; }
#additionalDetailsGroup { border-right:1px solid #e0edff; }
#additionalDetailsGroup textarea { min-height:42px; }
.field-hint { margin-top:4px; font-size:11px; }
.error-message { grid-column:1 / -1; padding:12px 14px; margin:0 0 12px; border-radius:10px; }
.submit-button {
    grid-column:1 / -1; display:flex; align-items:center; justify-content:center; gap:10px;
    margin:8px 2px 0; padding:14px 18px; border-radius:11px;
    background:linear-gradient(110deg,#087bf8,#0752d9); color:white;
    box-shadow:0 6px 15px rgba(0,91,220,.18); font-size:14px; font-weight:850;
    transition:transform .18s ease, box-shadow .18s ease, filter .18s ease;
}
.submit-button::before { content:"\f1d8"; font-family:"Font Awesome 6 Free"; font-weight:900; }
.submit-button:hover { background:linear-gradient(110deg,#066de3,#0648c3); transform:translateY(-1px); box-shadow:0 9px 20px rgba(0,91,220,.23); }
.submit-button:disabled { background:#94a3b8; box-shadow:none; }
.submit-help { grid-column:1 / -1; margin:7px 0 0; font-size:11px; }
.privacy-note { grid-column:1 / -1; margin-top:9px; padding:10px 12px; border:1px solid #dce9fa; border-radius:10px; background:#f5f9ff; font-size:11.5px; }
footer { margin-top:auto; }
@media (max-width: 1120px) {
    .page-container { width:calc(100% - 36px); }
    .request-layout { grid-template-columns:270px minmax(0,1fr); gap:14px; }
    .request-sidebar { padding:22px 16px; }
    .request-sidebar h1 { font-size:25px; }
    .request-card { padding:18px 16px 12px; }
    .request-header h2 { font-size:22px; }
    .track-link { font-size:11px; padding:8px 10px; }
}
@media (max-width: 850px) {
    .page-container { width:calc(100% - 28px); margin-top:18px; }
    .request-layout { grid-template-columns:1fr; }
    .request-sidebar { padding:17px 18px; border-radius:17px; }
    .sidebar-art { width:88px; height:76px; margin:0 auto 10px; font-size:46px; }
    .sidebar-art::before { width:68px; height:68px; border-radius:20px; }
    .sidebar-art i { font-size:46px; }
    .request-sidebar h1 { font-size:22px; }
    .request-sidebar .sidebar-description { max-width:620px; }
    .sidebar-divider { margin:12px 0; }
    .progress-list { grid-template-columns:repeat(2,minmax(0,1fr)); gap:6px; }
    .progress-step { grid-template-columns:36px 22px minmax(0,1fr); gap:7px; padding:7px; }
    .step-icon { width:34px; height:34px; font-size:14px; }
    .step-copy strong { font-size:11.5px; }
    .step-copy span { font-size:10px; }
    .reminder-card { margin-top:12px; }
}
@media (max-width: 600px) {
    .page-container { width:calc(100% - 20px); margin:13px auto 22px; }
    .back-link { margin:0 0 11px 5px; font-size:13px; }
    .request-card { padding:15px 11px 11px; border-radius:16px; }
    .sidebar-art { width:70px; height:62px; margin:0 auto 8px; }
    .sidebar-art::before { width:54px; height:54px; border-radius:16px; }
    .sidebar-art i { font-size:38px; }
    .request-header-top { align-items:flex-start; flex-direction:column; gap:12px; }
    .request-header-copy { gap:8px; }
    .request-header-copy::before { flex-basis:30px; width:30px; font-size:25px; }
    .request-header h2 { font-size:20px; }
    .request-header p { font-size:12px; }
    .track-link { align-self:flex-start; }
    .request-card form { grid-template-columns:repeat(2,minmax(0,1fr)); column-gap:8px; }
    .form-section-title { min-height:46px; padding:8px; gap:9px; font-size:13px; border-radius:12px 12px 0 0; }
    .form-section-title::before { width:32px; height:32px; flex-basis:32px; font-size:14px; }
    .form-group { padding:7px 7px 8px; }
    .form-row .form-group { grid-column:span 1; }
    .request-card form > .form-group { grid-column:span 2; }
    .request-card form > .form-section-title + .form-group { grid-column:1 / -1; }
    .request-card form > .form-section-title:nth-of-type(2) ~ .form-row .form-group { grid-column:span 1; }
    .request-card form > .form-section-title:nth-of-type(3) ~ .form-row .form-group { grid-column:span 1; }
    #blotterFields { grid-template-columns:1fr; }
    #blotterFields .form-group, #blotterFields .form-group:nth-child(n) { grid-column:1 / -1; }
    label { font-size:11.5px; }
    input, select, textarea { padding:10px 9px; font-size:12px; }
    .progress-list { grid-template-columns:1fr; }
    .progress-step { grid-template-columns:38px 23px minmax(0,1fr); }
    .step-copy strong { font-size:12px; }
    .step-copy span { font-size:10.5px; }
}
@media (prefers-reduced-motion: reduce) {
    html { scroll-behavior:auto; }
    *, *::before, *::after { transition:none !important; animation:none !important; }
}


/* =====================================================
   BONGDO — FORM ICON DESIGN
   Matching the supplied Online Service Request images
===================================================== */

/* Section header icons */
.form-section-title {
    position: relative;
    display: flex;
    align-items: center;
    gap: 12px;
}

.form-section-title::before {
    content: "\f15c";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    font-size: 17px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    width: 40px;
    height: 40px;
    flex: 0 0 40px;

    border-radius: 50%;
    color: #0873f5;
    background: #d8eaff;
}

/* Service Details — blue */
.form-section-title:nth-of-type(1)::before {
    content: "\f15c";
    color: #0873f5;
    background: #d8eaff;
}

/* Resident Information — green */
.form-section-title:nth-of-type(2)::before {
    content: "\f2bd";
    color: #08a85a;
    background: #d5f8e7;
}

/* Contact Information — orange */
.form-section-title:nth-of-type(3)::before {
    content: "\f095";
    color: #f59e0b;
    background: #fff0c7;
}

/* Purpose and Additional Details — purple */
.form-section-title:nth-of-type(4)::before {
    content: "\f15c";
    color: #6551e8;
    background: #dfdcff;
}

/* =====================================================
   INPUT FIELD ICONS
===================================================== */

.form-group {
    position: relative;
}

.form-group:has(
    #first_name,
    #middle_name,
    #last_name,
    #respondent
) input,
.form-group:has(
    #birth_date,
    #incident_date
) input,
.form-group:has(
    #gender,
    #civil_status,
    #suffix,
    #service_type,
    #assistance_type
) select,
.form-group:has(
    #address,
    #contact_number,
    #email,
    #purpose
) input,
.form-group:has(
    #additional_details,
    #incident,
    #blotter_remarks
) textarea {
    padding-left: 40px;
}

/* Default field icon positioning */
.form-group:has(
    #first_name,
    #middle_name,
    #last_name,
    #respondent,
    #birth_date,
    #incident_date,
    #gender,
    #civil_status,
    #suffix,
    #service_type,
    #assistance_type,
    #address,
    #contact_number,
    #email,
    #purpose,
    #additional_details,
    #incident,
    #blotter_remarks
)::after {
    position: absolute;
    left: 22px;
    top: 39px;

    display: flex;
    align-items: center;
    justify-content: center;

    width: 16px;
    height: 18px;

    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    font-size: 15px;

    color: #7187a6;
    pointer-events: none;
}

/* Resident name icons */
.form-group:has(#first_name)::after,
.form-group:has(#middle_name)::after,
.form-group:has(#last_name)::after,
.form-group:has(#respondent)::after {
    content: "\f007";
}

/* Birth date */
.form-group:has(#birth_date)::after,
.form-group:has(#incident_date)::after {
    content: "\f073";
}

/* Gender */
.form-group:has(#gender)::after {
    content: "\f228";
}

/* Civil status */
.form-group:has(#civil_status)::after {
    content: "\f004";
}

/* Suffix */
.form-group:has(#suffix)::after {
    content: "\f521";
}

/* Service requested */
.form-group:has(#service_type)::after,
.form-group:has(#assistance_type)::after {
    content: "\f15c";
}

/* Address */
.form-group:has(#address)::after {
    content: "\f015";
}

/* Contact number */
.form-group:has(#contact_number)::after {
    content: "\f095";
}

/* Email */
.form-group:has(#email)::after {
    content: "\f0e0";
}

/* Purpose and additional details */
.form-group:has(#purpose)::after,
.form-group:has(#additional_details)::after,
.form-group:has(#incident)::after,
.form-group:has(#blotter_remarks)::after {
    content: "\f15c";
}

/* Focused field icon */
.form-group:has(
    input:focus,
    select:focus,
    textarea:focus
)::after {
    color: #0878f9;
}

/* Sidebar progress icons */
.step-icon {
    width: 46px;
    height: 46px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: #e0e9f5;
    color: #637b9e;
    font-size: 19px;
    transition:
        background .2s ease,
        color .2s ease,
        transform .2s ease;
}

.progress-step.active .step-icon {
    background: linear-gradient(145deg, #1186ff, #0757df);
    color: #fff;
    box-shadow: 0 5px 12px rgba(0, 94, 229, .22);
}

.progress-step:hover .step-icon {
    transform: translateY(-2px);
}

/* Mobile adjustments */
@media (max-width: 600px) {
    .form-section-title::before {
        width: 32px;
        height: 32px;
        flex-basis: 32px;
        font-size: 14px;
    }

    .form-group:has(
        #first_name,
        #middle_name,
        #last_name,
        #respondent,
        #birth_date,
        #incident_date,
        #gender,
        #civil_status,
        #suffix,
        #service_type,
        #assistance_type,
        #address,
        #contact_number,
        #email,
        #purpose,
        #additional_details,
        #incident,
        #blotter_remarks
    )::after {
        left: 18px;
        top: 38px;
        font-size: 13px;
    }

    .form-group:has(
        #first_name,
        #middle_name,
        #last_name,
        #respondent,
        #birth_date,
        #incident_date,
        #gender,
        #civil_status,
        #suffix,
        #service_type,
        #assistance_type,
        #address,
        #contact_number,
        #email,
        #purpose,
        #additional_details,
        #incident,
        #blotter_remarks
    ) input,
    .form-group:has(
        #gender,
        #civil_status,
        #suffix,
        #service_type,
        #assistance_type
    ) select,
    .form-group:has(
        #additional_details,
        #incident,
        #blotter_remarks
    ) textarea {
        padding-left: 34px;
    }
}


/* =====================================================
   BONGDO — FORM ICON DESIGN
   Matching the supplied Online Service Request images
===================================================== */

/* Section header icons */
.form-section-title {
    position: relative;
    display: flex;
    align-items: center;
    gap: 12px;
}

.form-section-title::before {
    content: "\f15c";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    font-size: 17px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    width: 40px;
    height: 40px;
    flex: 0 0 40px;

    border-radius: 50%;
    color: #0873f5;
    background: #d8eaff;
}

/* Service Details — blue */
.form-section-title:nth-of-type(1)::before {
    content: "\f15c";
    color: #0873f5;
    background: #d8eaff;
}

/* Resident Information — green */
.form-section-title:nth-of-type(2)::before {
    content: "\f2bd";
    color: #08a85a;
    background: #d5f8e7;
}

/* Contact Information — orange */
.form-section-title:nth-of-type(3)::before {
    content: "\f095";
    color: #f59e0b;
    background: #fff0c7;
}

/* Purpose and Additional Details — purple */
.form-section-title:nth-of-type(4)::before {
    content: "\f15c";
    color: #6551e8;
    background: #dfdcff;
}

/* =====================================================
   INPUT FIELD ICONS
===================================================== */

.form-group {
    position: relative;
}

.form-group:has(
    #first_name,
    #middle_name,
    #last_name,
    #respondent
) input,
.form-group:has(
    #birth_date,
    #incident_date
) input,
.form-group:has(
    #gender,
    #civil_status,
    #suffix,
    #service_type,
    #assistance_type
) select,
.form-group:has(
    #address,
    #contact_number,
    #email,
    #purpose
) input,
.form-group:has(
    #additional_details,
    #incident,
    #blotter_remarks
) textarea {
    padding-left: 40px;
}

/* Default field icon positioning */
.form-group:has(
    #first_name,
    #middle_name,
    #last_name,
    #respondent,
    #birth_date,
    #incident_date,
    #gender,
    #civil_status,
    #suffix,
    #service_type,
    #assistance_type,
    #address,
    #contact_number,
    #email,
    #purpose,
    #additional_details,
    #incident,
    #blotter_remarks
)::after {
    position: absolute;
    left: 22px;
    top: 39px;

    display: flex;
    align-items: center;
    justify-content: center;

    width: 16px;
    height: 18px;

    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    font-size: 15px;

    color: #7187a6;
    pointer-events: none;
}

/* Resident name icons */
.form-group:has(#first_name)::after,
.form-group:has(#middle_name)::after,
.form-group:has(#last_name)::after,
.form-group:has(#respondent)::after {
    content: "\f007";
}

/* Birth date */
.form-group:has(#birth_date)::after,
.form-group:has(#incident_date)::after {
    content: "\f073";
}

/* Gender */
.form-group:has(#gender)::after {
    content: "\f228";
}

/* Civil status */
.form-group:has(#civil_status)::after {
    content: "\f004";
}

/* Suffix */
.form-group:has(#suffix)::after {
    content: "\f521";
}

/* Service requested */
.form-group:has(#service_type)::after,
.form-group:has(#assistance_type)::after {
    content: "\f15c";
}

/* Address */
.form-group:has(#address)::after {
    content: "\f015";
}

/* Contact number */
.form-group:has(#contact_number)::after {
    content: "\f095";
}

/* Email */
.form-group:has(#email)::after {
    content: "\f0e0";
}

/* Purpose and additional details */
.form-group:has(#purpose)::after,
.form-group:has(#additional_details)::after,
.form-group:has(#incident)::after,
.form-group:has(#blotter_remarks)::after {
    content: "\f15c";
}

/* Focused field icon */
.form-group:has(
    input:focus,
    select:focus,
    textarea:focus
)::after {
    color: #0878f9;
}

/* Sidebar progress icons */
.step-icon {
    width: 46px;
    height: 46px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: #e0e9f5;
    color: #637b9e;
    font-size: 19px;
    transition:
        background .2s ease,
        color .2s ease,
        transform .2s ease;
}

.progress-step.active .step-icon {
    background: linear-gradient(145deg, #1186ff, #0757df);
    color: #fff;
    box-shadow: 0 5px 12px rgba(0, 94, 229, .22);
}

.progress-step:hover .step-icon {
    transform: translateY(-2px);
}


/* Form heading — same document-with-pencil icon used in the sidebar */
.request-header-copy::before {
    content: "\f573"; /* Font Awesome: file-signature */
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    display: grid;
    place-items: center;
    width: 40px;
    height: 42px;
    flex: 0 0 40px;
    color: #0877f9;
    font-size: 29px;
    line-height: 1;
}


/* Mobile adjustments */
@media (max-width: 600px) {
    .form-section-title::before {
        width: 32px;
        height: 32px;
        flex-basis: 32px;
        font-size: 14px;
    }

    .form-group:has(
        #first_name,
        #middle_name,
        #last_name,
        #respondent,
        #birth_date,
        #incident_date,
        #gender,
        #civil_status,
        #suffix,
        #service_type,
        #assistance_type,
        #address,
        #contact_number,
        #email,
        #purpose,
        #additional_details,
        #incident,
        #blotter_remarks
    )::after {
        left: 18px;
        top: 38px;
        font-size: 13px;
    }

    .form-group:has(
        #first_name,
        #middle_name,
        #last_name,
        #respondent,
        #birth_date,
        #incident_date,
        #gender,
        #civil_status,
        #suffix,
        #service_type,
        #assistance_type,
        #address,
        #contact_number,
        #email,
        #purpose,
        #additional_details,
        #incident,
        #blotter_remarks
    ) input,
    .form-group:has(
        #gender,
        #civil_status,
        #suffix,
        #service_type,
        #assistance_type
    ) select,
    .form-group:has(
        #additional_details,
        #incident,
        #blotter_remarks
    ) textarea {
        padding-left: 34px;
    }
}

</style>

</head>

<body>


<?php
// Identify the current page for navbar active states.
$current_view = 'services';
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
?>

<?php require __DIR__ . '/includes/navbar.php'; ?>

<main class="page-container">

    <a href="index.php?view=services" class="back-link">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Services
    </a>

    <div class="request-layout">
        <aside class="request-sidebar" aria-label="Service request steps">
            <div class="sidebar-inner">
                <div class="sidebar-art" aria-hidden="true">
                    <i class="fa-solid fa-file-signature"></i>
                </div>
                <h1>Online Service Request</h1>
                <p class="sidebar-description">Complete the form below to request a barangay certificate or service. Fields marked <span class="required">*</span> are required.</p>
                <div class="sidebar-divider"></div>
                <div class="progress-list">
                    <div class="progress-step active"><div class="step-icon"><i class="fa-solid fa-file-lines"></i></div><span class="step-number">1</span><div class="step-copy"><strong>Service Details</strong><span>Select the service you need</span></div></div>
                    <div class="progress-step"><div class="step-icon"><i class="fa-solid fa-user"></i></div><span class="step-number">2</span><div class="step-copy"><strong>Resident Information</strong><span>Provide your personal details</span></div></div>
                    <div class="progress-step"><div class="step-icon"><i class="fa-solid fa-phone"></i></div><span class="step-number">3</span><div class="step-copy"><strong>Contact Information</strong><span>How can we reach you?</span></div></div>
                    <div class="progress-step"><div class="step-icon"><i class="fa-solid fa-file-alt"></i></div><span class="step-number">4</span><div class="step-copy"><strong>Purpose and Details</strong><span>Additional information</span></div></div>
                </div>
                <div class="reminder-card"><div class="reminder-icon"><i class="fa-solid fa-info"></i></div><div><strong>Important Reminder</strong><p>Please provide accurate information. Your details will be used only for processing your barangay service request.</p></div></div>
            </div>
        </aside>

    <section class="request-card">

        <div class="request-header">
            <div class="request-header-top">
                <div class="request-header-copy">
                    <h2>Online Service Request</h2>
                    <p>Complete the form below to request a barangay certificate or service. Fields marked <span class="required">*</span> are required.</p>
                </div>
                <a class="track-link" href="track_request.php">Track an existing request →</a>
            </div>
        </div>


        <?php if ($error !== ""): ?>

            <div class="error-message">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </div>

        <?php endif; ?>


        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION["service_request_csrf"],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <!-- Anti-bot honeypot: hidden from normal users. -->
            <div
                aria-hidden="true"
                style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;"
            >
                <label for="website">Website</label>
                <input
                    type="text"
                    id="website"
                    name="website"
                    tabindex="-1"
                    autocomplete="off"
                >
            </div>


            <h3 class="form-section-title">Service Details</h3>

            <?php if ($serviceType !== ""): ?>

                <input
                    type="hidden"
                    name="service_type"
                    value="<?= htmlspecialchars(
                        $serviceType,
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                >

            <div class="form-group">

                    <label>
                        Service Requested
                    </label>

                    <div class="service-display">

                        <?= htmlspecialchars(
                            $serviceType,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>

                    </div>

                </div>

            <?php else: ?>

                <div class="form-group">

                    <label for="service_type">
                        Service Requested
                        <span class="required">*</span>
                    </label>

                    <select
                        id="service_type"
                        name="service_type"
                        required
                    >

                            <option value="" disabled <?= $serviceType === "" ? "selected" : "" ?>>
                            Select service
                        </option>

                        <?php foreach ($allowedServices as $service): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $service,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                <?= $serviceType === $service ? "selected" : "" ?>
                            >
                                <?= htmlspecialchars(
                                    $service,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            <?php endif; ?>

            <div
                class="form-group"
                id="assistanceTypeGroup"
                style="<?= $serviceType === "Community Assistance" ? "" : "display:none;" ?>"
            >
                <label for="assistance_type">
                    Type of Assistance
                    <span class="required">*</span>
                </label>

                <select
                    id="assistance_type"
                    name="assistance_type"
                    <?= $serviceType === "Community Assistance" ? "required" : "disabled" ?>
                >
                    <option value="" disabled
                        <?= empty($_POST["assistance_type"] ?? "") ? "selected" : "" ?>>
                        Select Assistance Type
                    </option>

                    <option value="Medical Assistance"
                        <?= ($_POST["assistance_type"] ?? "") === "Medical Assistance" ? "selected" : "" ?>>
                        Medical Assistance
                    </option>

                    <option value="Financial Assistance"
                        <?= ($_POST["assistance_type"] ?? "") === "Financial Assistance" ? "selected" : "" ?>>
                        Financial Assistance
                    </option>

                    <option value="Food Assistance"
                        <?= ($_POST["assistance_type"] ?? "") === "Food Assistance" ? "selected" : "" ?>>
                        Food Assistance
                    </option>

                    <option value="Educational Assistance"
                        <?= ($_POST["assistance_type"] ?? "") === "Educational Assistance" ? "selected" : "" ?>>
                        Educational Assistance
                    </option>

                    <option value="Burial/Funeral Assistance"
                        <?= ($_POST["assistance_type"] ?? "") === "Burial/Funeral Assistance" ? "selected" : "" ?>>
                        Burial/Funeral Assistance
                    </option>

                    <option value="Emergency Assistance"
                        <?= ($_POST["assistance_type"] ?? "") === "Emergency Assistance" ? "selected" : "" ?>>
                        Emergency Assistance
                    </option>

                    <option value="PWD Assistance"
                        <?= ($_POST["assistance_type"] ?? "") === "PWD Assistance" ? "selected" : "" ?>>
                        PWD Assistance
                    </option>

                    <option value="Senior Citizen Assistance"
                        <?= ($_POST["assistance_type"] ?? "") === "Senior Citizen Assistance" ? "selected" : "" ?>>
                        Senior Citizen Assistance
                    </option>

                    <option value="Solo Parent Assistance"
                        <?= ($_POST["assistance_type"] ?? "") === "Solo Parent Assistance" ? "selected" : "" ?>>
                        Solo Parent Assistance
                    </option>

                    <option value="Livelihood Assistance"
                        <?= ($_POST["assistance_type"] ?? "") === "Livelihood Assistance" ? "selected" : "" ?>>
                        Livelihood Assistance
                    </option>

                    <option value="Other"
                        <?= ($_POST["assistance_type"] ?? "") === "Other" ? "selected" : "" ?>>
                        Other
                    </option>
                </select>
            </div>


            <!-- PERSONAL INFORMATION -->

            <h3 class="form-section-title">Resident Information</h3>

            <div class="form-row">

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
                        autocomplete="given-name"
                        value="<?= htmlspecialchars(
                            $_POST["first_name"] ?? "",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="Enter first name"
                    >
                </div>


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
                        autocomplete="additional-name"
                        value="<?= htmlspecialchars(
                            $_POST["middle_name"] ?? "",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="Enter middle name"
                    >
                </div>

            </div>

            <div class="form-row">

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
                        autocomplete="family-name"
                        value="<?= htmlspecialchars(
                            $_POST["last_name"] ?? "",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="Enter last name"
                    >
                </div>


                <div class="form-group">
                    <label for="suffix">
                        Suffix
                    </label>

                    <select id="suffix" name="suffix">

                        <option value="">None</option>

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
                                value="<?= htmlspecialchars(
                                    $value,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                <?= (($_POST["suffix"] ?? "") === $value)
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= htmlspecialchars($value) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>

            </div>


            <div class="form-row">

                <div class="form-group">
                    <label for="birth_date">
                        Birth Date
                    </label>

                    <input
                        type="date"
                        id="birth_date"
                        name="birth_date"
                        max="<?= date("Y-m-d") ?>"
                        value="<?= htmlspecialchars(
                            $_POST["birth_date"] ?? "",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >
                </div>


                <div class="form-group">
                    <label for="gender">
                        Gender <span class="required">*</span>
                    </label>

                    <select
                        id="gender"
                        name="gender"
                        required
                    >

                        <option
                            value=""
                            disabled
                            <?= empty($_POST["gender"] ?? "")
                                ? "selected"
                                : "" ?>
                        >
                            Select Gender
                        </option>

                        <option
                            value="Male"
                            <?= ($_POST["gender"] ?? "") === "Male"
                                ? "selected"
                                : "" ?>
                        >
                            Male
                        </option>

                        <option
                            value="Female"
                            <?= ($_POST["gender"] ?? "") === "Female"
                                ? "selected"
                                : "" ?>
                        >
                            Female
                        </option>

                    </select>
                </div>

            </div>


            <div class="form-row">

                <div class="form-group">
                    <label for="civil_status">
                        Civil Status <span class="required">*</span>
                    </label>

                    <select
                        id="civil_status"
                        name="civil_status"
                        required
                    >

                        <option
                            value=""
                            disabled
                            <?= empty($_POST["civil_status"] ?? "")
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

                        foreach ($civilStatuses as $status):
                        ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $status,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>"
                                <?= (($_POST["civil_status"] ?? "") === $status)
                                    ? "selected"
                                    : "" ?>
                            >
                                <?= htmlspecialchars($status) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </div>


                <div class="form-group">
                    <label for="address">
                        Complete Address <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="address"
                        name="address"
                        minlength="5"
                        maxlength="255"
                        autocomplete="street-address"
                        required
                        value="<?= htmlspecialchars(
                            $_POST["address"] ?? "",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="House No., Purok, Barangay"
                    >
                </div>

            </div>



                <div id="blotterFields" style="display:none;">

                    <div class="form-group">

                        <label for="respondent">
                            Respondent
                        </label>

                        <input
                            type="text"
                            id="respondent"
                            name="respondent"
                            maxlength="150"
                            pattern="[A-Za-zÀ-ÿÑñ' -]+"
                            title="Please enter letters only."
                            value="<?= htmlspecialchars(
                                $_POST["respondent"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                            placeholder="Enter respondent's complete name"
                        >

                    </div>


                    <div class="form-group">

                        <label for="incident">
                            Incident <span class="required">*</span>
                        </label>

                        <textarea
                            id="incident"
                            name="incident"
                            maxlength="500"
                            placeholder="Describe what happened..."
                        ><?= htmlspecialchars(
                            $_POST["incident"] ?? "",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?></textarea>

                    </div>


                    <div class="form-group">

                        <label for="incident_date">
                            Incident Date
                        </label>

                        <input
                            type="date"
                            id="incident_date"
                            name="incident_date"
                            max="<?= date("Y-m-d") ?>"
                            value="<?= htmlspecialchars(
                                $_POST["incident_date"] ?? "",
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>"
                        >

                    </div>


                    <div class="form-group">

                        <label for="blotter_remarks">
                            Remarks
                        </label>

                        <textarea
                            id="blotter_remarks"
                            name="blotter_remarks"
                            maxlength="2000"
                            placeholder="Enter remarks or additional information"
                        ><?= htmlspecialchars(
                            $_POST["blotter_remarks"] ?? "",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?></textarea>

                    </div>

                </div>


            <h3 class="form-section-title">Contact Information</h3>

            <div class="form-row">

                <div class="form-group">

                    <label for="contact_number">
                        Contact Number
                        <span class="required">*</span>
                    </label>

                    <input
                        type="tel"
                        id="contact_number"
                        name="contact_number"
                        maxlength="11"
                        minlength="11"
                        pattern="09[0-9]{9}"
                        inputmode="numeric"
                        autocomplete="tel"
                        required
                        value="<?= htmlspecialchars(
                            $_POST["contact_number"] ?? "",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="09XXXXXXXXX"
                    >
                    <small class="field-hint">Use an active Philippine mobile number for updates.</small>

                </div>


                <div class="form-group">

                    <label for="email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="150"
                        autocomplete="email"
                        value="<?= htmlspecialchars(
                            $_POST["email"] ?? "",
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="Optional"
                    >

                </div>

            </div>


            <div class="form-group" id="purposeGroup">

                <label for="purpose">
                    Purpose
                    <span class="required">*</span>
                </label>

                <input
                    type="text"
                    id="purpose"
                    name="purpose"
                    minlength="3"
                    maxlength="255"
                    value="<?= htmlspecialchars(
                        $_POST["purpose"] ?? "",
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>"
                    placeholder="Example: Employment requirement"
                >

            </div>

            <input
                type="hidden"
                id="blotterPurpose"
                name="blotter_purpose"
                value="Blotter Assistance"
            >


            <div class="form-group" id="additionalDetailsGroup">

                <label for="additional_details" id="additionalDetailsLabel">
                    <?= $serviceType === "Blotter Assistance"
                        ? "Additional Information"
                        : "Additional Details" ?>
                </label>

                <textarea
                    id="additional_details"
                    name="additional_details"
                    maxlength="2000"
                    placeholder="Enter additional information if necessary"
                ><?= htmlspecialchars(
                    $_POST["additional_details"] ?? "",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?></textarea>

            </div>


            <button
                type="submit"
                class="submit-button"
            >
                Submit Request
            </button>

            <p class="submit-help">After submission, keep your reference number to check the request status.</p>


            <div class="privacy-note">

                🔒 Please provide accurate information.
                Your details will be used only for processing
                your barangay service request.

            </div>

        </form>

    </section>
    </div><!-- /.request-layout -->

</main>


<footer>

    © <?= date("Y") ?>
    Barangay Management System.
    All rights reserved.

</footer>


<script>
(function () {

    const serviceSelect = document.getElementById("service_type");
    const serviceHidden = document.querySelector('input[name="service_type"]');

    const blotterFields = document.getElementById("blotterFields");

    const respondentInput = document.getElementById("respondent");
    const incidentInput = document.getElementById("incident");
    const incidentDateInput = document.getElementById("incident_date");

    const purposeGroup = document.getElementById("purposeGroup");
    const purposeInput = document.getElementById("purpose");

    const assistanceTypeGroup =
        document.getElementById("assistanceTypeGroup");

    const assistanceTypeInput =
        document.getElementById("assistance_type");

    const additionalDetailsGroup =
        document.getElementById("additionalDetailsGroup");

    const additionalDetailsInput =
        document.getElementById("additional_details");

    const additionalDetailsLabel =
        document.getElementById("additionalDetailsLabel");


    function updateServiceFields() {

        const selectedService = serviceSelect
            ? serviceSelect.value
            : (serviceHidden ? serviceHidden.value : "");

        const isCommunityAssistance =
            selectedService === "Community Assistance";

        if (assistanceTypeGroup && assistanceTypeInput) {
            if (isCommunityAssistance) {

                assistanceTypeGroup.style.display = "block";

                assistanceTypeInput.disabled = false;

                assistanceTypeInput.required = true;

            } else {

                assistanceTypeGroup.style.display = "none";

                assistanceTypeInput.disabled = true;

                assistanceTypeInput.required = false;

            }
        }

        if (!selectedService) {
            return;
        }

        const isBlotter =
            selectedService === "Blotter Assistance";


        if (isBlotter) {

            if (additionalDetailsGroup) {
                additionalDetailsGroup.style.display = "none";
            }

            if (additionalDetailsInput) {
                additionalDetailsInput.disabled = true;
            }

            /* Show Blotter fields */
            if (blotterFields) {
                blotterFields.style.display = "block";
            }


            /* Hide Purpose */
            if (purposeGroup) {
                purposeGroup.style.display = "none";
            }

            if (purposeInput) {
                purposeInput.disabled = true;
                purposeInput.required = false;
                purposeInput.value = "Blotter Assistance";
            }


            /* Blotter-specific fields */
            if (incidentInput) {
                incidentInput.disabled = false;
                incidentInput.required = true;
            }

            if (respondentInput) {
                respondentInput.disabled = false;
            }

            if (incidentDateInput) {
                incidentDateInput.disabled = false;
            }


        } else {

            if (additionalDetailsGroup) {
                additionalDetailsGroup.style.display = "block";
            }

            if (additionalDetailsInput) {
                additionalDetailsInput.disabled = false;
            }

            /* Hide Blotter fields */
            if (blotterFields) {
                blotterFields.style.display = "none";
            }


            /* Show Purpose */
            if (purposeGroup) {
                purposeGroup.style.display = "block";
            }

            if (purposeInput) {
                purposeInput.disabled = false;
                purposeInput.required = true;

                if (purposeInput.value === "Blotter Assistance") {
                    purposeInput.value = "";
                }
            }


            /* Disable Blotter-specific fields */
            if (incidentInput) {
                incidentInput.disabled = true;
                incidentInput.required = false;
            }

            if (respondentInput) {
                respondentInput.disabled = true;
            }

            if (incidentDateInput) {
                incidentDateInput.disabled = true;
            }

        }

    }


    /* When user changes Service Requested */
    if (serviceSelect) {

        serviceSelect.addEventListener(
            "change",
            updateServiceFields
        );

    }


    /* Run immediately when page loads */
    updateServiceFields();

})();
</script>


<script>
const contactInput = document.getElementById("contact_number");

if (contactInput) {
    contactInput.addEventListener("input", function () {
        this.value = this.value.replace(/\D/g, "").slice(0, 11);
    });
}
</script>

<script>
(function () {

    const form = document.querySelector("form");

    const submitButton =
        document.querySelector(".submit-button");


    if (!form) {
        return;
    }


    function updateRequestAvailability() {

        if (!submitButton) {
            return;
        }


        if (navigator.onLine) {

            submitButton.disabled = false;

            submitButton.textContent =
                "Submit Request";

            submitButton.title = "";


        } else {

            submitButton.disabled = true;

            submitButton.textContent =
                "🔴 Internet Required";

            submitButton.title =
                "Internet connection is required to submit an online service request.";

        }

    }


    form.addEventListener(
        "submit",
        function (event) {

            if (!navigator.onLine) {

                event.preventDefault();

                alert(
                    "Internet connection is unavailable. Your online service request cannot be submitted right now. Please reconnect to the Internet and try again."
                );

                return false;
            }

        }
    );


    window.addEventListener(
        "online",
        updateRequestAvailability
    );


    window.addEventListener(
        "offline",
        updateRequestAvailability
    );


    updateRequestAvailability();

})();
</script>

</body>

</html>

