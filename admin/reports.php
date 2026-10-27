<?php

/*
 * Reports is an Admin-only page.
 * admin_auth.php handles session validation and rejects Staff accounts.
 */
require_once __DIR__ . "/../config/admin_auth.php";
require_once __DIR__ . "/../config/database.php";


/* =========================
   GET COUNTS
========================= */

$residents_count = 0;
$officials_count = 0;
$certificates_count = 0;
$blotter_count = 0;
$announcements_count = 0;


/* RESIDENTS */

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM residents"
);

if ($result) {
    $row = $result->fetch_assoc();
    $residents_count = (int) $row['total'];
}


/* OFFICIALS */

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM officials"
);

if ($result) {
    $row = $result->fetch_assoc();
    $officials_count = (int) $row['total'];
}


/* CERTIFICATES */

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM certificates"
);

if ($result) {
    $row = $result->fetch_assoc();
    $certificates_count = (int) $row['total'];
}


/* BLOTTER */

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM blotter"
);

if ($result) {
    $row = $result->fetch_assoc();
    $blotter_count = (int) $row['total'];
}


/* ANNOUNCEMENTS */

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM announcements"
);

if ($result) {
    $row = $result->fetch_assoc();
    $announcements_count = (int) $row['total'];
}


/* =========================
   CERTIFICATE STATUS
========================= */

$pending_certificates = 0;
$approved_certificates = 0;
$released_certificates = 0;
$rejected_certificates = 0;


$result = $conn->query(
    "SELECT status, COUNT(*) AS total
     FROM certificates
     GROUP BY status"
);

if ($result) {

    while ($row = $result->fetch_assoc()) {

        switch ($row['status']) {

            case 'Pending':
                $pending_certificates = (int) $row['total'];
                break;

            case 'Approved':
                $approved_certificates = (int) $row['total'];
                break;

            case 'Released':
                $released_certificates = (int) $row['total'];
                break;

            case 'Rejected':
                $rejected_certificates = (int) $row['total'];
                break;
        }
    }
}


/* =========================
   BLOTTER STATUS
========================= */

$pending_blotter = 0;
$investigation_blotter = 0;
$resolved_blotter = 0;
$dismissed_blotter = 0;


$result = $conn->query(
    "SELECT status, COUNT(*) AS total
     FROM blotter
     GROUP BY status"
);

if ($result) {

    while ($row = $result->fetch_assoc()) {

        switch ($row['status']) {

            case 'Pending':
                $pending_blotter = (int) $row['total'];
                break;

            case 'Under Investigation':
                $investigation_blotter = (int) $row['total'];
                break;

            case 'Resolved':
                $resolved_blotter = (int) $row['total'];
                break;

            case 'Dismissed':
                $dismissed_blotter = (int) $row['total'];
                break;
        }
    }
}


/* =========================
   RECENT ANNOUNCEMENTS
========================= */

$recent_announcements = $conn->query(
    "SELECT *
     FROM announcements
     ORDER BY id DESC
     LIMIT 5"
);


/* =========================
   HEADER
========================= */

require_once "../includes/header.php";

?>


<!-- =========================
     TOP BAR
========================= -->

<div class="topbar">

    <div>

        <h1>Reports</h1>

        <p>
            Barangay management system reports and summaries
        </p>

    </div>


    <div class="admin-profile">

        <div class="admin-avatar">

            <?= strtoupper(
                substr(
                    $_SESSION['username'] ?? 'A',
                    0,
                    1
                )
            ) ?>

        </div>

        <div>

            <strong>
                <?= htmlspecialchars(
                    $_SESSION['username'] ?? 'Administrator'
                ) ?>
            </strong>

            <?php
            $role = $_SESSION['role'] ?? 'Staff';
            $displayRole = ($role === 'admin') ? 'Administrator' : $role;
            ?>

            <small><?= htmlspecialchars($displayRole) ?></small>

        </div>

    </div>

</div>


<!-- =========================
     REPORT HEADER
========================= -->

<section class="content-section report-print-area" id="report-records">

    <div class="section-header">

        <div>

            <h2>
                Barangay Management Summary
            </h2>

            <p>
                Summary of records currently stored in the system.
            </p>

        </div>


        <button
            type="button"
            class="btn btn-primary no-print"
            onclick="window.print()"
        >
            Print Report
        </button>

    </div>


    <!-- =========================
         SUMMARY CARDS
    ========================= -->

    <div class="dashboard-cards">


        <!-- RESIDENTS -->

        <div class="stat-card">

            <div class="stat-icon">
                👥
            </div>

            <div>

                <span>
                    Total Residents
                </span>

                <strong>
                    <?= $residents_count ?>
                </strong>

            </div>

        </div>


        <!-- OFFICIALS -->

        <div class="stat-card">

            <div class="stat-icon">
                🏛️
            </div>

            <div>

                <span>
                    Total Officials
                </span>

                <strong>
                    <?= $officials_count ?>
                </strong>

            </div>

        </div>


        <!-- CERTIFICATES -->

        <div class="stat-card">

            <div class="stat-icon">
                📄
            </div>

            <div>

                <span>
                    Total Certificates
                </span>

                <strong>
                    <?= $certificates_count ?>
                </strong>

            </div>

        </div>


        <!-- BLOTTER -->

        <div class="stat-card">

            <div class="stat-icon">
                ⚠️
            </div>

            <div>

                <span>
                    Total Blotter
                </span>

                <strong>
                    <?= $blotter_count ?>
                </strong>

            </div>

        </div>


        <!-- ANNOUNCEMENTS -->

        <div class="stat-card">

            <div class="stat-icon">
                📢
            </div>

            <div>

                <span>
                    Announcements
                </span>

                <strong>
                    <?= $announcements_count ?>
                </strong>

            </div>

        </div>

    </div>

</section>


<!-- =========================
     CERTIFICATE REPORT
========================= -->

<section class="content-section report-print-area" id="certificate-report">

    <div class="section-header">

        <div>

            <h2>
                Certificate Summary
            </h2>

            <p>
                Certificate requests according to status.
            </p>

        </div>

    </div>


    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>Status</th>

                    <th>Total</th>

                </tr>

            </thead>

            <tbody>

                <tr>

                    <td>
                        Pending
                    </td>

                    <td>
                        <?= $pending_certificates ?>
                    </td>

                </tr>


                <tr>

                    <td>
                        Approved
                    </td>

                    <td>
                        <?= $approved_certificates ?>
                    </td>

                </tr>


                <tr>

                    <td>
                        Released
                    </td>

                    <td>
                        <?= $released_certificates ?>
                    </td>

                </tr>


                <tr>

                    <td>
                        Rejected
                    </td>

                    <td>
                        <?= $rejected_certificates ?>
                    </td>

                </tr>

            </tbody>

        </table>

    </div>

</section>


<!-- =========================
     BLOTTER REPORT
========================= -->

<section class="content-section report-print-area" id="blotter-report">

    <div class="section-header">

        <div>

            <h2>
                Blotter Summary
            </h2>

            <p>
                Blotter records according to status.
            </p>

        </div>

    </div>


    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>Status</th>

                    <th>Total</th>

                </tr>

            </thead>

            <tbody>

                <tr>

                    <td>
                        Pending
                    </td>

                    <td>
                        <?= $pending_blotter ?>
                    </td>

                </tr>


                <tr>

                    <td>
                        Under Investigation
                    </td>

                    <td>
                        <?= $investigation_blotter ?>
                    </td>

                </tr>


                <tr>

                    <td>
                        Resolved
                    </td>

                    <td>
                        <?= $resolved_blotter ?>
                    </td>

                </tr>


                <tr>

                    <td>
                        Dismissed
                    </td>

                    <td>
                        <?= $dismissed_blotter ?>
                    </td>

                </tr>

            </tbody>

        </table>

    </div>

</section>


<!-- =========================
     RECENT ANNOUNCEMENTS
========================= -->

<section class="content-section report-print-area" id="recent-announcements">

    <div class="section-header">

        <div>

            <h2>
                Recent Announcements
            </h2>

            <p>
                Latest announcements posted in the system.
            </p>

        </div>

    </div>


    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>#</th>

                    <th>Title</th>

                    <th>Content</th>

                    <th>Date Posted</th>

                </tr>

            </thead>


            <tbody>

            <?php if (
                $recent_announcements &&
                $recent_announcements->num_rows > 0
            ): ?>

                <?php $counter = 1; ?>

                <?php while (
                    $row = $recent_announcements->fetch_assoc()
                ): ?>

                    <tr>

                        <td>
                            <?= $counter++ ?>
                        </td>

                        <td>
                            <strong>
                                <?= htmlspecialchars(
                                    $row['title']
                                ) ?>
                            </strong>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $row['content']
                            ) ?>
                        </td>

                        <td>
                            <?= date(
                                'M d, Y',
                                strtotime(
                                    $row['created_at']
                                )
                            ) ?>
                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="4"
                        style="text-align:center; padding:30px;"
                    >
                        No announcements found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<!-- =========================
     REPORT NAVIGATION CSS
========================= -->

<style>

.report-navigation {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 0 0 20px;
    padding: 14px 0;
}

.report-navigation .btn {
    text-decoration: none;
}

.report-print-area {
    border-top: 5px solid #2563eb;
    scroll-margin-top: 20px;
}

</style>


<!-- =========================
     PRINT CSS
========================= -->

<style>

@media print {

    .sidebar,
    .topbar .admin-profile,
    .no-print {
        display: none !important;
    }

    .main-content {
        margin-left: 0 !important;
        width: 100% !important;
    }

    .content-section {
        box-shadow: none !important;
        border: 1px solid #ddd;
        page-break-inside: avoid;
    }

    body {
        background: white !important;
    }

}

</style>


<?php

require_once "../includes/footer.php";

?>
