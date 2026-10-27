<?php
require_once "config/database.php";

/* =========================================================
   BARANGAY SETTINGS
   ========================================================= */

$settings = [
    "barangay_name"     => "BONGDO",
    "municipality_city" => "Borbon",
    "province"          => "Cebu",
    "contact_number"    => "0939 296 6391",
    "address"            => "Bongdo, Borbon, Cebu"
];

$settings_result = $conn->query("
    SELECT barangay_name, municipality_city, province,
           contact_number, address
    FROM barangay_settings
    ORDER BY id ASC
    LIMIT 1
");

if ($settings_result && $settings_result->num_rows > 0) {
    $db_settings = $settings_result->fetch_assoc();

    foreach ($settings as $key => $default) {
        if (
            isset($db_settings[$key]) &&
            trim((string)$db_settings[$key]) !== ""
        ) {
            $settings[$key] = trim((string)$db_settings[$key]);
        }
    }
}

$barangay = $settings["barangay_name"] ?: "Barangay";

function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}

/* =========================================================
   GET ANNOUNCEMENTS
   ========================================================= */

$announcements = [];

$result = $conn->query("
    SELECT id, title, content, created_at
    FROM announcements
    ORDER BY created_at DESC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}

$totalAnnouncements = count($announcements);

/*
 * The current announcements table query does not include
 * status or event-date fields. Therefore, all retrieved
 * announcements are treated as published, and upcoming
 * events are not inferred from the publication date.
 */
$activeAnnouncements = $totalAnnouncements;
$upcomingEvents = 0;

/* =========================================================
   DERIVE A DISPLAY CATEGORY FROM EXISTING TEXT
   ========================================================= */

function announcementCategory($title, $content) {
    $text = strtolower($title . " " . $content);

    if (preg_match(
        '/medical|health|vaccin|check.?up|dental|clinic/',
        $text
    )) {
        return "Health Program";
    }

    if (preg_match(
        '/clean.?up|community|volunteer|tree.?plant|feeding/',
        $text
    )) {
        return "Community Activity";
    }

    if (preg_match(
        '/water|electric|road|maintenance|service|interruption/',
        $text
    )) {
        return "Service Update";
    }

    if (preg_match(
        '/meeting|assembly|consultation|orientation/',
        $text
    )) {
        return "Community Meeting";
    }

    return "Public Announcement";
}

function announcementIcon($category) {
    switch ($category) {
        case "Health Program":
            return "fa-kit-medical";

        case "Community Activity":
            return "fa-broom";

        case "Service Update":
            return "fa-droplet";

        case "Community Meeting":
            return "fa-users";

        default:
            return "fa-bullhorn";
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

    <title>
        <?= e($barangay) ?> - Public Announcements
    </title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

    <style>
    /* =====================================================
       GLOBAL
       ===================================================== */

    :root {
        --primary: #0866ed;
        --primary-dark: #06469b;
        --navy: #0c3472;
        --text: #102a56;
        --muted: #687b99;
        --border: #e4ebf6;
        --bg: #f0f5fd;
        --white: #ffffff;
        --green: #079b61;
        --orange: #f59e0b;
        --shadow: 0 8px 25px rgba(21, 57, 112, .07);
    }

    * {
        box-sizing: border-box;
    }

    html {
        scroll-behavior: smooth;
    }

    body {
        margin: 0;
        min-height: 100vh;
        font-family: Inter, "Segoe UI", Arial, sans-serif;
        color: var(--text);
        background:
            radial-gradient(
                circle at 0 10%,
                rgba(44, 115, 237, .08),
                transparent 30%
            ),
            linear-gradient(180deg, #f7faff, #edf4fd);
    }

    button,
    input,
    select {
        font: inherit;
    }

    button,
    a,
    input,
    select {
        -webkit-tap-highlight-color: transparent;
    }

    a {
        color: inherit;
    }

    /* =====================================================
       FIX: RESTORE EXISTING NAVBAR DESIGN
       ===================================================== */

    .main-header {
        width: 100%;
        height: 88px;
        background: linear-gradient(135deg, #123873, #16448f, #0f3d82);
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

    .brand {
        display: flex;
        align-items: center;
        gap: 13px;
        min-width: 315px;
        flex-shrink: 0;
        color: #fff;
    }

    .brand-logo {
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
    }

    .brand-text h1 {
        margin: 0;
        color: #fff;
        font-size: 23px;
        line-height: 1.1;
        font-weight: 900;
    }

    .brand-text span {
        display: block;
        margin-top: 5px;
        color: rgba(255, 255, 255, .86);
        font-size: 13px;
        white-space: nowrap;
    }

    .main-nav-links {
        flex: 1;
        min-width: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    .nav-item {
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
        transition: .2s ease;
    }

    .nav-item:hover {
        background: rgba(255, 255, 255, .14);
    }

    .nav-item.active {
        background: linear-gradient(135deg, #3b91f7, #1473ed);
        box-shadow: 0 7px 18px rgba(0, 0, 0, .16);
    }

    .nav-icon {
        display: inline-flex;
        width: 20px;
        height: 20px;
        flex: 0 0 20px;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .nav-icon i {
        line-height: 1;
    }

    .announcement-nav {
        position: relative;
    }

    .notification-dot {
        position: absolute;
        top: 6px;
        right: 7px;
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: #ef4444;
        border: 2px solid #17458b;
    }

    .nav-right {
        display: flex;
        align-items: center;
        gap: 20px;
        flex-shrink: 0;
    }

    .nav-search {
        width: 282px;
        height: 43px;
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 0 15px;
        border-radius: 24px;
        background: rgba(255, 255, 255, .13);
        border: 1px solid rgba(255, 255, 255, .08);
    }

    .nav-search span {
        flex: 0 0 auto;
        color: #fff;
        font-size: 22px;
    }

    .nav-search input {
        width: 100%;
        min-width: 0;
        border: 0;
        outline: none;
        background: transparent;
        color: #fff;
        font-size: 13px;
    }

    .nav-search input::placeholder {
        color: rgba(255, 255, 255, .75);
    }

    .admin-menu {
        display: flex;
        align-items: center;
        gap: 9px;
        color: #fff;
        cursor: pointer;
        white-space: nowrap;
    }

    .admin-avatar {
        width: 42px;
        height: 42px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #eaf2ff;
        color: #214c8d;
        font-size: 21px;
    }

    .admin-name strong {
        color: #fff;
        font-size: 14px;
        font-weight: 800;
    }

    .admin-arrow {
        color: #fff;
        font-size: 18px;
    }

    @media (max-width: 1250px) {
        .main-nav {
            width: calc(100% - 30px);
            gap: 15px;
        }

        .brand {
            min-width: auto;
        }

        .nav-item {
            padding: 0 10px;
            font-size: 12px;
        }

        .nav-search {
            width: 190px;
        }
    }

    @media (max-width: 1000px) {
        .brand-text span {
            display: none;
        }

        .nav-right {
            gap: 10px;
        }

        .nav-search {
            width: 150px;
        }

        .admin-name {
            display: none;
        }
    }

    @media (max-width: 700px) {
        .main-header {
            height: auto;
        }

        .main-nav {
            height: auto;
            min-height: 88px;
            padding: 10px 0;
            flex-wrap: wrap;
            gap: 10px;
        }

        .brand {
            width: 100%;
            min-width: 0;
        }

        .brand-logo {
            width: 52px;
            height: 52px;
            font-size: 27px;
        }

        .main-nav-links {
            order: 3;
            width: 100%;
            flex: none;
            justify-content: flex-start;
            overflow-x: auto;
            padding-bottom: 3px;
        }

        .nav-item {
            flex: 0 0 auto;
            height: 40px;
            padding: 0 11px;
            font-size: 11px;
        }

        .nav-right {
            display: none;
        }
    }

    /* =====================================================
       HERO BANNER
       ===================================================== */

    .page-container {
        width: min(1550px, calc(100% - 60px));
        margin: 20px auto 0;
    }

    .hero {
        min-height: 230px;
        position: relative;
        overflow: hidden;
        display: flex;
        align-items: center;
        border: 1px solid #dce8f8;
        border-radius: 23px;
        background:
            linear-gradient(
                90deg,
                rgba(255,255,255,1) 0%,
                rgba(247,251,255,.98) 36%,
                rgba(239,246,255,.88) 55%,
                rgba(222,237,255,.25) 100%
            ),
            url("https://images.unsplash.com/photo-1487958449943-2429e8be8625?auto=format&fit=crop&w=1800&q=85")
            right center / cover no-repeat;
        box-shadow: 0 12px 32px rgba(28, 70, 130, .07);
    }

    .hero-content {
        position: relative;
        z-index: 2;
        width: 65%;
        padding: 30px 40px;
    }

    .hero-label {
        display: inline-block;
        margin-bottom: 12px;
        color: var(--primary);
        font-size: 11px;
        font-weight: 900;
        letter-spacing: 3px;
    }

    .hero h2 {
        margin: 0;
        font-size: clamp(34px, 4vw, 58px);
        line-height: 1.08;
        letter-spacing: -2px;
        font-weight: 900;
        color: #132b5e;
    }

    .hero h2 span {
        color: #0969ef;
    }

    .hero-line {
        width: 65px;
        height: 5px;
        margin: 15px 0;
        border-radius: 10px;
        background: linear-gradient(90deg, #0866ed, #67a9ff);
    }

    .hero p {
        max-width: 620px;
        margin: 0;
        color: #5d7192;
        font-size: 15px;
        line-height: 1.7;
    }

    .hero-quote {
        position: absolute;
        z-index: 3;
        right: 33%;
        bottom: 18px;
        max-width: 300px;
        padding: 14px 18px;
        display: flex;
        align-items: center;
        gap: 13px;
        border: 1px solid rgba(201, 222, 255, .95);
        border-radius: 17px;
        background: rgba(255, 255, 255, .91);
        box-shadow: 0 8px 24px rgba(30, 81, 150, .12);
        backdrop-filter: blur(10px);
    }

    .hero-quote i {
        font-size: 28px;
        color: var(--primary);
    }

    .hero-quote span {
        color: #174b9a;
        font-size: 12px;
        font-style: italic;
        font-weight: 650;
        line-height: 1.6;
    }

    /* =====================================================
       MAIN TWO-COLUMN LAYOUT
       ===================================================== */

    .dashboard {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 495px;
        gap: 22px;
        align-items: start;
        margin-top: 22px;
        margin-bottom: 35px;
    }

    .main-column,
    .sidebar {
        min-width: 0;
    }

    .section-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        margin-bottom: 15px;
    }

    .heading-left {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
    }

    .heading-icon {
        width: 55px;
        height: 55px;
        flex: 0 0 55px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 13px;
        color: #fff;
        font-size: 24px;
        background: linear-gradient(135deg, #60a5ff, #0866ed);
        box-shadow: 0 5px 14px rgba(8, 102, 237, .2);
    }

    .section-heading h3 {
        margin: 0;
        color: #102957;
        font-size: 23px;
        line-height: 1.25;
        font-weight: 900;
    }

    .section-heading p {
        margin: 5px 0 0;
        color: var(--muted);
        font-size: 13px;
        line-height: 1.5;
    }

    /* =====================================================
       BUTTONS
       ===================================================== */

    .btn {
        min-height: 43px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 9px;
        padding: 0 17px;
        border: 1px solid transparent;
        border-radius: 11px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
        transition: .2s ease;
    }

    .btn-primary {
        color: #fff;
        background: linear-gradient(135deg, #147bff, #0054df);
        box-shadow: 0 5px 13px rgba(0, 94, 230, .17);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 18px rgba(0, 94, 230, .24);
    }

    /* =====================================================
       ANNOUNCEMENT LIST
       ===================================================== */

    .announcement-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .announcement-card {
        min-width: 0;
        display: grid;
        grid-template-columns: 135px minmax(0, 1fr) 45px;
        gap: 17px;
        align-items: center;
        padding: 17px;
        border: 1px solid var(--border);
        border-radius: 19px;
        background: rgba(255, 255, 255, .97);
        box-shadow: var(--shadow);
        transition: transform .2s ease, box-shadow .2s ease;
    }

    .announcement-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 13px 28px rgba(21, 57, 112, .11);
    }

    .announcement-picture {
        width: 135px;
        height: 124px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 17px;
        font-size: 43px;
    }

    .announcement-picture.health {
        color: #ed5680;
        background: linear-gradient(135deg, #fce4e9, #f9dce5);
    }

    .announcement-picture.community {
        color: #0ba978;
        background: linear-gradient(135deg, #dff8ed, #d4f3e4);
    }

    .announcement-picture.service {
        color: #2787ed;
        background: linear-gradient(135deg, #e1edff, #d9eaff);
    }

    .announcement-picture.meeting {
        color: #8b5cf6;
        background: linear-gradient(135deg, #eee7ff, #e3dbff);
    }

    .announcement-picture.general {
        color: #0969ef;
        background: linear-gradient(135deg, #e8f1ff, #dceaff);
    }

    .announcement-details {
        min-width: 0;
    }

    .announcement-top {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        margin-bottom: 7px;
    }

    .category {
        display: inline-flex;
        align-items: center;
        width: fit-content;
        padding: 5px 10px;
        border-radius: 20px;
        color: #0969ef;
        background: #e1efff;
        font-size: 11px;
        line-height: 1.2;
        font-weight: 850;
        text-transform: uppercase;
    }

    .category.health {
        color: #db2850;
        background: #ffe2e8;
    }

    .category.community {
        color: #07854f;
        background: #d9f8e9;
    }

    .category.service {
        color: #0866ed;
        background: #deebff;
    }

    .category.meeting {
        color: #7041cb;
        background: #eee5ff;
    }

    .status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 8px;
        color: #078753;
        background: #daf8e9;
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #0da66b;
    }

    .announcement-details h4 {
        margin: 0 0 5px;
        color: #102957;
        font-size: 19px;
        font-weight: 900;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .announcement-description {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        margin: 0;
        color: #647797;
        font-size: 13px;
        line-height: 1.55;
        overflow-wrap: anywhere;
    }

    .announcement-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 12px 20px;
        margin-top: 10px;
        color: #6b7d99;
        font-size: 12px;
    }

    .announcement-meta span {
        display: inline-flex;
        align-items: center;
        gap: 7px;
    }

    .announcement-meta i {
        color: #8295b2;
        font-size: 14px;
    }

    .announcement-arrow {
        width: 39px;
        height: 39px;
        flex: 0 0 39px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        color: #0866ed;
        background: #e7f1ff;
        text-decoration: none;
        transition: .2s ease;
    }

    .announcement-arrow:hover {
        color: #fff;
        background: #0866ed;
        transform: translateX(2px);
    }

    /* =====================================================
       SIDEBAR BOXES
       ===================================================== */

    .side-box {
        padding: 13px;
        margin-bottom: 11px;
        border: 1px solid var(--border);
        border-radius: 18px;
        background: rgba(255, 255, 255, .97);
        box-shadow: var(--shadow);
    }

    /* =====================================================
       STATISTICS
       ===================================================== */

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }

    .stat-card {
        min-width: 0;
        min-height: 142px;
        padding: 16px 13px;
        border-radius: 14px;
    }

    .stat-card.total {
        background: linear-gradient(145deg, #e8f1ff, #dfeaff);
    }

    .stat-card.active {
        background: linear-gradient(145deg, #e3f9ee, #d9f5e6);
    }

    .stat-card.upcoming {
        background: linear-gradient(145deg, #fff3dc, #fff0d6);
    }

    .stat-icon {
        width: 34px;
        height: 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 8px;
        border-radius: 50%;
        color: #fff;
        background: #0874f9;
        font-size: 17px;
    }

    .active .stat-icon {
        background: #0bb779;
    }

    .upcoming .stat-icon {
        background: #f59e0b;
    }

    .stat-number {
        display: block;
        margin-bottom: 4px;
        color: #0754c4;
        font-size: 25px;
        line-height: 1.1;
        font-weight: 900;
    }

    .active .stat-number {
        color: #087747;
    }

    .upcoming .stat-number {
        color: #a14c00;
    }

    .stat-label {
        color: #5c6f89;
        font-size: 12px;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }

    /* =====================================================
       SIDEBAR HEADING
       ===================================================== */

    .side-title {
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 0 0 12px;
        color: #102957;
        font-size: 18px;
        font-weight: 900;
    }

    .side-title-icon {
        width: 34px;
        height: 34px;
        flex: 0 0 34px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        color: #fff;
        background: linear-gradient(135deg, #1682ff, #0059e5);
        font-size: 15px;
    }

    /* =====================================================
       SEARCH AND FILTER
       ===================================================== */

    .filter-form {
        display: flex;
        flex-direction: column;
        gap: 9px;
    }

    .search-field {
        position: relative;
    }

    .search-field i {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        color: #8192ad;
        pointer-events: none;
    }

    .search-field input,
    .filter-form select {
        width: 100%;
        height: 40px;
        padding: 0 12px;
        border: 1px solid #d6e0ef;
        border-radius: 10px;
        outline: none;
        color: #253c61;
        background: #fff;
        font-size: 12px;
        transition: border-color .2s, box-shadow .2s;
    }

    .search-field input {
        padding-left: 37px;
    }

    .search-field input:focus,
    .filter-form select:focus {
        border-color: #4590ff;
        box-shadow: 0 0 0 3px rgba(8, 102, 237, .1);
    }

    .filter-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 9px;
    }

    .filter-button {
        width: 100%;
        min-height: 39px;
        border: none;
        border-radius: 9px;
        color: #fff;
        background: linear-gradient(135deg, #147bff, #0054df);
        font-size: 12px;
        font-weight: 850;
        cursor: pointer;
        transition: .2s;
    }

    .filter-button:hover {
        filter: brightness(.95);
        box-shadow: 0 5px 13px rgba(0, 94, 230, .2);
    }

    .reset-link {
        display: block;
        text-align: center;
        color: #0866ed;
        font-size: 12px;
        font-weight: 750;
        text-decoration: none;
    }

    .reset-link:hover {
        text-decoration: underline;
    }

    /* =====================================================
       QUICK LINKS
       ===================================================== */

    .quick-link {
        display: flex;
        align-items: center;
        gap: 11px;
        padding: 11px;
        margin-top: 7px;
        border: 1px solid #e5edf8;
        border-radius: 12px;
        text-decoration: none;
        transition: .2s;
    }

    .quick-link:hover {
        border-color: #b8d4ff;
        background: #f6f9ff;
        transform: translateX(2px);
    }

    .quick-link-icon {
        width: 38px;
        height: 38px;
        flex: 0 0 38px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        color: #0866ed;
        background: #e2eeff;
        font-size: 17px;
    }

    .quick-link-text {
        flex: 1;
        min-width: 0;
    }

    .quick-link-text strong {
        display: block;
        margin-bottom: 3px;
        color: #0866ed;
        font-size: 12px;
        line-height: 1.4;
    }

    .quick-link-text span {
        display: block;
        color: #71819a;
        font-size: 11px;
        line-height: 1.45;
    }

    .quick-link-arrow {
        color: #637b9f;
    }

    /* =====================================================
       EMPTY STATE
       ===================================================== */

    .empty-state {
        padding: 45px 20px;
        text-align: center;
        border: 1px solid var(--border);
        border-radius: 18px;
        background: #fff;
        box-shadow: var(--shadow);
    }

    .empty-state i {
        margin-bottom: 13px;
        color: #8db8fa;
        font-size: 42px;
    }

    .empty-state h4 {
        margin: 0 0 7px;
        color: #102957;
        font-size: 19px;
    }

    .empty-state p {
        margin: 0;
        color: var(--muted);
        font-size: 13px;
        line-height: 1.6;
    }

    .empty-state .btn {
        margin-top: 15px;
    }

    .no-results {
        display: none;
    }

    /* =====================================================
       FOOTER — MATCHES INDEX.PHP
       ===================================================== */

    footer {
        width: 100%;
        min-height: 62px;
        margin-top: auto;
        padding: 18px 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        background: linear-gradient(135deg, #0b1e3d, #102a56);
        color: #cbd5e1;
        font-size: 12px;
        line-height: 1.5;
        text-align: center;
        border-top: 1px solid rgba(255, 255, 255, .05);
    }

    /* =====================================================
       BACK TO TOP
       ===================================================== */

    #backToTop {
        position: fixed;
        right: 22px;
        bottom: 22px;
        z-index: 1000;
        width: 44px;
        height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
        border-radius: 50%;
        color: #fff;
        background: #0866ed;
        box-shadow: 0 7px 18px rgba(8, 102, 237, .3);
        cursor: pointer;
        opacity: 0;
        visibility: hidden;
        transform: translateY(8px);
        transition: .2s;
    }

    #backToTop.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    #backToTop:hover {
        background: #064fc5;
    }

    /* =====================================================
       TABLET
       ===================================================== */

    @media (max-width: 1200px) {
        .dashboard {
            grid-template-columns: minmax(0, 1fr) 365px;
            gap: 15px;
        }

        .announcement-card {
            grid-template-columns: 100px minmax(0, 1fr) 38px;
            gap: 12px;
            padding: 13px;
        }

        .announcement-picture {
            width: 100px;
            height: 110px;
            font-size: 35px;
        }

        .announcement-details h4 {
            font-size: 17px;
        }

        .hero-quote {
            right: 25%;
        }
    }

    @media (max-width: 900px) {
        .page-container {
            width: calc(100% - 32px);
        }

        .dashboard {
            grid-template-columns: 1fr;
        }

        .sidebar {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            align-items: start;
            gap: 12px;
        }

        .side-box {
            margin-bottom: 0;
        }

        .hero-content {
            width: 75%;
        }

        .hero-quote {
            right: 18px;
            bottom: 15px;
            max-width: 245px;
        }
    }

    /* =====================================================
       MOBILE
       ===================================================== */

    @media (max-width: 600px) {
        .page-container {
            width: calc(100% - 22px);
            margin-top: 12px;
        }

        .hero {
            min-height: 260px;
            align-items: flex-start;
            background:
                linear-gradient(
                    90deg,
                    rgba(255,255,255,.97),
                    rgba(244,249,255,.88)
                ),
                url("https://images.unsplash.com/photo-1487958449943-2429e8be8625?auto=format&fit=crop&w=900&q=80")
                center / cover no-repeat;
            border-radius: 17px;
        }

        .hero-content {
            width: 100%;
            padding: 25px 20px 100px;
        }

        .hero-label {
            font-size: 9px;
            letter-spacing: 2px;
        }

        .hero h2 {
            font-size: clamp(32px, 9vw, 43px);
            letter-spacing: -1.3px;
        }

        .hero p {
            font-size: 12px;
        }

        .hero-quote {
            right: 12px;
            bottom: 12px;
            left: 12px;
            max-width: none;
            padding: 10px 13px;
        }

        .hero-quote i {
            font-size: 23px;
        }

        .dashboard {
            margin-top: 17px;
            gap: 20px;
        }

        .heading-icon {
            width: 43px;
            height: 43px;
            flex-basis: 43px;
            font-size: 19px;
        }

        .section-heading h3 {
            font-size: 18px;
        }

        .section-heading p {
            font-size: 11px;
        }

        .section-heading {
            align-items: center;
            gap: 9px;
        }

        .announcement-card {
            grid-template-columns: 74px minmax(0, 1fr) 32px;
            gap: 10px;
            padding: 11px;
            border-radius: 14px;
        }

        .announcement-picture {
            width: 74px;
            height: 85px;
            border-radius: 12px;
            font-size: 28px;
        }

        .announcement-top {
            align-items: flex-start;
            gap: 5px;
        }

        .category {
            padding: 4px 7px;
            font-size: 9px;
        }

        .status {
            padding: 4px 6px;
            font-size: 9px;
        }

        .status-dot {
            width: 6px;
            height: 6px;
        }

        .announcement-details h4 {
            font-size: 14px;
        }

        .announcement-description {
            font-size: 11px;
        }

        .announcement-meta {
            gap: 7px;
            margin-top: 7px;
            font-size: 10px;
        }

        .announcement-meta i {
            font-size: 12px;
        }

        .announcement-arrow {
            width: 30px;
            height: 30px;
            flex-basis: 30px;
            font-size: 12px;
        }

        .sidebar {
            grid-template-columns: 1fr;
        }

        .stat-card {
            min-height: 125px;
            padding: 12px 9px;
        }

        .stat-label {
            font-size: 10px;
        }

        .stat-number {
            font-size: 22px;
        }

        .stat-icon {
            width: 30px;
            height: 30px;
            font-size: 14px;
        }

        .side-title {
            font-size: 16px;
        }

        footer {
            padding: 17px 12px;
            font-size: 11px;
        }

        #backToTop {
            right: 13px;
            bottom: 13px;
            width: 39px;
            height: 39px;
        }
    }

    @media (max-width: 370px) {
        .announcement-card {
            grid-template-columns: 60px minmax(0, 1fr) 27px;
            gap: 8px;
            padding: 9px;
        }

        .announcement-picture {
            width: 60px;
            height: 72px;
            font-size: 24px;
        }

        .announcement-details h4 {
            font-size: 13px;
        }

        .announcement-arrow {
            width: 27px;
            height: 27px;
            flex-basis: 27px;
        }

        .status {
            font-size: 8px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            scroll-behavior: auto !important;
            transition-duration: .01ms !important;
        }
    }

/* Hide search but preserve navbar positions */
.main-nav .nav-search-wrap {
    visibility: visible;
}

/* About page */
body:has(.about-container) .main-nav .nav-search-wrap {
    visibility: hidden !important;
}

/* Public announcements page */
body:has(.dashboard) .main-nav .nav-search-wrap {
    visibility: hidden !important;
}


    </style>
</head>

<body>

<?php
/*
 * Reuse the project's existing navbar.
 * The navbar should already contain the BONGDO branding,
 * navigation links, search area, and admin menu.
 */
require __DIR__ . "/includes/navbar.php";
?>

<!-- =====================================================
     HERO
     ===================================================== -->

<div class="page-container">

    <section class="hero">

        <div class="hero-content">

            <span class="hero-label">
                BARANGAY MANAGEMENT SYSTEM
            </span>

            <h2>
                Public <span>Announcements</span>
            </h2>

            <div class="hero-line"></div>

            <p>
                Stay informed about the latest announcements,
                activities, programs, and important updates
                from <?= e($barangay) ?>.
            </p>

        </div>

        <div class="hero-quote">
            <i class="fa-solid fa-bullhorn"></i>

            <span>
                "A well-informed community builds a stronger
                barangay."
            </span>
        </div>

    </section>

    <!-- =================================================
         MAIN CONTENT
         ================================================= -->

    <main class="dashboard">

        <!-- LEFT COLUMN -->

        <section class="main-column">

            <div class="section-heading">

                <div class="heading-left">

                    <div class="heading-icon">
                        <i class="fa-solid fa-bullhorn"></i>
                    </div>

                    <div>
                        <h3>Latest Announcements</h3>

                        <p>
                            Important updates and information
                            from the barangay.
                        </p>
                    </div>

                </div>

            </div>

            <div class="announcement-list" id="announcementList">

                <?php if ($totalAnnouncements > 0): ?>

                    <?php foreach ($announcements as $row): ?>

                        <?php
                        $category = announcementCategory(
                            $row["title"],
                            $row["content"]
                        );

                        $icon = announcementIcon($category);

                        $categoryClass = "general";

                        if ($category === "Health Program") {
                            $categoryClass = "health";
                        } elseif ($category === "Community Activity") {
                            $categoryClass = "community";
                        } elseif ($category === "Service Update") {
                            $categoryClass = "service";
                        } elseif ($category === "Community Meeting") {
                            $categoryClass = "meeting";
                        }

                        $createdTimestamp = strtotime(
                            $row["created_at"]
                        );

                        $displayDate = $createdTimestamp
                            ? date("F d, Y", $createdTimestamp)
                            : "Date unavailable";

                        $displayTime = $createdTimestamp
                            ? date("g:i A", $createdTimestamp)
                            : "";

                        $searchText = strtolower(
                            $row["title"] . " " .
                            $row["content"] . " " .
                            $category
                        );
                        ?>

                        <article
                            class="announcement-card"
                            data-category="<?= e($category) ?>"
                            data-search="<?= e($searchText) ?>"
                        >

                            <div class="announcement-picture <?= e($categoryClass) ?>">
                                <i class="fa-solid <?= e($icon) ?>"></i>
                            </div>

                            <div class="announcement-details">

                                <div class="announcement-top">

                                    <span class="category <?= e($categoryClass) ?>">
                                        <?= e($category) ?>
                                    </span>

                                    <span class="status">
                                        <span class="status-dot"></span>
                                        Published
                                    </span>

                                </div>

                                <h4>
                                    <?= e($row["title"]) ?>
                                </h4>

                                <p class="announcement-description">
                                    <?= e($row["content"]) ?>
                                </p>

                                <div class="announcement-meta">

                                    <span>
                                        <i class="fa-regular fa-calendar-days"></i>
                                        <?= e($displayDate) ?>
                                    </span>

                                    <?php if ($displayTime !== ""): ?>
                                        <span>
                                            <i class="fa-regular fa-clock"></i>
                                            <?= e($displayTime) ?>
                                        </span>
                                    <?php endif; ?>

                                </div>

                            </div>

                            <a
                                href="announcement_details.php?id=<?= (int)$row["id"] ?>"
                                class="announcement-arrow"
                                aria-label="Read <?= e($row["title"]) ?>"
                                title="Read full announcement"
                            >
                                <i class="fa-solid fa-chevron-right"></i>
                            </a>

                        </article>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="empty-state">

                        <i class="fa-regular fa-bell-slash"></i>

                        <h4>No Announcements Yet</h4>

                        <p>
                            There are currently no public
                            announcements from the barangay.
                            Please check again later.
                        </p>

                    </div>

                <?php endif; ?>

            </div>

            <!-- SEARCH EMPTY RESULT -->

            <div class="empty-state no-results" id="noResults">

                <i class="fa-solid fa-magnifying-glass"></i>

                <h4>No Matching Announcements</h4>

                <p>
                    Try another keyword or select a different category.
                </p>

                <button
                    type="button"
                    class="btn btn-primary"
                    onclick="resetFilters()"
                >
                    <i class="fa-solid fa-rotate-left"></i>
                    Reset Filters
                </button>

            </div>

        </section>

        <!-- RIGHT SIDEBAR -->

        <aside class="sidebar">

            <!-- STATISTICS -->

            <section class="side-box">

                <div class="stats-grid">

                    <div class="stat-card total">

                        <div class="stat-icon">
                            <i class="fa-solid fa-bullhorn"></i>
                        </div>

                        <strong class="stat-number">
                            <?= $totalAnnouncements ?>
                        </strong>

                        <div class="stat-label">
                            Total Announcements
                        </div>

                    </div>

                    <div class="stat-card active">

                        <div class="stat-icon">
                            <i class="fa-solid fa-check"></i>
                        </div>

                        <strong class="stat-number">
                            <?= $activeAnnouncements ?>
                        </strong>

                        <div class="stat-label">
                            Published Announcements
                        </div>

                    </div>

                    <div class="stat-card upcoming">

                        <div class="stat-icon">
                            <i class="fa-regular fa-clock"></i>
                        </div>

                        <strong class="stat-number">
                            <?= $upcomingEvents ?>
                        </strong>

                        <div class="stat-label">
                            Upcoming Events
                        </div>

                    </div>

                </div>

            </section>

            <!-- SEARCH AND FILTER -->

            <section class="side-box">

                <h3 class="side-title">

                    <span class="side-title-icon">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </span>

                    Search &amp; Filter

                </h3>

                <form class="filter-form" id="filterForm">

                    <div class="search-field">

                        <i class="fa-solid fa-magnifying-glass"></i>

                        <input
                            type="search"
                            id="announcementSearch"
                            placeholder="Search announcements..."
                            autocomplete="off"
                        >

                    </div>

                    <div class="filter-row">

                        <select id="categoryFilter">
                            <option value="">All Categories</option>
                            <option value="Community Activity">
                                Community Activity
                            </option>
                            <option value="Health Program">
                                Health Program
                            </option>
                            <option value="Service Update">
                                Service Update
                            </option>
                            <option value="Community Meeting">
                                Community Meeting
                            </option>
                            <option value="Public Announcement">
                                Public Announcement
                            </option>
                        </select>

                        <select id="statusFilter">
                            <option value="">All Status</option>
                            <option value="published">Published</option>
                        </select>

                    </div>

                    <button type="submit" class="filter-button">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        &nbsp; Search
                    </button>

                    <a
                        href="#"
                        class="reset-link"
                        onclick="resetFilters(); return false;"
                    >
                        <i class="fa-solid fa-rotate-left"></i>
                        Reset Search
                    </a>

                </form>

            </section>

        </aside>

    </main>

</div>

<!-- FOOTER — SAME STRUCTURE AND STYLE AS INDEX.PHP -->
<footer>
    © <?= date("Y") ?> Barangay Management System. All rights reserved.
</footer>

<button
    type="button"
    id="backToTop"
    aria-label="Back to top"
    title="Back to top"
>↑</button>

<script>
(function () {
    const searchInput = document.getElementById("announcementSearch");
    const categoryFilter = document.getElementById("categoryFilter");
    const statusFilter = document.getElementById("statusFilter");
    const form = document.getElementById("filterForm");
    const cards = Array.from(document.querySelectorAll(".announcement-card"));
    const noResults = document.getElementById("noResults");
    const backToTop = document.getElementById("backToTop");

    function applyFilters() {
        const query = (searchInput?.value || "").trim().toLowerCase();
        const category = categoryFilter?.value || "";
        const status = (statusFilter?.value || "").toLowerCase();
        let visibleCount = 0;

        cards.forEach(function (card) {
            const searchableText = (card.dataset.search || "").toLowerCase();
            const cardCategory = card.dataset.category || "";
            const matchesQuery = !query || searchableText.includes(query);
            const matchesCategory = !category || cardCategory === category;
            const matchesStatus = !status || status === "published";
            const show = matchesQuery && matchesCategory && matchesStatus;

            card.style.display = show ? "" : "none";
            if (show) visibleCount++;
        });

        if (noResults) {
            noResults.style.display =
                cards.length > 0 && visibleCount === 0 ? "block" : "none";
        }
    }

    window.resetFilters = function () {
        if (searchInput) searchInput.value = "";
        if (categoryFilter) categoryFilter.value = "";
        if (statusFilter) statusFilter.value = "";
        applyFilters();
    };

    form?.addEventListener("submit", function (event) {
        event.preventDefault();
        applyFilters();
    });

    searchInput?.addEventListener("input", applyFilters);
    categoryFilter?.addEventListener("change", applyFilters);
    statusFilter?.addEventListener("change", applyFilters);

    function updateBackToTop() {
        if (backToTop) {
            backToTop.classList.toggle("show", window.scrollY > 300);
        }
    }

    backToTop?.addEventListener("click", function () {
        window.scrollTo({ top: 0, behavior: "smooth" });
    });

    window.addEventListener("scroll", updateBackToTop, { passive: true });
    updateBackToTop();
})();
</script>

</body>
</html>
