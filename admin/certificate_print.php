<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../config/database.php";
require_once "../config/audit.php";

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}


/* =========================================
   GET CERTIFICATE OR ONLINE REQUEST
========================================= */

$certificate_id = (int)($_GET["id"] ?? 0);
$online_request_id = (int)($_GET["request_id"] ?? 0);
$auditRecordId = $certificate_id;

if ($certificate_id <= 0 && $online_request_id <= 0) {
    header("Location: certificates.php");
    exit;
}

if ($online_request_id > 0) {

    // Fetch original online request
    $stmt = $conn->prepare("
        SELECT
            id,
            reference_no,
            service_type,
            full_name,
            purpose,
            created_at,
            status
        FROM service_requests
        WHERE id = ?
          AND service_type IN (
              'Barangay Certificate',
              'Certificate of Residency',
              'Certificate of Indigency'
          )
        LIMIT 1
    ");

    $stmt->bind_param("i", $online_request_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        $stmt->close();
        header("Location: certificates.php#online-requests");
        exit;
    }

    $onlineRequest = $result->fetch_assoc();
    $stmt->close();

    // Only approved online requests may be printed
    if (($onlineRequest["status"] ?? "") !== "Approved") {
        header("Location: certificates.php#online-requests");
        exit;
    }

    $certificate = [
        "id" => (int)$onlineRequest["id"],
        "resident_name" => $onlineRequest["full_name"] ?? "",
        "certificate_type" => $onlineRequest["service_type"] ?? "",
        "purpose" => $onlineRequest["purpose"] ?? "",
        "date_requested" => !empty($onlineRequest["created_at"])
            ? date("Y-m-d", strtotime($onlineRequest["created_at"]))
            : date("Y-m-d"),
        "date_issued" => date("Y-m-d"),
        "status" => $onlineRequest["status"],
        "reference_no" => $onlineRequest["reference_no"] ?? ""
    ];

    $auditRecordId = $online_request_id;

} else {

    // Keep existing manually entered certificates working
    $stmt = $conn->prepare("
        SELECT
            id,
            reference_no,
            resident_name,
            certificate_type,
            purpose,
            date_requested,
            date_issued,
            status
        FROM certificates
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $certificate_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        $stmt->close();
        header("Location: certificates.php#certificate-records");
        exit;
    }

    $certificate = $result->fetch_assoc();
    $stmt->close();
}

// Only approved/released certificates can be printed
if (
    ($certificate["status"] ?? "") !== "Approved" &&
    ($certificate["status"] ?? "") !== "Released"
) {
    header("Location: certificates.php");
    exit;
}


/* ==========================================================
   GET BARANGAY SETTINGS
========================================================== */

$settings_result = $conn->query("
    SELECT
        barangay_name,
        municipality_city,
        province,
        punong_barangay,
        barangay_secretary,
        contact_number,
        address,
        barangay_logo
    FROM barangay_settings
    ORDER BY id ASC
    LIMIT 1
");


if ($settings_result && $settings_result->num_rows > 0) {

    $barangay = $settings_result->fetch_assoc();

} else {

    $barangay = [
        "barangay_name" => "",
        "municipality_city" => "",
        "province" => "",
        "punong_barangay" => "",
        "barangay_secretary" => "",
        "contact_number" => "",
        "address" => "",
        "barangay_logo" => ""
    ];
}


/* ==========================================================
   AUDIT LOG
========================================================== */

addAuditLog($conn,"PRINT","Certificates","Printed certificate: ".$certificate["certificate_type"].".",$auditRecordId);


/* ==========================================================
   SAFE DISPLAY VALUES
========================================================== */

$barangay_name = htmlspecialchars(
    $barangay["barangay_name"] ?? ""
);

$municipality_city = htmlspecialchars(
    $barangay["municipality_city"] ?? ""
);

$province = htmlspecialchars(
    $barangay["province"] ?? ""
);

$punong_barangay = htmlspecialchars(
    $barangay["punong_barangay"] ?? ""
);

$barangay_secretary = htmlspecialchars(
    $barangay["barangay_secretary"] ?? ""
);

$contact_number = htmlspecialchars(
    $barangay["contact_number"] ?? ""
);

$address = htmlspecialchars(
    $barangay["address"] ?? ""
);

$resident_name = htmlspecialchars(
    $certificate["resident_name"]
);

$certificate_type = htmlspecialchars(
    strtoupper($certificate["certificate_type"])
);

$purpose = htmlspecialchars(
    $certificate["purpose"]
);

$date_issued = !empty($certificate["date_issued"])
    ? date("F d, Y", strtotime($certificate["date_issued"]))
    : date("F d, Y");

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
    <?= $certificate_type ?> - <?= $resident_name ?>
</title>


<style>

* {
    box-sizing: border-box;
}


body {

    margin: 0;

    background: #eef2f7;

    color: #111827;

    font-family: "Times New Roman", Times, serif;

}


/* ==========================================================
   PRINT CONTROLS
========================================================== */

.print-controls {

    width: 8.5in;

    margin: 20px auto 12px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    font-family: Arial, sans-serif;

}


.back-button,
.print-button {

    border: none;

    border-radius: 6px;

    padding: 10px 16px;

    font-size: 13px;

    text-decoration: none;

    cursor: pointer;

}


.back-button {

    background: #e2e8f0;

    color: #334155;

}


.print-button {

    background: #2563eb;

    color: white;

}


.print-button:hover {

    background: #1d4ed8;

}


/* ==========================================================
   CERTIFICATE PAPER
========================================================== */

.certificate-paper {

    position: relative;

    width: 8.5in;

    min-height: 11in;

    margin: 0 auto 30px;

    padding: 0.55in 0.65in;

    background: #ffffff;

    border: 1px solid #cbd5e1;

}


/* ==========================================================
   DECORATIVE BORDER
========================================================== */

.certificate-border {

    position: absolute;

    top: 0.25in;

    left: 0.25in;

    right: 0.25in;

    bottom: 0.25in;

    border: 2px solid #1e3a8a;

    pointer-events: none;

}


.certificate-border-inner {

    position: absolute;

    top: 0.08in;

    left: 0.08in;

    right: 0.08in;

    bottom: 0.08in;

    border: 1px solid #94a3b8;

}


/* ==========================================================
   HEADER
========================================================== */

.certificate-header {

    position: relative;

    z-index: 2;

    text-align: center;

    line-height: 1.35;

}


.republic {

    font-size: 14px;

}


.province {

    font-size: 14px;

}


.municipality {

    font-size: 14px;

}


.barangay {

    margin-top: 4px;

    font-size: 17px;

    font-weight: bold;

    text-transform: uppercase;

}


/* ==========================================================
   SEAL PLACEHOLDER
========================================================== */

.seal-area {

    margin: 18px auto 0;

    width: 72px;

    height: 72px;

    border: 2px solid #1e3a8a;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    text-align: center;

    font-family: Arial, sans-serif;

    font-size: 9px;

    color: #1e3a8a;

    font-weight: bold;

}


.seal-area span {

    display: block;

}


.barangay-seal {
    width: 105px;
    height: 105px;
    object-fit: contain;
    display: block;
    margin: 14px auto 18px;
}


/* ==========================================================
   TITLE
========================================================== */

.certificate-title {
    position: relative;
    z-index: 2;
    margin-top: 18px;
    margin-bottom: 28px;
    text-align: center;
    font-size: 23px;
    font-weight: bold;
    letter-spacing: 1px;
    text-transform: uppercase;
}


/* ==========================================================
   BODY
========================================================== */

.certificate-body {
    position: relative;
    z-index: 2;
    padding: 0 0.35in;
    font-size: 15px;
    line-height: 1.75;
    text-align: justify;
}


.to-whom {

    margin-bottom: 20px;

    font-weight: bold;

    text-align: left;

}


.resident-name {

    font-weight: bold;

    text-transform: uppercase;

}


.purpose {

    font-weight: bold;

}


/* ==========================================================
   ISSUED
========================================================== */

.issued {

    margin-top: 25px;

}


/* ==========================================================
   SIGNATURE
========================================================== */

.signature-section {
    position: relative;
    z-index: 2;
    margin-top: 48px;
    display: flex;
    justify-content: flex-end;
    gap: 45px;
    padding-right: 0.3in;
}


.signature {
    width: 190px;
    text-align: center;
}


.signature-line {

    border-top: 1px solid #111827;

    margin-bottom: 6px;

}


.signature-name {

    font-weight: bold;

    text-transform: uppercase;

    font-size: 14px;

}


.signature-position {

    font-size: 13px;

}


/* ==========================================================
   CERTIFICATE NUMBER
========================================================== */

.certificate-number {

    position: relative;

    z-index: 2;

    margin-top: 45px;

    padding: 0 0.25in;

    font-size: 12px;

}


/* ==========================================================
   DOCUMENT FOOTER
========================================================== */

.document-footer {

    position: relative;

    z-index: 2;

    margin-top: 28px;

    text-align: center;

    font-size: 11px;

    color: #475569;

}


.document-contact {

    margin-top: 3px;

}


/* ==========================================================
   PRINT
========================================================== */

@media print {

    @page {

        size: Letter;

        margin: 0;

    }


    body {

        background: #ffffff;

    }


    .print-controls {

        display: none !important;

    }


    .certificate-paper {

        width: 8.5in;

        min-height: 11in;

        margin: 0;

        border: none;

        padding: 0.55in 0.65in;

    }


    .certificate-border {

        display: block;

    }

}


/* ==========================================================
   SCREEN
========================================================== */

@media screen {

    .certificate-paper {

        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);

    }

}

</style>

</head>


<body>


<!-- ========================================================
     PRINT CONTROLS
========================================================= -->

<div class="print-controls">

    <a
        href="<?= $online_request_id > 0 ? 'certificates.php#online-requests' : 'certificates.php#certificate-records' ?>"
        class="back-button"
    >
        ← Back
    </a>


    <button
        type="button"
        class="print-button"
        onclick="window.print()"
    >
        🖨️ Print Certificate
    </button>

</div>



<!-- ========================================================
     CERTIFICATE
========================================================= -->

<div class="certificate-paper">


    <!-- DECORATIVE BORDER -->

    <div class="certificate-border">

        <div class="certificate-border-inner"></div>

    </div>



    <!-- HEADER -->

    <div class="certificate-header">

        <div class="republic">
            Republic of the Philippines
        </div>

        <div class="province">
            Province of <?= $province ?>
        </div>

        <div class="municipality">
            Municipality/City of <?= $municipality_city ?>
        </div>

        <div class="barangay">
            BARANGAY <?= $barangay_name ?>
        </div>


        <?php if (!empty($barangay["barangay_logo"])): ?>

            <img
                src="../<?= htmlspecialchars($barangay["barangay_logo"]) ?>"
                alt="Barangay Seal"
                class="barangay-seal">

        <?php endif; ?>

    </div>



    <!-- TITLE -->

    <div class="certificate-title">

        <?= $certificate_type ?>

    </div>



    <!-- BODY -->

    <div class="certificate-body">

        <div class="to-whom">
            TO WHOM IT MAY CONCERN:
        </div>

        <?php if (in_array($certificate["certificate_type"], ["Barangay Clearance", "Barangay Certificate"], true)): ?>

            <p>
                This is to certify that
                <span class="resident-name"><?= $resident_name ?></span>
                is a resident of Barangay
                <span class="resident-name"><?= $barangay_name ?></span>,
                <?= $municipality_city ?>, <?= $province ?>,
                and that the information contained in this certification
                is based on the records available in the Barangay Management System.
            </p>

            <p>
                This certification is issued upon the request of the
                above-named person for
                <span class="purpose"><?= $purpose ?></span>
                and for whatever lawful purpose it may serve.
            </p>


        <?php elseif ($certificate["certificate_type"] === "Certificate of Indigency"): ?>

            <p>
                This is to certify that
                <span class="resident-name"><?= $resident_name ?></span>
                is a resident of Barangay
                <span class="resident-name"><?= $barangay_name ?></span>,
                <?= $municipality_city ?>, <?= $province ?>.
            </p>

            <p>
                This certification is issued to certify the
                financial status of the above-named person based on
                the available records of this barangay and is issued
                upon request for
                <span class="purpose"><?= $purpose ?></span>.
            </p>


        <?php elseif ($certificate["certificate_type"] === "Certificate of Residency"): ?>

            <p>
                This is to certify that
                <span class="resident-name"><?= $resident_name ?></span>
                is a bona fide resident of Barangay
                <span class="resident-name"><?= $barangay_name ?></span>,
                <?= $municipality_city ?>, <?= $province ?>.
            </p>

            <p>
                This certification is issued upon the request of the
                above-named person for
                <span class="purpose"><?= $purpose ?></span>
                and for whatever lawful purpose it may serve.
            </p>


        <?php elseif ($certificate["certificate_type"] === "Certificate of Good Moral Character"): ?>

            <p>
                This is to certify that
                <span class="resident-name"><?= $resident_name ?></span>
                is a resident of Barangay
                <span class="resident-name"><?= $barangay_name ?></span>,
                <?= $municipality_city ?>, <?= $province ?>,
                and is known to this barangay to be a person of
                good moral character based on available barangay records.
            </p>

            <p>
                This certification is issued upon the request of the
                above-named person for
                <span class="purpose"><?= $purpose ?></span>
                and for whatever lawful purpose it may serve.
            </p>


        <?php elseif ($certificate["certificate_type"] === "Certificate of No Business"): ?>

            <p>
                This is to certify that
                <span class="resident-name"><?= $resident_name ?></span>
                is a resident of Barangay
                <span class="resident-name"><?= $barangay_name ?></span>,
                <?= $municipality_city ?>, <?= $province ?>.
            </p>

            <p>
                Based on the available records of this barangay,
                the above-named person has no registered business
                recorded in this barangay as of the date of issuance
                of this certification.
            </p>

            <p>
                This certification is issued upon request for
                <span class="purpose"><?= $purpose ?></span>.
            </p>


        <?php else: ?>

            <p>
                This is to certify that
                <span class="resident-name"><?= $resident_name ?></span>
                is a resident of Barangay
                <span class="resident-name"><?= $barangay_name ?></span>,
                <?= $municipality_city ?>, <?= $province ?>,
                and that the information contained in this certification
                is based on the records available in the Barangay Management System.
            </p>

            <p>
                This certification is issued upon the request of the
                above-named person for
                <span class="purpose"><?= $purpose ?></span>
                and for whatever lawful purpose it may serve.
            </p>

        <?php endif; ?>


        <p class="issued">

            Issued this
            <strong><?= $date_issued ?></strong>
            at Barangay
            <strong><?= $barangay_name ?></strong>,
            <?= $municipality_city ?>,
            <?= $province ?>, Philippines.

        </p>

    </div>



    <!-- SIGNATURE -->

    <div class="signature-section">

        <div class="signature">

            <div class="signature-line"></div>

            <div class="signature-name">
                <?= $punong_barangay ?>
            </div>

            <div class="signature-position">
                Punong Barangay
            </div>

        </div>


        <?php if ($barangay_secretary !== ""): ?>

            <div class="signature">

                <div class="signature-line"></div>

                <div class="signature-name">
                    <?= $barangay_secretary ?>
                </div>

                <div class="signature-position">
                    Barangay Secretary
                </div>

            </div>

        <?php endif; ?>

    </div>



    <!-- CERTIFICATE NUMBER -->

    <div class="certificate-number">

        Certificate Request No.:

        <strong>
            <?= htmlspecialchars(!empty($certificate["reference_no"]) ? $certificate["reference_no"] : (string)$certificate["id"]) ?>
        </strong>

    </div>



    <!-- FOOTER -->

    <div class="document-footer">

        <div>
            Barangay <?= $barangay_name ?>,
            <?= $municipality_city ?>,
            <?= $province ?>
        </div>

        <?php if ($contact_number !== ""): ?>

            <div class="document-contact">
                Contact No.: <?= $contact_number ?>
            </div>

        <?php endif; ?>

    </div>


</div>


</body>

</html>