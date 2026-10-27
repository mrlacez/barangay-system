
<?php

require_once __DIR__ . "/../config/admin_auth.php";
require_once __DIR__ . "/../config/database.php";


$username = $_SESSION["username"];
$role = $_SESSION["role"] ?? "User";

$displayRole = ($role === "admin")
    ? "Administrator"
    : $role;


/* =========================
   TOTAL RESIDENTS
========================= */

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM residents"
);

$totalResidents = 0;

if ($result) {
    $totalResidents = $result->fetch_assoc()["total"];
}


/* =========================
   TOTAL OFFICIALS
========================= */

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM officials"
);

$totalOfficials = 0;

if ($result) {
    $totalOfficials = $result->fetch_assoc()["total"];
}


/* =========================
   PENDING CERTIFICATES
========================= */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM certificates
     WHERE status = 'Pending'"
);

$pendingRequests = 0;

if ($result) {
    $pendingRequests = $result->fetch_assoc()["total"];
}


/* =========================
   TOTAL BLOTTER CASES
========================= */

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM blotter"
);

$totalBlotter = 0;

if ($result) {
    $totalBlotter = $result->fetch_assoc()["total"];
}


/* =========================
   TOTAL ANNOUNCEMENTS
========================= */

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM announcements"
);

$totalAnnouncements = 0;

if ($result) {
    $totalAnnouncements = $result->fetch_assoc()["total"];
}


/* =========================
   SERVICE REQUESTS
========================= */

$result = $conn->query("
    SELECT
        SUM(status = 'Pending') AS pending,
        SUM(status = 'Under Review') AS under_review,
        SUM(status = 'Approved') AS approved,
        SUM(status = 'Ready for Pickup') AS ready_pickup,
        SUM(status = 'Completed') AS completed
    FROM service_requests
");

$pendingServiceRequests = 0;
$underReviewRequests = 0;
$approvedRequests = 0;
$readyPickupRequests = 0;
$completedRequests = 0;

if ($result) {
    $serviceRequestStats = $result->fetch_assoc();

    $pendingServiceRequests = (int) ($serviceRequestStats["pending"] ?? 0);
    $underReviewRequests = (int) ($serviceRequestStats["under_review"] ?? 0);
    $approvedRequests = (int) ($serviceRequestStats["approved"] ?? 0);
    $readyPickupRequests = (int) ($serviceRequestStats["ready_pickup"] ?? 0);
    $completedRequests = (int) ($serviceRequestStats["completed"] ?? 0);
}


/* =========================
   RECENT ACTIVITY
========================= */

$recent_activity = $conn->query("
    SELECT
        username,
        role,
        action,
        module,
        description,
        created_at
    FROM audit_logs
    ORDER BY id DESC
    LIMIT 5
");


/* =========================
   SHARED HEADER + SIDEBAR
========================= */

require_once "../includes/header.php";

?>
<style>
/* Match dashboard panels and summary tiles to the blue records-card accent. */
.main-content > .content-section {
    border: 1px solid #e4e9f2;
    border-top: 5px solid #2563eb;
    border-radius: 14px;
}

.main-content > .stats-grid > .stat-card {
    border-top: 4px solid #2563eb;
    border-radius: 12px;
}
</style>


<!-- =========================
     TOP BAR
========================= -->

<header class="topbar">

    <div>

        <h1>
            Dashboard
        </h1>

        <p>
            Barangay Management Overview
        </p>

    </div>


    <div class="admin-profile">

        <div class="admin-avatar">

            <?= strtoupper(
                substr(
                    $username,
                    0,
                    1
                )
            ) ?>

        </div>


        <div>

            <strong>
                <?= htmlspecialchars($username) ?>
            </strong>

            <small>
                <?= htmlspecialchars($displayRole) ?>
            </small>

        </div>

    </div>

</header>


<!-- =========================
     WELCOME CARD
========================= -->

<section class="welcome-card">

    <div>

        <h2>
            Welcome back,
            <?= htmlspecialchars($username) ?>!
        </h2>

        <p>
            Here's what's happening in your barangay today.
        </p>

    </div>


    <div class="welcome-icon">
        🏛️
    </div>

</section>


<!-- =========================
     STATISTICS
========================= -->

<section class="stats-grid">


    <!-- RESIDENTS -->

    <div class="stat-card">

        <div class="stat-icon residents-icon">
            👥
        </div>

        <div class="stat-info">

            <span>
                Total Residents
            </span>

            <strong>
                <?= $totalResidents ?>
            </strong>

        </div>

    </div>


    <!-- OFFICIALS -->

    <div class="stat-card">

        <div class="stat-icon officials-icon">
            🏛️
        </div>

        <div class="stat-info">

            <span>
                Barangay Officials
            </span>

            <strong>
                <?= $totalOfficials ?>
            </strong>

        </div>

    </div>


    <!-- PENDING CERTIFICATES -->

    <div class="stat-card">

        <div class="stat-icon certificate-icon">
            📄
        </div>

        <div class="stat-info">

            <span>
                Pending Requests
            </span>

            <strong>
                <?= $pendingRequests ?>
            </strong>

        </div>

    </div>


    <!-- BLOTTER -->

    <div class="stat-card">

        <div class="stat-icon blotter-icon">
            ⚠️
        </div>

        <div class="stat-info">

            <span>
                Blotter Cases
            </span>

            <strong>
                <?= $totalBlotter ?>
            </strong>

        </div>

    </div>

</section>

<!-- =========================
     SERVICE REQUEST STATUS
========================= -->

<section class="content-section">

    <div class="section-header">
        <div>
            <h2>📋 Online Service Requests</h2>
            <p>Current status of online requests submitted by residents.</p>
        </div>

        <a href="service_requests.php" class="btn btn-primary">
            View Service Requests
        </a>
    </div>

    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-icon certificate-icon">
                ⏳
            </div>

            <div class="stat-info">
                <span>Pending</span>
                <strong><?= $pendingServiceRequests ?></strong>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                🔍
            </div>

            <div class="stat-info">
                <span>Under Review</span>
                <strong><?= $underReviewRequests ?></strong>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                ✅
            </div>

            <div class="stat-info">
                <span>Approved</span>
                <strong><?= $approvedRequests ?></strong>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                📦
            </div>

            <div class="stat-info">
                <span>Ready for Pickup</span>
                <strong><?= $readyPickupRequests ?></strong>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                ✔️
            </div>

            <div class="stat-info">
                <span>Completed</span>
                <strong><?= $completedRequests ?></strong>
            </div>
        </div>

    </div>

</section>


<!-- =========================
     QUICK ACTIONS
========================= -->

<section class="content-section">

    <div class="section-header">

        <div>

            <h2>
                Quick Actions
            </h2>

            <p>
                Frequently used barangay services
            </p>

        </div>

    </div>


    <div class="quick-actions">


        <!-- ADD RESIDENT -->

        <a
            href="residents.php"
            class="action-card"
        >

            <div class="action-icon">
                👥
            </div>

            <div>

                <strong>
                    Add Resident
                </strong>

                <span>
                    Register a new resident
                </span>

            </div>

        </a>


        <!-- OFFICIALS -->

        <a
            href="officials.php"
            class="action-card"
        >

            <div class="action-icon">
                🏛️
            </div>

            <div>

                <strong>
                    Manage Officials
                </strong>

                <span>
                    View barangay officials
                </span>

            </div>

        </a>


        <!-- CERTIFICATES -->

        <a
            href="certificates.php"
            class="action-card"
        >

            <div class="action-icon">
                📄
            </div>

            <div>

                <strong>
                    Certificate Requests
                </strong>

                <span>
                    Manage requests
                </span>

            </div>

        </a>

        <!-- SERVICE REQUESTS -->

        <a
            href="service_requests.php"
            class="action-card"
        >

            <div class="action-icon">
                📋
            </div>

            <div>

                <strong>
                    Service Requests
                </strong>

                <span>
                    Manage online requests
                </span>

            </div>

        </a>


        <!-- BLOTTER -->

        <a
            href="blotter.php"
            class="action-card"
        >

            <div class="action-icon">
                ⚠️
            </div>

            <div>

                <strong>
                    Blotter Records
                </strong>

                <span>
                    View incident records
                </span>

            </div>

        </a>


        <!-- ANNOUNCEMENTS -->

        <a
            href="announcements.php"
            class="action-card"
        >

            <div class="action-icon">
                📢
            </div>

            <div>

                <strong>
                    Announcements
                </strong>

                <span>
                    Manage announcements
                </span>

            </div>

        </a>


        <!-- REPORTS -->

        <a
            href="reports.php"
            class="action-card"
        >

            <div class="action-icon">
                📊
            </div>

            <div>

                <strong>
                    Reports
                </strong>

                <span>
                    View barangay reports
                </span>

            </div>

        </a>

    </div>

</section>


<!-- =========================
     RECENT ACTIVITY
========================= -->

<?php if (in_array(
    strtolower(trim((string) ($_SESSION["role"] ?? ""))),
    ["admin", "administrator"],
    true
)): ?>

<section class="content-section">

    <div class="section-header">

        <div>
            <h2>
                Recent Activity
            </h2>

            <p>
                Latest activities performed in the system.
            </p>
        </div>

        <?php if (
            ($_SESSION["role"] ?? "") === "admin" ||
            ($_SESSION["role"] ?? "") === "Administrator"
        ): ?>

            <a
                href="/barangay-system/admin/audit_logs.php"
                class="btn btn-primary"
            >
                View All Audit Logs
            </a>

        <?php endif; ?>

    </div>


    <div class="recent-activity">

        <?php if (
            $recent_activity &&
            $recent_activity->num_rows > 0
        ): ?>

            <?php while (
                $activity = $recent_activity->fetch_assoc()
            ): ?>

                <div class="activity-item">

                    <div class="activity-icon">
                        📝
                    </div>


                    <div class="activity-content">

                        <div class="activity-main">

                            <strong>
                                <?= htmlspecialchars(
                                    $activity["username"]
                                ) ?>
                            </strong>

                            <span class="activity-action">
                                <?= htmlspecialchars(
                                    $activity["action"]
                                ) ?>
                            </span>

                            <span class="activity-module">
                                <?= htmlspecialchars(
                                    $activity["module"]
                                ) ?>
                            </span>

                        </div>


                        <p>
                            <?= htmlspecialchars(
                                $activity["description"]
                            ) ?>
                        </p>


                        <small>
                            <?= date(
                                "M d, Y h:i A",
                                strtotime(
                                    $activity["created_at"]
                                )
                            ) ?>
                        </small>

                    </div>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="activity-empty">
                No recent activity found.
            </div>

        <?php endif; ?>

    </div>

</section>

<?php endif; ?>


<!-- =========================
     SYSTEM INFORMATION
========================= -->

<section class="content-section">

    <div class="section-header">

        <div>

            <h2>
                System Information
            </h2>

            <p>
                Barangay management system status
            </p>

        </div>

    </div>


    <div class="system-card">


        <!-- SYSTEM STATUS -->

        <div class="system-row">

            <span>
                System Status
            </span>

            <span class="status-active">
                ● Online
            </span>

        </div>


        <!-- USER -->

        <div class="system-row">

            <span>
                Logged-in User
            </span>

            <strong>
                <?= htmlspecialchars($username) ?>
            </strong>

        </div>


        <!-- ROLE -->

        <div class="system-row">

            <span>
                Account Role
            </span>

            <strong>
                <?= htmlspecialchars($displayRole) ?>
            </strong>

        </div>


        <!-- ANNOUNCEMENTS -->

        <div class="system-row">

            <span>
                Total Announcements
            </span>

            <strong>
                <?= $totalAnnouncements ?>
            </strong>

        </div>

    </div>

</section>


<?php

require_once "../includes/footer.php";

?>
