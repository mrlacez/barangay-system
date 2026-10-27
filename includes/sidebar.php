<aside class="sidebar">

    <div class="sidebar-header">
        <div class="barangay-logo">
            🏛️
        </div>

        <div>
            <h2>BARANGAY</h2>
            <span>Management System</span>
        </div>
    </div>

    <nav class="sidebar-menu">

        <!-- DASHBOARD -->
        <a href="/barangay-system/admin/dashboard.php" title="Dashboard" aria-label="Dashboard"
           class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">
            <span class="menu-icon dashboard-icon">▣</span>
            <span>Dashboard</span>
        </a>

        <!-- RESIDENTS -->
        <a href="/barangay-system/admin/residents.php" title="Residents" aria-label="Residents"
           class="<?= basename($_SERVER['PHP_SELF']) === 'residents.php' ? 'active' : '' ?>">
            <span class="menu-icon residents-icon">👥</span>
            <span>Residents</span>
        </a>

        <!-- OFFICIALS -->
        <a href="/barangay-system/admin/officials.php" title="Officials" aria-label="Officials"
           class="<?= basename($_SERVER['PHP_SELF']) === 'officials.php' ? 'active' : '' ?>">
            <span class="menu-icon officials-icon">🏛️</span>
            <span>Officials</span>
        </a>

        <!-- CERTIFICATES -->
        <a href="/barangay-system/admin/certificates.php" title="Certificates" aria-label="Certificates"
           class="<?= basename($_SERVER['PHP_SELF']) === 'certificates.php' ? 'active' : '' ?>">
            <span class="menu-icon certificates-icon">📄</span>
            <span>Certificates</span>
        </a>

        <!-- BLOTTER -->
        <a href="/barangay-system/admin/blotter.php" title="Blotter" aria-label="Blotter"
           class="<?= basename($_SERVER['PHP_SELF']) === 'blotter.php' ? 'active' : '' ?>">
            <span class="menu-icon blotter-icon">⚠️</span>
            <span>Blotter</span>
        </a>

        <!-- ANNOUNCEMENTS -->
        <a href="/barangay-system/admin/announcements.php" title="Announcements" aria-label="Announcements"
           class="<?= basename($_SERVER['PHP_SELF']) === 'announcements.php' ? 'active' : '' ?>">
            <span class="menu-icon announcements-icon">📢</span>
            <span>Announcements</span>
        </a>

        <!-- SERVICE REQUESTS -->
        <a
            href="/barangay-system/admin/service_requests.php"
            title="Service Requests"
            aria-label="Service Requests"
            class="<?= (
                basename($_SERVER['PHP_SELF']) === 'service_requests.php'
                && ($_GET['service'] ?? '') === ''
            ) ? 'active' : '' ?>"
        >
            <span class="menu-icon service-requests-icon">📋</span>
            <span>Service Requests</span>
        </a>


        <!-- COMMUNITY ASSISTANCE -->
        <a
            href="/barangay-system/admin/community_assistance.php"
            title="Community Assistance"
            aria-label="Community Assistance"
            class="<?= basename($_SERVER['PHP_SELF']) === 'community_assistance.php' ? 'active' : '' ?>"
        >
            <span class="menu-icon community-assistance-icon">🏘️</span>
            <span>Community Assistance</span>
        </a>

        <!-- REPORTS -->
        <a href="/barangay-system/admin/reports.php" title="Reports" aria-label="Reports"
           class="<?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>">
            <span class="menu-icon reports-icon">📊</span>
            <span>Reports</span>
        </a>


        <!-- ===================================== -->
        <!-- ADMINISTRATOR ONLY MENU               -->
        <!-- ===================================== -->

        <?php if (
            ($_SESSION["role"] ?? "") === "admin" ||
            ($_SESSION["role"] ?? "") === "Administrator"
        ): ?>

            <!-- BARANGAY SETTINGS -->
            <a href="/barangay-system/admin/barangay_settings.php" title="Barangay Settings" aria-label="Barangay Settings"
               class="<?= basename($_SERVER['PHP_SELF']) === 'barangay_settings.php' ? 'active' : '' ?>">
                <span class="menu-icon">⚙️</span>
                <span>Barangay Settings</span>
            </a>

            <!-- AUDIT LOGS -->
            <a href="/barangay-system/admin/audit_logs.php" title="Audit Logs" aria-label="Audit Logs"
               class="<?= basename($_SERVER['PHP_SELF']) === 'audit_logs.php' ? 'active' : '' ?>">
                <span class="menu-icon audit-icon">📝</span>
                <span>Audit Logs</span>
            </a>

            <!-- USER MANAGEMENT -->
            <a href="/barangay-system/admin/users.php" title="User Management" aria-label="User Management"
               class="<?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>">
                <span class="menu-icon users-icon">👤</span>
                <span>User Management</span>
            </a>

            <a href="/barangay-system/admin/database_backup.php" title="Database Backup" aria-label="Database Backup"
               class="<?= basename($_SERVER['PHP_SELF']) === 'database_backup.php' ? 'active' : '' ?>">
                <span class="menu-icon">💾</span>
                <span>Database Backup</span>
            </a>

        <?php endif; ?>

    </nav>

    <!-- LOGOUT -->
    <div class="sidebar-bottom">
        <a href="/barangay-system/logout.php" title="Logout" aria-label="Logout">
            <span class="menu-icon logout-icon">↪</span>
            <span>Logout</span>
        </a>
    </div>

</aside>


<script>
document.addEventListener("DOMContentLoaded", function () {

    const sidebarMenu = document.querySelector(".sidebar-menu");
    const activeMenu = document.querySelector(".sidebar-menu a.active");

    if (sidebarMenu && activeMenu) {

        const menuTop = activeMenu.offsetTop;
        const menuHeight = activeMenu.offsetHeight;
        const visibleHeight = sidebarMenu.clientHeight;

        const currentScroll = sidebarMenu.scrollTop;

        if (menuTop < currentScroll) {
            sidebarMenu.scrollTop = menuTop - 20;
        }
        else if (menuTop + menuHeight > currentScroll + visibleHeight) {
            sidebarMenu.scrollTop =
                menuTop - visibleHeight + menuHeight + 20;
        }
    }

});
</script>


<style>
/* =========================================
   SIDEBAR COLLAPSE FIX
   Applies only to the sidebar and main content
========================================= */

:root {
    --sidebar-collapsed-width: 78px;
    --sidebar-expanded-width: 260px;
    --sidebar-transition: 0.25s ease;
}

/* SIDEBAR CONTAINER */
body .sidebar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    bottom: 0 !important;

    width: var(--sidebar-collapsed-width) !important;
    min-width: var(--sidebar-collapsed-width) !important;
    height: 100vh !important;

    display: flex !important;
    flex-direction: column !important;

    margin: 0 !important;
    padding: 0 !important;

    overflow: hidden !important;
    box-sizing: border-box !important;

    background: #111827 !important;
    color: #ffffff !important;

    z-index: 2000 !important;

    transition: width var(--sidebar-transition) !important;
}

/* EXPAND WHEN HOVERED */
body .sidebar:hover,
body .sidebar:focus-within {
    width: var(--sidebar-expanded-width) !important;
}

/* HEADER */
body .sidebar .sidebar-header {
    flex: 0 0 90px !important;
    width: 100% !important;
    height: 90px !important;

    display: flex !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 12px !important;

    padding: 0 16px !important;
    margin: 0 !important;

    overflow: hidden !important;
    white-space: nowrap !important;
    box-sizing: border-box !important;

    border-bottom: 1px solid #263244 !important;
}

/* LOGO */
body .sidebar .barangay-logo {
    flex: 0 0 44px !important;
    width: 44px !important;
    min-width: 44px !important;
    height: 44px !important;

    display: flex !important;
    align-items: center !important;
    justify-content: center !important;

    margin: 0 !important;
    padding: 0 !important;

    box-sizing: border-box !important;
}

/* HEADER TEXT */
body .sidebar .sidebar-header > div:last-child {
    flex: 1 1 auto !important;
    min-width: 0 !important;
    overflow: hidden !important;
}

body .sidebar .sidebar-header h2,
body .sidebar .sidebar-header span {
    white-space: nowrap !important;
}

/* MENU AREA */
body .sidebar .sidebar-menu {
    flex: 1 1 auto !important;
    min-height: 0 !important;
    width: 100% !important;

    overflow-x: hidden !important;
    overflow-y: auto !important;

    padding: 14px 8px 84px !important;
    margin: 0 !important;

    box-sizing: border-box !important;
}

/* MENU LINKS AND LOGOUT LINK */
body .sidebar .sidebar-menu > a,
body .sidebar .sidebar-bottom > a {
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: flex-start !important;

    flex: 0 0 auto !important;
    gap: 12px !important;

    width: 100% !important;
    min-width: 0 !important;
    min-height: 50px !important;

    padding: 12px 13px !important;
    margin: 0 0 5px !important;

    color: #d1d5db !important;
    background: transparent !important;

    border: 0 !important;
    border-radius: 8px !important;

    font-size: 14px !important;
    line-height: 1.35 !important;
    text-align: left !important;
    text-decoration: none !important;
    white-space: nowrap !important;

    overflow: hidden !important;
    box-sizing: border-box !important;
}

/* HOVER */
body .sidebar .sidebar-menu > a:hover,
body .sidebar .sidebar-bottom > a:hover {
    background: #1f2937 !important;
    color: #ffffff !important;
}

/* ACTIVE LINK */
body .sidebar .sidebar-menu > a.active {
    background: #2563eb !important;
    color: #ffffff !important;
}

/* ICONS ONLY — FIXED WIDTH */
body .sidebar .menu-icon {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;

    flex: 0 0 24px !important;
    width: 24px !important;
    min-width: 24px !important;
    max-width: 24px !important;
    height: 24px !important;

    margin: 0 !important;
    padding: 0 !important;

    font-size: 18px !important;
    line-height: 1 !important;
    text-align: center !important;
    white-space: nowrap !important;

    overflow: visible !important;
    box-sizing: border-box !important;
}

/* TEXT LABELS — NEVER USE ICON WIDTH */
body .sidebar .sidebar-menu > a > span:not(.menu-icon),
body .sidebar .sidebar-bottom > a > span:not(.menu-icon) {
    display: block !important;

    flex: 1 1 auto !important;
    width: auto !important;
    min-width: 0 !important;

    margin: 0 !important;
    padding: 0 !important;

    font-size: 14px !important;
    line-height: 1.35 !important;
    text-align: left !important;
    white-space: nowrap !important;

    overflow: hidden !important;
    text-overflow: ellipsis !important;
    box-sizing: border-box !important;
}

/* COLLAPSED HEADER */
body .sidebar:not(:hover):not(:focus-within) .sidebar-header {
    justify-content: center !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

body .sidebar:not(:hover):not(:focus-within)
.sidebar-header > div:last-child {
    display: none !important;
}

/* COLLAPSED MENU — CENTER ICONS */
body .sidebar:not(:hover):not(:focus-within)
.sidebar-menu > a,
body .sidebar:not(:hover):not(:focus-within)
.sidebar-bottom > a {
    justify-content: center !important;
    gap: 0 !important;
    padding-left: 0 !important;
    padding-right: 0 !important;
}

/* HIDE LABELS WHEN COLLAPSED */
body .sidebar:not(:hover):not(:focus-within)
.sidebar-menu > a > span:not(.menu-icon),
body .sidebar:not(:hover):not(:focus-within)
.sidebar-bottom > a > span:not(.menu-icon) {
    display: none !important;
}

/* LOGOUT FIXED AT BOTTOM OF SIDEBAR */
body .sidebar .sidebar-bottom {
    position: absolute !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;

    width: 100% !important;
    height: 72px !important;

    display: flex !important;
    align-items: center !important;

    padding: 10px 8px !important;
    margin: 0 !important;

    overflow: hidden !important;
    box-sizing: border-box !important;

    background: #111827 !important;
    border-top: 1px solid #263244 !important;

    z-index: 20 !important;
}

body .sidebar .sidebar-bottom > a {
    margin: 0 !important;
}

/* MAIN CONTENT */
body .main-content {
    margin-left: var(--sidebar-collapsed-width) !important;
    width: calc(100% - var(--sidebar-collapsed-width)) !important;
    min-width: 0 !important;

    box-sizing: border-box !important;

    transition:
        margin-left var(--sidebar-transition),
        width var(--sidebar-transition) !important;
}

/* MAIN CONTENT WHEN SIDEBAR EXPANDS */
body:has(.sidebar:hover) .main-content,
body:has(.sidebar:focus-within) .main-content {
    margin-left: var(--sidebar-expanded-width) !important;
    width: calc(100% - var(--sidebar-expanded-width)) !important;
}

/* MOBILE */
@media (max-width: 700px) {
    :root {
        --sidebar-collapsed-width: 64px;
        --sidebar-expanded-width: 240px;
    }
}
</style>

