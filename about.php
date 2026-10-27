
<?php
require_once __DIR__ . "/config/database.php";

/* BARANGAY SETTINGS */
$settings = [
    "barangay_name"     => "BONGDO",
    "municipality_city" => "Borbon",
    "province"          => "Cebu",
    "contact_number"    => "",
    "address"           => "Bongdo, Borbon, Cebu"
];

if (isset($conn) && $conn instanceof mysqli) {
    $settingsResult = $conn->query("
        SELECT barangay_name, municipality_city, province,
               contact_number, address
        FROM barangay_settings
        ORDER BY id ASC
        LIMIT 1
    ");

    if ($settingsResult && $settingsResult->num_rows > 0) {
        $dbSettings = $settingsResult->fetch_assoc();

        foreach ($settings as $key => $default) {
            if (
                isset($dbSettings[$key]) &&
                trim((string)$dbSettings[$key]) !== ""
            ) {
                $settings[$key] = trim((string)$dbSettings[$key]);
            }
        }
    }
}

$barangayName = $settings["barangay_name"] ?: "Barangay";

$locationParts = array_filter([
    $settings["address"],
    $settings["municipality_city"],
    $settings["province"]
]);

$location = implode(", ", array_unique($locationParts));

function about_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= about_e($barangayName) ?> | About</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/navbar.css">

    <style>
        :root {
            --primary: #0866ed;
            --navy: #102a56;
            --text: #142d58;
            --muted: #637796;
            --border: #e1eaf7;
            --shadow: 0 10px 28px rgba(20, 58, 115, .07);
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
            display: flex;
            flex-direction: column;
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            color: var(--text);
            background:
                radial-gradient(
                    circle at 0 8%,
                    rgba(44, 115, 237, .08),
                    transparent 28%
                ),
                linear-gradient(180deg, #f7faff, #f1f6fd);
        }

        /* MAIN CONTAINER */
        .about-container {
            width: min(1450px, calc(100% - 56px));
            margin: 24px auto 42px;
            flex: 1;
        }

        /* HERO */
        .about-hero {
            position: relative;
            overflow: hidden;
            min-height: 270px;
            display: flex;
            align-items: center;
            padding: 42px 48px;
            border: 1px solid #dce8f8;
            border-radius: 24px;
            background:
                radial-gradient(
                    circle at 85% 50%,
                    rgba(89,151,255,.19) 0 100px,
                    transparent 102px
                ),
                radial-gradient(
                    circle at 85% 50%,
                    rgba(89,151,255,.12) 0 145px,
                    transparent 147px
                ),
                linear-gradient(
                    115deg,
                    #fff 0%,
                    #f4f8ff 65%,
                    #e8f1ff 100%
                );
            box-shadow: var(--shadow);
        }

        .hero-copy {
            position: relative;
            z-index: 1;
            max-width: 760px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 13px;
            color: var(--primary);
            font-size: 11px;
            font-weight: 900;
            letter-spacing: 2.5px;
            text-transform: uppercase;
        }

        .eyebrow::before {
            content: "";
            width: 25px;
            height: 3px;
            border-radius: 5px;
            background: var(--primary);
        }

        h1 {
            margin: 0;
            color: #142b5c;
            font-size: clamp(36px, 5vw, 58px);
            line-height: 1.06;
            letter-spacing: -1.8px;
            font-weight: 900;
        }

        h1 span {
            color: var(--primary);
        }

        .hero-copy p {
            max-width: 680px;
            margin: 18px 0 0;
            color: #5d7192;
            font-size: 15px;
            line-height: 1.8;
        }

        .hero-mark {
            position: absolute;
            right: 8%;
            top: 50%;
            transform: translateY(-50%);
            width: 150px;
            height: 150px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(8,102,237,.13);
            border-radius: 35px;
            color: #2479f3;
            background: rgba(255,255,255,.75);
            box-shadow: 0 18px 40px rgba(25,87,175,.10);
            font-size: 72px;
        }

        /* SECTION HEADINGS */
        .section-heading {
            margin: 30px 0 16px;
            display: flex;
            align-items: center;
            gap: 13px;
        }

        .heading-icon {
            width: 48px;
            height: 48px;
            flex: 0 0 48px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            color: #fff;
            background: linear-gradient(135deg, #4d9bff, #0866ed);
            box-shadow: 0 7px 16px rgba(8,102,237,.18);
            font-size: 21px;
        }

        .section-heading h2 {
            margin: 0;
            color: #142b5c;
            font-size: 24px;
            line-height: 1.25;
            font-weight: 900;
        }

        .section-heading p {
            margin: 5px 0 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        /* ABOUT CARDS */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .info-card {
            min-width: 0;
            padding: 25px;
            border: 1px solid var(--border);
            border-radius: 20px;
            background: rgba(255,255,255,.96);
            box-shadow: var(--shadow);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .info-card:hover,
        .value-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 30px rgba(20,58,115,.11);
        }

        .card-icon {
            width: 49px;
            height: 49px;
            display: grid;
            place-items: center;
            margin-bottom: 16px;
            border-radius: 15px;
            color: #0866ed;
            background: #e6f0ff;
            font-size: 22px;
        }

        .info-card h3 {
            margin: 0 0 10px;
            color: #152f60;
            font-size: 19px;
            font-weight: 900;
        }

        .info-card p {
            margin: 0;
            color: #647797;
            font-size: 14px;
            line-height: 1.8;
        }

        /* SERVICE COMMITMENTS */
        .values-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
        }

        .value-card {
            padding: 22px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: #fff;
            box-shadow: var(--shadow);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .value-card .card-icon {
            margin-bottom: 13px;
        }

        .value-card h3 {
            margin: 0 0 8px;
            font-size: 17px;
            font-weight: 900;
        }

        .value-card p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
            line-height: 1.7;
        }

        /* CONTACT PANEL */
        .contact-panel {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(250px, .7fr);
            align-items: center;
            gap: 22px;
            margin-top: 30px;
            padding: 28px;
            border-radius: 22px;
            color: #fff;
            background: linear-gradient(135deg, #123873, #0b2c62);
            box-shadow: 0 12px 28px rgba(11,46,101,.17);
        }

        .contact-panel h2 {
            margin: 0 0 9px;
            font-size: 24px;
            font-weight: 900;
        }

        .contact-panel p {
            margin: 0;
            color: rgba(255,255,255,.78);
            font-size: 13px;
            line-height: 1.7;
        }

        .contact-list {
            display: grid;
            gap: 12px;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            color: #fff;
            font-size: 13px;
            line-height: 1.6;
            overflow-wrap: anywhere;
        }

        .contact-item i {
            width: 34px;
            height: 34px;
            flex: 0 0 34px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            color: #fff;
            background: rgba(255,255,255,.14);
        }

        /* FOOTER — MATCH INDEX.PHP */
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
            border-top: 1px solid rgba(255,255,255,.05);
        }

        /* TABLET */
        @media (max-width: 900px) {
            .about-container {
                width: calc(100% - 32px);
            }

            .hero-mark {
                right: 5%;
                width: 115px;
                height: 115px;
                font-size: 55px;
            }

            .hero-copy {
                max-width: 70%;
            }

            .values-grid {
                grid-template-columns: 1fr;
            }

            .contact-panel {
                grid-template-columns: 1fr;
            }
        }

        /* MOBILE */
        @media (max-width: 600px) {
            .about-container {
                width: calc(100% - 22px);
                margin-top: 12px;
                margin-bottom: 28px;
            }

            .about-hero {
                min-height: 260px;
                padding: 28px 22px;
                border-radius: 18px;
            }

            .hero-copy {
                max-width: 100%;
            }

            .hero-mark {
                right: 16px;
                top: auto;
                bottom: 15px;
                transform: none;
                width: 52px;
                height: 52px;
                border-radius: 15px;
                font-size: 25px;
            }

            .hero-copy p {
                max-width: 100%;
                font-size: 13px;
                padding-bottom: 30px;
            }

            .section-heading {
                margin-top: 24px;
            }

            .section-heading h2 {
                font-size: 20px;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .info-card {
                padding: 21px;
            }

            .contact-panel {
                padding: 23px 20px;
            }

            footer {
                min-height: 48px;
                padding: 14px 12px;
                font-size: 11px;
            }
        }

    /* ==========================================
       ABOUT PAGE — NAVBAR MATCH WITH INDEX.PHP
       ========================================== */

    .main-header {
        width: 100%;
        height: 88px;
        background: linear-gradient(
            135deg,
            #123873 0%,
            #16448f 48%,
            #0f3d82 100%
        );
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
        box-shadow: 0 6px 18px rgba(0, 0, 0, .18);
    }

    .brand-text {
        min-width: 0;
    }

    .brand-text h1 {
        margin: 0;
        color: #fff;
        font-size: 23px;
        line-height: 1.1;
        font-weight: 900;
        letter-spacing: .3px;
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
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        min-width: 0;
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
        transition: background .2s ease, transform .2s ease,
                    box-shadow .2s ease;
    }

    .nav-item:hover {
        background: rgba(255, 255, 255, .14);
        transform: translateY(-1px);
    }

    .nav-item.active {
        background: linear-gradient(135deg, #3b91f7, #1473ed);
        box-shadow: 0 7px 18px rgba(0, 0, 0, .16);
    }


/* ==========================================
   FIX NAVBAR SEARCH BOX AND ADMIN PROFILE
   ========================================== */

.main-nav .nav-right {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-shrink: 0;
}

.main-nav .nav-search-wrap {
    position: relative;
    width: 282px;
}

.main-nav .nav-search {
    width: 100%;
    height: 43px;
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 0 15px;
    border-radius: 24px;
    background: rgba(255, 255, 255, .13);
    border: 1px solid rgba(255, 255, 255, .15);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .04);
    transition: background .2s ease, box-shadow .2s ease;
}

.main-nav .nav-search:focus-within {
    background: rgba(255, 255, 255, .2);
    box-shadow: 0 0 0 3px rgba(255, 255, 255, .08);
}

.main-nav .nav-search span {
    color: #fff;
    font-size: 22px;
    line-height: 1;
    flex-shrink: 0;
}

.main-nav .nav-search input {
    width: 100%;
    min-width: 0;
    padding: 0;
    border: 0;
    outline: none;
    background: transparent;
    color: #fff;
    font-size: 13px;
}

.main-nav .nav-search input::placeholder {
    color: rgba(255, 255, 255, .8);
}

.main-nav .clear-service-search {
    border: 0;
    background: transparent;
    color: #fff;
    font-size: 19px;
    cursor: pointer;
}

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
    flex-shrink: 0;
    border-radius: 50%;
    background: #eaf2ff;
    color: #214c8d;
    font-size: 21px;
    border: 2px solid rgba(255, 255, 255, .55);
    box-shadow: 0 3px 10px rgba(0, 0, 0, .1);
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
    color: #fff;
    font-size: 11px;
    flex-shrink: 0;
    opacity: .9;
}

.main-nav .admin-arrow i {
    display: block;
    color: #fff;
    line-height: 1;
}

/* Tablet */
@media (max-width: 1250px) {
    .main-nav .nav-search-wrap {
        width: 190px;
    }
}

@media (max-width: 1000px) {
    .main-nav .nav-right {
        gap: 10px;
    }

    .main-nav .nav-search-wrap {
        width: 150px;
    }

    .main-nav .admin-name {
        display: none;
    }
}

/* Mobile */
@media (max-width: 700px) {
    .main-nav .nav-right {
        display: none;
    }
}


/* Services grid icon */
.main-nav .services-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    flex: 0 0 20px;
}

.main-nav .services-icon svg {
    display: block;
    width: 18px;
    height: 18px;
    fill: currentColor;
}


/* Announcements megaphone icon */
.main-nav .announcement-nav .nav-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    flex: 0 0 20px;
    color: #fff;
    font-size: 18px;
    line-height: 1;
}

.main-nav .announcement-nav .nav-icon i {
    display: inline-block;
    color: inherit;
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
}

/* Red notification dot */
.main-nav .announcement-nav {
    position: relative;
}

.main-nav .announcement-nav .notification-dot {
    position: absolute;
    top: 6px;
    right: 7px;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #ef4444;
    border: 2px solid #17458b;
    box-sizing: content-box;
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
// Gamiton ang existing navbar; ayaw usba ang includes/navbar.php.
require __DIR__ . "/includes/navbar.php";
?>

<main class="about-container">

    <!-- HERO -->
    <section class="about-hero">
        <div class="hero-copy">
            <div class="eyebrow">Barangay Management System</div>

            <h1>
                About <span><?= about_e($barangayName) ?></span>
            </h1>

            <p>
                Learn more about our barangay and the online platform
                created to make community information and public services
                easier to access.
            </p>
        </div>

        <div class="hero-mark" aria-hidden="true">
            <i class="fa-solid fa-landmark"></i>
        </div>
    </section>

    <!-- ABOUT OUR BARANGAY -->
    <section aria-labelledby="barangay-heading">
        <div class="section-heading">
            <div class="heading-icon">
                <i class="fa-solid fa-people-group"></i>
            </div>

            <div>
                <h2 id="barangay-heading">About Our Barangay</h2>
                <p>Serving residents and supporting a connected community.</p>
            </div>
        </div>

        <div class="info-grid">

            <article class="info-card">
                <div class="card-icon">
                    <i class="fa-solid fa-house-flag"></i>
                </div>

                <h3>Barangay <?= about_e($barangayName) ?></h3>

                <p>
                    Barangay <?= about_e($barangayName) ?> serves its community
                    through local governance, public information, and barangay
                    services. This page provides residents with a central place
                    to learn about the barangay and access information published
                    through this website.
                </p>
            </article>

            <article class="info-card">
                <div class="card-icon">
                    <i class="fa-solid fa-laptop-file"></i>
                </div>

                <h3>About the System</h3>

                <p>
                    The Barangay Management System is an online platform where
                    residents can explore available services, submit service
                    requests, check request status when supported by the system,
                    and read public announcements. It is designed to make
                    information easier to find and help streamline service access.
                </p>
            </article>

        </div>
    </section>

    <!-- SERVICE COMMITMENTS -->
    <section aria-labelledby="purpose-heading">
        <div class="section-heading">
            <div class="heading-icon">
                <i class="fa-solid fa-bullseye"></i>
            </div>

            <div>
                <h2 id="purpose-heading">Our Service Commitments</h2>
                <p>Guiding principles for a helpful and accessible online service.</p>
            </div>
        </div>

        <div class="values-grid">

            <article class="value-card">
                <div class="card-icon">
                    <i class="fa-solid fa-handshake-angle"></i>
                </div>

                <h3>Accessible Service</h3>

                <p>
                    Make barangay information and available online services
                    easier for residents to access.
                </p>
            </article>

            <article class="value-card">
                <div class="card-icon">
                    <i class="fa-solid fa-eye"></i>
                </div>

                <h3>Clear Information</h3>

                <p>
                    Present announcements and service information in a clear,
                    organized, and understandable way.
                </p>
            </article>

            <article class="value-card">
                <div class="card-icon">
                    <i class="fa-solid fa-people-arrows-left-right"></i>
                </div>

                <h3>Community Connection</h3>

                <p>
                    Support communication between residents and the barangay
                    office through the online platform.
                </p>
            </article>

        </div>
    </section>

    <!-- CONTACT INFORMATION -->
    <section class="contact-panel" aria-labelledby="contact-heading">

        <div>
            <h2 id="contact-heading">Barangay Office Information</h2>

            <p>
                For official concerns or assistance, contact or visit the
                barangay office using the information available below.
            </p>
        </div>

        <div class="contact-list">

            <div class="contact-item">
                <i class="fa-solid fa-location-dot"></i>

                <span>
                    <strong>Address</strong><br>
                    <?= about_e($location ?: "Please contact the barangay office for location details.") ?>
                </span>
            </div>

            <div class="contact-item">
                <i class="fa-solid fa-phone"></i>

                <span>
                    <strong>Contact Number</strong><br>
                    <?= about_e($settings["contact_number"] ?: "Contact number not yet provided.") ?>
                </span>
            </div>

            <div class="contact-item">
                <i class="fa-regular fa-clock"></i>

                <span>
                    <strong>Office Hours</strong><br>
                    Monday – Friday, 8:00 AM – 5:00 PM<br>
                    <small>Please confirm official office hours with the barangay.</small>
                </span>
            </div>

        </div>
    </section>

</main>

<!-- FOOTER — SAME STYLE AS INDEX.PHP -->
<footer>
    © <?= date("Y") ?> Barangay Management System. All rights reserved.
</footer>

</body>
</html>
