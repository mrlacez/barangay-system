<?php

$reference_no = trim($_GET["ref"] ?? "");
$serviceType  = trim($_GET["service"] ?? "");

if ($reference_no === "") {
    header("Location: index.php");
    exit;
}

/*
 * Determine where "Submit Another Request"
 * should go.
 *
 * Community Assistance
 * → return directly to Community Assistance form
 *
 * Other services
 * → return to general service request page
 */

if ($serviceType === "Community Assistance") {
    $anotherRequestUrl =
        "service_request.php?service=" .
        urlencode("Community Assistance");
} else {
    $anotherRequestUrl = "service_request.php";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Submitted - Barangay Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
        }
        .container {
            width: 100%;
            max-width: 700px;
            margin: 50px auto;
            padding: 0 20px;
            flex: 1 0 auto;
        }
        .success-card {
            background: #fff;
            border-radius: 16px;
            padding: 45px 35px;
            text-align: center;
            box-shadow: 0 8px 30px rgba(0,0,0,.08);
            border-top: 5px solid #16a34a;
        }
        .success-icon {
            width: 75px;
            height: 75px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: #dcfce7;
            color: #16a34a;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }
        .success-card h2 {
            margin: 0 0 10px;
            color: #172554;
            font-size: 30px;
        }
        .success-message {
            color: #64748b;
            line-height: 1.7;
            margin-bottom: 25px;
        }
        .reference-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 20px;
            margin: 25px 0;
        }
        .reference-label {
            display: block;
            color: #64748b;
            font-size: 13px;
            margin-bottom: 8px;
        }
        .reference-number {
            color: #2563eb;
            font-size: 25px;
            font-weight: bold;
            letter-spacing: 1px;
            overflow-wrap: anywhere;
        }
        .notice {
            background: #f8fafc;
            border-radius: 8px;
            padding: 15px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        .buttons {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .button {
            display: inline-block;
            padding: 12px 22px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }
        .primary { background: #2563eb; color: #fff; }
        .primary:hover { background: #1d4ed8; }
        .track-button { background: #16a34a; color: #fff; }
        .track-button:hover { background: #15803d; }
        .secondary { background: #e5e7eb; color: #1f2937; }
        .secondary:hover { background: #d1d5db; }
        footer {
            width: 100%;
            min-height: 44px;
            margin-top: auto;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg,#0b1e3d,#102a56);
            color: #cbd5e1;
            font-size: 12px;
            border-top: 1px solid rgba(255,255,255,.05);
        }

        /* BONGDO navbar: dimensions and visual styling aligned with index.php */
        .main-header {
            width: 100%; height: 88px; min-height: 88px; margin: 0; padding: 0;
            background: linear-gradient(135deg,#123873 0%,#16448f 48%,#0f3d82 100%);
            box-shadow: 0 5px 22px rgba(15,23,42,.18);
            position: relative; z-index: 1000; color: #fff;
        }
        .main-header .main-nav {
            width: min(1540px,calc(100% - 55px)); height: 88px; min-height: 88px;
            margin: 0 auto; padding: 0; display: flex; align-items: center; gap: 25px;
            box-sizing: border-box;
        }
        .main-header .brand {
            display: flex; align-items: center; gap: 13px; min-width: 315px;
            flex-shrink: 0; margin: 0; padding: 0; color: #fff;
        }
        .main-header .brand-logo {
            width: 66px; height: 66px; min-width: 66px; min-height: 66px;
            flex-shrink: 0; display: flex; align-items: center; justify-content: center;
            box-sizing: border-box; background: #fff; border-radius: 14px;
            color: #1768e8; font-size: 34px; box-shadow: 0 6px 18px rgba(0,0,0,.18);
        }
        .main-header .brand-text { min-width: 0; }
        .main-header .brand-text h1 {
            margin: 0; color: #fff; font-size: 23px; line-height: 1.1;
            font-weight: 900; letter-spacing: .3px;
        }
        .main-header .brand-text span {
            display: block; margin-top: 5px; color: rgba(255,255,255,.86);
            font-size: 13px; white-space: nowrap;
        }
        .main-header .main-nav-links {
            flex: 1; min-width: 0; display: flex; align-items: center;
            justify-content: center; gap: 5px;
        }
        .main-header .nav-item {
            position: relative; height: 46px; min-height: 46px; padding: 0 15px;
            display: flex; align-items: center; justify-content: center; gap: 8px;
            border: 0; border-radius: 12px; color: #fff; background: transparent;
            text-decoration: none; font-size: 14px; font-weight: 700; white-space: nowrap;
            transition: background .2s ease,transform .2s ease,box-shadow .2s ease;
            box-sizing: border-box;
        }
        .main-header .nav-item:hover {
            color: #fff; background: rgba(255,255,255,.14); transform: translateY(-1px);
        }
        .main-header .nav-item.active {
            color: #fff; background: linear-gradient(135deg,#3b91f7,#1473ed);
            box-shadow: 0 7px 18px rgba(0,0,0,.16);
        }
        .main-header .nav-icon {
            display: inline-flex; align-items: center; justify-content: center;
            color: inherit; font-size: 18px; line-height: 1;
        }
        .main-header .nav-icon svg { display: block; width: 18px; height: 18px; }
        .main-header .announcement-nav { position: relative; }
        .main-header .notification-dot {
            position: absolute; top: 6px; right: 7px; width: 9px; height: 9px;
            border-radius: 50%; background: #ef4444; border: 2px solid #17458b;
        }
        .main-header .nav-right {
            display: flex; align-items: center; gap: 20px; flex-shrink: 0;
        }
        .main-header .nav-search-wrap { position: relative; width: 282px; flex-shrink: 0; }
        .main-header .nav-search {
            width: 100%; height: 43px; min-height: 43px; padding: 0 15px;
            display: flex; align-items: center; gap: 9px;
            border: 1px solid rgba(255,255,255,.08); border-radius: 24px;
            background: rgba(255,255,255,.13); color: #fff;
            box-shadow: inset 0 1px 0 rgba(255,255,255,.04);
        }
        .main-header .nav-search span { flex: 0 0 auto; color: #fff; font-size: 22px; line-height: 1; }
        .main-header .nav-search input {
            width: 100%; min-width: 0; padding: 0; border: 0; outline: none;
            background: transparent; color: #fff; font-size: 13px;
        }
        .main-header .nav-search input::placeholder { color: rgba(255,255,255,.75); }
        .main-header .nav-search:focus-within {
            background: rgba(255,255,255,.18); box-shadow: 0 0 0 3px rgba(255,255,255,.08);
        }
        .main-header .admin-menu {
            display: flex; align-items: center; gap: 9px; color: #fff;
            cursor: pointer; white-space: nowrap; user-select: none;
        }
        .main-header .admin-avatar {
            width: 42px; height: 42px; flex-shrink: 0; display: flex;
            align-items: center; justify-content: center; border-radius: 50%;
            background: #eaf2ff; color: #214c8d; font-size: 21px;
            border: 2px solid rgba(255,255,255,.55);
            box-shadow: 0 3px 10px rgba(0,0,0,.10);
        }
        .main-header .admin-name strong { color: #fff; font-size: 14px; font-weight: 800; }
        .main-header .admin-arrow {
            display: inline-flex; align-items: center; justify-content: center;
            width: 16px; height: 16px; margin-left: 1px; color: #fff; font-size: 11px;
            line-height: 1; flex-shrink: 0; opacity: .9;
        }
        @media (max-width: 1200px) {
            .main-header .main-nav { width: calc(100% - 30px); gap: 14px; }
            .main-header .brand { min-width: auto; }
            .main-header .nav-search-wrap { width: 200px; }
            .main-header .nav-item { padding: 0 10px; }
        }
        @media (max-width: 900px) {
            .main-header { height: auto; min-height: 88px; }
            .main-header .main-nav {
                width: calc(100% - 30px); height: auto; min-height: 88px;
                flex-wrap: wrap; padding: 10px 0; gap: 12px;
            }
            .main-header .brand { min-width: 0; }
            .main-header .main-nav-links {
                width: 100%; flex: 1 1 100%; flex-wrap: wrap; justify-content: flex-start;
            }
            .main-header .nav-right { flex-wrap: wrap; gap: 10px; }
        }
        @media (max-width: 600px) {
            .main-header .main-nav { width: calc(100% - 20px); }
            .main-header .brand-logo {
                width: 52px; height: 52px; min-width: 52px; min-height: 52px;
            }
            .main-header .brand-text h1 { font-size: 18px; }
            .main-header .brand-text span { font-size: 11px; white-space: normal; }
            .main-header .main-nav-links { gap: 5px; }
            .main-header .nav-item { height: 40px; min-height: 40px; padding: 0 10px; font-size: 12px; }
            .main-header .nav-search-wrap { width: 100%; max-width: 100%; }
        }

        @media (max-width: 600px) {
            .container { margin: 35px auto; }
            .success-card { padding: 35px 20px; }
            .success-card h2 { font-size: 25px; }
            .reference-number { font-size: 20px; }
        }
    </style>
</head>
<body>

<?php
$current_view = 'service_request_success';
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
require __DIR__ . '/includes/navbar.php';
?>

<main class="container">
    <div class="success-card">
        <div class="success-icon">✓</div>
        <h2>Request Submitted Successfully!</h2>
        <p class="success-message">
            Your barangay service request has been successfully submitted.
            Please keep your reference number for future tracking and follow-up.
        </p>
        <div class="reference-box">
            <span class="reference-label">Your Reference Number</span>
            <div class="reference-number"><?= htmlspecialchars($reference_no, ENT_QUOTES, "UTF-8") ?></div>
        </div>
        <div class="notice">
            📌 The Barangay will review your request.
            Please wait for the status update or contact the Barangay Office if you need assistance.
        </div>
        <div class="buttons">
            <a href="track_request.php?reference_no=<?= urlencode($reference_no) ?>" class="button track-button">Track Request Status</a>
            <a href="index.php" class="button primary">← 🏠 Back to Home</a>
            <a href="<?= htmlspecialchars($anotherRequestUrl, ENT_QUOTES, "UTF-8") ?>" class="button secondary">↻ Submit Another Request</a>
        </div>
    </div>
</main>

<footer>
    © <?= date("Y") ?> Barangay Management System. All rights reserved.
</footer>

</body>
</html>
