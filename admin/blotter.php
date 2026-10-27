
<?php

// Admin authentication, role check, ug session timeout.
require_once __DIR__ . "/../config/admin_auth.php";

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/audit.php";


$message = "";
$messageType = "";

/* =========================
   CSRF PROTECTION
========================= */

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];

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
   ALLOWED STATUS VALUES
========================= */

$allowedStatuses = [
    "Pending",
    "Under Investigation",
    "Resolved",
    "Dismissed"
];

/* =========================
   DELETE BLOTTER RECORD
   POST + CSRF PROTECTED
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_id"])
) {
    $id = (int) $_POST["delete_id"];
    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        $submittedToken === "" ||
        !hash_equals($csrf_token, $submittedToken)
    ) {
        $message = "Invalid security token. Please try again.";
        $messageType = "error";

    } elseif ($id <= 0) {

        $message = "Invalid blotter record.";
        $messageType = "error";

    } else {

        try {

            /*
             * START DATABASE TRANSACTION
             */
            $conn->begin_transaction();


            /*
             * CHECK IF THIS BLOTTER RECORD
             * IS LINKED TO AN ONLINE SERVICE REQUEST
             */

            $checkStmt = $conn->prepare("
                SELECT id
                FROM service_requests
                WHERE blotter_id = ?
                LIMIT 1
            ");

            if (!$checkStmt) {
                throw new Exception(
                    "Unable to check related service request."
                );
            }

            $checkStmt->bind_param("i", $id);
            $checkStmt->execute();

            $checkResult = $checkStmt->get_result();

            $hasServiceRequest =
                $checkResult->num_rows > 0;

            $checkStmt->close();


            /*
             * DELETE BLOTTER RECORD
             */

            $stmt = $conn->prepare("
                DELETE FROM blotter
                WHERE id = ?
            ");

            if (!$stmt) {
                throw new Exception(
                    "Unable to prepare blotter deletion."
                );
            }

            $stmt->bind_param("i", $id);

            if (!$stmt->execute()) {

                $stmt->close();

                throw new Exception(
                    "Unable to delete blotter record."
                );
            }

            /*
             * MAKE SURE A RECORD WAS ACTUALLY DELETED
             */
            if ($stmt->affected_rows !== 1) {

                $stmt->close();

                throw new Exception(
                    "Blotter record was not found."
                );
            }

            $stmt->close();


            /*
             * REMOVE THE LINK FROM
             * RELATED SERVICE REQUEST
             *
             * DO NOT DELETE THE SERVICE REQUEST.
             */

            if ($hasServiceRequest) {

                $unlinkStmt = $conn->prepare("
                    UPDATE service_requests
                    SET blotter_id = NULL
                    WHERE blotter_id = ?
                ");

                if (!$unlinkStmt) {
                    throw new Exception(
                        "Unable to prepare service request unlink."
                    );
                }

                $unlinkStmt->bind_param(
                    "i",
                    $id
                );

                if (!$unlinkStmt->execute()) {

                    $unlinkStmt->close();

                    throw new Exception(
                        "Unable to unlink the related service request."
                    );
                }

                $unlinkStmt->close();
            }


            /*
             * AUDIT LOG
             */

            addAuditLog(
                $conn,
                "DELETE",
                "Blotter",
                "Deleted blotter record.",
                $id
            );


            /*
             * COMMIT EVERYTHING
             */
            $conn->commit();


            $_SESSION["flash_message"] =
                "Blotter record deleted successfully.";

            $_SESSION["flash_type"] =
                "success";


            header(
                "Location: blotter.php#blotter-records"
            );

            exit;


        } catch (Throwable $e) {

            /*
             * ROLLBACK EVERYTHING
             */
            $conn->rollback();

            error_log("Blotter deletion failed: " . $e->getMessage());
            $message = "Unable to delete blotter record. Please try again.";

            $messageType =
                "error";
        }
    }
}

/* =========================
   ADD / UPDATE BLOTTER
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
        $message =
            "Invalid security token. Please refresh the page and try again.";
        $messageType = "error";
    } else {

        $id = isset($_POST["id"])
            ? (int) $_POST["id"]
            : 0;

        $complainant_type =
            trim($_POST["complainant_type"] ?? "Resident");

        $resident_id = isset($_POST["resident_id"])
            ? (int) $_POST["resident_id"]
            : 0;

        $complainant = trim(
            $_POST["complainant"] ?? ""
        );

        /* NON-RESIDENT INFORMATION */
        $complainant_first_name =
            trim($_POST["complainant_first_name"] ?? "");

        $complainant_middle_name =
            trim($_POST["complainant_middle_name"] ?? "");

        $complainant_last_name =
            trim($_POST["complainant_last_name"] ?? "");

        $complainant_suffix =
            trim($_POST["complainant_suffix"] ?? "");

        $complainant_birth_date =
            $_POST["complainant_birth_date"] ?? "";

        $complainant_gender =
            trim($_POST["complainant_gender"] ?? "");

        $complainant_civil_status =
            trim($_POST["complainant_civil_status"] ?? "");

        $complainant_address =
            trim($_POST["complainant_address"] ?? "");

        $complainant_contact_number =
            trim($_POST["complainant_contact_number"] ?? "");

        $respondent = trim(
            $_POST["respondent"] ?? ""
        );

        $incident = trim(
            $_POST["incident"] ?? ""
        );

        $incident_date =
            $_POST["incident_date"] ?? "";

        $status =
            $_POST["status"] ?? "Pending";

        $remarks = trim(
            $_POST["remarks"] ?? ""
        );


        if (
            !in_array(
                $complainant_type,
                ["Resident", "Non-Resident"],
                true
            )
        ) {
            $message = "Please select a valid complainant type.";
            $messageType = "error";

        } elseif ($complainant_type === "Resident") {

            /*
             * RESIDENT COMPLAINANT
             */

            if ($resident_id <= 0) {

                /*
                 * Allow existing legacy records to remain editable
                 * even if they do not have resident_id.
                 */
                if ($id === 0) {
                    $message = "Please select a resident.";
                    $messageType = "error";
                }

            } else {

                $residentStmt = $conn->prepare("
                    SELECT
                        first_name,
                        middle_name,
                        last_name,
                        suffix,
                        birth_date,
                        gender,
                        civil_status,
                        address,
                        contact_number
                    FROM residents
                    WHERE id = ?
                    LIMIT 1
                ");

                if (!$residentStmt) {

                    $message =
                        "Unable to verify selected resident.";
                    $messageType = "error";

                } else {

                    $residentStmt->bind_param(
                        "i",
                        $resident_id
                    );

                    $residentStmt->execute();

                    $residentResult =
                        $residentStmt->get_result();

                    $selectedResident =
                        $residentResult->fetch_assoc();

                    $residentStmt->close();

                    if (!$selectedResident) {

                        $message =
                            "Selected resident was not found.";
                        $messageType = "error";

                    } else {

                        /*
                         * When editing an existing blotter record, accept the
                         * edited complainant snapshot fields from this form.
                         * Do not update the master Residents record here.
                         * For new records, continue using the selected resident's
                         * saved details as the source of truth.
                         */
                        $useEditedResidentDetails =
                            isset($_POST["resident_edit_first_name"]);

                        $complainant_first_name = $useEditedResidentDetails
                            ? trim($_POST["resident_edit_first_name"] ?? "")
                            : ($selectedResident["first_name"] ?? "");

                        $complainant_middle_name = $useEditedResidentDetails
                            ? trim($_POST["resident_edit_middle_name"] ?? "")
                            : ($selectedResident["middle_name"] ?? "");

                        $complainant_last_name = $useEditedResidentDetails
                            ? trim($_POST["resident_edit_last_name"] ?? "")
                            : ($selectedResident["last_name"] ?? "");

                        $complainant_suffix = $useEditedResidentDetails
                            ? trim($_POST["resident_edit_suffix"] ?? "")
                            : ($selectedResident["suffix"] ?? "");

                        $complainant_birth_date = $useEditedResidentDetails
                            ? trim($_POST["resident_edit_birth_date"] ?? "")
                            : ($selectedResident["birth_date"] ?? "");

                        $complainant_gender = isset($_POST["resident_edit_gender"])
                            ? trim($_POST["resident_edit_gender"])
                            : ($selectedResident["gender"] ?? "");

                        $complainant_civil_status = isset($_POST["resident_edit_civil_status"])
                            ? trim($_POST["resident_edit_civil_status"])
                            : ($selectedResident["civil_status"] ?? "");

                        $complainant_address = $useEditedResidentDetails
                            ? trim($_POST["resident_edit_address"] ?? "")
                            : ($selectedResident["address"] ?? "");

                        $complainant_contact_number = $useEditedResidentDetails
                            ? trim($_POST["resident_edit_contact_number"] ?? "")
                            : ($selectedResident["contact_number"] ?? "");

                        $complainant = trim(
                            preg_replace(
                                '/\s+/u',
                                ' ',
                                implode(' ', array_filter([
                                    $complainant_first_name,
                                    $complainant_middle_name,
                                    $complainant_last_name,
                                    $complainant_suffix
                                ]))
                            )
                        );
                    }
                }
            }

        } else {

            /*
             * NON-RESIDENT COMPLAINANT
             */

            $resident_id = 0;

            $complainant = trim(
                preg_replace(
                    '/\s+/u',
                    ' ',
                    implode(' ', array_filter([
                        $complainant_first_name,
                        $complainant_middle_name,
                        $complainant_last_name,
                        $complainant_suffix
                    ]))
                )
            );
        }

        /* =========================
           SERVER-SIDE VALIDATION
        ========================= */

        if (
            $complainant_type === "Resident" &&
            isset($_POST["resident_edit_gender"]) &&
            !in_array($complainant_gender, ["", "Male", "Female"], true)
        ) {
            $message = "Please select a valid resident gender.";
            $messageType = "error";
        } elseif (
            $complainant_type === "Resident" &&
            isset($_POST["resident_edit_civil_status"]) &&
            !in_array($complainant_civil_status, ["", "Single", "Married", "Widowed", "Separated"], true)
        ) {
            $message = "Please select a valid resident civil status.";
            $messageType = "error";
        } elseif (
            $complainant_type === "Resident" &&
            isset($_POST["resident_edit_first_name"]) &&
            (
                $complainant_first_name === "" ||
                $complainant_last_name === ""
            )
        ) {
            $message = "First Name and Last Name are required.";
            $messageType = "error";

        } elseif ($complainant === "") {
            $message = "Complainant is required.";
            $messageType = "error";

        } elseif (
            $complainant_type === "Non-Resident" &&
            (
                $complainant_first_name === "" ||
                $complainant_last_name === ""
            )
        ) {
            $message =
                "First Name and Last Name are required for a non-resident complainant.";
            $messageType = "error";

        } elseif (
            $complainant_type === "Non-Resident" &&
            (
                ($complainant_first_name !== "" && !preg_match("/^[\\p{L}\\p{M}' -]+$/u", $complainant_first_name)) ||
                ($complainant_middle_name !== "" && !preg_match("/^[\\p{L}\\p{M}' -]+$/u", $complainant_middle_name)) ||
                ($complainant_last_name !== "" && !preg_match("/^[\\p{L}\\p{M}' -]+$/u", $complainant_last_name)) ||
                ($complainant_suffix !== "" && !preg_match("/^[A-Za-z0-9. -]+$/", $complainant_suffix))
            )
        ) {
            $message = "Name fields contain invalid characters.";
            $messageType = "error";

        } elseif (
            $complainant_type === "Non-Resident" &&
            $complainant_birth_date !== "" &&
            (
                !preg_match("/^\\d{4}-\\d{2}-\\d{2}$/", $complainant_birth_date) ||
                ($birthDateObject = DateTime::createFromFormat("!Y-m-d", $complainant_birth_date)) === false ||
                $birthDateObject->format("Y-m-d") !== $complainant_birth_date ||
                $complainant_birth_date > date("Y-m-d")
            )
        ) {
            $message = "Please enter a valid birth date that is not in the future.";
            $messageType = "error";

        } elseif (
            $complainant_type === "Resident" &&
            $complainant_contact_number !== "" &&
            !preg_match("/^09[0-9]{9}$/", $complainant_contact_number)
        ) {
            $message = "Contact number must start with 09 and contain exactly 11 digits.";
            $messageType = "error";

        } elseif (
            $complainant_type === "Non-Resident" &&
            $complainant_contact_number !== "" &&
            !preg_match("/^09[0-9]{9}$/", $complainant_contact_number)
        ) {
            $message = "Contact number must start with 09 and contain exactly 11 digits.";
            $messageType = "error";

        } elseif (
            $complainant_type === "Non-Resident" &&
            $complainant_gender !== "" &&
            !in_array($complainant_gender, ["Male", "Female"], true)
        ) {
            $message = "Please select a valid gender.";
            $messageType = "error";

        } elseif (
            $complainant_type === "Non-Resident" &&
            $complainant_civil_status !== "" &&
            !in_array($complainant_civil_status, ["Single", "Married", "Widowed", "Separated"], true)
        ) {
            $message = "Please select a valid civil status.";
            $messageType = "error";

        } elseif (
            !preg_match(
                "/^[\p{L}\p{M}'., -]+$/u",
                $complainant
            )
        ) {
            $message =
                "Complainant name must contain letters only.";
            $messageType = "error";

        } elseif (
            strlen($complainant) < 2 ||
            strlen($complainant) > 100
        ) {
            $message =
                "Complainant must be between 2 and 100 characters.";
            $messageType = "error";

        } elseif ($respondent === "") {
            $message = "Respondent is required.";
            $messageType = "error";

        } elseif (
            !preg_match(
                "/^[\p{L}\p{M}'., -]+$/u",
                $respondent
            )
        ) {
            $message =
                "Respondent name must contain letters only.";
            $messageType = "error";

        } elseif (
            strlen($respondent) > 100
        ) {
            $message =
                "Respondent must not exceed 100 characters.";
            $messageType = "error";

        } elseif ($incident === "") {
            $message = "Incident is required.";
            $messageType = "error";

        } elseif (
            strlen($incident) < 2 ||
            strlen($incident) > 500
        ) {
            $message =
                "Incident must be between 2 and 500 characters.";
            $messageType = "error";

        } elseif (
            !preg_match(
                "/^[A-Za-z0-9À-ÿÑñ'.,()\/&:;#%!?'\- ]+$/u",
                $incident
            )
        ) {
            $message =
                "Incident contains invalid characters.";
            $messageType = "error";

        } elseif (
            $incident_date !== "" &&
            (
                !preg_match(
                    "/^\d{4}-\d{2}-\d{2}$/",
                    $incident_date
                ) ||
                ($incidentDateObject = DateTime::createFromFormat("!Y-m-d", $incident_date)) === false ||
                $incidentDateObject->format("Y-m-d") !== $incident_date
            )
        ) {
            $message =
                "Please enter a valid incident date.";
            $messageType = "error";

        } elseif (
            $incident_date !== "" &&
            $incident_date > date("Y-m-d")
        ) {
            $message =
                "Incident date cannot be in the future.";
            $messageType = "error";

        } elseif (
            !in_array(
                $status,
                $allowedStatuses,
                true
            )
        ) {
            $message =
                "Please select a valid status.";
            $messageType = "error";

        } elseif (
            strlen($remarks) > 1000
        ) {
            $message =
                "Remarks must not exceed 1000 characters.";
            $messageType = "error";

        } elseif (
            $remarks !== "" &&
            !preg_match(
                "/^[A-Za-z0-9À-ÿÑñ'.,()\/&:;#%!?'\-\r\n ]+$/u",
                $remarks
            )
        ) {
            $message =
                "Remarks contain invalid characters.";
            $messageType = "error";
        }

        /* =========================
           SAVE ONLY IF VALID
        ========================= */

        if ($message === "") {

            if ($id > 0) {

                /* =========================
                   UPDATE WITH TRANSACTION
                ========================= */

                try {

                    $conn->begin_transaction();

                    /* UPDATE BLOTTER */
                    $stmt = $conn->prepare("
                        UPDATE blotter
                        SET
                            resident_id = NULLIF(?, 0),
                            complainant_type = ?,
                            complainant = ?,
                            complainant_first_name = ?,
                            complainant_middle_name = ?,
                            complainant_last_name = ?,
                            complainant_suffix = ?,
                            complainant_birth_date = NULLIF(?, ''),
                            complainant_gender = ?,
                            complainant_civil_status = ?,
                            complainant_address = ?,
                            complainant_contact_number = ?,
                            respondent = ?,
                            incident = ?,
                            incident_date = NULLIF(?, ''),
                            status = ?,
                            remarks = ?
                        WHERE id = ?
                    ");

                    $stmt->bind_param(
                        "issssssssssssssssi",
                        $resident_id,
                        $complainant_type,
                        $complainant,
                        $complainant_first_name,
                        $complainant_middle_name,
                        $complainant_last_name,
                        $complainant_suffix,
                        $complainant_birth_date,
                        $complainant_gender,
                        $complainant_civil_status,
                        $complainant_address,
                        $complainant_contact_number,
                        $respondent,
                        $incident,
                        $incident_date,
                        $status,
                        $remarks,
                        $id
                    );

                    if (!$stmt->execute()) {
                        throw new Exception(
                            "Unable to update blotter record."
                        );
                    }

                    $stmt->close();


                    /* =========================
                       SYNC SERVICE REQUEST
                    ========================= */

                    $serviceRequestStatusMap = [

                        "Pending" =>
                            "Pending",

                        "Under Investigation" =>
                            "Under Review",

                        "Resolved" =>
                            "Completed",

                        "Dismissed" =>
                            "Rejected"

                    ];

                    $serviceRequestStatus =
                        $serviceRequestStatusMap[$status] ?? null;


                    if ($serviceRequestStatus !== null) {

                        $syncStmt = $conn->prepare("
                            UPDATE service_requests
                            SET status = ?
                            WHERE blotter_id = ?
                        ");

                        if (!$syncStmt) {
                            throw new Exception(
                                "Unable to prepare service request status synchronization."
                            );
                        }

                        $syncStmt->bind_param(
                            "si",
                            $serviceRequestStatus,
                            $id
                        );

                        if (!$syncStmt->execute()) {

                            $syncStmt->close();

                            throw new Exception(
                                "Unable to synchronize the related service request status."
                            );
                        }

                        $syncStmt->close();
                    }


                    /* =========================
                       AUDIT LOG
                    ========================= */

                    addAuditLog(
                        $conn,
                        "UPDATE",
                        "Blotter",
                        "Updated blotter record #{$id}. Status synchronized to related service request.",
                        $id
                    );


                    /* =========================
                       COMMIT
                    ========================= */

                    $conn->commit();


                    $_SESSION["flash_message"] =
                        "Blotter record updated successfully.";

                    $_SESSION["flash_type"] =
                        "success";


                    header(
                        "Location: blotter.php#blotter-records"
                    );

                    exit;


                } catch (Throwable $e) {

                    $conn->rollback();

                    error_log("Blotter update failed: " . $e->getMessage());
                    $message = "Unable to update blotter record. Please try again.";

                    $messageType =
                        "error";
                }
            } else {

                /* INSERT WITH TRANSACTION */
                try {
                    if (!$conn->begin_transaction()) {
                        throw new Exception("Unable to start database transaction.");
                    }

                    $stmt = $conn->prepare("
                        INSERT INTO blotter
                        (
                            resident_id,
                            complainant_type,
                            complainant,
                            complainant_first_name,
                            complainant_middle_name,
                            complainant_last_name,
                            complainant_suffix,
                            complainant_birth_date,
                            complainant_gender,
                            complainant_civil_status,
                            complainant_address,
                            complainant_contact_number,
                            respondent,
                            incident,
                            incident_date,
                            status,
                            remarks
                        )
                        VALUES (
                            NULLIF(?, 0),
                            ?, ?, ?, ?, ?, ?, NULLIF(?, ''),
                            ?, ?, ?, ?, ?, ?, NULLIF(?, ''),
                            ?, ?
                        )
                    ");

                    if (!$stmt) {
                        throw new Exception("Unable to prepare blotter record.");
                    }

                    $stmt->bind_param(
                        "issssssssssssssss",
                        $resident_id,
                        $complainant_type,
                        $complainant,
                        $complainant_first_name,
                        $complainant_middle_name,
                        $complainant_last_name,
                        $complainant_suffix,
                        $complainant_birth_date,
                        $complainant_gender,
                        $complainant_civil_status,
                        $complainant_address,
                        $complainant_contact_number,
                        $respondent,
                        $incident,
                        $incident_date,
                        $status,
                        $remarks
                    );

                    if (!$stmt->execute()) {
                        $stmt->close();
                        throw new Exception("Unable to add blotter record.");
                    }

                    $blotter_id = (int) $stmt->insert_id;
                    $stmt->close();

                    /* Generate automatic Blotter Number
                       Example: BLT-2026-000027 */
                    $blotter_year = date('Y');
                    $blotter_no = 'BLT-' . $blotter_year . '-' . str_pad(
                        (string) $blotter_id,
                        6,
                        '0',
                        STR_PAD_LEFT
                    );

                    $blotterNoStmt = $conn->prepare("
                        UPDATE blotter
                        SET blotter_no = ?
                        WHERE id = ?
                    ");

                    if (!$blotterNoStmt) {
                        throw new Exception("Unable to prepare blotter number.");
                    }

                    $blotterNoStmt->bind_param("si", $blotter_no, $blotter_id);

                    if (!$blotterNoStmt->execute()) {
                        $blotterNoStmt->close();
                        throw new Exception("Unable to generate blotter number.");
                    }

                    $blotterNoStmt->close();

                    addAuditLog(
                        $conn,
                        "ADD",
                        "Blotter",
                        "Added a new blotter record.",
                        $blotter_id
                    );

                    $conn->commit();

                    $_SESSION["flash_message"] = "Blotter record added successfully.";
                    $_SESSION["flash_type"] = "success";

                    header("Location: blotter.php#blotter-records");
                    exit;
                } catch (Throwable $e) {
                    $conn->rollback();
                    error_log("Blotter insert failed: " . $e->getMessage());
                    $message = "Unable to add blotter record. Please try again.";
                    $messageType = "error";
                }
            }
        }
    }
}

/* =========================
   EDIT RECORD
========================= */

$edit_record = null;

if (isset($_GET["edit"])) {

    $id = (int) $_GET["edit"];

    if ($id > 0) {

        $stmt = $conn->prepare("
            SELECT *
            FROM blotter
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $edit_record = $result->fetch_assoc();
        }

        $stmt->close();
    }
}

/* =========================
   GET RESIDENTS
========================= */

$residents = [];

$residentResult = $conn->query("
    SELECT
        id,
        first_name,
        middle_name,
        last_name,
        suffix,
        birth_date,
        gender,
        civil_status,
        address,
        contact_number
    FROM residents
    ORDER BY last_name ASC, first_name ASC
");

if ($residentResult) {

    while ($resident = $residentResult->fetch_assoc()) {
        $residents[] = $resident;
    }
}

/* =========================
   SEARCH + PAGINATION
========================= */

$search = trim($_GET["search"] ?? "");

$recordSource = trim($_GET["source"] ?? "all");

if (!in_array($recordSource, ["all", "manual", "online"], true)) {
    $recordSource = "all";
}

$recordsPerPage = 10;

$currentPage = isset($_GET["page"])
    ? (int) $_GET["page"]
    : 1;

if ($currentPage < 1) {
    $currentPage = 1;
}

$searchTerm = "%" . $search . "%";


/* =========================
   SOURCE CONDITION
========================= */

$sourceCondition = "";

if ($recordSource === "online") {

    $sourceCondition = "
        AND EXISTS (
            SELECT 1
            FROM service_requests sr
            WHERE sr.blotter_id = blotter.id
        )
    ";

} elseif ($recordSource === "manual") {

    $sourceCondition = "
        AND NOT EXISTS (
            SELECT 1
            FROM service_requests sr
            WHERE sr.blotter_id = blotter.id
        )
    ";
}


/* =========================
   SEARCH CONDITION
========================= */

$searchCondition = "";

if ($search !== "") {

    $searchCondition = "
        AND (
            blotter.blotter_no LIKE ?
            OR blotter.complainant LIKE ?
            OR blotter.respondent LIKE ?
            OR blotter.incident LIKE ?
            OR blotter.status LIKE ?
            OR blotter.remarks LIKE ?
            OR EXISTS (
                SELECT 1
                FROM service_requests sr
                WHERE sr.blotter_id = blotter.id
                AND sr.reference_no LIKE ?
            )
        )
    ";
}


/* =========================
   COUNT TOTAL RECORDS
========================= */

$countSql = "
    SELECT COUNT(*) AS total
    FROM blotter
    WHERE 1=1
    $sourceCondition
    $searchCondition
";

$countStmt = $conn->prepare($countSql);

if (!$countStmt) {
    die("Unable to load blotter records.");
}

if ($search !== "") {

    $countStmt->bind_param(
        "sssssss",
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    );
}

if (!$countStmt->execute()) {

    $countStmt->close();

    die("Unable to load blotter records.");
}

$countResult = $countStmt->get_result();

$totalRecords = (int) (
    $countResult->fetch_assoc()["total"] ?? 0
);

$countStmt->close();


/* =========================
   CALCULATE PAGES
========================= */

$totalPages = max(
    1,
    (int) ceil($totalRecords / $recordsPerPage)
);

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $recordsPerPage;


/* =========================
   GET CURRENT PAGE RECORDS
========================= */

$sql = "
    SELECT
        blotter.*,

        CASE
            WHEN EXISTS (
                SELECT 1
                FROM service_requests sr
                WHERE sr.blotter_id = blotter.id
            )
            THEN 'Online Request'
            ELSE 'Manual Entry'
        END AS source_type,

        (
            SELECT sr.reference_no
            FROM service_requests sr
            WHERE sr.blotter_id = blotter.id
            LIMIT 1
        ) AS online_reference_no,

        (
            SELECT sr.id
            FROM service_requests sr
            WHERE sr.blotter_id = blotter.id
            LIMIT 1
        ) AS service_request_id

    FROM blotter

    WHERE 1=1

    $sourceCondition
    $searchCondition

    ORDER BY blotter.id DESC

    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Unable to load blotter records.");
}


if ($search !== "") {

    $stmt->bind_param(
        "sssssssii",
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $recordsPerPage,
        $offset
    );

} else {

    $stmt->bind_param(
        "ii",
        $recordsPerPage,
        $offset
    );
}


if (!$stmt->execute()) {

    $stmt->close();

    die("Unable to load blotter records.");
}


$records = $stmt->get_result();

/* =========================
   HEADER
========================= */

require_once "../includes/header.php";

?>
<style>
/* Page-scoped, responsive blotter form styling. */
.content-section:has(#blotterForm) { background:#fff; border:1px solid #e4e9f2; border-top:5px solid #2563eb; border-radius:18px; box-shadow:0 8px 28px rgba(15,23,42,.055); padding:clamp(18px,2.5vw,30px); margin-bottom:26px; }
#blotter-records { border-top:5px solid #2563eb; }
.content-section:has(#blotterForm) .section-header { margin-bottom:22px; padding-bottom:18px; border-bottom:1px solid #e8edf5; }
.content-section:has(#blotterForm) .section-header h2 { color:#17233d; font-size:clamp(1.25rem,2vw,1.65rem); font-weight:750; letter-spacing:-.025em; margin:0 0 6px; }
.content-section:has(#blotterForm) .section-header p { color:#64748b; margin:0; line-height:1.55; }
.content-section:has(#blotterForm) .edit-record-context { display:flex; flex-wrap:wrap; align-items:center; gap:8px 12px; margin-top:14px; padding:11px 14px; border:1px solid #bfdbfe; border-radius:10px; background:#eff6ff; color:#1e3a8a; font-size:.9rem; }
.content-section:has(#blotterForm) .edit-record-context strong { font-variant-numeric:tabular-nums; }
.content-section:has(#blotterForm) .edit-record-context .edit-record-label { padding:4px 8px; border-radius:999px; background:#dbeafe; color:#1d4ed8; font-size:.76rem; font-weight:800; letter-spacing:.04em; text-transform:uppercase; }
#blotterForm { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:20px 22px; align-items:start; background:#fbfcfe; border:1px solid #e8edf5; border-radius:15px; padding:clamp(16px,2.2vw,26px); }
#blotterForm .form-group { min-width:0; margin:0; }
#blotterForm > .form-group:first-of-type { grid-column:1/-1; }
#blotterForm label { display:block; color:#26354d; font-size:.9rem; font-weight:650; margin:0 0 8px; }
#blotterForm input:not([type=hidden]),#blotterForm select,#blotterForm textarea { box-sizing:border-box; display:block; width:100%; min-width:0; min-height:46px; padding:11px 13px; border:1px solid #d4dce8; border-radius:9px; background:#fff; color:#182337; font:inherit; font-size:.95rem; line-height:1.4; box-shadow:0 1px 2px rgba(15,23,42,.025); transition:border-color .18s ease,box-shadow .18s ease,background .18s ease; }
#blotterForm textarea { min-height:116px; resize:vertical; }
#blotterForm input::placeholder,#blotterForm textarea::placeholder { color:#94a3b8; }
#blotterForm input:focus,#blotterForm select:focus,#blotterForm textarea:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.15); }
#blotterForm input[readonly] { background:#f0f4f9; color:#536277; cursor:default; }
#blotterForm #residentSection,#blotterForm #residentDetailsSection,#blotterForm #nonResidentSection { grid-column:1/-1; min-width:0; display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px 22px; align-items:start; }
#blotterForm #residentSection > .form-group { grid-column:1/-1; }
#blotterForm #residentSection[hidden],#blotterForm #residentDetailsSection[hidden],#blotterForm #nonResidentSection[hidden] { display:none !important; }
#blotterForm .form-section-title { grid-column:1/-1; display:flex; align-items:center; gap:10px; color:#172b4d; font-size:1rem; font-weight:750; padding:3px 0 11px; margin:2px 0 0; border-bottom:1px solid #e1e8f2; }
#blotterForm .form-section-title::before { content:""; width:4px; height:19px; border-radius:5px; background:#2563eb; flex:0 0 4px; }
#blotterForm #residentDetailsSection > .form-group[style*=grid-column] { grid-column:1/-1; }
#blotterForm #residentDetailsSection small { display:block; padding:11px 13px; color:#475569; line-height:1.5; background:#f1f5f9; border:1px solid #e2e8f0; border-radius:9px; }
#blotterForm .resident-search-hint,
#blotterForm .resident-search-status { display:block; margin-top:7px; color:#64748b; font-size:.84rem; line-height:1.45; }
#blotterForm .resident-search-status { min-height:1.2em; }
#blotterForm .form-actions { grid-column:1/-1; display:flex; flex-wrap:wrap; align-items:center; justify-content:flex-end; gap:10px; border-top:1px solid #e3eaf3; padding-top:20px; margin-top:2px; }
#blotterForm .form-actions .btn { display:inline-flex; align-items:center; justify-content:center; min-height:44px; padding:10px 20px; border-radius:9px; font-weight:700; text-decoration:none; cursor:pointer; transition:transform .15s ease,box-shadow .15s ease,filter .15s ease; }
#blotterForm .form-actions .btn:hover { transform:translateY(-1px); filter:brightness(.98); }
#blotterForm .form-actions .btn-primary { box-shadow:0 4px 10px rgba(37,99,235,.18); }
#blotterForm.is-editing .form-actions { position:sticky; bottom:0; z-index:4; margin:8px -8px -8px; padding:14px 8px 8px; border-radius:0 0 10px 10px; background:linear-gradient(180deg,rgba(251,252,254,.92),#fbfcfe 30%); }
@media(max-width:760px) { #blotterForm { grid-template-columns:minmax(0,1fr); gap:17px; } #blotterForm > .form-group:first-of-type,#blotterForm #residentSection,#blotterForm #residentDetailsSection,#blotterForm #nonResidentSection,#blotterForm .form-section-title,#blotterForm .form-actions { grid-column:1; } #blotterForm #residentSection,#blotterForm #residentDetailsSection,#blotterForm #nonResidentSection { grid-template-columns:minmax(0,1fr); gap:16px; } #blotterForm .form-actions { justify-content:stretch; } #blotterForm .form-actions .btn { flex:1 1 140px; } }
@media(prefers-reduced-motion:reduce) { #blotterForm input,#blotterForm select,#blotterForm textarea,#blotterForm .form-actions .btn { transition:none; } }

/* Blotter edit button - disabled until something changes */
#blotterForm #blotterSubmitButton:disabled {
    background: #2563eb !important;
        border-color: #2563eb !important;
        color: #fff !important;
        cursor: not-allowed !important;
        opacity: 0.45;
        box-shadow: none !important;
        transform: none !important;
        filter: none !important;
}

/* Enabled after a change */
#blotterForm #blotterSubmitButton:not(:disabled) {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
    cursor: pointer;
    opacity: 1;
}

/* Hover only when enabled */
#blotterForm #blotterSubmitButton:not(:disabled):hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
}

/* Match Blotter search row to Certificate Records */
#blotter-records .search-form {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin: 22px 0 18px;
}

#blotter-records .search-form input[name="search"] {
    box-sizing: border-box;
    flex: 1 1 400px;
    width: 100%;
    max-width: 495px;
    min-width: 220px;
    min-height: 48px;
    padding: 11px 15px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: #fff;
    color: #334155;
    font: inherit;
    font-size: 14px;
    transition: border-color .15s ease, box-shadow .15s ease;
}

#blotter-records .search-form input[name="search"]:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
    outline: none;
}

#blotter-records .search-form select[name="source"] {
    box-sizing: border-box;
    flex: 0 0 180px;
    width: 180px;
    min-height: 48px;
    padding: 11px 36px 11px 13px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    background: #fff;
    color: #334155;
    font: inherit;
    font-size: 14px;
    cursor: pointer;
}

#blotter-records .search-form select[name="source"]:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
    outline: none;
}

#blotter-records .search-form .btn {
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    padding: 11px 20px;
    border-radius: 9px;
    font-size: 14px;
    font-weight: 700;
    white-space: nowrap;
}

@media (max-width: 760px) {
    #blotter-records .search-form {
        gap: 10px;
    }

    #blotter-records .search-form input[name="search"] {
        flex: 1 1 100%;
        max-width: none;
        min-width: 0;
    }

    #blotter-records .search-form select[name="source"] {
        flex: 1 1 100%;
        width: 100%;
    }

    #blotter-records .search-form .btn {
        flex: 1 1 auto;
    }
}
    

/* Compact Blotter Records table */
#blotter-records .table-container {
    width: 100%;
    max-width: 100%;
    overflow-x: hidden;
    margin-top: 12px;
}

#blotter-records .table-container table {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
    font-size: 12px;
}

#blotter-records .table-container th,
#blotter-records .table-container td {
    padding: 9px 6px;
    text-align: left;
    vertical-align: middle;
    overflow-wrap: anywhere;
    line-height: 1.35;
}

#blotter-records .table-container th {
    font-size: 11px;
    font-weight: 700;
    white-space: normal;
}

#blotter-records .table-container th:nth-child(1),
#blotter-records .table-container td:nth-child(1) {
    width: 8%;
}

#blotter-records .table-container th:nth-child(2),
#blotter-records .table-container td:nth-child(2) {
    width: 11%;
}

#blotter-records .table-container th:nth-child(3),
#blotter-records .table-container td:nth-child(3) {
    width: 11%;
}

#blotter-records .table-container th:nth-child(4),
#blotter-records .table-container td:nth-child(4) {
    width: 11%;
}

#blotter-records .table-container th:nth-child(5),
#blotter-records .table-container td:nth-child(5) {
    width: 13%;
}

#blotter-records .table-container th:nth-child(6),
#blotter-records .table-container td:nth-child(6) {
    width: 9%;
}

#blotter-records .table-container th:nth-child(7),
#blotter-records .table-container td:nth-child(7) {
    width: 10%;
}

#blotter-records .table-container th:nth-child(8),
#blotter-records .table-container td:nth-child(8) {
    width: 11%;
}

#blotter-records .table-container th:nth-child(9),
#blotter-records .table-container td:nth-child(9) {
    width: 16%;
}

#blotter-records .action-buttons {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    gap: 4px;
}

#blotter-records .action-buttons .btn,
#blotter-records .action-buttons .delete-form {
    margin: 0;
}

#blotter-records .action-buttons .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 30px;
    padding: 5px 7px;
    font-size: 11px;
    line-height: 1.2;
    white-space: nowrap;
}

#blotter-records .action-buttons .delete-form {
    display: inline-flex;
}

#blotter-records .source-badge {
    display: inline-block;
    max-width: 100%;
    padding: 5px 6px;
    font-size: 10px;
    white-space: normal;
}  
    

/* Fix Blotter action buttons for both sources */
#blotter-records .action-buttons {
    display: flex !important;
    flex-direction: row;
    flex-wrap: wrap !important;
    align-items: center;
    gap: 5px;
    width: 100%;
    max-width: 100%;
    overflow: visible !important;
    box-sizing: border-box;
}

#blotter-records .action-buttons > a.btn,
#blotter-records .action-buttons > form.delete-form {
    display: inline-flex !important;
    flex: 0 0 auto !important;
    width: auto !important;
    max-width: 100%;
    margin: 0 !important;
    box-sizing: border-box;
    visibility: visible !important;
    opacity: 1;
}

#blotter-records .action-buttons > form.delete-form {
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
}

#blotter-records .action-buttons > form.delete-form button {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
    min-height: 30px;
    padding: 5px 7px;
    font-size: 11px;
    cursor: pointer;
}

/* Keep the Actions column inside the table */
#blotter-records .table-container td:last-child {
    overflow: visible !important;
    vertical-align: middle;
}   
    

/* Align all three Blotter action buttons in one row */
#blotter-records .action-buttons {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 6px !important;
    width: max-content !important;
    min-width: 100%;
}

/* Keep Manage Request, Edit, and Delete side by side */
#blotter-records .action-buttons > a,
#blotter-records .action-buttons > form.delete-form {
    display: inline-flex !important;
    flex: 0 0 auto !important;
    align-items: center !important;
    width: auto !important;
    margin: 0 !important;
}

/* Prevent Delete form from going to the next line */
#blotter-records .action-buttons > form.delete-form {
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
}

/* Consistent button sizing */
#blotter-records .action-buttons .btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-height: 32px !important;
    padding: 6px 9px !important;
    margin: 0 !important;
    white-space: nowrap !important;
    font-size: 11px !important;
    line-height: 1.2 !important;
    box-sizing: border-box !important;
}    



/* FINAL DESKTOP FIT: keep all Blotter columns and actions inside the card */
#blotter-records .table-container {
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: auto !important;
    overflow-y: visible !important;
}

#blotter-records .table-container table {
    width: 100% !important;
    min-width: 0 !important;
    table-layout: fixed !important;
    border-collapse: collapse !important;
    font-size: 11px !important;
}

#blotter-records .table-container th,
#blotter-records .table-container td {
    box-sizing: border-box !important;
    padding: 8px 5px !important;
    line-height: 1.3 !important;
    vertical-align: middle !important;
    overflow-wrap: anywhere !important;
    word-break: normal !important;
}

/* Rebalance the nine columns; reserve enough room for all three actions. */
#blotter-records .table-container th:nth-child(1),
#blotter-records .table-container td:nth-child(1) {
    width: 12% !important;
    white-space: nowrap !important;
    overflow-wrap: normal !important;
    word-break: keep-all !important;
}
#blotter-records .table-container th:nth-child(2),
#blotter-records .table-container td:nth-child(2) { width: 9% !important; }
#blotter-records .table-container th:nth-child(3),
#blotter-records .table-container td:nth-child(3) { width: 9% !important; }
#blotter-records .table-container th:nth-child(4),
#blotter-records .table-container td:nth-child(4) { width: 9% !important; }
#blotter-records .table-container th:nth-child(5),
#blotter-records .table-container td:nth-child(5) { width: 11% !important; }
#blotter-records .table-container th:nth-child(6),
#blotter-records .table-container td:nth-child(6) { width: 8% !important; }
#blotter-records .table-container th:nth-child(7),
#blotter-records .table-container td:nth-child(7) { width: 8% !important; }
#blotter-records .table-container th:nth-child(8),
#blotter-records .table-container td:nth-child(8) { width: 10% !important; }
#blotter-records .table-container th:nth-child(9),
#blotter-records .table-container td:nth-child(9) { width: 24% !important; }

#blotter-records .action-buttons {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 4px !important;
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    overflow: visible !important;
}

#blotter-records .action-buttons > a,
#blotter-records .action-buttons > form.delete-form {
    display: inline-flex !important;
    flex: 0 0 auto !important;
    align-items: center !important;
    width: auto !important;
    min-width: 0 !important;
    max-width: none !important;
    margin: 0 !important;
}

#blotter-records .action-buttons > form.delete-form {
    padding: 0 !important;
    border: 0 !important;
    background: transparent !important;
}

#blotter-records .action-buttons .btn,
#blotter-records .action-buttons > form.delete-form button {
    display: inline-flex !important;
    flex: 0 0 auto !important;
    align-items: center !important;
    justify-content: center !important;
    min-height: 30px !important;
    padding: 5px 7px !important;
    margin: 0 !important;
    font-size: 10px !important;
    line-height: 1.15 !important;
    white-space: nowrap !important;
    box-sizing: border-box !important;
}

#blotter-records .table-container td:last-child {
    overflow: visible !important;
}

@media (max-width: 1100px) {
    #blotter-records .table-container table {
        min-width: 1050px !important;
    }
    #blotter-records .table-container {
        overflow-x: auto !important;
    }
}


/* Final layout safeguard: keep every record detail in its own readable column. */
#blotter-records .table-container {
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: auto !important;
    overflow-y: visible !important;
    -webkit-overflow-scrolling: touch;
}

#blotter-records .table-container table {
    width: 100% !important;
    min-width: 1380px !important;
    table-layout: fixed !important;
    border-collapse: collapse !important;
}

#blotter-records .table-container th,
#blotter-records .table-container td {
    box-sizing: border-box !important;
    padding: 9px 8px !important;
    vertical-align: middle !important;
    overflow: hidden !important;
    text-overflow: ellipsis;
    overflow-wrap: break-word !important;
    word-break: normal !important;
}

/* Blotter numbers stay on one line and are never split. */
#blotter-records .table-container th:nth-child(1),
#blotter-records .table-container td:nth-child(1) {
    width: 11% !important;
    white-space: nowrap !important;
    overflow-wrap: normal !important;
    word-break: keep-all !important;
    text-overflow: clip !important;
}

#blotter-records .table-container th:nth-child(2),
#blotter-records .table-container td:nth-child(2) { width: 12% !important; }
#blotter-records .table-container th:nth-child(3),
#blotter-records .table-container td:nth-child(3) { width: 10% !important; }
#blotter-records .table-container th:nth-child(4),
#blotter-records .table-container td:nth-child(4) { width: 11% !important; }
#blotter-records .table-container th:nth-child(5),
#blotter-records .table-container td:nth-child(5) { width: 13% !important; }
#blotter-records .table-container th:nth-child(6),
#blotter-records .table-container td:nth-child(6) { width: 9% !important; }
#blotter-records .table-container th:nth-child(7),
#blotter-records .table-container td:nth-child(7) { width: 9% !important; }
#blotter-records .table-container th:nth-child(8),
#blotter-records .table-container td:nth-child(8) { width: 10% !important; }
#blotter-records .table-container th:nth-child(9),
#blotter-records .table-container td:nth-child(9) {
    width: 15% !important;
    overflow: visible !important;
    text-overflow: clip !important;
}

/* Source badges wrap inside their own column instead of covering Respondent. */
#blotter-records .table-container td.source-column {
    overflow: hidden !important;
}
#blotter-records .source-badge {
    display: inline-block !important;
    box-sizing: border-box !important;
    max-width: 100% !important;
    white-space: normal !important;
    overflow-wrap: anywhere !important;
    word-break: normal !important;
    line-height: 1.35 !important;
    vertical-align: middle !important;
}

/* Keep action controls aligned and inside the Actions column. */
#blotter-records .table-container td:last-child .action-buttons {
    display: flex !important;
    flex-flow: row nowrap !important;
    align-items: center !important;
    gap: 4px !important;
    width: max-content !important;
    min-width: 0 !important;
    max-width: none !important;
}
#blotter-records .table-container td:last-child .action-buttons .btn,
#blotter-records .table-container td:last-child .action-buttons button {
    white-space: nowrap !important;
    flex: 0 0 auto !important;
}



/* Final clean-fit layout: no horizontal scrolling or overlapping record details. */
#blotter-records .table-container {
    width: 100% !important;
    max-width: 100% !important;
    overflow-x: hidden !important;
    overflow-y: visible !important;
}
#blotter-records .table-container table {
    width: 100% !important;
    min-width: 0 !important;
    table-layout: fixed !important;
    border-collapse: collapse !important;
}
#blotter-records .table-container th,
#blotter-records .table-container td {
    box-sizing: border-box !important;
    padding: 10px 7px !important;
    vertical-align: middle !important;
    text-align: left;
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: clip !important;
    overflow-wrap: anywhere !important;
    word-break: normal !important;
    line-height: 1.35 !important;
}
#blotter-records .table-container th {
    font-size: 11px !important;
    font-weight: 700 !important;
}
#blotter-records .table-container th:nth-child(1),
#blotter-records .table-container td:nth-child(1) { width: 13% !important; }
#blotter-records .table-container th:nth-child(2),
#blotter-records .table-container td:nth-child(2) { width: 12% !important; }
#blotter-records .table-container th:nth-child(3),
#blotter-records .table-container td:nth-child(3) { width: 9% !important; }
#blotter-records .table-container th:nth-child(4),
#blotter-records .table-container td:nth-child(4) { width: 10% !important; }
#blotter-records .table-container th:nth-child(5),
#blotter-records .table-container td:nth-child(5) { width: 11% !important; }
#blotter-records .table-container th:nth-child(6),
#blotter-records .table-container td:nth-child(6) { width: 9% !important; }
#blotter-records .table-container th:nth-child(7),
#blotter-records .table-container td:nth-child(7) { width: 9% !important; }
#blotter-records .table-container th:nth-child(8),
#blotter-records .table-container td:nth-child(8) { width: 9% !important; }
#blotter-records .table-container th:nth-child(9),
#blotter-records .table-container td:nth-child(9) { width: 18% !important; }
#blotter-records .table-container td:first-child,
#blotter-records .table-container td:first-child strong {
    white-space: nowrap !important;
    overflow-wrap: normal !important;
    word-break: keep-all !important;
    font-size: 10px !important;
    letter-spacing: -.02em;
}
#blotter-records .table-container td.source-column { overflow: hidden !important; }
#blotter-records .source-badge {
    display: inline-block !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    white-space: normal !important;
    overflow-wrap: anywhere !important;
    line-height: 1.3 !important;
    padding: 5px 6px !important;
    font-size: 10px !important;
}
#blotter-records .table-container td:last-child { overflow: visible !important; }
#blotter-records .table-container td:last-child .action-buttons {
    display: flex !important;
    flex-flow: row wrap !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 5px !important;
    width: 100% !important;
    min-width: 0 !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
}
#blotter-records .table-container td:last-child .action-buttons > a,
#blotter-records .table-container td:last-child .action-buttons > form.delete-form {
    display: inline-flex !important;
    flex: 0 1 auto !important;
    width: auto !important;
    min-width: 0 !important;
    max-width: 100% !important;
    margin: 0 !important;
}
#blotter-records .table-container td:last-child .action-buttons .btn,
#blotter-records .table-container td:last-child .action-buttons button {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-height: 30px !important;
    max-width: 100% !important;
    padding: 6px 7px !important;
    margin: 0 !important;
    font-size: 10px !important;
    line-height: 1.2 !important;
    white-space: normal !important;
    overflow-wrap: anywhere !important;
    box-sizing: border-box !important;
}
@media (max-width: 1100px) {
    #blotter-records .table-container th,
    #blotter-records .table-container td { padding: 8px 4px !important; font-size: 10px !important; }
    #blotter-records .table-container th:nth-child(1),
    #blotter-records .table-container td:nth-child(1) { width: 13% !important; }
    #blotter-records .table-container th:nth-child(9),
    #blotter-records .table-container td:nth-child(9) { width: 19% !important; }
}

</style>

<!-- =========================
     TOP BAR
========================= -->

<div class="topbar">

    <div>
        <h1>Blotter Records</h1>
        <p>Manage official blotter records</p>
    </div>

    <a
        href="service_requests.php?search=Blotter%20Assistance#service-request-records"
        class="btn-primary"
        style="text-decoration:none;"
    >
        📋 Online Blotter Requests
    </a>

    <div class="admin-profile">

        <div class="admin-avatar">
            A
        </div>

        <div>
            <strong>
                <?= htmlspecialchars($_SESSION["username"] ?? "User") ?>
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

</div>


<!-- =========================
     CRUD SUCCESS / ERROR POPUP
========================= -->

<?php if ($message !== ""): ?>

    <div class="crud-popup-overlay" id="crudPopup">

        <div class="crud-popup-box <?= $messageType ?>">

            <div class="crud-popup-icon">
                <?= $messageType === "success" ? "✓" : "!" ?>
            </div>

            <h3>
                <?= $messageType === "success"
                    ? "Success!"
                    : "Error!" ?>
            </h3>

            <p>
                <?= htmlspecialchars($message) ?>
            </p>

            <button
                type="button"
                class="crud-popup-button"
                onclick="closeCrudPopup()"
            >
                OK
            </button>

        </div>

    </div>

<?php endif; ?>


<!-- =========================
     CONTENT
========================= -->

<section class="content-section">

    <div class="section-header">

        <div>
            <h2>
                <?= $edit_record ? 'Edit Blotter Record' : 'Add Blotter Record' ?>
            </h2>

            <p>
                <?= $edit_record
                    ? 'Update the selected blotter record.'
                    : 'Enter the details of the blotter record.' ?>
            </p>

            <?php if ($edit_record): ?>
                <div class="edit-record-context" role="status">
                    <span class="edit-record-label">Editing Blotter</span>

                    <strong>
                        <?= htmlspecialchars($edit_record['blotter_no'] ?? '—') ?>
                    </strong>

                    <span>Changes here apply to this blotter record only.</span>
                </div>
            <?php endif; ?>
        </div>

    </div>


    <!-- =========================
         FORM
    ========================= -->

<form method="POST" class="form-grid<?= $edit_record ? ' is-editing' : '' ?>" id="blotterForm" data-auto-capitalize novalidate>

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($csrf_token) ?>"
        >

        <?php if ($edit_record): ?>

            <input
                type="hidden"
                name="id"
                value="<?= htmlspecialchars($edit_record['id']) ?>"
            >

        <?php endif; ?>


        <!-- COMPLAINANT TYPE -->
        <div class="form-group">

            <label for="complainant_type">
                Complainant Type
            </label>

            <select
                id="complainant_type"
                name="complainant_type"
                required
            >
                <option value="Resident"
                    <?= (($edit_record["complainant_type"] ?? "Resident") === "Resident")
                        ? "selected"
                        : "" ?>>
                    Resident
                </option>

                <option value="Non-Resident"
                    <?= (($edit_record["complainant_type"] ?? "") === "Non-Resident")
                        ? "selected"
                        : "" ?>>
                    Non-Resident
                </option>
            </select>

        </div>


        <!-- RESIDENT INFORMATION SECTION -->
        <div id="residentSection">

            <div class="form-section-title">Resident Selection</div>

            <div class="form-group">

                <label for="residentSearch">
                    Search Residents
                </label>

                <input
                    type="search"
                    id="residentSearch"
                    autocomplete="off"
                    placeholder="Type a name, contact number, or address"
                    aria-describedby="residentSearchHint residentSearchStatus"
                >

                <small class="resident-search-hint" id="residentSearchHint">
                    Search to narrow the list, then choose the matching resident below.
                </small>

                <small class="resident-search-status" id="residentSearchStatus" aria-live="polite"></small>

                <label for="resident_id">
                    Select Resident
                </label>

                <select
                    id="resident_id"
                    name="resident_id"
                >

                <option value="">
                    Select resident
                </option>

                <?php foreach ($residents as $resident): ?>

                    <?php
                    $residentFullName = trim(
                        preg_replace(
                            '/\s+/u',
                            ' ',
                            implode(' ', array_filter([
                                $resident["first_name"],
                                $resident["middle_name"],
                                $resident["last_name"],
                                $resident["suffix"]
                            ]))
                        )
                    );
                    ?>

                    <option
                        value="<?= (int) $resident["id"] ?>"
                        data-first-name="<?= htmlspecialchars((isset($edit_record["complainant_first_name"]) && $edit_record["complainant_first_name"] !== null && $edit_record["complainant_first_name"] !== "") ? $edit_record["complainant_first_name"] : $resident["first_name"]) ?>"
                        data-middle-name="<?= htmlspecialchars((isset($edit_record["complainant_middle_name"]) && $edit_record["complainant_middle_name"] !== null) ? $edit_record["complainant_middle_name"] : $resident["middle_name"]) ?>"
                        data-last-name="<?= htmlspecialchars((isset($edit_record["complainant_last_name"]) && $edit_record["complainant_last_name"] !== null && $edit_record["complainant_last_name"] !== "") ? $edit_record["complainant_last_name"] : $resident["last_name"]) ?>"
                        data-suffix="<?= htmlspecialchars((isset($edit_record["complainant_suffix"]) && $edit_record["complainant_suffix"] !== null) ? $edit_record["complainant_suffix"] : $resident["suffix"]) ?>"
                        data-birth-date="<?= htmlspecialchars((isset($edit_record["complainant_birth_date"]) && $edit_record["complainant_birth_date"] !== null && $edit_record["complainant_birth_date"] !== "") ? $edit_record["complainant_birth_date"] : ($resident["birth_date"] ?? "")) ?>"
                        data-gender="<?= htmlspecialchars((isset($edit_record["complainant_gender"]) && $edit_record["complainant_gender"] !== null && $edit_record["complainant_gender"] !== "") ? $edit_record["complainant_gender"] : $resident["gender"]) ?>"
                        data-civil-status="<?= htmlspecialchars((isset($edit_record["complainant_civil_status"]) && $edit_record["complainant_civil_status"] !== null && $edit_record["complainant_civil_status"] !== "") ? $edit_record["complainant_civil_status"] : $resident["civil_status"]) ?>"
                        data-address="<?= htmlspecialchars((isset($edit_record["complainant_address"]) && $edit_record["complainant_address"] !== null && $edit_record["complainant_address"] !== "") ? $edit_record["complainant_address"] : $resident["address"]) ?>"
                        data-contact-number="<?= htmlspecialchars((isset($edit_record["complainant_contact_number"]) && $edit_record["complainant_contact_number"] !== null && $edit_record["complainant_contact_number"] !== "") ? $edit_record["complainant_contact_number"] : $resident["contact_number"]) ?>"
                        <?= (
                            isset($edit_record["resident_id"]) &&
                            (int) $edit_record["resident_id"] === (int) $resident["id"]
                        ) ? "selected" : "" ?>
                    >
                        <?= htmlspecialchars($residentFullName) ?>
                    </option>

                <?php endforeach; ?>

            </select>

        </div>

    </div>


        <!-- NON-RESIDENT INFORMATION -->
        <div
            id="nonResidentSection"
            style="display:none;"
        >

            <div class="form-section-title">Non-Resident Complainant Details</div>

            <div class="form-group">

                <label for="complainant_first_name">
                    First Name
                </label>

                <input
                    type="text"
                    id="complainant_first_name"
                    name="complainant_first_name"
                    maxlength="100"
                    pattern="[A-Za-zÀ-ÿÑñ][A-Za-zÀ-ÿÑñ .'-]*"
                    title="Use letters, spaces, apostrophes, periods, or hyphens only."
                    placeholder="Enter first name"
                    value="<?= htmlspecialchars($edit_record['complainant_first_name'] ?? '') ?>"
                >

            </div>


            <div class="form-group">

                <label for="complainant_middle_name">
                    Middle Name
                </label>

                <input
                    type="text"
                    id="complainant_middle_name"
                    name="complainant_middle_name"
                    maxlength="100"
                    pattern="[A-Za-zÀ-ÿÑñ][A-Za-zÀ-ÿÑñ .'-]*"
                    title="Use letters, spaces, apostrophes, periods, or hyphens only."
                    placeholder="Enter middle name"
                    value="<?= htmlspecialchars($edit_record['complainant_middle_name'] ?? '') ?>"
                >

            </div>


            <div class="form-group">

                <label for="complainant_last_name">
                    Last Name
                </label>

                <input
                    type="text"
                    id="complainant_last_name"
                    name="complainant_last_name"
                    maxlength="100"
                    pattern="[A-Za-zÀ-ÿÑñ][A-Za-zÀ-ÿÑñ .'-]*"
                    title="Use letters, spaces, apostrophes, periods, or hyphens only."
                    placeholder="Enter last name"
                    value="<?= htmlspecialchars($edit_record['complainant_last_name'] ?? '') ?>"
                >

            </div>


            <div class="form-group">

                <label for="complainant_suffix">
                    Suffix
                </label>

                <input
                    type="text"
                    id="complainant_suffix"
                    name="complainant_suffix"
                    maxlength="20"
                    placeholder="Jr., Sr., II, III"
                    value="<?= htmlspecialchars($edit_record['complainant_suffix'] ?? '') ?>"
                >

            </div>


            <div class="form-group">

                <label for="complainant_birth_date">
                    Birth Date
                </label>

                <input
                    type="date"
                    id="complainant_birth_date"
                    name="complainant_birth_date"
                    max="<?= date("Y-m-d") ?>"
                    value="<?= htmlspecialchars($edit_record['complainant_birth_date'] ?? '') ?>"
                >

            </div>


            <div class="form-group">

                <label for="complainant_gender">
                    Gender
                </label>

                <select
                    id="complainant_gender"
                    name="complainant_gender"
                >
                    <option value="">Select gender</option>

                    <option value="Male"
                        <?= (($edit_record['complainant_gender'] ?? '') === 'Male')
                            ? 'selected'
                            : '' ?>>
                        Male
                    </option>

                    <option value="Female"
                        <?= (($edit_record['complainant_gender'] ?? '') === 'Female')
                            ? 'selected'
                            : '' ?>>
                        Female
                    </option>
                </select>

            </div>


            <div class="form-group">

                <label for="complainant_civil_status">
                    Civil Status
                </label>

                <select
                    id="complainant_civil_status"
                    name="complainant_civil_status"
                >
                    <option value="">Select civil status</option>

                    <option value="Single"
                        <?= (($edit_record['complainant_civil_status'] ?? '') === 'Single')
                            ? 'selected'
                            : '' ?>>
                        Single
                    </option>

                    <option value="Married"
                        <?= (($edit_record['complainant_civil_status'] ?? '') === 'Married')
                            ? 'selected'
                            : '' ?>>
                        Married
                    </option>

                    <option value="Widowed"
                        <?= (($edit_record['complainant_civil_status'] ?? '') === 'Widowed')
                            ? 'selected'
                            : '' ?>>
                        Widowed
                    </option>

                    <option value="Separated"
                        <?= (($edit_record['complainant_civil_status'] ?? '') === 'Separated')
                            ? 'selected'
                            : '' ?>>
                        Separated
                    </option>
                </select>

            </div>


            <div class="form-group">

                <label for="complainant_address">
                    Complete Address
                </label>

                <input
                    type="text"
                    id="complainant_address"
                    name="complainant_address"
                    maxlength="255"
                    placeholder="Enter complete address"
                    value="<?= htmlspecialchars($edit_record['complainant_address'] ?? '') ?>"
                >

            </div>


            <div class="form-group">

                <label for="complainant_contact_number">
                    Contact Number
                </label>

                <input
                    type="text"
                    id="complainant_contact_number"
                    name="complainant_contact_number"
                    maxlength="11"
                    inputmode="numeric"
                    pattern="09[0-9]{9}"
                    title="Contact number must start with 09 and contain exactly 11 digits."
                    placeholder="09XXXXXXXXX"
                    value="<?= htmlspecialchars($edit_record['complainant_contact_number'] ?? '') ?>"
                >

            </div>

        </div>

        <!-- HIDDEN COMPLAINANT -->

        <input
            type="hidden"
            id="complainant"
            name="complainant"
            value="<?= htmlspecialchars($edit_record["complainant"] ?? "") ?>"
        >

        <div id="residentDetailsSection">

        <div class="form-section-title">Resident Information</div>

        <?php if ($edit_record && !empty($edit_record["resident_id"])): ?>
            <div class="form-group" style="grid-column: 1 / -1;">
                <small>Changes here update this blotter record only; the master Residents record remains unchanged.</small>
            </div>
        <?php endif; ?>

        <!-- RESIDENT INFORMATION -->

        <div class="form-group">

            <label for="resident_first_name">
                First Name
            </label>

            <input
                type="text"
                id="resident_first_name"
                name="resident_edit_first_name"

            >

        </div>

        <div class="form-group">

            <label for="resident_middle_name">
                Middle Name
            </label>

            <input
                type="text"
                id="resident_middle_name"
                name="resident_edit_middle_name"

            >

        </div>

        <div class="form-group">

            <label for="resident_last_name">
                Last Name
            </label>

            <input
                type="text"
                id="resident_last_name"
                name="resident_edit_last_name"

            >

        </div>

        <div class="form-group">

            <label for="resident_suffix">
                Suffix (Jr., Sr., II, III)
            </label>

            <input
                type="text"
                id="resident_suffix"
                name="resident_edit_suffix"
                maxlength="20"
                placeholder="Jr., Sr., II, III"
            >

        </div>

        <div class="form-group">

            <label for="resident_birth_date">
                Birth Date
            </label>

            <input
                type="date"
                id="resident_birth_date"
                name="resident_edit_birth_date"

            >

        </div>

        <div class="form-group">

            <label for="resident_gender">
                Gender
            </label>

            <select
                id="resident_gender"
                name="resident_edit_gender"

            >
                <option value="">Select gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select>

        </div>

        <div class="form-group">

            <label for="resident_civil_status">
                Civil Status
            </label>

            <select
                id="resident_civil_status"
                name="resident_edit_civil_status"

            >
                <option value="">Select civil status</option>
                <option value="Single">Single</option>
                <option value="Married">Married</option>
                <option value="Widowed">Widowed</option>
                <option value="Separated">Separated</option>
            </select>

        </div>

        <div class="form-group">

            <label for="resident_address">
                Complete Address
            </label>

            <input
                type="text"
                id="resident_address"
                name="resident_edit_address"

            >

        </div>

        <div class="form-group">

            <label for="resident_contact_number">
                Contact Number
            </label>

            <input
                type="text"
                id="resident_contact_number"
                name="resident_edit_contact_number"
                maxlength="11"
                inputmode="numeric"
                pattern="09[0-9]{9}"
                title="Contact number must start with 09 and contain exactly 11 digits."
                placeholder="09XXXXXXXXX"
            >

        </div>

        </div>


        <div class="form-section-title">Incident Details</div>

        <!-- RESPONDENT -->

        <div class="form-group">

            <label for="respondent">
                Respondent
            </label>

            <input
                type="text"
                id="respondent"
                name="respondent"
                maxlength="100"
                pattern="[A-Za-zÀ-ÿÑñ][A-Za-zÀ-ÿÑñ .'-]*"
                title="Use letters, spaces, apostrophes, periods, or hyphens only."
                required
                placeholder="Enter respondent name"
                value="<?= htmlspecialchars($edit_record['respondent'] ?? '') ?>"
            >

        </div>


        <!-- INCIDENT -->

        <div class="form-group">

            <label for="incident">
                Incident
            </label>

            <input
                type="text"
                id="incident"
                name="incident"
                maxlength="500"
                placeholder="Enter incident"
                value="<?= htmlspecialchars($edit_record['incident'] ?? '') ?>"
                required
            >

        </div>


        <!-- INCIDENT DATE -->

        <div class="form-group">

            <label for="incident_date">
                Incident Date
            </label>

            <input
                type="date"
                id="incident_date"
                name="incident_date"
                max="<?= date("Y-m-d") ?>"
                value="<?= htmlspecialchars($edit_record['incident_date'] ?? '') ?>"
            >

        </div>


        <!-- STATUS -->

        <div class="form-group">

            <label for="status">
                Status
            </label>

            <select
                id="status"
                name="status"
            >

                <?php
                $current_status = $edit_record['status'] ?? 'Pending';
                ?>

                <option value="Pending"
                    <?= $current_status === 'Pending' ? 'selected' : '' ?>>
                    Pending
                </option>

                <option value="Under Investigation"
                    <?= $current_status === 'Under Investigation' ? 'selected' : '' ?>>
                    Under Investigation
                </option>

                <option value="Resolved"
                    <?= $current_status === 'Resolved' ? 'selected' : '' ?>>
                    Resolved
                </option>

                <option value="Dismissed"
                    <?= $current_status === 'Dismissed' ? 'selected' : '' ?>>
                    Dismissed
                </option>

            </select>

        </div>


        <!-- REMARKS -->

        <div class="form-group">

            <label for="remarks">
                Remarks
            </label>

            <textarea
                id="remarks"
                name="remarks"
                maxlength="1000"
                rows="4"
                placeholder="Enter remarks or additional information"
            ><?= htmlspecialchars($edit_record['remarks'] ?? '') ?></textarea>

        </div>


        <!-- BUTTONS -->

        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
                id="blotterSubmitButton"
                <?= $edit_record ? 'disabled' : '' ?>
            >
                <?= $edit_record ? 'Save Changes' : 'Add Record' ?>
            </button>


            <?php if ($edit_record): ?>

                <a
                    href="blotter.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

            <?php else: ?>

                <button
                    type="reset"
                    class="btn btn-secondary"
                >
                    Clear
                </button>

            <?php endif; ?>

        </div>

    </form>

</section>


<!-- =========================
     BLOTTER RECORDS
========================= -->

<section class="content-section" id="blotter-records">

    <div class="section-header">

        <div>

            <h2>Blotter Records</h2>

            <p>
                View and manage all blotter incidents, including manual entries
                and online requests.
            </p>

        </div>

    </div>


    <!-- =========================
         SEARCH
    ========================= -->

    <form
        method="GET"
        action="blotter.php#blotter-records"
        class="search-form"
    >

        <input
            type="text"
            name="search"
            placeholder="Search blotter no., complainant, respondent, incident, or reference no."
            value="<?= htmlspecialchars(
                $search,
                ENT_QUOTES,
                "UTF-8"
            ) ?>"
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


        <?php if (
            $search !== "" ||
            $recordSource !== "all"
        ): ?>

            <a
                href="blotter.php#blotter-records"
                class="btn btn-secondary"
            >
                Clear
            </a>

        <?php endif; ?>

    </form>


    <!-- =========================
         TABLE
    ========================= -->

    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>Blotter No.</th>

                    <th>Complainant</th>

                    <th class="source-column">Source</th>

                    <th>Respondent</th>

                    <th>Incident</th>

                    <th>Incident Date</th>

                    <th>Status</th>

                    <th>Remarks</th>

                    <th>Actions</th>

                </tr>

            </thead>

            <tbody>

            <?php if ($records && $records->num_rows > 0): ?>

                <?php $counter = $offset + 1; ?>

                <?php while ($row = $records->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <strong>
                                <?= htmlspecialchars($row['blotter_no'] ?? '—') ?>
                            </strong>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['complainant']) ?>
                        </td>

                        <td class="source-column">

                            <?php if (($row["source_type"] ?? "") === "Online Request"): ?>

                                <span class="source-badge online">
                                    🌐 Online Request
                                </span>

                            <?php else: ?>

                                <span class="source-badge manual">
                                    📝 Manual Entry
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>
                            <?= htmlspecialchars($row['respondent'] ?? '') ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($row['incident']) ?>
                        </td>

                        <td>

                            <?php if (!empty($row['incident_date'])): ?>

                                <?= date(
                                    'M d, Y',
                                    strtotime($row['incident_date'])
                                ) ?>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>

                        <td>

                            <span class="status-badge">

                                <?= htmlspecialchars(
                                    $row['status'] ?? 'Pending'
                                ) ?>

                            </span>

                        </td>

                        <td>

                            <?= !empty($row['remarks'])
                                ? htmlspecialchars($row['remarks'])
                                : '—'
                            ?>

                        </td>

                        <td>

                            
<div class="action-buttons">

    <?php if (($row["source_type"] ?? "") === "Online Request"): ?>
        <a href="service_requests.php?search=<?= urlencode(
            $row["online_reference_no"] ?? ""
        ) ?>#service-request-records"
           class="btn btn-primary">
            📋 Manage Request
        </a>
    <?php endif; ?>

    <a href="blotter.php?edit=<?= (int) $row["id"] ?>#blotter-records"
       class="btn btn-secondary">
        Edit
    </a>

    <!-- Delete applies to BOTH sources -->
    <form method="POST"
          class="delete-form"
          onsubmit="return showDeletePopup(this);">

        <input type="hidden" name="delete_id"
               value="<?= (int) $row["id"] ?>">

        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, "UTF-8") ?>">

        <button type="submit" class="btn btn-danger">
            Delete
        </button>
    </form>

</div>


                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="9"
                        style="text-align:center; padding:30px;"
                    >
                        No blotter records found.
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
                            href="blotter.php?<?= http_build_query([
                                "search" => $search,
                                    "source" => $recordSource,
                                    "page" => $currentPage - 1
                            ]) ?>#blotter-records"
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
                            href="blotter.php?<?= http_build_query([
                                "search" => $search,
                                    "source" => $recordSource,
                                    "page" => $page
                            ]) ?>#blotter-records"
                            class="page-link <?= $page === $currentPage ? "active" : "" ?>"
                        >
                            <?= $page ?>
                        </a>

                    <?php endfor; ?>


                    <?php if ($currentPage < $totalPages): ?>

                        <a
                            href="blotter.php?<?= http_build_query([
                                "search" => $search,
                                "source" => $recordSource,
                                "page" => $currentPage + 1
                            ]) ?>#blotter-records"
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
     DELETE CONFIRMATION POPUP
========================= -->

<div
    class="delete-popup-overlay"
    id="deletePopup"
>

    <div class="delete-popup-box">

        <div class="delete-popup-icon">
            ⚠️
        </div>

        <h3>
            Confirm Delete
        </h3>

        <p>
            Are you sure you want to delete this blotter record?
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

function closeCrudPopup() {

    const popup =
        document.getElementById("crudPopup");

    if (popup) {
        popup.remove();
    }

}

setTimeout(function () {

    closeCrudPopup();

}, 3000);


let deleteForm = null;


function showDeletePopup(form) {

    deleteForm = form;

    const popup =
        document.getElementById("deletePopup");

    if (popup) {
        popup.style.display = "flex";
    }

    return false;

}


function closeDeletePopup() {

    const popup =
        document.getElementById("deletePopup");

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

<script src="../assets/js/auto-capitalize.js" defer></script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const complainantType =
        document.getElementById("complainant_type");

    const residentSection =
        document.getElementById("residentSection");

    const nonResidentSection =
        document.getElementById("nonResidentSection");


        function updateComplainantType() {

            const type =
                complainantType
                    ? complainantType.value
                    : "Resident";

            const blotterForm = document.querySelector(".form-grid");
            if (blotterForm) {
                blotterForm.classList.toggle(
                    "non-resident-mode",
                    type === "Non-Resident"
                );
            }

            const residentDetailsSection =
                document.getElementById("residentDetailsSection");

            if (type === "Non-Resident") {

                if (residentSection) {
                    residentSection.style.display = "none";
                }

                if (residentDetailsSection) {
                    residentDetailsSection.style.display = "none";
                }

                if (nonResidentSection) {
                    nonResidentSection.style.display = "grid";
                }

                if (residentSelect) {
                    residentSelect.value = "";
                }

                if (residentSearch) {
                    residentSearch.value = "";
                    filterResidentOptions("");
                }

            } else {

                if (residentSection) {
                    residentSection.style.display = "grid";
                }

                if (residentDetailsSection) {
                    residentDetailsSection.style.display = "grid";
                }

                if (nonResidentSection) {
                    nonResidentSection.style.display = "none";
                }
                loadResidentInformation();
            }
        }

    const residentSelect =
        document.getElementById("resident_id");

    const residentSearch =
        document.getElementById("residentSearch");

    const residentSearchStatus =
        document.getElementById("residentSearchStatus");

    const complainantInput =
        document.getElementById("complainant");

    if (!residentSelect) {
        return;
    }

    function loadResidentInformation() {

        const selectedOption =
            residentSelect.options[
                residentSelect.selectedIndex
            ];

        if (
            !selectedOption ||
            !selectedOption.value
        ) {

            if (complainantInput && !<?= $edit_record ? 'true' : 'false' ?>) {
                complainantInput.value = "";
            }

            document.getElementById("resident_first_name").value = "";
            document.getElementById("resident_middle_name").value = "";
            document.getElementById("resident_last_name").value = "";
            document.getElementById("resident_suffix").value = "";
            document.getElementById("resident_birth_date").value = "";
            document.getElementById("resident_gender").value = "";
            document.getElementById("resident_civil_status").value = "";
            document.getElementById("resident_address").value = "";
            document.getElementById("resident_contact_number").value = "";

            return;
        }

        const firstName =
            selectedOption.dataset.firstName || "";

        const middleName =
            selectedOption.dataset.middleName || "";

        const lastName =
            selectedOption.dataset.lastName || "";

        const suffix =
            selectedOption.dataset.suffix || "";

        const fullName = [
            firstName,
            middleName,
            lastName,
            suffix
        ]
        .filter(Boolean)
        .join(" ");

        if (complainantInput) {
            complainantInput.value = fullName;
        }

        document.getElementById("resident_first_name").value =
            firstName;

        document.getElementById("resident_middle_name").value =
            middleName;

        document.getElementById("resident_last_name").value =
            lastName;

        document.getElementById("resident_suffix").value =
            suffix;

        document.getElementById("resident_birth_date").value =
            selectedOption.dataset.birthDate || "";

        document.getElementById("resident_gender").value =
            selectedOption.dataset.gender || "";

        document.getElementById("resident_civil_status").value =
            selectedOption.dataset.civilStatus || "";

        document.getElementById("resident_address").value =
            selectedOption.dataset.address || "";

        document.getElementById("resident_contact_number").value =
            selectedOption.dataset.contactNumber || "";
    }

    function filterResidentOptions(query) {
        const normalizeText = function (value) {
            return (value || "")
                .toString()
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .toLowerCase();
        };
        const normalizedQuery = normalizeText(query).trim();
        let matchCount = 0;

        Array.from(residentSelect.options).forEach(function (option) {
            if (!option.value) {
                option.hidden = false;
                return;
            }

            const searchableText = [
                option.textContent,
                option.dataset.contactNumber,
                option.dataset.address
            ].filter(Boolean).map(normalizeText).join(" ");

            const matches = !normalizedQuery || searchableText.includes(normalizedQuery);
            option.hidden = !matches;
            if (matches) matchCount++;
        });

        if (residentSearchStatus) {
            residentSearchStatus.textContent = normalizedQuery
                ? (matchCount === 0
                    ? "No residents match your search. Try another name, number, or address."
                    : matchCount + (matchCount === 1 ? " resident found." : " residents found."))
                : matchCount + (matchCount === 1 ? " resident available." : " residents available.");
        }
    }

    if (residentSearch) {
        residentSearch.addEventListener("input", function () {
            filterResidentOptions(residentSearch.value);

            // Require a fresh selection after starting a new search.
            if (residentSelect.value) {
                residentSelect.value = "";
                loadResidentInformation();
            }
        });
    }

    if (complainantType) {
        complainantType.addEventListener("change", function () {
            // Clear only the complainant section being left. Keep incident,
            // respondent, status, and remarks intact when changing complainant type.
            const sectionToClear = complainantType.value === "Non-Resident"
                ? document.getElementById("residentDetailsSection")
                : document.getElementById("nonResidentSection");

            if (sectionToClear) {
                sectionToClear.querySelectorAll("input, select, textarea").forEach(function (field) {
                    field.value = "";
                    field.setCustomValidity("");
                });
            }

            updateComplainantType();
        });
    }

    updateComplainantType();

    residentSelect.addEventListener(
        "change",
        function () {
            if (residentSearch) {
                residentSearch.value = "";
                filterResidentOptions("");
            }
            loadResidentInformation();
        }
    );

    filterResidentOptions("");
    loadResidentInformation();

    const blotterForm = document.getElementById("blotterForm");
    const firstNameInput = document.getElementById("complainant_first_name");
    const middleNameInput = document.getElementById("complainant_middle_name");
    const lastNameInput = document.getElementById("complainant_last_name");
    const birthDateInput = document.getElementById("complainant_birth_date");
    const contactInput = document.getElementById("complainant_contact_number");
    const residentContactInput = document.getElementById("resident_contact_number");
    const respondentInput = document.getElementById("respondent");
    const incidentInput = document.getElementById("incident");
    const incidentDateInput = document.getElementById("incident_date");
    function validateContactNumberField(input, showPopup) {
        if (!input) return true;

        const isNonResident = complainantType && complainantType.value === "Non-Resident";
        const isActiveField = input === contactInput ? isNonResident : !isNonResident;
        const value = input.value.trim();
        const valid = !isActiveField || value === "" || /^09[0-9]{9}$/.test(value);

        input.setCustomValidity(
            valid ? "" : "Number must start with 09 and contain exactly 11 digits."
        );

        if (!valid && showPopup) {
            input.reportValidity();
        }

        return valid;
    }

    function syncConditionalRequiredFields() {
        const isNonResident = complainantType && complainantType.value === "Non-Resident";
        if (firstNameInput) firstNameInput.required = isNonResident;
        if (lastNameInput) lastNameInput.required = isNonResident;
        if (residentSelect) residentSelect.required = !isNonResident && !<?= $edit_record && empty($edit_record['resident_id']) ? 'true' : 'false' ?>;
    }

    if (complainantType) {
        complainantType.addEventListener("change", syncConditionalRequiredFields);
    }

    [contactInput, residentContactInput].forEach(function (input) {
        if (input) {
            input.addEventListener("input", function () {
                this.value = this.value.replace(/\D/g, "").slice(0, 11);
                validateContactNumberField(this, false);
            });
            input.addEventListener("blur", function () {
                validateContactNumberField(this, true);
            });
        }
    });

    if (blotterForm) {
        blotterForm.addEventListener("submit", function (event) {
            const isNonResident = complainantType && complainantType.value === "Non-Resident";
            [firstNameInput, middleNameInput, lastNameInput, respondentInput, incidentInput, contactInput, residentContactInput].forEach(function (input) {
                if (input) input.value = input.value.trim();
            });

            if (isNonResident) {
                for (const input of [firstNameInput, lastNameInput]) {
                    if (input && !input.value.trim()) {
                        event.preventDefault();
                        input.setCustomValidity("First Name and Last Name are required for a non-resident complainant.");
                        input.reportValidity();
                        input.setCustomValidity("");
                        return;
                    }
                }
                const namePattern = /^[\p{L}\p{M}' .-]+$/u;
                for (const input of [firstNameInput, middleNameInput, lastNameInput]) {
                    if (input && input.value !== "" && !namePattern.test(input.value)) {
                        event.preventDefault();
                        input.setCustomValidity("Use letters, spaces, apostrophes, periods, or hyphens only.");
                        input.reportValidity();
                        input.setCustomValidity("");
                        return;
                    }
                }
                if (birthDateInput && birthDateInput.value && birthDateInput.value > new Date().toISOString().slice(0, 10)) {
                    event.preventDefault();
                    birthDateInput.setCustomValidity("Birth date cannot be in the future.");
                    birthDateInput.reportValidity();
                    birthDateInput.setCustomValidity("");
                    return;
                }
                if (contactInput && contactInput.value && !/^09[0-9]{9}$/.test(contactInput.value)) {
                    event.preventDefault();
                    contactInput.setCustomValidity("Number must start with 09 and contain exactly 11 digits.");
                    contactInput.reportValidity();
                    return;
                }
            }

            if (!isNonResident && residentContactInput && residentContactInput.value && !/^09[0-9]{9}$/.test(residentContactInput.value)) {
                event.preventDefault();
                residentContactInput.setCustomValidity("Number must start with 09 and contain exactly 11 digits.");
                residentContactInput.reportValidity();
                return;
            }
            if (!isNonResident && residentSelect && residentSelect.required && !residentSelect.value) {
                event.preventDefault();
                residentSelect.setCustomValidity("Please select a resident.");
                residentSelect.reportValidity();
                residentSelect.setCustomValidity("");
                return;
            }
            if (incidentInput && incidentInput.value.trim().length < 2) {
                event.preventDefault();
                incidentInput.setCustomValidity("Incident must be at least 2 characters.");
                incidentInput.reportValidity();
                incidentInput.setCustomValidity("");
                return;
            }
            if (respondentInput && !respondentInput.value.trim()) {
                event.preventDefault();
                respondentInput.setCustomValidity("Respondent is required.");
                respondentInput.reportValidity();
                respondentInput.setCustomValidity("");
                return;
            }
            const respondentNamePattern = /^[\p{L}\p{M}'., -]+$/u;
            if (respondentInput && !respondentNamePattern.test(respondentInput.value.trim())) {
                event.preventDefault();
                respondentInput.setCustomValidity("Respondent name may contain letters, spaces, apostrophes, periods, commas, or hyphens only.");
                respondentInput.reportValidity();
                respondentInput.setCustomValidity("");
                return;
            }
            const incidentPattern = /^[A-Za-z0-9À-ÿÑñ'.,()\/&:;#%!?\- ]+$/u;
            if (incidentInput && incidentInput.value.trim() && !incidentPattern.test(incidentInput.value.trim())) {
                event.preventDefault();
                incidentInput.setCustomValidity("Incident contains invalid characters.");
                incidentInput.reportValidity();
                incidentInput.setCustomValidity("");
                return;
            }
            if (incidentDateInput && incidentDateInput.value && incidentDateInput.value > new Date().toISOString().slice(0, 10)) {
                event.preventDefault();
                incidentDateInput.setCustomValidity("Incident date cannot be in the future.");
                incidentDateInput.reportValidity();
                incidentDateInput.setCustomValidity("");
            }
        });
    }

    syncConditionalRequiredFields();
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const form = document.getElementById("blotterForm");
    const submitButton = document.getElementById("blotterSubmitButton");

    // Only apply this behavior when editing an existing blotter record.
    const isEditing = <?= $edit_record ? "true" : "false" ?>;

    if (!form || !submitButton || !isEditing) {
        return;
    }

    /*
     * All editable fields inside the blotter form.
     * Hidden fields such as CSRF token and record ID are excluded.
     */
    const fields = form.querySelectorAll(
        "input:not([type='hidden']), select, textarea"
    );

    const originalValues = new Map();

    /*
     * Save the values when the Edit page first loads.
     */
    fields.forEach(function (field) {
        originalValues.set(field, field.value);
    });

    function hasChanges() {

        for (const field of fields) {

            const originalValue =
                originalValues.get(field) ?? "";

            const currentValue =
                field.value;

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

    /*
     * Detect typing and selection changes.
     */
    fields.forEach(function (field) {

        field.addEventListener(
            "input",
            updateButtonState
        );

        field.addEventListener(
            "change",
            updateButtonState
        );
    });

    /*
     * Initial state:
     * disabled because nothing has been changed.
     */
    updateButtonState();

});
</script>


<?php

require_once "../includes/footer.php";

?>
