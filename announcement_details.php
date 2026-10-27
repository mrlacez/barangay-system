

<?php

require_once "config/database.php";

/* =========================
   GET ANNOUNCEMENT ID
========================= */

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($id <= 0) {
    header("Location: announcements_public.php");
    exit;
}


/* =========================
   GET ANNOUNCEMENT
========================= */

$stmt = $conn->prepare("
    SELECT
        id,
        title,
        content,
        created_at
    FROM announcements
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: announcements_public.php");
    exit;
}

$announcement = $result->fetch_assoc();

$stmt->close();

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
        <?= htmlspecialchars(
            $announcement["title"],
            ENT_QUOTES,
            "UTF-8"
        ) ?>
        - Barangay
    </title>

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <style>


/* RESET AND PAGE */
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
    background: #f4f8fd;
    color: #14264d;
}

/* SHARED NAVBAR */
.main-header {
    width: 100%;
    min-height: 88px;
    background: linear-gradient(135deg, #123873 0%, #16448f 48%, #0f3d82 100%);
    box-shadow: 0 5px 22px rgba(15, 23, 42, .18);
    position: relative;
    z-index: 1000;
}

.main-nav {
    width: min(1540px, calc(100% - 55px));
    min-height: 88px;
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
    color: rgba(255,255,255,.86);
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
    transition: background .2s ease, transform .2s ease;
}

.nav-item:hover {
    background: rgba(255,255,255,.14);
}

.nav-item.active {
    background: linear-gradient(135deg, #3b91f7, #1473ed);
    box-shadow: 0 7px 18px rgba(0,0,0,.16);
}

.nav-icon {
    display: inline-flex;
    width: 20px;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    line-height: 1;
    flex-shrink: 0;
}

.nav-icon.services-icon {
    height: 20px;
    flex: 0 0 20px;
}

.nav-icon.services-icon svg {
    display: block;
    width: 18px;
    height: 18px;
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
    background: rgba(255,255,255,.13);
    border: 1px solid rgba(255,255,255,.08);
}

.nav-search span {
    color: #fff;
    font-size: 22px;
    line-height: 1;
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
    color: rgba(255,255,255,.75);
}

.admin-menu {
    display: flex;
    align-items: center;
    gap: 9px;
    color: #fff;
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
    border: 2px solid rgba(255,255,255,.55);
}

.admin-name strong {
    color: #fff;
    font-size: 14px;
    font-weight: 800;
}

.admin-arrow {
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
}

.admin-arrow i {
    display: block;
    line-height: 1;
}

/* ANNOUNCEMENT DETAILS — screenshot layout, without photo */
html, body {
    min-height: 100%;
}

body {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background:
        radial-gradient(circle at 0 30%, rgba(37, 99, 235, .10), transparent 17%),
        radial-gradient(circle at 100% 70%, rgba(37, 99, 235, .08), transparent 18%),
        linear-gradient(180deg, #f7faff 0%, #edf4ff 100%);
    color: #142b57;
}

main.container {
    width: min(1460px, calc(100% - 60px));
    margin: 24px auto 42px;
    flex: 1 0 auto;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 18px 10px;
    padding: 8px 0;
    color: #075de8;
    font-size: 16px;
    font-weight: 800;
    text-decoration: none;
    transition: color .2s ease, transform .2s ease;
}
.back-link:hover {
    color: #0648b7;
    transform: translateX(-3px);
}

/* Main white card mirrors the reference; intentionally no image */
.announcement-card {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    padding: clamp(26px, 3vw, 42px);
    border: 1px solid rgba(214, 229, 251, .95);
    border-top: 5px solid #1769f5;
    border-radius: 28px;
    background: linear-gradient(120deg, rgba(255,255,255,.99), rgba(249,251,255,.98));
    box-shadow: 0 18px 46px rgba(28, 70, 135, .12);
}
.announcement-card::before {
    content: "";
    position: absolute;
    z-index: -1;
    top: -120px;
    right: -85px;
    width: 300px;
    height: 300px;
    border: 34px solid rgba(49, 119, 245, .055);
    border-radius: 50%;
    pointer-events: none;
}
.announcement-card::after {
    content: "";
    position: absolute;
    z-index: -1;
    right: 0;
    top: 0;
    width: 5px;
    height: 100%;
    background: linear-gradient(180deg, #80b9ff, rgba(128,185,255,0));
    pointer-events: none;
}

.detail-category {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 10px 18px;
    border: 1px solid #bdeed8;
    border-radius: 30px;
    background: linear-gradient(135deg, #e2fff1, #d2f7e6);
    color: #087b50;
    font-size: 13px;
    font-weight: 850;
    letter-spacing: .2px;
}
.detail-category i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 31px;
    height: 31px;
    margin: -5px 0 -5px -7px;
    border-radius: 50%;
    background: #0a9d78;
    color: #fff;
    font-size: 15px;
}

.announcement-title {
    position: relative;
    max-width: 100%;
    margin: 17px 0 20px;
    color: #122b5c;
    font-size: clamp(32px, 3.6vw, 48px);
    font-weight: 900;
    letter-spacing: -1.35px;
    line-height: 1.16;
    overflow-wrap: anywhere;
}
.announcement-title .title-highlight {
    color: #0866ed;
}
.announcement-title::after {
    content: "";
    display: block;
    width: 80px;
    height: 4px;
    margin-top: 16px;
    border-radius: 20px;
    background: linear-gradient(90deg, #0866ed, #77b6ff);
}

.announcement-date {
    position: relative;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px 18px;
    padding: 0 0 23px;
    border-bottom: 1px solid #e0e9f7;
    color: #526b91;
    font-size: 15px;
    line-height: 1.7;
}
.announcement-date > span:first-child {
    display: inline-flex;
    align-items: center;
    gap: 9px;
}
.announcement-date i {
    color: #1769f5;
    font-size: 17px;
}
.detail-published {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 7px 13px;
    border-radius: 30px;
    background: #d9fbe9;
    color: #087c4d;
    font-size: 12px;
    font-weight: 850;
}
.detail-published i {
    color: #087c4d;
    font-size: 14px;
}

/* Keep the announcement text close to its heading; no oversized empty panel */
.announcement-content {
    position: relative;
    margin-top: 24px;
    padding: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    color: #344b70;
    font-size: 16px;
    line-height: 1.85;
    white-space: normal;
    overflow-wrap: anywhere;
}
.detail-content-heading {
    display: flex;
    align-items: center;
    gap: 13px;
    margin: 0 0 12px;
    color: #15366e;
    font-size: 15px;
    font-weight: 850;
}
.detail-content-icon {
    width: 43px;
    height: 43px;
    flex: 0 0 43px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    background: #dfecff;
    color: #0866ed;
    font-size: 19px;
}
.detail-content-text {
    max-width: 1050px;
    margin: 0;
    padding: 0;
    color: #243e68;
    font-size: 17px;
    line-height: 1.8;
    white-space: pre-line;
    overflow-wrap: anywhere;
}

.detail-info-strip {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 14px 30px;
    margin-top: 24px;
    padding: 15px 20px;
    border: 1px solid #e0eaff;
    border-radius: 30px;
    background: #eaf2ff;
}
.detail-info-item {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: #153b77;
    font-size: 13px;
    font-weight: 800;
}
.detail-info-item i {
    color: #1769f5;
    font-size: 18px;
}
.detail-info-item:last-child i {
    color: #0b9b70;
}

.detail-actions {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: flex-start;
    flex-wrap: wrap;
    gap: 13px;
    margin-top: 27px;
}
.detail-action-group {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}
.detail-btn {
    min-height: 49px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 0 23px;
    border: 1px solid transparent;
    border-radius: 28px;
    font-size: 13px;
    font-weight: 850;
    text-decoration: none;
    cursor: pointer;
    transition: transform .2s ease, box-shadow .2s ease, background .2s ease;
}
.detail-btn:hover {
    transform: translateY(-2px);
}
.detail-btn-primary {
    color: #fff;
    background: linear-gradient(135deg, #1678ff, #0755e7);
    box-shadow: 0 7px 17px rgba(8, 102, 237, .2);
}
.detail-btn-primary:hover {
    box-shadow: 0 10px 22px rgba(8, 102, 237, .27);
}
.detail-btn-light {
    color: #123c7b;
    background: #eaf2ff;
    border-color: #e0eaff;
}
.detail-btn-light:hover {
    background: #dceaff;
}

/* Keep the existing footer appearance and placement unchanged. */
.public-footer {
    width: 100%;
    min-height: 48px;
    margin-top: auto;
    padding: 14px 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: linear-gradient(135deg, #0b1e3d, #102a56);
    color: #cbd5e1;
    font-size: 13px;
    line-height: 1.5;
    text-align: center;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
}

#detailBackToTop {
    position: fixed;
    right: 25px;
    bottom: 25px;
    z-index: 999;
    width: 49px;
    height: 49px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    border-radius: 50%;
    color: #fff;
    background: linear-gradient(135deg, #2684ff, #0755e7);
    box-shadow: 0 7px 20px rgba(8, 102, 237, .25);
    cursor: pointer;
    opacity: 0;
    visibility: hidden;
    transform: translateY(8px);
    transition: .2s ease;
}
#detailBackToTop.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}
#detailBackToTop:hover {
    transform: translateY(-3px);
}

@media (max-width: 1250px) {
    main.container { width: calc(100% - 40px); }
}
@media (max-width: 700px) {
    main.container {
        width: calc(100% - 24px);
        margin: 18px auto 28px;
    }
    .back-link {
        margin-left: 4px;
        margin-bottom: 13px;
        font-size: 14px;
    }
    .announcement-card {
        padding: 23px 17px;
        border-radius: 19px;
    }
    .detail-category {
        padding: 9px 14px;
        font-size: 11px;
    }
    .announcement-title {
        margin: 16px 0 17px;
        font-size: clamp(28px, 8vw, 36px);
        letter-spacing: -.7px;
    }
    .announcement-date {
        gap: 9px 12px;
        padding-bottom: 18px;
        font-size: 12px;
    }
    .announcement-content {
        margin-top: 19px;
        padding: 19px 15px;
        border-radius: 15px;
    }
    .detail-content-heading {
        gap: 11px;
        font-size: 14px;
    }
    .detail-content-icon {
        width: 41px;
        height: 41px;
        flex-basis: 41px;
        font-size: 18px;
    }
    .detail-content-text { font-size: 15px; }
    .detail-info-strip {
        align-items: flex-start;
        flex-direction: column;
        gap: 13px;
        padding: 16px;
        border-radius: 16px;
    }
    .detail-actions,
    .detail-action-group {
        width: 100%;
        align-items: stretch;
        flex-direction: column;
    }
    .detail-btn {
        width: 100%;
        min-height: 45px;
        padding: 0 14px;
        font-size: 12px;
    }
    .public-footer {
        min-height: 48px;
        padding: 14px 12px;
        font-size: 12px;
    }
    #detailBackToTop {
        right: 15px;
        bottom: 15px;
        width: 43px;
        height: 43px;
    }
}
    </style>

</head>

<body>


<!-- =========================
     HEADER
========================= -->


<?php require __DIR__ . '/includes/navbar.php'; ?>



<!-- =========================
     MAIN
========================= -->

<!-- MAIN -->
<main class="container">

    <a href="announcements_public.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i>
        Back to Announcements
    </a>

    <?php
    $detailTitle = $announcement["title"];
    $detailContent = $announcement["content"];
    $detailText = strtolower($detailTitle . " " . $detailContent);

    if (preg_match('/medical|health|vaccin|check.?up|dental/', $detailText)) {
        $detailCategory = "Health Program";
        $detailIcon = "fa-kit-medical";
    } elseif (preg_match('/clean.?up|community|volunteer|tree.?plant|feeding/', $detailText)) {
        $detailCategory = "Community Activity";
        $detailIcon = "fa-people-group";
    } elseif (preg_match('/water|electric|road|maintenance|service|interruption/', $detailText)) {
        $detailCategory = "Service Update";
        $detailIcon = "fa-droplet";
    } elseif (preg_match('/meeting|assembly|consultation|orientation/', $detailText)) {
        $detailCategory = "Community Meeting";
        $detailIcon = "fa-users";
    } else {
        $detailCategory = "General Announcement";
        $detailIcon = "fa-bullhorn";
    }

    $detailTimestamp = strtotime($announcement["created_at"]);
    $detailDate = $detailTimestamp
        ? date("F d, Y", $detailTimestamp)
        : "Date unavailable";
    ?>

    <article class="announcement-card">

        <div class="detail-category">
            <i class="fa-solid <?= htmlspecialchars($detailIcon, ENT_QUOTES, 'UTF-8') ?>"></i>
            <?= htmlspecialchars($detailCategory, ENT_QUOTES, 'UTF-8') ?>
        </div>

        <?php
        $titleParts = preg_split('/\s+/', trim($detailTitle), 2);
        $titleFirst = $titleParts[0] ?? '';
        $titleRest = $titleParts[1] ?? '';
        ?>
        <h2 class="announcement-title">
            <?= htmlspecialchars($titleFirst, ENT_QUOTES, 'UTF-8') ?><?php if ($titleRest !== ''): ?> <span class="title-highlight"><?= htmlspecialchars($titleRest, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
        </h2>

        <div class="announcement-date">

            <span>
                <i class="fa-regular fa-calendar-days"></i>
                Posted on <?= htmlspecialchars($detailDate, ENT_QUOTES, 'UTF-8') ?>
            </span>

            <span class="detail-published">
                <i class="fa-solid fa-circle-check"></i>
                Published
            </span>

        </div>

        <div class="announcement-content">

            <div class="detail-content-heading">
                <span class="detail-content-icon">
                    <i class="fa-solid fa-users"></i>
                </span>

                <span>Announcement Information</span>
            </div>

            <div class="detail-content-text"><?= htmlspecialchars(
                $detailContent,
                ENT_QUOTES,
                "UTF-8"
            ) ?></div>

        </div>

        <div class="detail-info-strip">

            <div class="detail-info-item">
                <i class="fa-solid fa-people-group"></i>
                For All Residents
            </div>

            <div class="detail-info-item">
                <i class="fa-solid fa-location-dot"></i>
                Barangay Community
            </div>

            <div class="detail-info-item">
                <i class="fa-solid fa-bell"></i>
                Stay Informed
            </div>

        </div>

        <div class="detail-actions">

            <a
                href="announcements_public.php"
                class="detail-btn detail-btn-primary"
            >
                <i class="fa-solid fa-arrow-left"></i>
                Back to Announcements
            </a>

            <div class="detail-action-group">

                <button
                    type="button"
                    class="detail-btn detail-btn-light"
                    id="shareAnnouncement"
                >
                    <i class="fa-solid fa-share-nodes"></i>
                    Share Announcement
                </button>


            </div>

        </div>

    </article>

</main>

<button
    type="button"
    id="detailBackToTop"
    aria-label="Back to top"
    title="Back to top"
>
    <i class="fa-solid fa-arrow-up"></i>
</button>

<script>
(function () {
    const shareButton = document.getElementById("shareAnnouncement");
    const backToTop = document.getElementById("detailBackToTop");

    if (shareButton) {
        shareButton.addEventListener("click", async function () {
            const shareData = {
                title: document.querySelector(".announcement-title")?.innerText
                    || document.title,
                text: "Barangay announcement from BONGDO",
                url: window.location.href
            };

            try {
                if (navigator.share) {
                    await navigator.share(shareData);
                } else if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(shareData.url);
                    alert("Announcement link copied!");
                } else {
                    window.prompt("Copy this announcement link:", shareData.url);
                }
            } catch (error) {
                if (error.name !== "AbortError") {
                    window.prompt("Copy this announcement link:", shareData.url);
                }
            }
        });
    }

    function toggleBackToTop() {
        if (window.scrollY > 300) {
            backToTop.classList.add("show");
        } else {
            backToTop.classList.remove("show");
        }
    }

    window.addEventListener("scroll", toggleBackToTop, { passive: true });

    backToTop.addEventListener("click", function () {
        window.scrollTo({
            top: 0,
            behavior: "smooth"
        });
    });

    toggleBackToTop();
})();
</script>


<!-- =========================
     FOOTER
========================= -->

<footer class="public-footer">

    © <?= date("Y") ?>
    Barangay Management System.
    All rights reserved.

</footer>


</body>

</html>

