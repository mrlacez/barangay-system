
<?php
require_once "config/database.php";

/* =========================
   GET BARANGAY SETTINGS
========================= */

$settings = [
    "barangay_name" => "Barangay",
    "municipality_city" => "Borbon",
    "province" => "Cebu",
    "contact_number" => "0939 296 6391",
    "address" => "Bongdo, Borbon, Cebu"
];

$settings_result = $conn->query("
    SELECT barangay_name, municipality_city, province, contact_number, address
    FROM barangay_settings
    ORDER BY id ASC
    LIMIT 1
");

if ($settings_result && $settings_result->num_rows > 0) {
    $db_settings = $settings_result->fetch_assoc();

    foreach ($settings as $key => $default_value) {
        if (isset($db_settings[$key]) && trim((string)$db_settings[$key]) !== "") {
            $settings[$key] = trim((string)$db_settings[$key]);
        }
    }
}

$public_address = $settings["address"];

if ($public_address === "") {
    $public_address = trim(
        $settings["municipality_city"] . ", " . $settings["province"],
        " ,"
    );
}

$public_barangay_name = $settings["barangay_name"] !== ""
    ? $settings["barangay_name"]
    : "Barangay";

/* =========================
   GET LATEST ANNOUNCEMENTS
========================= */

$sql = "
    SELECT id, title, content, created_at
    FROM announcements
    ORDER BY created_at DESC
    LIMIT 3
";

$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Barangay Management System</title>

    <style>
    /* =========================================================
       BONGDO BARANGAY MANAGEMENT SYSTEM
       FULL MODERN PROFESSIONAL CSS
       ========================================================= */

    :root{
        --blue:#2563eb;
        --blue2:#1d4ed8;
        --blue3:#1473ed;
        --navy:#0f2f68;
        --dark:#102a56;
        --text:#14264d;
        --muted:#64748b;
        --light:#f4f8ff;
        --white:#ffffff;
        --border:#e3eaf5;

        --success:#16a34a;
        --danger:#ef4444;
        --warning:#f59e0b;

        --shadow:0 12px 30px rgba(15,42,86,.09);
    }


    /* =========================================================
       RESET
       ========================================================= */

    *{
        box-sizing:border-box;
    }

    html{
        scroll-behavior:smooth;
    }

    body{
        margin:0;
        min-height:100vh;

        font-family:
            Inter,
            Segoe UI,
            Arial,
            sans-serif;

        background:
            radial-gradient(
                circle at 0% 20%,
                rgba(37,99,235,.08),
                transparent 28%
            ),
            radial-gradient(
                circle at 100% 30%,
                rgba(59,130,246,.07),
                transparent 25%
            ),
            #f4f8fd;

        color:var(--text);
    }

    a{
        color:inherit;
    }

    button,
    input{
        font-family:inherit;
    }


    /* =========================================================
       MAIN HEADER / NAVBAR
       ========================================================= */

    .main-header{
        width:100%;
        height:88px;

        background:
            linear-gradient(
                135deg,
                #123873 0%,
                #16448f 48%,
                #0f3d82 100%
            );

        box-shadow:
            0 5px 22px rgba(15,23,42,.18);

        position:relative;
        z-index:1000;
    }


    /* =========================================================
       NAV CONTAINER
       ========================================================= */

    .main-nav{
        width:min(1540px,calc(100% - 55px));
        height:88px;

        margin:0 auto;

        display:flex;
        align-items:center;

        gap:25px;
    }


    /* =========================================================
       BRAND
       ========================================================= */

    .brand{
        display:flex;
        align-items:center;

        gap:13px;

        min-width:315px;

        flex-shrink:0;

        color:#fff;
    }

    .brand-logo{
        width:66px;
        height:66px;

        display:flex;
        align-items:center;
        justify-content:center;

        flex-shrink:0;

        background:#fff;

        border-radius:14px;

        color:#1768e8;

        font-size:34px;

        box-shadow:
            0 6px 18px rgba(0,0,0,.18);
    }

    .brand-text{
        min-width:0;
    }

    .brand-text h1{
        margin:0;

        color:#fff;

        font-size:23px;
        line-height:1.1;

        font-weight:900;

        letter-spacing:.3px;
    }

    .brand-text span{
        display:block;

        margin-top:5px;

        color:rgba(255,255,255,.86);

        font-size:13px;

        white-space:nowrap;
    }


    /* =========================================================
       NAVIGATION LINKS
       ========================================================= */

    .main-nav-links{
        flex:1;

        display:flex;
        align-items:center;
        justify-content:center;

        gap:5px;

        min-width:0;
    }

    .nav-item{
        position:relative;

        height:46px;

        padding:0 15px;

        display:flex;
        align-items:center;
        justify-content:center;

        gap:8px;

        border-radius:12px;

        color:#fff;

        text-decoration:none;

        font-size:14px;
        font-weight:700;

        white-space:nowrap;

        transition:
            background .2s ease,
            transform .2s ease,
            box-shadow .2s ease;
    }

    .nav-item:hover{
        background:rgba(255,255,255,.14);

        transform:translateY(-1px);
    }

    .nav-item.active{
        background:
            linear-gradient(
                135deg,
                #3b91f7,
                #1473ed
            );

        box-shadow:
            0 7px 18px rgba(0,0,0,.16);
    }

    .nav-icon{
        display:inline-flex;

        align-items:center;
        justify-content:center;

        font-size:18px;

        line-height:1;
    }


    /* =========================================================
       ANNOUNCEMENT NOTIFICATION DOT
       ========================================================= */

    .announcement-nav{
        position:relative;
    }

    .notification-dot{
        position:absolute;

        top:6px;
        right:7px;

        width:9px;
        height:9px;

        border-radius:50%;

        background:#ef4444;

        border:2px solid #17458b;

        box-shadow:
            0 0 0 1px rgba(255,255,255,.08);
    }


    /* =========================================================
       NAV RIGHT SIDE
       ========================================================= */

    .nav-right{
        display:flex;
        align-items:center;

        gap:20px;

        flex-shrink:0;
    }


    /* =========================================================
       SEARCH BOX
       ========================================================= */

    .nav-search{
        width:282px;
        height:43px;

        display:flex;
        align-items:center;

        gap:9px;

        padding:0 15px;

        border-radius:24px;

        background:
            rgba(255,255,255,.13);

        border:
            1px solid rgba(255,255,255,.08);

        box-shadow:
            inset 0 1px 0 rgba(255,255,255,.04);

        transition:
            background .2s ease,
            box-shadow .2s ease;
    }

    .nav-search:focus-within{
        background:
            rgba(255,255,255,.18);

        box-shadow:
            0 0 0 3px rgba(255,255,255,.08);
    }

    .nav-search span{
        flex:0 0 auto;

        color:#fff;

        font-size:22px;

        line-height:1;
    }

    .nav-search input{
        width:100%;

        min-width:0;

        border:0;
        outline:none;

        background:transparent;

        color:#fff;

        font-size:13px;
    }

    .nav-search input::placeholder{
        color:rgba(255,255,255,.75);
    }


    /* =========================================================
       ADMIN
       ========================================================= */

    .admin-menu{
        display:flex;
        align-items:center;

        gap:9px;

        color:#fff;

        cursor:pointer;

        white-space:nowrap;

        user-select:none;
    }

    .admin-avatar{
        width:42px;
        height:42px;

        display:flex;
        align-items:center;
        justify-content:center;

        border-radius:50%;

        background:#eaf2ff;

        color:#214c8d;

        font-size:21px;

        border:
            2px solid rgba(255,255,255,.55);

        box-shadow:
            0 3px 10px rgba(0,0,0,.10);
    }

    .admin-name strong{
        color:#fff;

        font-size:14px;

        font-weight:800;
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
    opacity: 0.9;
    vertical-align: middle;
}

.admin-arrow i {
    display: block;
    line-height: 1;
}



    /* =========================================================
       HOME LAYOUT
       ========================================================= */


.home-layout {
    width: min(1400px, calc(100% - 60px));
    margin: 12px auto 16px;
    display: grid;
    grid-template-columns: minmax(0, 2.15fr) minmax(330px, .9fr);
    gap: 16px;
    align-items: stretch;
}



    /* =========================================================
       HERO
       ========================================================= */

    .home-layout .hero{
        position:relative;

        min-height:535px;

        padding:52px 50px;

        border-radius:24px;

        overflow:hidden;

        background:
            linear-gradient(
                135deg,
                #ffffff 0%,
                #f9fbff 50%,
                #edf5ff 100%
            );

        border:1px solid #dbe7f5;

        box-shadow:
            0 18px 45px rgba(15,42,86,.09);

        display:flex;

        flex-direction:column;

        justify-content:center;

        align-items:flex-start;
    }


    /* =========================================================
       HERO BLUE DECORATION
       ========================================================= */

    .home-layout .hero::before{
        content:"";

        position:absolute;

        width:560px;
        height:560px;

        right:-170px;
        top:-250px;

        border-radius:50%;

        border:
            38px solid rgba(59,130,246,.16);

        box-shadow:
            0 0 0 22px rgba(59,130,246,.08),
            0 0 0 52px rgba(59,130,246,.04);

        pointer-events:none;
    }

    .home-layout .hero::after{
        content:"";

        position:absolute;

        width:420px;
        height:420px;

        left:-220px;
        bottom:-260px;

        border-radius:50%;

        background:
            radial-gradient(
                circle,
                rgba(59,130,246,.16),
                rgba(59,130,246,.04) 55%,
                transparent 72%
            );

        pointer-events:none;
    }


    /* =========================================================
       HERO TITLE
       ========================================================= */


.home-layout .hero {
    position: relative;
    min-height: 0;
    padding: 28px 32px;
    border-radius: 22px;
    overflow: hidden;
    background: linear-gradient(
        135deg,
        #ffffff 0%,
        #f9fbff 50%,
        #edf5ff 100%
    );
    border: 1px solid #dbe7f5;
    box-shadow: 0 18px 45px rgba(15,42,86,.09);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: flex-start;
}


    .home-layout .hero h2{
        position:relative;

        z-index:3;

        max-width:620px;

        margin:0 0 20px;

        color:#172554;

        font-size:
            clamp(44px,4.5vw,66px);

        line-height:.98;

        font-weight:900;

        letter-spacing:-2.5px;
    }

    .home-layout .hero h2::before{
        content:"BARANGAY MANAGEMENT SYSTEM";

        display:block;

        margin-bottom:22px;

        color:#64779a;

        font-size:12px;

        font-weight:800;

        letter-spacing:4px;

        line-height:1.4;
    }

    .home-layout .hero h2::after{
        content:"";

        display:block;

        width:54px;
        height:4px;

        margin-top:20px;

        border-radius:20px;

        background:
            linear-gradient(
                90deg,
                #2563eb,
                #60a5fa
            );
    }


    /* =========================================================
       BARANGAY NAME
       ========================================================= */

    .hero-barangay-name{
        display:block;

        color:#1673ed;

        font-weight:950;
    }


    /* =========================================================
       HERO DESCRIPTION
       ========================================================= */

    .home-layout .hero > p{
        position:relative;

        z-index:3;

        max-width:590px;

        margin:0 0 24px;

        color:#536987;

        font-size:16px;

        line-height:1.65;

        font-weight:500;
    }


    /* =========================================================
       ONLINE STATUS
       ========================================================= */

    .home-layout .online-service-status{
        position:relative;

        z-index:5;

        width:100%;
        max-width:430px;

        margin:0 0 22px;

        padding:14px 18px;

        border-radius:15px;

        background:
            linear-gradient(
                135deg,
                #ecfdf5,
                #f0fdf4
            );

        border:1px solid #bbf7d0;

        box-shadow:
            0 8px 20px rgba(22,101,52,.06);
    }

    .home-layout .online-service-status strong{
        display:block;

        color:#15803d;

        font-size:14px;

        font-weight:850;
    }

    .home-layout .online-service-status span{
        display:block;

        margin-top:4px;

        color:#52745e;

        font-size:12px;
    }


    /* =========================================================
       HERO ACTIONS
       ========================================================= */

    .hero-actions{
        position:relative;

        z-index:5;

        width:100%;
        max-width:900px;

        display:grid;

        grid-template-columns:
            repeat(3,1fr);

        gap:12px;
    }

    .hero-button{
        min-height:82px;

        padding:16px 18px;

        display:flex;

        align-items:center;

        justify-content:space-between;

        gap:10px;

        border-radius:17px;

        background:
            rgba(255,255,255,.92);

        border:
            1px solid #dce7f5;

        color:#102a56;

        text-decoration:none;

        font-size:14px;

        font-weight:800;

        box-shadow:
            0 8px 22px rgba(15,42,86,.07);

        transition:
            transform .22s ease,
            box-shadow .22s ease,
            border-color .22s ease;
    }

    .hero-button::after{
        content:"→";

        font-size:24px;

        font-weight:400;

        flex-shrink:0;
    }

    .hero-button:hover{
        transform:translateY(-4px);

        border-color:#bfdbfe;

        box-shadow:
            0 16px 32px rgba(15,42,86,.13);
    }

    .hero-button:first-child{
        background:
            linear-gradient(
                135deg,
                #1473ed,
                #2563eb
            );

        color:#fff;

        border-color:transparent;

        box-shadow:
            0 12px 28px rgba(37,99,235,.28);
    }

    .hero-button:first-child:hover{
        box-shadow:
            0 17px 34px rgba(37,99,235,.34);
    }


    /* =========================================================
       BARANGAY INFORMATION
       ========================================================= */

    #barangay-info{
        scroll-margin-top:105px;
    }

    .home-layout .barangay-info{
        padding:0;

        background:transparent;

        border:0;

        box-shadow:none;
    }

    .home-layout .info-container{
        height:100%;

        display:flex;

        flex-direction:column;

        gap:14px;
    }


    /* =========================================================
       INFORMATION HEADER
       ========================================================= */

    .home-layout .section-title{
        padding:22px 24px 5px;
    }

    .home-layout .section-title h2{
        margin:0;

        color:#14264d;

        font-size:21px;

        font-weight:900;
    }

    .home-layout .section-title p{
        margin:5px 0 0;

        color:#64748b;

        font-size:12px;
    }


    /* =========================================================
       INFORMATION GRID
       ========================================================= */

    .home-layout .info-grid{
        display:flex;

        flex-direction:column;

        gap:14px;
    }


    /* =========================================================
       INFORMATION CARD
       ========================================================= */

    .home-layout .info-card{
        position:relative;

        min-height:118px;

        padding:20px 20px 20px 24px;

        background:
            linear-gradient(
                135deg,
                #ffffff,
                #f8fbff
            );

        border:1px solid #e1e9f4;

        border-radius:20px;

        box-shadow:
            0 9px 25px rgba(15,42,86,.07);

        overflow:hidden;

        transition:
            transform .22s ease,
            box-shadow .22s ease;
    }

    .home-layout .info-card:hover{
        transform:translateY(-3px);

        box-shadow:
            0 16px 32px rgba(15,42,86,.12);
    }


    /* =========================================================
       CARD COLORED LEFT BORDER
       ========================================================= */

    .home-layout .info-card::before{
        content:"";

        position:absolute;

        left:0;
        top:0;
        bottom:0;

        width:4px;

        background:#2563eb;
    }

    .home-layout .info-card:nth-child(2)::before{
        background:#ef4444;
    }

    .home-layout .info-card:nth-child(3)::before{
        background:#16a34a;
    }


    /* =========================================================
       CARD BACKGROUNDS
       ========================================================= */

    .home-layout .info-card:first-child{
        background:
            linear-gradient(
                135deg,
                #eaf3ff,
                #ffffff
            );
    }

    .home-layout .info-card:nth-child(2){
        background:
            linear-gradient(
                135deg,
                #fff7f8,
                #ffffff
            );
    }

    .home-layout .info-card:nth-child(3){
        background:
            linear-gradient(
                135deg,
                #effcf5,
                #ffffff
            );
    }


    /* =========================================================
       INFORMATION ICON
       ========================================================= */

    .home-layout .info-icon{
        width:48px;
        height:48px;

        margin-bottom:10px;

        display:flex;

        align-items:center;
        justify-content:center;

        border-radius:15px;

        background:#e8f2ff;

        color:#2563eb;

        font-size:22px;

        box-shadow:
            inset 0 0 0 1px rgba(37,99,235,.04);
    }

    .home-layout .info-card:nth-child(2) .info-icon{
        background:#fff0f2;

        color:#dc2626;
    }

    .home-layout .info-card:nth-child(3) .info-icon{
        background:#eafaf2;

        color:#16a34a;
    }


    /* =========================================================
       INFORMATION CARD TEXT
       ========================================================= */

    .home-layout .info-card h3{
        margin:0 0 5px;

        color:#14264d;

        font-size:15px;

        font-weight:900;
    }

    .home-layout .info-card p{
        margin:3px 0;

        color:#64748b;

        font-size:12px;

        line-height:1.45;
    }


    /* =========================================================
       QUICK ACCESS
       ========================================================= */

    .quick-access-section{
        width:min(1400px,calc(100% - 60px));

        margin:0 auto 28px;
    }

    .quick-access-header{
        display:flex;

        align-items:flex-end;

        justify-content:space-between;

        margin-bottom:14px;
    }

    .quick-access-label{
        display:block;

        margin-bottom:4px;

        color:#2563eb;

        font-size:10px;

        font-weight:900;

        letter-spacing:2px;
    }

    .quick-access-header h2{
        margin:0;

        color:#14264d;

        font-size:24px;

        font-weight:900;
    }

    .quick-access-header p{
        margin:4px 0 0;

        color:#64748b;

        font-size:12px;
    }

    .view-services-link{
        color:#1261db;

        text-decoration:none;

        font-size:13px;

        font-weight:800;
    }

    .view-services-link:hover{
        text-decoration:underline;
    }


    /* =========================================================
       QUICK ACCESS GRID
       ========================================================= */

    .quick-access-grid{
        display:grid;

        grid-template-columns:
            repeat(6,1fr);

        gap:13px;
    }


    /* =========================================================
       QUICK CARD
       ========================================================= */

    .quick-card{
        min-height:108px;

        padding:17px 15px;

        display:flex;

        align-items:flex-start;

        gap:11px;

        background:
            rgba(255,255,255,.97);

        border:1px solid #e1e9f4;

        border-radius:17px;

        text-decoration:none;

        color:inherit;

        box-shadow:
            0 8px 22px rgba(15,42,86,.055);

        transition:
            transform .22s ease,
            border-color .22s ease,
            box-shadow .22s ease;
    }

    .quick-card:hover{
        transform:translateY(-4px);

        border-color:#bfdbfe;

        box-shadow:
            0 15px 30px rgba(15,42,86,.10);
    }


    /* =========================================================
       QUICK ICON
       ========================================================= */

    .quick-icon{
        flex:0 0 44px;

        width:44px;
        height:44px;

        display:flex;

        align-items:center;
        justify-content:center;

        border-radius:13px;

        font-size:20px;
    }

    .quick-icon.blue{
        background:#eaf3ff;
    }

    .quick-icon.green{
        background:#eafaf2;
    }

    .quick-icon.orange{
        background:#fff4e8;
    }

    .quick-icon.purple{
        background:#f3efff;
    }

    .quick-icon.red{
        background:#fff0f2;
    }

    .quick-icon.slate{
        background:#edf2f7;
    }


    /* =========================================================
       QUICK CONTENT
       ========================================================= */

    .quick-content{
        min-width:0;
    }

    .quick-content h3{
        margin:1px 0 5px;

        color:#172554;

        font-size:12px;

        font-weight:900;

        line-height:1.3;
    }

    .quick-content p{
        margin:0;

        color:#64748b;

        font-size:10px;

        line-height:1.45;
    }

    .quick-arrow{
        display:none;
    }


    /* =========================================================
       SERVICES PAGE
       ========================================================= */

    .services-section{
        width:min(1400px,calc(100% - 60px));

        margin:25px auto 35px;
    }

    .services-section .section-title{
        margin-bottom:20px;
    }

    .services-grid{
        display:grid;

        grid-template-columns:
            repeat(3,1fr);

        gap:18px;
    }

    .service-card{
        display:block;

        padding:25px;

        background:#fff;

        border:1px solid var(--border);

        border-radius:18px;

        text-decoration:none;

        color:inherit;

        box-shadow:
            0 8px 25px rgba(15,42,86,.06);

        transition:
            transform .2s ease,
            box-shadow .2s ease,
            border-color .2s ease;
    }

    .service-card:hover{
        transform:translateY(-4px);

        border-color:#bfdbfe;

        box-shadow:
            0 15px 30px rgba(15,42,86,.11);
    }

    .service-icon{
        width:52px;
        height:52px;

        display:flex;

        align-items:center;
        justify-content:center;

        border-radius:15px;

        background:#eff6ff;

        font-size:24px;

        margin-bottom:14px;
    }

    .service-card h3{
        margin:0 0 8px;

        color:#172554;

        font-size:17px;

        font-weight:850;
    }

    .service-card p{
        margin:0 0 15px;

        color:#64748b;

        font-size:13px;

        line-height:1.6;
    }

    .request-online{
        color:#2563eb;

        font-size:12px;

        font-weight:800;
    }


    /* =========================================================
       FOOTER
       ========================================================= */

    footer{
        min-height:62px;

        padding:18px 35px;

        display:flex;

        align-items:center;
        justify-content:center;

        background:
            linear-gradient(
                135deg,
                #0b1e3d,
                #102a56
            );

        color:#cbd5e1;

        font-size:12px;

        border-top:
            1px solid rgba(255,255,255,.05);
    }


    /* =========================================================
       BACK TO TOP
       ========================================================= */

    #backToTop{
        position:fixed;

        right:25px;
        bottom:25px;

        width:44px;
        height:44px;

        border:0;

        border-radius:50%;

        background:#2563eb;

        color:#fff;

        font-size:20px;

        box-shadow:
            0 8px 20px rgba(37,99,235,.30);

        cursor:pointer;

        opacity:0;

        visibility:hidden;

        transition:
            opacity .2s ease,
            visibility .2s ease,
            transform .2s ease;

        z-index:1000;
    }

    #backToTop.show{
        opacity:1;

        visibility:visible;
    }

    #backToTop:hover{
        transform:translateY(-3px);

        background:#1d4ed8;
    }


    /* =========================================================
       OFFLINE STATUS
       ========================================================= */

    .online-service-status.offline{
        background:#fef2f2;

        border-color:#fecaca;
    }

    .online-service-status.offline strong{
        color:#b91c1c;
    }

    .online-service-status.offline span{
        color:#7f1d1d;
    }

    .offline-disabled{
        opacity:.55;

        filter:grayscale(.25);

        cursor:not-allowed;
    }


    /* =========================================================
       TABLET
       ========================================================= */

    @media(max-width:1250px){

        .main-nav{
            width:calc(100% - 30px);

            gap:15px;
        }

        .brand{
            min-width:auto;
        }

        .brand-text h1{
            font-size:20px;
        }

        .brand-text span{
            font-size:11px;
        }

        .nav-item{
            padding:0 10px;

            font-size:12px;
        }

        .nav-icon{
            font-size:16px;
        }

        .nav-search{
            width:190px;
        }

        .home-layout{
            width:min(1100px,calc(100% - 35px));

            grid-template-columns:1fr;
        }

        .home-layout .hero{
            min-height:500px;
        }

        .home-layout .info-grid{
            display:grid;

            grid-template-columns:
                repeat(3,1fr);

            gap:14px;
        }

        .quick-access-section{
            width:min(1100px,calc(100% - 35px));
        }

        .quick-access-grid{
            grid-template-columns:
                repeat(3,1fr);
        }

        .services-section{
            width:min(1100px,calc(100% - 35px));
        }

        .services-grid{
            grid-template-columns:
                repeat(2,1fr);
        }
    }


    /* =========================================================
       SMALL TABLET
       ========================================================= */

    @media(max-width:1000px){

        .brand-text span{
            display:none;
        }

        .nav-right{
            gap:10px;
        }

        .nav-search{
            width:160px;
        }

        .admin-name{
            display:none;
        }

        .home-layout .hero{
            min-height:520px;
        }

        .hero-actions{
            max-width:100%;
        }
    }


    /* =========================================================
       MOBILE
       ========================================================= */

    @media(max-width:700px){

        .main-header{
            height:auto;

            min-height:88px;
        }

        .main-nav{
            width:calc(100% - 20px);

            height:auto;

            min-height:88px;

            padding:10px 0;

            flex-wrap:wrap;

            gap:10px;
        }

        .brand{
            width:100%;

            min-width:0;

            justify-content:flex-start;
        }

        .brand-logo{
            width:52px;
            height:52px;

            font-size:27px;
        }

        .brand-text h1{
            font-size:18px;
        }

        .brand-text span{
            display:block;

            font-size:11px;
        }

        .main-nav-links{
            order:3;

            width:100%;

            flex:none;

            justify-content:flex-start;

            overflow-x:auto;

            padding-bottom:3px;

            scrollbar-width:none;
        }

        .main-nav-links::-webkit-scrollbar{
            display:none;
        }

        .nav-item{
            flex:0 0 auto;

            height:40px;

            padding:0 11px;

            font-size:11px;
        }

        .nav-icon{
            font-size:15px;
        }

        .nav-right{
            display:none;
        }


        /* HOME */

        .home-layout{
            width:calc(100% - 20px);

            margin:10px auto 18px;

            gap:13px;
        }

        .home-layout .hero{
            min-height:520px;

            padding:35px 20px;

            text-align:center;

            align-items:center;

            background:
                linear-gradient(
                    180deg,
                    #ffffff,
                    #eff6ff
                );
        }

        .home-layout .hero h2{
            max-width:100%;

            font-size:38px;

            letter-spacing:-1.8px;
        }

        .home-layout .hero h2::before{
            font-size:9px;

            letter-spacing:3px;

            margin-bottom:16px;
        }

        .home-layout .hero h2::after{
            margin-left:auto;
            margin-right:auto;
        }

        .home-layout .hero > p{
            font-size:13px;

            line-height:1.6;
        }

        .home-layout .online-service-status{
            max-width:100%;

            text-align:left;
        }

        .hero-actions{
            grid-template-columns:1fr;

            max-width:380px;
        }

        .hero-button{
            width:100%;

            min-height:60px;
        }


        /* INFORMATION */

        .home-layout .info-grid{
            display:grid;

            grid-template-columns:1fr;
        }

        .home-layout .section-title{
            padding:18px 8px 5px;
        }

        .home-layout .section-title h2{
            font-size:19px;
        }

        .home-layout .info-card{
            min-height:120px;
        }


        /* QUICK ACCESS */

        .quick-access-section{
            width:calc(100% - 20px);
        }

        .quick-access-header{
            flex-direction:column;

            align-items:flex-start;

            gap:8px;
        }

        .quick-access-header h2{
            font-size:22px;
        }

        .quick-access-grid{
            grid-template-columns:1fr;
        }


        /* SERVICES */

        .services-section{
            width:calc(100% - 20px);

            margin:18px auto 25px;
        }

        .services-grid{
            grid-template-columns:1fr;

            gap:13px;
        }

        .service-card{
            padding:20px;
        }


        /* FOOTER */

        footer{
            padding:18px 12px;

            text-align:center;

            font-size:11px;
        }

        #backToTop{
            right:15px;

            bottom:15px;

            width:40px;

            height:40px;
        }
    }


    /* =========================================================
       VERY SMALL PHONES
       ========================================================= */

    @media(max-width:400px){

        .brand-logo{
            width:48px;
            height:48px;

            font-size:24px;
        }

        .brand-text h1{
            font-size:16px;
        }

        .brand-text span{
            font-size:10px;
        }

        .nav-item{
            padding:0 9px;

            font-size:10px;
        }

        .home-layout .hero{
            padding:30px 15px;
        }

        .home-layout .hero h2{
            font-size:32px;
        }

        .home-layout .hero h2::before{
            font-size:8px;

            letter-spacing:2px;
        }

        .hero-button{
            font-size:13px;
        }

        .quick-content h3{
            font-size:12px;
        }

        .quick-content p{
            font-size:10px;
        }
    }

.nav-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    font-size: 16px;
    line-height: 1;
}

.brand-logo i {
    font-size: 34px;
}

.admin-avatar i {
    font-size: 21px;
}



.nav-icon.services-icon {
    display: inline-flex;
    width: 20px;
    height: 20px;
    flex: 0 0 20px;
    align-items: center;
    justify-content: center;
    font-size: 17px;
    line-height: 1;
}

.nav-icon.services-icon i {
    display: block;
    line-height: 1;
}



/* COMPACT HOMEPAGE — DESKTOP */
@media (min-width: 1001px) {
    .home-layout {
        margin-top: 10px;
        margin-bottom: 12px;
        gap: 14px;
    }

    .home-layout .hero {
        padding: 24px 30px;
    }

    .home-layout .hero h2 {
        font-size: clamp(42px, 3.7vw, 54px);
        margin-bottom: 12px;
    }

    .home-layout .hero h2::before {
        margin-bottom: 14px;
        font-size: 10px;
    }

    .home-layout .hero h2::after {
        margin-top: 12px;
    }

    .home-layout .hero > p {
        margin-bottom: 14px;
        font-size: 14px;
        line-height: 1.5;
    }

    .home-layout .online-service-status {
        margin-bottom: 14px;
        padding: 10px 14px;
    }

    .hero-actions {
        gap: 9px;
    }

    .hero-button {
        min-height: 62px;
        padding: 12px;
        font-size: 12px;
    }

    .home-layout .section-title {
        padding: 8px 12px 2px;
    }

    .home-layout .section-title h2 {
        font-size: 19px;
    }

    .home-layout .info-container,
    .home-layout .info-grid {
        gap: 10px;
    }

    .home-layout .info-card {
        min-height: 0;
        padding: 12px 16px 12px 20px;
        border-radius: 17px;
    }

    .home-layout .info-icon {
        width: 38px;
        height: 38px;
        margin-bottom: 7px;
        font-size: 19px;
    }

    .home-layout .info-card h3 {
        font-size: 14px;
        margin-bottom: 3px;
    }

    .home-layout .info-card p {
        font-size: 11px;
        line-height: 1.35;
    }

    .quick-access-section {
        margin-top: 0;
        margin-bottom: 12px;
    }

    .quick-access-header {
        margin-bottom: 9px;
    }

    .quick-access-header h2 {
        font-size: 21px;
    }

    .quick-access-grid {
        gap: 10px;
    }

    .quick-card {
        min-height: 82px;
        padding: 12px;
        gap: 8px;
    }

    .quick-icon {
        flex-basis: 36px;
        width: 36px;
        height: 36px;
        font-size: 18px;
    }

    .quick-content h3 {
        font-size: 11px;
    }

    .quick-content p {
        font-size: 9px;
    }

    footer {
        min-height: 48px;
        padding: 12px 20px;
    }
}



/* =========================================================
   RESPONSIVE LAYOUT IMPROVEMENTS
   Desktop, Laptop, Tablet and Mobile
   ========================================================= */

/* DESKTOP AND LAPTOP */
@media (min-width: 1001px) {

    .home-layout {
        width: min(1400px, calc(100% - 40px));
        margin: 8px auto 10px;
        gap: 12px;
        align-items: stretch;
    }

    .home-layout .hero {
        min-height: 0;
        padding: 20px 26px;
    }

    .home-layout .hero h2 {
        font-size: clamp(38px, 3.4vw, 50px);
        margin-bottom: 10px;
    }

    .home-layout .hero h2::before {
        margin-bottom: 10px;
        font-size: 10px;
        letter-spacing: 3px;
    }

    .home-layout .hero h2::after {
        margin-top: 10px;
    }

    .home-layout .hero > p {
        margin-bottom: 10px;
        font-size: 13px;
        line-height: 1.45;
    }

    .home-layout .online-service-status {
        margin-bottom: 10px;
        padding: 8px 12px;
    }

    .hero-actions {
        gap: 8px;
    }

    .hero-button {
        min-height: 56px;
        padding: 10px;
        font-size: 11px;
        gap: 6px;
    }

    .home-layout .info-container,
    .home-layout .info-grid {
        gap: 8px;
    }

    .home-layout .section-title {
        padding: 5px 10px 2px;
    }

    .home-layout .section-title h2 {
        font-size: 18px;
    }

    .home-layout .info-card {
        min-height: 0;
        padding: 10px 14px 10px 18px;
    }

    .home-layout .info-icon {
        width: 34px;
        height: 34px;
        margin-bottom: 5px;
        font-size: 18px;
    }

    .home-layout .info-card h3 {
        font-size: 13px;
        margin-bottom: 2px;
    }

    .home-layout .info-card p {
        font-size: 10px;
        line-height: 1.3;
    }

    .quick-access-section {
        width: min(1400px, calc(100% - 40px));
        margin: 0 auto 10px;
    }

    .quick-access-header {
        margin-bottom: 7px;
    }

    .quick-access-header h2 {
        font-size: 20px;
    }

    .quick-access-grid {
        gap: 8px;
    }

    .quick-card {
        min-height: 72px;
        padding: 10px;
        gap: 8px;
    }

    .quick-icon {
        flex-basis: 32px;
        width: 32px;
        height: 32px;
        font-size: 16px;
    }

    .quick-content h3 {
        font-size: 10px;
    }

    .quick-content p {
        font-size: 9px;
    }

    footer {
        min-height: 44px;
        padding: 10px 16px;
    }
}


/* TABLET */
@media (min-width: 701px) and (max-width: 1000px) {

    .main-nav {
        width: calc(100% - 24px);
        gap: 10px;
    }

    .brand {
        min-width: 0;
        gap: 8px;
    }

    .brand-logo {
        width: 48px;
        height: 48px;
        font-size: 25px;
    }

    .brand-text h1 {
        font-size: 17px;
    }

    .main-nav-links {
        gap: 2px;
    }

    .nav-item {
        height: 40px;
        padding: 0 8px;
        gap: 5px;
        font-size: 11px;
    }

    .nav-search {
        width: 125px;
    }

    .nav-right {
        gap: 8px;
    }

    .home-layout {
        width: calc(100% - 28px);
        grid-template-columns: minmax(0, 1fr);
        gap: 14px;
        margin: 14px auto;
    }

    .home-layout .hero {
        min-height: 0;
        padding: 32px 28px;
    }

    .home-layout .info-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }

    .home-layout .info-card {
        min-width: 0;
        min-height: 0;
        padding: 14px;
    }

    .hero-actions {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .hero-button {
        min-height: 68px;
        padding: 12px;
        font-size: 12px;
    }

    .quick-access-section {
        width: calc(100% - 28px);
        margin-bottom: 20px;
    }

    .quick-access-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .services-section {
        width: calc(100% - 28px);
    }
}


/* MOBILE PHONES */
@media (max-width: 700px) {

    .home-layout {
        width: calc(100% - 20px);
        margin: 12px auto 18px;
        grid-template-columns: minmax(0, 1fr);
        gap: 14px;
    }

    .home-layout .hero {
        min-height: 0;
        padding: 28px 18px;
    }

    .home-layout .hero h2 {
        font-size: clamp(30px, 8vw, 38px);
        letter-spacing: -1px;
        overflow-wrap: anywhere;
    }

    .home-layout .hero > p {
        font-size: 13px;
        overflow-wrap: anywhere;
    }

    .hero-actions {
        grid-template-columns: minmax(0, 1fr);
        width: 100%;
    }

    .hero-button {
        min-width: 0;
        min-height: 58px;
        padding: 12px;
        font-size: 12px;
    }

    .home-layout .info-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .home-layout .info-card {
        min-width: 0;
    }

    .quick-access-section {
        width: calc(100% - 20px);
        margin: 0 auto 22px;
    }

    .quick-access-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .quick-card {
        min-width: 0;
    }

    .quick-content {
        overflow-wrap: anywhere;
    }

    .services-section {
        width: calc(100% - 20px);
    }

    .services-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    footer {
        min-height: 48px;
        padding: 14px 12px;
        text-align: center;
        line-height: 1.5;
    }
}


/* VERY SHORT DESKTOP SCREENS */
@media (min-width: 1001px) and (max-height: 800px) {

    .home-layout .hero {
        padding-top: 14px;
        padding-bottom: 14px;
    }

    .home-layout .hero h2 {
        font-size: clamp(34px, 3vw, 44px);
    }

    .home-layout .hero > p {
        margin-bottom: 8px;
    }

    .home-layout .online-service-status {
        margin-bottom: 8px;
        padding: 7px 10px;
    }

    .home-layout .info-card {
        padding-top: 8px;
        padding-bottom: 8px;
    }

    .quick-access-section {
        margin-bottom: 6px;
    }
}


/* =========================================================
RESPONSIVE NAVBAR FIX
========================================================= */

/* Tablets and Small Laptops */
@media (min-width: 701px) and (max-width: 1100px) {
.main-nav {
width: calc(100% - 24px);
gap: 10px;
}

.brand {
    min-width: 0;
    gap: 8px;
}

.brand-logo {
    width: 48px;
    height: 48px;
    font-size: 26px;
    border-radius: 11px;
}

.brand-text h1 {
    font-size: 18px;
}

.brand-text span {
    display: none;
}

.main-nav-links {
    flex: 1 1 auto;
    min-width: 0;
    justify-content: flex-start;
    gap: 2px;
}

.nav-item {
    flex: 0 0 auto;
    height: 42px;
    padding: 0 8px;
    gap: 5px;
    font-size: 12px;
}

.nav-icon {
    font-size: 15px;
}

.nav-right {
    gap: 8px;
}

.nav-search {
    width: 145px;
    padding: 0 10px;
}

.admin-avatar {
    width: 36px;
    height: 36px;
    font-size: 17px;
}

}

/* Mobile navigation */
@media (max-width: 700px) {
.main-nav {
width: calc(100% - 20px);
height: auto;
min-height: 0;
padding: 10px 0;
display: flex;
flex-wrap: wrap;
gap: 10px;
}

.brand {
    width: 100%;
    min-width: 0;
    gap: 10px;
}

.main-nav-links {
    order: 2;
    width: 100%;
    min-width: 0;
    flex: 1 1 100%;
    display: flex;
    justify-content: flex-start;
    gap: 5px;
    overflow-x: auto;
    overflow-y: hidden;
    white-space: nowrap;
    padding: 2px 0 6px;
    scrollbar-width: thin;
    -webkit-overflow-scrolling: touch;
}

.main-nav-links .nav-item {
    flex: 0 0 auto;
    height: 40px;
    padding: 0 10px;
    gap: 6px;
    font-size: 12px;
}

.main-nav-links .nav-icon {
    flex: 0 0 auto;
    font-size: 15px;
}

.notification-dot {
    top: 3px;
    right: 3px;
}

.nav-right {
    display: none;
}

}


/* =====================================================
   FINAL RESPONSIVE NAVBAR FIX
   Keep desktop layout unchanged
   ===================================================== */

/* TABLET: 701px–1000px */
@media (min-width: 701px) and (max-width: 1000px) {
    .main-header {
        height: auto;
    }

    .main-nav {
        width: calc(100% - 24px);
        height: auto;
        min-height: 0;
        padding: 10px 0;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .brand {
        order: 1;
        flex: 1 1 auto;
        min-width: 0;
        gap: 8px;
    }

    .brand-logo {
        width: 44px;
        height: 44px;
        font-size: 23px;
    }

    .brand-text h1 {
        font-size: 17px;
    }

    .brand-text span {
        display: none;
    }

    .nav-right {
        order: 2;
        flex: 0 0 auto;
        gap: 8px;
    }

    .nav-search {
        display: none;
    }

    .admin-avatar {
        width: 34px;
        height: 34px;
    }

    .main-nav-links {
        order: 3;
        flex: 0 0 100%;
        width: 100%;
        min-width: 0;
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-start;
        gap: 5px;
        overflow: visible;
    }

    .main-nav-links .nav-item {
        flex: 0 0 auto;
        height: 38px;
        padding: 0 9px;
        gap: 5px;
        font-size: 11px;
    }
}

/* MOBILE: 700px pababa */
@media (max-width: 700px) {
    .main-header {
        height: auto;
        min-height: 0;
    }

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

    .brand {
        order: 1;
        flex: 0 0 100%;
        width: 100%;
        min-width: 0;
        gap: 9px;
    }

    .brand-logo {
        width: 46px;
        height: 46px;
        font-size: 24px;
    }

    .brand-text h1 {
        font-size: 17px;
    }

    .brand-text span {
        display: block;
        font-size: 10px;
    }

    .nav-right {
        display: none;
    }

    .main-nav-links {
        order: 2;
        flex: 0 0 100%;
        width: 100%;
        min-width: 0;
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 5px;
        justify-content: initial;
        overflow: visible;
        padding: 0 0 3px;
    }

    .main-nav-links .nav-item {
        min-width: 0;
        width: 100%;
        height: 38px;
        padding: 0 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        font-size: 10px;
        white-space: nowrap;
        border-radius: 9px;
    }

    .main-nav-links .nav-icon {
        flex: 0 0 auto;
        width: 15px;
        font-size: 13px;
    }

    .main-nav-links .nav-icon.services-icon {
        width: 15px;
        height: 15px;
        flex-basis: 15px;
    }

    .main-nav-links .nav-icon.services-icon svg {
        width: 14px;
        height: 14px;
    }

    .notification-dot {
        top: 2px;
        right: 2px;
    }
}


html {
    min-height: 100%;
}

body {
    margin: 0;
    min-height: 100vh;

    display: flex;
    flex-direction: column;

    font-family: Inter, "Segoe UI", Arial, sans-serif;

    /* Keep the existing background and color settings */
}

/* Add this at the end of the CSS */
footer {
    margin-top: auto;
    width: 100%;
    flex-shrink: 0;
}


/* =========================================
   STANDARD FONT SIZES — ALL SCREEN SIZES
   Add this at the end of the existing CSS
   ========================================= */

/* Quick Access section */
.quick-access-label {
    font-size: 12px;
}

.quick-access-header h2 {
    font-size: 23px;
}

.quick-access-header p {
    font-size: 14px;
    line-height: 1.5;
}

.view-services-link {
    font-size: 14px;
}

/* Quick Access cards */
.quick-content h3 {
    font-size: 13px;
    line-height: 1.4;
}

.quick-content p {
    font-size: 12px;
    line-height: 1.5;
}

/* Hero Description and Status */
.home-layout .hero > p {
    font-size: 15px;
    line-height: 1.6;
}

.home-layout .online-service-status strong {
    font-size: 15px;
}

.home-layout .online-service-status span {
    font-size: 13px;
}

/* Barangay information */
.home-layout .section-title h2 {
    font-size: 21px;
}

.home-layout .section-title p {
    font-size: 14px;
}

.home-layout .info-card h3 {
    font-size: 15px;
}

.home-layout .info-card p {
    font-size: 13px;
    line-height: 1.5;
}

/* Service cards */
.service-card h3 {
    font-size: 18px;
}

.service-card p {
    font-size: 14px;
    line-height: 1.6;
}

.request-online {
    font-size: 13px;
}

/* Footer */
footer {
    font-size: 13px;
    line-height: 1.5;
}

/* Mobile: Maintain Readability on Small Screens */
@media (max-width: 700px) {
    .quick-access-header h2 {
        font-size: 22px;
    }

    .quick-access-header p {
        font-size: 13px;
    }

    .quick-content h3 {
        font-size: 14px;
    }

    .quick-content p {
        font-size: 12px;
    }

    .home-layout .hero > p {
        font-size: 14px;
    }

    .home-layout .info-card h3 {
        font-size: 15px;
    }

    .home-layout .info-card p {
        font-size: 13px;
    }

    footer {
        font-size: 12px;
    }
}


footer {
    margin-top: 12px;
    position: relative;
    flex-shrink: 0;
}


/* =========================================
   MOBILE NAVBAR — SHOW ADMIN
   ========================================= */
@media (max-width: 700px) {

    .main-nav {
        position: relative;
        width: calc(100% - 20px);
        gap: 8px;
        align-items: center;
    }

    .brand {
        width: auto;
        min-width: 0;
        flex: 1 1 auto;
        gap: 8px;
        padding-right: 5px;
    }

    .brand-logo {
        width: 44px;
        height: 44px;
        font-size: 23px;
        border-radius: 10px;
    }

    .brand-text h1 {
        font-size: 16px;
    }

    .brand-text span {
        display: block;
        font-size: 10px;
    }

    /* Show the Admin Menu */
    .nav-right {
        display: flex !important;
        order: 2;
        flex: 0 0 100%;
        width: 100%;
        min-width: 0;
        gap: 8px;
        margin-left: 0;
        align-items: center;
    }

    /* Show Search on Mobile */
    .nav-search {
        display: flex !important;
        flex: 1 1 auto;
        width: auto;
        min-width: 0;
        height: 38px;
        padding: 0 12px;
    }

    .nav-search input {
        display: block !important;
        flex: 1 1 auto;
        width: 100%;
        min-width: 0;
        color: #fff;
        font-size: 12px;
    }

    .admin-menu {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    .admin-avatar {
        width: 34px;
        height: 34px;
        font-size: 17px;
    }

    .admin-name {
        display: block !important;
    }

    .admin-name strong {
        font-size: 11px;
    }

    .admin-arrow {
        width: 12px;
        height: 12px;
        font-size: 10px;
    }

    .main-nav-links {
        order: 3;
        flex: 0 0 100%;
        width: 100%;
        min-width: 0;
        overflow-x: auto;
        overflow-y: hidden;
        justify-content: flex-start;
        gap: 4px;
    }

    .main-nav-links .nav-item {
        flex: 0 0 auto;
        height: 38px;
        padding: 0 9px;
        gap: 5px;
        font-size: 11px;
    }
}

/* =========================================
   TABLET — ALIGN ADMIN PROPERLY
   ========================================= */
@media (min-width: 701px) and (max-width: 1000px) {

    .main-nav {
        width: calc(100% - 20px);
        gap: 7px;
    }

    .brand {
        min-width: 0;
        flex: 0 0 auto;
        gap: 6px;
    }

    .brand-logo {
        width: 42px;
        height: 42px;
        font-size: 22px;
    }

    .brand-text h1 {
        font-size: 15px;
    }

    .brand-text span {
        display: none;
    }

    .main-nav-links {
        flex: 1 1 auto;
        min-width: 0;
        justify-content: center;
        gap: 1px;
    }

    .main-nav-links .nav-item {
        height: 36px;
        padding: 0 5px;
        gap: 4px;
        font-size: 10px;
    }

    .main-nav-links .nav-icon {
        width: 15px;
        font-size: 13px;
    }

    .nav-right {
        flex: 0 0 auto;
        gap: 5px;
    }

    .nav-search {
        width: 105px;
        height: 35px;
        padding: 0 7px;
    }

    .nav-search span {
        font-size: 16px;
    }

    .nav-search input {
        font-size: 10px;
    }

    .admin-menu {
        gap: 4px;
    }

    .admin-avatar {
        width: 30px;
        height: 30px;
        font-size: 15px;
    }

    .admin-name {
        display: block !important;
    }

    .admin-name strong {
        font-size: 10px;
    }

    .admin-arrow {
        width: 11px;
        font-size: 9px;
    }
}


/* TABLET NAVBAR — ADMIN ON THE RIGHT */
@media (min-width: 701px) and (max-width: 1000px) {

    .main-header {
        height: auto;
        min-height: 88px;
    }

    .main-nav {
        width: calc(100% - 24px);
        height: auto;
        min-height: 88px;
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        grid-template-rows: auto auto;
        align-items: center;
        column-gap: 10px;
        row-gap: 5px;
        padding: 8px 0;
    }

    /* Logo and Name on the Left */
    .brand {
        grid-column: 1;
        grid-row: 1;
        min-width: 0;
        gap: 6px;
    }

    .brand-logo {
        width: 38px;
        height: 38px;
        font-size: 20px;
        border-radius: 9px;
    }

    .brand-text h1 {
        font-size: 14px;
    }

    .brand-text span {
        display: none;
    }

    /* Admin Menu on the Far Right */
    .nav-right {
        grid-column: 3;
        grid-row: 1;
        display: flex !important;
        align-items: center;
        justify-self: end;
        gap: 0;
        margin-left: 0;
    }

    .nav-search {
        display: none;
    }

    .admin-menu {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 5px;
    }

    .admin-avatar {
        width: 32px;
        height: 32px;
        font-size: 16px;
    }

    .admin-name {
        display: block !important;
    }

    .admin-name strong {
        font-size: 11px;
    }

    .admin-arrow {
        width: 12px;
        height: 12px;
        font-size: 10px;
    }

    /* Navigation on the Second Row */
    .main-nav-links {
        grid-column: 1 / -1;
        grid-row: 2;
        width: 100%;
        min-width: 0;
        display: flex;
        justify-content: center;
        gap: 3px;
        overflow-x: auto;
        overflow-y: hidden;
        white-space: nowrap;
        padding: 2px 0;
    }

    .main-nav-links .nav-item {
        flex: 0 0 auto;
        height: 34px;
        padding: 0 8px;
        gap: 4px;
        font-size: 10px;
    }

    .main-nav-links .nav-icon {
        width: 14px;
        font-size: 12px;
    }
}


/* TABLET: ACTIVE SERVICE SEARCH */
@media (min-width: 701px) and (max-width: 1000px) {
    .nav-right {
        display: flex !important;
        align-items: center;
        gap: 8px;
    }

    .nav-search {
        display: flex !important;
        width: 150px;
        min-width: 120px;
        height: 36px;
        padding: 0 10px;
        flex: 0 1 150px;
        border-radius: 22px;
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.25);
    }

    .nav-search input {
        display: block !important;
        width: 100%;
        min-width: 0;
        color: #fff;
        font-size: 11px;
        outline: none;
    }

    .nav-search input::placeholder {
        color: rgba(255, 255, 255, 0.8);
    }

    .nav-search:focus-within {
        background: rgba(255, 255, 255, 0.24);
        box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
    }

    .admin-name {
        display: none !important;
    }

    .admin-avatar {
        width: 32px;
        height: 32px;
    }
}


/* SERVICE AUTOCOMPLETE */
.nav-search-wrap {
    position: relative;
    width: 282px;
    flex: 0 1 282px;
    min-width: 0;
    z-index: 1100;
}

.nav-search-wrap .nav-search {
    width: 100%;
    min-width: 0;
}

.clear-service-search {
    border: 0;
    background: transparent;
    color: white;
    font-size: 22px;
    cursor: pointer;
    padding: 0 3px;
}

.service-search-results {
    position: absolute;
    top: calc(100% + 10px);
    left: 0;
    right: 0;
    max-height: 340px;
    overflow-y: auto;
    background: #fff;
    color: #14264d;
    border: 1px solid #dbe5f3;
    border-radius: 14px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, .18);
    padding: 7px;
}

.service-search-results[hidden] {
    display: none;
}

.service-search-result {
    display: block;
    width: 100%;
    padding: 12px;
    border: 0;
    border-radius: 9px;
    background: #fff;
    color: #14264d;
    text-align: left;
    text-decoration: none;
    cursor: pointer;
}

.service-search-result:hover,
.service-search-result:focus,
.service-search-result.selected {
    background: #edf4ff;
    outline: none;
}

.service-search-result strong {
    display: block;
    font-size: 14px;
    margin-bottom: 4px;
}

.service-search-result small {
    display: block;
    color: #64748b;
    font-size: 12px;
    line-height: 1.5;
}

.service-search-empty {
    padding: 14px 12px;
    color: #64748b;
    font-size: 13px;
}

@media (max-width: 1100px) {
    .nav-search-wrap {
        width: 150px;
        flex-basis: 150px;
    }
}

@media (max-width: 700px) {
    .nav-search-wrap {
        order: 2;
        width: auto;
        flex: 1 1 auto;
        min-width: 0;
    }

    .nav-search-wrap .nav-search {
        height: 36px;
        padding: 0 9px;
    }

    .service-search-results {
        max-height: 280px;
    }
}



/* =========================================
   QUICK ACCESS — REFERENCE DESIGN
========================================= */

.quick-access-section {
    width: min(1400px, calc(100% - 60px));
    margin: 18px auto 28px;
}

.quick-access-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 18px;
}

.quick-access-title-wrap {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
}

.quick-access-main-icon {
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1673ed;
    font-size: 30px;
}

.quick-access-title-text h2 {
    margin: 0;
    color: #14264d;
    font-size: 23px;
    font-weight: 900;
    line-height: 1.2;
}

.quick-access-title-text p {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.5;
}

.view-services-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    color: #1261db;
    text-decoration: none;
    font-size: 13px;
    font-weight: 800;
}

.view-services-link:hover {
    color: #0f3d82;
    text-decoration: underline;
}

/* Service card grid */
.quick-access-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 14px;
}

.quick-card {
    min-width: 0;
    min-height: 106px;
    padding: 16px 14px;
    display: flex;
    align-items: flex-start;
    gap: 11px;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid #e1e9f4;
    border-radius: 18px;
    color: inherit;
    text-decoration: none;
    box-shadow: 0 8px 22px rgba(15, 42, 86, 0.055);
    transition: transform 0.2s ease,
                box-shadow 0.2s ease,
                border-color 0.2s ease;
}

.quick-card:hover {
    transform: translateY(-4px);
    border-color: #bfdbfe;
    box-shadow: 0 15px 30px rgba(15, 42, 86, 0.10);
}

/* Colored service icons */
.quick-icon {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    font-size: 21px;
}

.quick-icon.blue {
    background: #e0edff;
}

.quick-icon.green {
    background: #dcf8eb;
}

.quick-icon.orange {
    background: #ffeadc;
}

.quick-icon.purple {
    background: #eee5ff;
}

.quick-icon.red {
    background: #ffe1e6;
}

.quick-icon.slate {
    background: #e6edf5;
}

/* Card text */
.quick-content {
    min-width: 0;
    padding-top: 2px;
}

.quick-content h3 {
    margin: 1px 0 6px;
    color: #10244a;
    font-size: 12px;
    font-weight: 900;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.quick-content p {
    margin: 0;
    color: #64748b;
    font-size: 11px;
    line-height: 1.5;
}

.quick-arrow {
    display: none;
}

/* Tablet */
@media (max-width: 1100px) {
    .quick-access-section {
        width: calc(100% - 35px);
    }

    .quick-access-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
    }

    .quick-card {
        min-height: 100px;
    }
}

/* Mobile */
@media (max-width: 700px) {
    .quick-access-section {
        width: calc(100% - 24px);
        margin: 18px auto 24px;
    }

    .quick-access-header {
        align-items: flex-start;
        flex-direction: column;
        gap: 12px;
    }

    .quick-access-title-wrap {
        gap: 10px;
    }

    .quick-access-main-icon {
        width: 34px;
        height: 34px;
        flex-basis: 34px;
        font-size: 26px;
    }

    .quick-access-title-text h2 {
        font-size: 21px;
    }

    .quick-access-title-text p {
        font-size: 12px;
    }

    .quick-access-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .quick-card {
        min-height: 0;
        padding: 13px 11px;
        flex-direction: column;
        gap: 10px;
    }

    .quick-icon {
        width: 40px;
        height: 40px;
        flex-basis: 40px;
    }

    .quick-content h3 {
        font-size: 12px;
    }

    .quick-content p {
        font-size: 11px;
    }
}

/* Very small screens */
@media (max-width: 420px) {
    .quick-access-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .quick-card {
        flex-direction: row;
        align-items: flex-start;
        padding: 14px;
    }
}



/* Hero action buttons — reference design */
.hero-actions {
    display: grid;
    grid-template-columns: 1.2fr 1fr 1.1fr;
    gap: 14px;
    width: 100%;
    max-width: 900px;
    position: relative;
    z-index: 5;
}

.hero-button {
    min-width: 0;
    min-height: 88px;
    padding: 16px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border: 1px solid #e1e9f4;
    border-radius: 18px;
    background: rgba(255, 255, 255, 0.94);
    color: #102a56;
    text-decoration: none;
    font-size: 14px;
    font-weight: 800;
    line-height: 1.4;
    box-shadow: 0 8px 22px rgba(15, 42, 86, 0.07);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.hero-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 14px 28px rgba(15, 42, 86, 0.12);
}

.hero-button:first-child {
    background: linear-gradient(135deg, #0865f5, #287ff5);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 12px 25px rgba(37, 99, 235, 0.23);
}

.hero-button-content {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.hero-button-icon {
    width: 48px;
    height: 48px;
    flex: 0 0 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 15px;
    background: #e1edff;
    color: #1768e8;
    font-size: 21px;
}

.hero-button:first-child .hero-button-icon {
    background: rgba(255, 255, 255, 0.18);
    color: #fff;
}

.hero-button-arrow {
    flex: 0 0 auto;
    font-size: 23px;
    font-weight: 400;
    color: #1556c0;
}

.hero-button:first-child .hero-button-arrow {
    color: #fff;
}

@media (max-width: 700px) {
    .hero-actions {
        grid-template-columns: minmax(0, 1fr);
        gap: 11px;
    }

    .hero-button {
        min-height: 68px;
        padding: 12px 14px;
        font-size: 13px;
    }

    .hero-button-icon {
        width: 42px;
        height: 42px;
        flex-basis: 42px;
    }
}


/* Quick Access icon matching the reference */
.quick-access-main-icon {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1673ed;
}

.quick-grid-icon {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    grid-template-rows: repeat(2, 1fr);
    gap: 4px;
    width: 34px;
    height: 34px;
}

.quick-grid-icon span {
    display: block;
    border-radius: 5px;
    background: #287cf2;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.2);
}

@media (max-width: 700px) {
    .quick-access-main-icon {
        width: 36px;
        height: 36px;
        flex-basis: 36px;
    }

    .quick-grid-icon {
        width: 29px;
        height: 29px;
        gap: 3px;
    }
}


/* Barangay Certificate document icon */
.quick-icon.blue {
    background: #e0edff;
    color: #1768e8;
}

.quick-icon.blue i {
    font-size: 21px;
    line-height: 1;
}


/* Certificate of Indigency icon */
.quick-icon.green {
    background: #dcf8eb;
    color: #138b53;
}

.quick-icon.green i {
    font-size: 20px;
}

/* Certificate of Residency icon */
.quick-icon.purple {
    background: #eee5ff;
    color: #7138e8;
}

.quick-icon.purple i {
    font-size: 20px;
}


/* Barangay Information - Reference Design */
.home-layout .barangay-info {
    padding: 0;
    background: transparent;
    border: 0;
    box-shadow: none;
}

.home-layout .info-container {
    display: flex;
    flex-direction: column;
    gap: 14px;
    height: 100%;
}

.home-layout .section-title {
    padding: 8px 4px 4px;
}

.home-layout .section-title h2 {
    font-size: 21px;
    font-weight: 900;
    color: #14264d;
}

.home-layout .section-title p {
    margin-top: 6px;
    font-size: 13px;
    color: #64748b;
}

.home-layout .info-grid {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.home-layout .info-card {
    position: relative;
    min-height: 0;
    padding: 18px 20px;
    border-radius: 19px;
    border: 1px solid #e3ebf7;
    overflow: hidden;
    box-shadow: 0 8px 22px rgba(15, 42, 86, .07);
    transition: transform .2s ease, box-shadow .2s ease;
}

.home-layout .info-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 13px 28px rgba(15, 42, 86, .12);
}

.home-layout .info-card::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #2563eb;
}

.home-layout .info-card:first-child {
    background: linear-gradient(135deg, #eaf3ff, #ffffff);
}

.home-layout .info-card:nth-child(2) {
    background: linear-gradient(135deg, #fff5f6, #ffffff);
}

.home-layout .info-card:nth-child(2)::before {
    background: #ef4444;
}

.home-layout .info-card:nth-child(3) {
    background: linear-gradient(135deg, #edfcf4, #ffffff);
}

.home-layout .info-card:nth-child(3)::before {
    background: #16a34a;
}

.home-layout .info-icon {
    width: 42px;
    height: 42px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 15px;
    background: #e0edff;
    color: #1768e8;
    font-size: 21px;
}

.home-layout .info-card:nth-child(2) .info-icon {
    background: #ffe5eb;
    color: #e11d48;
}

.home-layout .info-card:nth-child(3) .info-icon {
    background: #dcf8eb;
    color: #138b53;
}

.home-layout .info-card h3 {
    margin: 0 0 5px;
    font-size: 15px;
    font-weight: 900;
    color: #14264d;
}

.home-layout .info-card p {
    margin: 3px 0;
    font-size: 12px;
    line-height: 1.5;
    color: #64748b;
}

/* Keep the information cards compact on desktop */
@media (min-width: 1001px) {
    .home-layout .section-title {
        padding: 8px 8px 2px;
    }

    .home-layout .info-container,
    .home-layout .info-grid {
        gap: 12px;
    }

    .home-layout .info-card {
        padding: 14px 18px;
    }

    .home-layout .info-icon {
        width: 38px;
        height: 38px;
        margin-bottom: 8px;
        font-size: 19px;
    }
}


/* BARANGAY INFORMATION HEADER */
.home-layout .info-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 10px 4px;
}

.info-heading {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
}

.home-layout .info-section-header h2 {
    margin: 0;
    color: #14264d;
    font-size: 20px;
    font-weight: 900;
}

.home-layout .info-section-header p {
    margin: 5px 0 0;
    color: #64748b;
    font-size: 12px;
}

.info-heading-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    font-size: 21px;
    flex-shrink: 0;
}

.info-heading-arrow {
    color: #64748b;
    font-size: 15px;
    flex-shrink: 0;
}

/* INFORMATION CARDS */
.home-layout .info-grid {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.home-layout .info-card {
    min-width: 0;
    min-height: 0;
    padding: 14px 16px 14px 19px;
    border: 1px solid #e1e9f4;
    border-radius: 18px;
    box-shadow: 0 8px 22px rgba(15, 42, 86, 0.06);
}

.home-layout .info-card::before {
    width: 4px;
}

.info-card-heading {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    min-width: 0;
}

.home-layout .info-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 40px;
    width: 40px;
    height: 40px;
    margin: 0;
    border-radius: 14px;
    font-size: 18px;
}

.home-layout .info-card h3 {
    margin: 3px 0 5px;
    color: #14264d;
    font-size: 14px;
    font-weight: 900;
}

.home-layout .info-card p {
    margin: 3px 0;
    color: #64748b;
    font-size: 12px;
    line-height: 1.45;
    overflow-wrap: anywhere;
}

.home-layout .info-card .info-description {
    margin-top: 7px;
}

.hall-card {
    background: linear-gradient(135deg, #eaf3ff, #ffffff);
}

.contact-card {
    background: linear-gradient(135deg, #fff7f8, #ffffff);
}

.hours-card {
    background: linear-gradient(135deg, #effcf5, #ffffff);
}

.hall-icon {
    background: #dbeafe;
    color: #2563eb;
}

.contact-icon {
    background: #ffe4e9;
    color: #e11d48;
}

.hours-icon {
    background: #d9fbe8;
    color: #15803d;
}

/* TABLET AND MOBILE */
@media (max-width: 1000px) {
    .home-layout .info-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }

    .info-card-heading {
        flex-direction: column;
        gap: 8px;
    }
}

@media (max-width: 700px) {
    .home-layout .info-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .info-card-heading {
        flex-direction: row;
        align-items: flex-start;
    }

    .home-layout .info-section-header h2 {
        font-size: 18px;
    }
}

/* =========================================
   PROFESSIONAL BARANGAY SERVICES UI
   Services page only — preserves existing links
========================================= */

.services-section {
    width: min(1400px, calc(100% - 64px));
    margin: 30px auto 42px;
}

.services-section .section-title {
    margin-bottom: 24px;
}

.services-section .section-title h2 {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0 0 10px;
    color: #102a56;
    font-size: clamp(25px, 2.2vw, 32px);
    font-weight: 850;
    letter-spacing: -0.6px;
}

.services-section .section-title p {
    max-width: 760px;
    margin: 0;
    color: #64748b;
    font-size: 15px;
    line-height: 1.75;
}

.services-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 22px;
    align-items: stretch;
}

.services-grid .service-card {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    min-width: 0;
    min-height: 245px;
    padding: 27px 26px 24px;
    overflow: hidden;

    background: linear-gradient(145deg, #ffffff 0%, #fbfdff 100%);
    border: 1px solid #e0e9f6;
    border-radius: 22px;
    box-shadow: 0 8px 24px rgba(15, 42, 86, 0.055);

    color: #14264d;
    text-decoration: none;

    transition:
        transform 0.22s ease,
        box-shadow 0.22s ease,
        border-color 0.22s ease;
}

.services-grid .service-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #1473ed, #60a5fa);
    opacity: 0;
    transition: opacity 0.22s ease;
}

.services-grid .service-card:hover {
    transform: translateY(-5px);
    border-color: #bfdbfe;
    box-shadow: 0 18px 38px rgba(15, 42, 86, 0.12);
}

.services-grid .service-card:hover::before {
    opacity: 1;
}

.services-grid .service-card:focus-visible {
    outline: 3px solid #60a5fa;
    outline-offset: 4px;
}

.services-grid .service-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 58px;
    width: 58px;
    height: 58px;
    margin: 0 0 20px;

    background: linear-gradient(145deg, #edf5ff, #dceaff);
    border: 1px solid #e0ecff;
    border-radius: 17px;

    font-size: 27px;
    line-height: 1;
    box-shadow: 0 5px 12px rgba(37, 99, 235, 0.06);
}

.services-grid .service-card:nth-child(2) .service-icon {
    background: #f0eaff;
    border-color: #e5dcff;
}

.services-grid .service-card:nth-child(3) .service-icon {
    background: #fff4e5;
    border-color: #ffebcc;
}

.services-grid .service-card:nth-child(4) .service-icon {
    background: #fff0f2;
    border-color: #ffe0e5;
}

.services-grid .service-card:nth-child(5) .service-icon {
    background: #e8f5ff;
    border-color: #d8edff;
}

.services-grid .service-card:nth-child(6) .service-icon {
    background: #e8fbf1;
    border-color: #d1f5e2;
}

.services-grid .service-card h3 {
    margin: 0 0 10px;
    color: #102a56;
    font-size: 18px;
    font-weight: 850;
    line-height: 1.4;
    letter-spacing: -0.25px;
}

.services-grid .service-card p {
    flex: 1;
    margin: 0 0 22px;
    color: #64748b;
    font-size: 14px;
    line-height: 1.8;
}

.services-grid .service-card .request-online {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: auto;
    color: #1768e8;
    font-size: 13px;
    font-weight: 850;
    line-height: 1.5;
    transition: gap 0.2s ease;
}

.services-grid .service-card:hover .request-online {
    gap: 13px;
}

/* Tablet */
@media (max-width: 1050px) {
    .services-section {
        width: calc(100% - 36px);
    }

    .services-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .services-grid .service-card {
        min-height: 235px;
        padding: 23px;
    }
}

/* Mobile */
@media (max-width: 600px) {
    .services-section {
        width: calc(100% - 24px);
        margin: 22px auto 30px;
    }

    .services-section .section-title {
        margin-bottom: 18px;
    }

    .services-section .section-title h2 {
        font-size: 24px;
    }

    .services-section .section-title p {
        font-size: 13px;
        line-height: 1.7;
    }

    .services-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 14px;
    }

    .services-grid .service-card {
        min-height: 0;
        padding: 22px;
        border-radius: 18px;
    }

    .services-grid .service-icon {
        width: 52px;
        height: 52px;
        flex-basis: 52px;
        margin-bottom: 16px;
        border-radius: 15px;
        font-size: 24px;
    }

    .services-grid .service-card h3 {
        font-size: 17px;
    }

    .services-grid .service-card p {
        margin-bottom: 18px;
        font-size: 13px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .services-grid .service-card,
    .services-grid .service-card::before,
    .services-grid .service-card .request-online {
        transition: none;
    }

    .services-grid .service-card:hover {
        transform: none;
    }
}


/* =====================================================
   BONGDO - PROFESSIONAL BARANGAY SERVICES UI
   Applies to Services section only
===================================================== */

.services-section {
    position: relative;
    isolation: isolate;
    width: min(1500px, calc(100% - 64px));
    margin: 0 auto;
    padding: 38px 0 54px;
    color: #142b55;
}

/* Soft blue background accents */
.services-section::before {
    content: "";
    position: absolute;
    z-index: -1;
    top: -20px;
    right: -25px;
    width: 340px;
    height: 280px;
    border-radius: 50%;
    background: radial-gradient(
        circle,
        rgba(59, 130, 246, 0.12),
        rgba(96, 165, 250, 0.04) 60%,
        transparent 72%
    );
    pointer-events: none;
}

/* HERO / HEADING */
.services-section .section-title {
    position: relative;
    max-width: 900px;
    margin: 0 0 32px;
    padding: 0 26px;
}

.services-section .services-eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 14px;
    color: #52709d;
    font-size: 12px;
    font-weight: 850;
    letter-spacing: 1.8px;
    text-transform: uppercase;
}

.services-section .services-eyebrow::after {
    content: "";
    display: inline-block;
    width: 30px;
    height: 3px;
    border-radius: 99px;
    background: #1673f9;
}

.services-section .section-title h2 {
    margin: 0 0 15px;
    color: #102b57;
    font-size: clamp(34px, 4vw, 54px);
    font-weight: 900;
    line-height: 1.12;
    letter-spacing: -1.8px;
}

.services-section .title-accent {
    color: #0868ed;
}

.services-section .section-title p {
    max-width: 800px;
    margin: 0;
    color: #61799d;
    font-size: 15px;
    line-height: 1.85;
}

/* SERVICES GRID */
.services-section .services-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    align-items: stretch;
}

/* SERVICE CARDS */
.services-section .service-card {
    --card-accent: #0868ed;
    --card-soft: #e8f2ff;
    --card-border: #dceaff;

    position: relative;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    min-width: 0;
    min-height: 265px;
    padding: 25px 27px 24px;
    overflow: hidden;

    color: #142b55;
    text-decoration: none;
    background: linear-gradient(145deg, #ffffff 0%, #f8fbff 100%);
    border: 1px solid var(--card-border);
    border-radius: 23px;
    box-shadow: 0 10px 28px rgba(25, 64, 120, 0.065);

    transition:
        transform 0.25s ease,
        box-shadow 0.25s ease,
        border-color 0.25s ease;
}

.services-section .service-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: var(--card-accent);
    opacity: 0;
    transition: opacity 0.25s ease;
}

.services-section .service-card::after {
    content: "";
    position: absolute;
    right: -30px;
    bottom: 12px;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: var(--card-soft);
    opacity: 0.52;
    pointer-events: none;
    transition: transform 0.3s ease;
}

.services-section .service-card:hover {
    transform: translateY(-5px);
    border-color: var(--card-accent);
    box-shadow: 0 18px 38px rgba(25, 64, 120, 0.13);
}

.services-section .service-card:hover::before {
    opacity: 1;
}

.services-section .service-card:hover::after {
    transform: scale(1.12);
}

.services-section .service-card:focus-visible {
    outline: 3px solid #60a5fa;
    outline-offset: 4px;
}

/* Different color themes for each card */
.services-section .service-card:nth-child(1) {
    --card-accent: #0868ed;
    --card-soft: #dcecff;
    --card-border: #dceaff;
}

.services-section .service-card:nth-child(2) {
    --card-accent: #7038f5;
    --card-soft: #eee4ff;
    --card-border: #e8ddff;
    background: linear-gradient(145deg, #fff 0%, #fcf9ff 100%);
}

.services-section .service-card:nth-child(3) {
    --card-accent: #ff8908;
    --card-soft: #ffefd8;
    --card-border: #ffead1;
    background: linear-gradient(145deg, #fff 0%, #fffaf3 100%);
}

.services-section .service-card:nth-child(4) {
    --card-accent: #ed2948;
    --card-soft: #ffe0e7;
    --card-border: #f9e0e5;
    background: linear-gradient(145deg, #fff 0%, #fff7f8 100%);
}

.services-section .service-card:nth-child(5) {
    --card-accent: #0868ed;
    --card-soft: #dcecff;
    --card-border: #dceaff;
}

.services-section .service-card:nth-child(6) {
    --card-accent: #07965b;
    --card-soft: #d8f8e9;
    --card-border: #d8f3e5;
    background: linear-gradient(145deg, #fff 0%, #f4fff9 100%);
}

/* ICON + CATEGORY ROW */
.services-section .service-topline {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 13px;
}

.services-section .service-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 60px;
    width: 60px;
    height: 60px;

    color: var(--card-accent);
    background: var(--card-soft);
    border: 1px solid rgba(255, 255, 255, 0.8);
    border-radius: 18px;

    font-size: 29px;
    line-height: 1;
    box-shadow: 0 5px 15px rgba(35, 72, 125, 0.05);
}

.services-section .service-category {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 30px;
    padding: 7px 14px;

    color: var(--card-accent);
    background: var(--card-soft);
    border: 1px solid rgba(255, 255, 255, 0.75);
    border-radius: 999px;

    font-size: 10px;
    font-weight: 900;
    line-height: 1.35;
    letter-spacing: 0.35px;
    text-transform: uppercase;
}

/* CARD TEXT */
.services-section .service-card h3 {
    position: relative;
    z-index: 1;
    margin: 0 0 10px;
    color: #102b57;
    font-size: 19px;
    font-weight: 900;
    line-height: 1.4;
    letter-spacing: -0.35px;
}

.services-section .service-card p {
    position: relative;
    z-index: 1;
    flex: 1;
    margin: 0 0 21px;
    color: #61799d;
    font-size: 14px;
    line-height: 1.75;
}

/* ACTION BUTTON */
.services-section .request-online {
    position: relative;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;

    width: fit-content;
    min-width: 190px;
    min-height: 44px;
    margin-top: auto;
    padding: 11px 17px 11px 20px;

    color: #ffffff;
    background: var(--card-accent);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 999px;
    box-shadow: 0 7px 15px rgba(31, 91, 180, 0.13);

    font-size: 13px;
    font-weight: 800;
    line-height: 1.4;

    transition:
        filter 0.2s ease,
        box-shadow 0.2s ease;
}

.services-section .request-online::after {
    content: "→";
    font-size: 20px;
    font-weight: 500;
    line-height: 1;
}

.services-section .service-card:hover .request-online {
    filter: brightness(1.08);
    box-shadow: 0 9px 20px rgba(31, 91, 180, 0.2);
}

/* TABLET */
@media (max-width: 1050px) {
    .services-section {
        width: calc(100% - 40px);
        padding-top: 30px;
    }

    .services-section .services-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 17px;
    }

    .services-section .service-card {
        min-height: 250px;
        padding: 23px;
    }

    .services-section .section-title {
        padding: 0 10px;
    }
}

/* MOBILE */
@media (max-width: 600px) {
    .services-section {
        width: calc(100% - 24px);
        padding: 24px 0 35px;
    }

    .services-section .section-title {
        margin-bottom: 23px;
        padding: 0 4px;
    }

    .services-section .services-eyebrow {
        font-size: 10px;
        letter-spacing: 1.2px;
    }

    .services-section .section-title h2 {
        font-size: 34px;
        letter-spacing: -1px;
    }

    .services-section .section-title p {
        font-size: 13px;
        line-height: 1.75;
    }

    .services-section .services-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 15px;
    }

    .services-section .service-card {
        min-height: 245px;
        padding: 22px;
        border-radius: 19px;
    }

    .services-section .service-icon {
        width: 53px;
        height: 53px;
        flex-basis: 53px;
        border-radius: 15px;
        font-size: 25px;
    }

    .services-section .service-category {
        font-size: 9px;
        padding: 6px 10px;
    }

    .services-section .service-card h3 {
        font-size: 18px;
    }

    .services-section .service-card p {
        font-size: 13px;
    }

    .services-section .request-online {
        min-width: 175px;
    }
}

/* REDUCED MOTION ACCESSIBILITY */
@media (prefers-reduced-motion: reduce) {
    .services-section .service-card,
    .services-section .service-card::before,
    .services-section .service-card::after,
    .services-section .request-online {
        transition: none;
    }

    .services-section .service-card:hover {
        transform: none;
    }
}


/* ==========================================
   BONGDO SERVICES — FINAL DESIGN FIX
   Place at the END of the existing stylesheet
========================================== */

/* Fix heading clipping and spacing */
.services-section {
    width: min(1500px, calc(100% - 64px));
    margin: 0 auto;
    padding: 34px 0 48px;
    overflow: visible;
}

.services-section .section-title {
    margin: 0 0 30px;
    padding: 0 26px;
}

.services-section .section-title h2 {
    margin: 0 0 14px;
    color: #102b57;
    font-size: clamp(38px, 4vw, 56px);
    font-weight: 900;
    line-height: 1.2;
    letter-spacing: -1.5px;
}

.services-section .section-title p {
    max-width: 850px;
    color: #60789e;
    font-size: 15px;
    line-height: 1.75;
}

/* Three-column professional card layout */
.services-section .services-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    align-items: stretch;
}

/* Card base */
.services-section .service-card {
    --accent: #0868ed;
    --soft: #e5f0ff;
    --tint: #f4f8ff;
    --line: #dceaff;

    position: relative;
    isolation: isolate;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    min-width: 0;
    min-height: 268px;
    padding: 24px 27px 23px;
    overflow: hidden;

    color: #102b57;
    text-decoration: none;
    background: linear-gradient(140deg, #fff 0%, var(--tint) 100%);
    border: 1px solid var(--line);
    border-radius: 22px;
    box-shadow: 0 10px 28px rgba(26, 67, 125, .07);

    transition: transform .25s ease, box-shadow .25s ease;
}

.services-section .service-card::before {
    content: "";
    position: absolute;
    z-index: -1;
    width: 180px;
    height: 180px;
    right: -5px;
    bottom: 0;
    border-radius: 50%;
    background: var(--soft);
    opacity: .5;
}

.services-section .service-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 18px 36px rgba(26, 67, 125, .14);
}

/* Individual service colors */
.services-section .service-card:nth-child(1) {
    --accent: #0868ed;
    --soft: #dcecff;
    --tint: #f5f9ff;
    --line: #dceaff;
}

.services-section .service-card:nth-child(2) {
    --accent: #7038f5;
    --soft: #eadfff;
    --tint: #fbf8ff;
    --line: #eadfff;
}

.services-section .service-card:nth-child(3) {
    --accent: #f58208;
    --soft: #ffead0;
    --tint: #fffaf3;
    --line: #ffead0;
}

.services-section .service-card:nth-child(4) {
    --accent: #e92749;
    --soft: #ffdee5;
    --tint: #fff7f8;
    --line: #f9dfe5;
}

.services-section .service-card:nth-child(5) {
    --accent: #0868ed;
    --soft: #dcecff;
    --tint: #f4f9ff;
    --line: #dceaff;
}

.services-section .service-card:nth-child(6) {
    --accent: #07965b;
    --soft: #d8f8e9;
    --tint: #f3fff9;
    --line: #d8f3e5;
}

/* Icon area */
.services-section .service-icon {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 60px;
    height: 60px;
    margin-bottom: 15px;
    border-radius: 18px;
    color: var(--accent);
    background: var(--soft);
    border: 1px solid rgba(255,255,255,.8);
    font-size: 29px;
    box-shadow: 0 7px 16px rgba(25, 65, 120, .06);
}

/* Category pill, if present in your HTML */
.services-section .service-category {
    position: relative;
    z-index: 1;
    display: inline-flex;
    align-items: center;
    width: fit-content;
    margin: -53px 0 25px 78px;
    min-height: 30px;
    padding: 7px 14px;
    border-radius: 999px;
    color: var(--accent);
    background: var(--soft);
    font-size: 10px;
    font-weight: 900;
    line-height: 1.4;
    text-transform: uppercase;
}

/* Titles and descriptions */
.services-section .service-card h3 {
    position: relative;
    z-index: 1;
    margin: 0 0 9px;
    color: #102b57;
    font-size: 19px;
    font-weight: 900;
    line-height: 1.4;
}

.services-section .service-card p {
    position: relative;
    z-index: 1;
    flex: 1;
    margin: 0 0 20px;
    color: #5e769b;
    font-size: 14px;
    line-height: 1.7;
}

/* High-contrast buttons */
.services-section .request-online {
    position: relative;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    justify-content: space-between;
    gap: 22px;
    width: fit-content;
    min-width: 212px;
    min-height: 44px;
    margin-top: auto;
    padding: 11px 18px 11px 20px;

    color: #fff !important;
    background: var(--accent);
    border: 1px solid transparent;
    border-radius: 999px;
    box-shadow: 0 8px 18px rgba(30, 75, 140, .15);

    font-size: 13px;
    font-weight: 800;
    line-height: 1.4;
    text-decoration: none;

    transition: filter .2s ease, transform .2s ease;
}

.services-section .request-online::after {
    content: "→";
    color: #fff;
    font-size: 20px;
    line-height: 1;
}

.services-section .request-online:hover {
    color: #fff !important;
    filter: brightness(.92);
    transform: translateY(-1px);
}

.services-section .request-online:hover::after {
    color: #fff;
}

/* Tablet */
@media (max-width: 1050px) {
    .services-section {
        width: calc(100% - 36px);
    }

    .services-section .services-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }
}

/* Mobile */
@media (max-width: 600px) {
    .services-section {
        width: calc(100% - 24px);
        padding: 24px 0 32px;
    }

    .services-section .section-title {
        padding: 0 5px;
        margin-bottom: 22px;
    }

    .services-section .section-title h2 {
        font-size: 36px;
        letter-spacing: -1px;
    }

    .services-section .services-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 15px;
    }

    .services-section .service-card {
        min-height: 250px;
        padding: 22px;
    }

    .services-section .service-card h3 {
        font-size: 18px;
    }

    .services-section .request-online {
        min-width: 195px;
    }
}


/* Large decorative illustrations on service cards */
.services-section .service-card {
    position: relative;
    isolation: isolate;
    overflow: hidden;
}

.services-section .service-topline {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.services-section .service-icon {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    width: 60px;
    height: 60px;
    border-radius: 18px;
    font-size: 27px;
    background: var(--soft);
    color: var(--accent);
}

/* Illustration shape */
.services-section .service-card::after {
    content: "";
    position: absolute;
    z-index: -1;
    right: 18px;
    top: 50%;
    width: 165px;
    height: 165px;
    transform: translateY(-38%);
    border-radius: 50%;
    background: var(--soft);
    opacity: .65;
    pointer-events: none;
}

/* Large icon artwork */
.services-section .service-card > .service-art {
    position: absolute;
    z-index: 0;
    right: 27px;
    top: 50%;
    width: 135px;
    height: 145px;
    display: flex;
    align-items: center;
    justify-content: center;
    transform: translateY(-35%);
    color: var(--accent);
    opacity: .95;
    pointer-events: none;
}

.services-section .service-art i {
    font-size: 92px;
    filter: drop-shadow(0 12px 12px rgba(30, 70, 130, .13));
}

/* Color themes */
.services-section .illustration-blue {
    --accent: #0868ed;
    --soft: #dcecff;
}

.services-section .illustration-purple {
    --accent: #7038f5;
    --soft: #eadfff;
}

.services-section .illustration-orange {
    --accent: #f58208;
    --soft: #ffead0;
}

.services-section .illustration-red {
    --accent: #e92749;
    --soft: #ffdee5;
}

.services-section .illustration-green {
    --accent: #07965b;
    --soft: #d8f8e9;
}

/* Keep card text above decorative background */
.services-section .service-card h3,
.services-section .service-card p,
.services-section .service-card .request-online {
    position: relative;
    z-index: 2;
}

@media (max-width: 600px) {
    .services-section .service-card > .service-art {
        right: 12px;
        width: 95px;
        opacity: .35;
    }

    .services-section .service-art i {
        font-size: 68px;
    }

    .services-section .service-card::after {
        right: -15px;
        width: 125px;
        height: 125px;
    }
}

/* Illustrated service artwork */
.services-section .service-card > .service-art {
    position: absolute;
    z-index: 0;
    right: 20px;
    top: 50%;
    width: 160px;
    height: 160px;
    transform: translateY(-40%);
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    opacity: 1;
}

.services-section .service-art svg {
    display: block;
    width: 100%;
    height: 100%;
    overflow: visible;
    filter: drop-shadow(0 8px 10px rgba(30, 64, 175, .08));
}

.services-section .service-card h3,
.services-section .service-card p,
.services-section .service-card .request-online {
    position: relative;
    z-index: 2;
}

/* Keep the button labels readable */
.services-section .service-card .request-online {
    color: #fff !important;
}

@media (max-width: 600px) {
    .services-section .service-card > .service-art {
        width: 105px;
        height: 105px;
        right: 8px;
        opacity: .45;
    }
}

/* =====================================================
   SERVICE CARD ARTWORK — PREVENT TEXT OVERLAP
   ===================================================== */

.services-section .service-card {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    min-width: 0;
}

/* Keep headings above the artwork */
.services-section .service-card h3 {
    position: relative;
    z-index: 2;
    max-width: 100%;
    line-height: 1.35;
    overflow-wrap: break-word;
    margin-top: 22px;
}

/* Keep descriptions clear of the illustration */
.services-section .service-card p {
    position: relative;
    z-index: 2;
    width: calc(100% - 112px);
    max-width: 100%;
    line-height: 1.65;
    overflow-wrap: break-word;
}

/* Move artwork to the lower-right corner */
.services-section .service-card > .service-art {
    position: absolute;
    z-index: 1;
    top: auto;
    right: 14px;
    bottom: 54px;
    width: 112px;
    height: 112px;
    transform: none;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 1;
    pointer-events: none;
}

/* Scale SVG drawings correctly */
.services-section .service-card > .service-art svg {
    display: block;
    width: 100%;
    height: 100%;
    overflow: visible;
    filter: drop-shadow(0 5px 8px rgba(30, 64, 175, .10));
}

/* Smaller background circle behind the illustration */
.services-section .service-card::after {
    top: auto;
    right: -12px;
    bottom: 20px;
    width: 138px;
    height: 138px;
    transform: none;
    pointer-events: none;
}

/* Keep the button in front and readable */
.services-section .service-card .request-online {
    position: relative;
    z-index: 3;
    width: fit-content;
    max-width: 100%;
}

/* Mobile adjustments */
@media (max-width: 600px) {
    .services-section .service-card {
        padding: 20px;
    }

    .services-section .service-card h3 {
        margin-top: 18px;
        max-width: 100%;
    }

    .services-section .service-card p {
        width: calc(100% - 72px);
        font-size: 13px;
        line-height: 1.55;
    }

    .services-section .service-card > .service-art {
        right: 8px;
        bottom: 54px;
        width: 76px;
        height: 76px;
    }

    .services-section .service-card::after {
        right: -18px;
        bottom: 22px;
        width: 105px;
        height: 105px;
    }
}

    </style>


<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
>

</head>


<body>


<!-- =========================
     HEADER
========================= -->


<?php require __DIR__ . '/includes/navbar.php'; ?>





<!-- =========================
     HOME
========================= -->

<?php if (!isset($_GET['view']) || $_GET['view'] === 'home'): ?>

<div class="home-layout">

    <!-- LEFT: WELCOME -->
    <section class="hero">

        <h2>
            Welcome to
            <span class="hero-barangay-name">
                <?= htmlspecialchars($public_barangay_name, ENT_QUOTES, "UTF-8") ?>
            </span>
        </h2>

        <p>
            Access barangay services online, submit service requests,
            track your request status, and stay updated with community announcements.
        </p>

        <div id="onlineServiceStatus" class="online-service-status">
            <strong>🟢 Online Services Available</strong>
            <span>You may submit an online barangay service request.</span>
        </div>

        
<div class="hero-actions">

    <!-- Request a Barangay Service -->
    <a href="index.php?view=services" class="hero-button">
        <span class="hero-button-content">
            <span class="hero-button-icon">
                <i class="fas fa-file-alt"></i>
            </span>
            <span>Request a Barangay Service</span>
        </span>
        <span class="hero-button-arrow">
            <i class="fas fa-arrow-right"></i>
        </span>
    </a>

    <!-- Track My Request -->
    <a href="track_request.php" class="hero-button">
        <span class="hero-button-content">
            <span class="hero-button-icon">
                <i class="fas fa-search"></i>
            </span>
            <span>Track My Request</span>
        </span>
        <span class="hero-button-arrow">
            <i class="fas fa-arrow-right"></i>
        </span>
    </a>

    <!-- View Public Announcements -->
    <a href="announcements_public.php" class="hero-button">
        <span class="hero-button-content">
            <span class="hero-button-icon">
                <i class="fas fa-bullhorn"></i>
            </span>
            <span>View Public Announcements</span>
        </span>
        <span class="hero-button-arrow">
            <i class="fas fa-arrow-right"></i>
        </span>
    </a>

</div>


    </section>


    <!-- RIGHT: BARANGAY INFORMATION -->
    <!-- RIGHT: BARANGAY INFORMATION -->
    <section class="barangay-info" id="barangay-info">
        <div class="info-container">

            <div class="section-title info-section-header">
                <div class="info-heading">
                    <span class="info-heading-icon">
                        <i class="fas fa-landmark"></i>
                    </span>
                    <div>
                        <h2>Barangay Information</h2>
                        <p>Important information about our barangay.</p>
                    </div>
                </div>
                <i class="fas fa-chevron-right info-heading-arrow"></i>
            </div>

            <div class="info-grid">

                <!-- BARANGAY HALL -->
                <div class="info-card hall-card">
                    <div class="info-card-heading">
                        <span class="info-icon hall-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </span>
                        <div>
                            <h3>Barangay Hall</h3>
                            <p>
                                <?= htmlspecialchars($public_address, ENT_QUOTES, "UTF-8") ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- CONTACT INFORMATION -->
                <div class="info-card contact-card">
                    <div class="info-card-heading">
                        <span class="info-icon contact-icon">
                            <i class="fas fa-phone-alt"></i>
                        </span>
                        <div>
                            <h3>Contact Information</h3>
                            <p>
                                <?= htmlspecialchars($settings["contact_number"], ENT_QUOTES, "UTF-8") ?>
                            </p>
                            <p class="info-description">
                                Contact the barangay office for official email assistance.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- OFFICE HOURS -->
                <div class="info-card hours-card">
                    <div class="info-card-heading">
                        <span class="info-icon hours-icon">
                            <i class="far fa-clock"></i>
                        </span>
                        <div>
                            <h3>Office Hours</h3>
                            <p>Monday – Friday</p>
                            <p>8:00 AM – 5:00 PM</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

</div>

<!-- =========================
     QUICK ACCESS
========================= -->

<section class="quick-access-section">


    <div class="quick-access-header">

        <div class="quick-access-title-wrap">

            <span class="quick-access-main-icon">
                <span class="quick-grid-icon">
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </span>


            <div class="quick-access-title-text">
                <h2>Quick Access</h2>
                <p>Common barangay services and online community features.</p>
            </div>
        </div>

        <a href="index.php?view=services" class="view-services-link">
            View All Services <i class="fas fa-arrow-right"></i>
        </a>

    </div>



    <div class="quick-access-grid">

        <!-- Barangay Certificate -->
        <a href="service_request.php?service=Barangay%20Certificate"
           class="quick-card online-service">

            <div class="quick-icon blue">
                <i class="fas fa-file-alt"></i>
            </div>


            <div class="quick-content">
                <h3>Barangay Certificate</h3>
                <p>Request your barangay certificate online.</p>
            </div>

            <span class="quick-arrow">→</span>

        </a>


        <!-- Certificate of Residency -->
        <a href="service_request.php?service=Certificate%20of%20Residency"
           class="quick-card online-service">

            <div class="quick-icon purple">
                <i class="fas fa-house-user"></i>
            </div>

            <div class="quick-content">
                <h3>Certificate of Residency</h3>
                <p>Certification confirming barangay residency.</p>
            </div>

            <span class="quick-arrow">→</span>

        </a>


        <!-- Certificate of Indigency -->
        <a href="service_request.php?service=Certificate%20of%20Indigency"
           class="quick-card online-service">

            <div class="quick-icon green">
                <i class="fas fa-users"></i>
            </div>

            <div class="quick-content">
                <h3>Certificate of Indigency</h3>
                <p>Request certification for official purposes.</p>
            </div>

            <span class="quick-arrow">→</span>

        </a>


        <!-- Blotter Assistance -->
        <a href="service_request.php?service=Blotter%20Assistance"
           class="quick-card online-service">

            <div class="quick-icon purple">
                ⚖️
            </div>

            <div class="quick-content">
                <h3>Blotter Assistance</h3>
                <p>Submit a blotter assistance request online.</p>
            </div>

            <span class="quick-arrow">→</span>

        </a>


        <!-- Public Announcements -->
        <a href="announcements_public.php"
           class="quick-card">

            <div class="quick-icon red">
                📢
            </div>

            <div class="quick-content">
                <h3>Public Announcements</h3>
                <p>View the latest barangay announcements.</p>
            </div>

            <span class="quick-arrow">→</span>

        </a>


        <!-- Community Assistance -->
        <a href="service_request.php?service=Community%20Assistance"
           class="quick-card online-service">

            <div class="quick-icon slate">
                🤝
            </div>

            <div class="quick-content">
                <h3>Community Assistance</h3>
                <p>Request information or community assistance.</p>
            </div>

            <span class="quick-arrow">→</span>

        </a>

    </div>

</section>

<?php endif; ?>


<!-- =========================
     BARANGAY SERVICES
========================= -->


<!-- =========================
     BARANGAY SERVICES
========================= -->

<?php if (isset($_GET['view']) && $_GET['view'] === 'services'): ?>

<section class="services-section" id="barangay-services">

    <div class="section-title">
        <span class="services-eyebrow">BARANGAY MANAGEMENT SYSTEM</span>

        <h2>
            Barangay <span class="title-accent">Services</span>
        </h2>

        <p>
            Submit selected barangay service requests online and track them
            using your reference number. Our goal is to make public services
            more accessible, faster, and more convenient for all residents.
        </p>
    </div>

    <div class="services-grid">

        <a href="service_request.php?service=Barangay%20Certificate"
           class="service-card service-card-link online-service">
            <div class="service-topline">
                <div class="service-icon illustration-blue">
                    <i class="fa-solid fa-file-circle-check"></i>
                </div>
                <span class="service-category">Official Document</span>
            </div>
            <div class="service-art art-certificate" aria-hidden="true">
                <svg viewBox="0 0 160 160" xmlns="http://www.w3.org/2000/svg">
                    <ellipse cx="80" cy="145" rx="55" ry="7" fill="#bfdbfe" opacity=".6"/>
                    <g transform="rotate(7 72 75)">
                        <rect x="36" y="22" width="77" height="104" rx="8" fill="#bfdbfe"/>
                        <path d="M48 13h43l22 22v84a8 8 0 0 1-8 8H48a8 8 0 0 1-8-8V21a8 8 0 0 1 8-8Z" fill="#fff"/>
                        <path d="M91 13v22h22" fill="#dbeafe"/>
                        <path d="M52 49h44M52 60h44M52 71h35M52 82h38M52 93h28" stroke="#93c5fd" stroke-width="5" stroke-linecap="round"/>
                    </g>
                    <circle cx="111" cy="111" r="23" fill="#fbbf24"/>
                    <circle cx="111" cy="108" r="16" fill="#fff0ad"/>
                    <path d="m111 96 4 8 9 1-7 6 2 9-8-5-8 5 2-9-7-6 9-1Z" fill="#f59e0b"/>
                    <path d="m101 129-3 19 13-7 9 10 1-20" fill="#2563eb"/>
                </svg>
            </div>
            <h3>Barangay Certificate</h3>
            <p>Request your barangay certificate online for official purposes.</p>
            <span class="request-online">Request Online</span>
        </a>

        <a href="service_request.php?service=Certificate%20of%20Residency"
           class="service-card service-card-link online-service">
            <div class="service-topline">
                <div class="service-icon illustration-purple">
                    <i class="fa-solid fa-house"></i>
                </div>
                <span class="service-category">Residency Document</span>
            </div>
            <div class="service-art art-residency" aria-hidden="true">
                <svg viewBox="0 0 160 160" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="80" cy="80" r="62" fill="#ede9fe"/>
                    <ellipse cx="80" cy="137" rx="53" ry="7" fill="#c4b5fd" opacity=".7"/>
                    <path d="M25 77 80 31l55 46" fill="none" stroke="#fb7185" stroke-width="13" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M39 72v49a7 7 0 0 0 7 7h68a7 7 0 0 0 7-7V72L80 43Z" fill="#fff"/>
                    <path d="M67 128V92a13 13 0 0 1 26 0v36" fill="#b77952"/>
                    <rect x="49" y="79" width="15" height="17" rx="3" fill="#93c5fd"/>
                    <rect x="97" y="79" width="15" height="17" rx="3" fill="#93c5fd"/>
                    <path d="M126 70v52" stroke="#16a34a" stroke-width="8" stroke-linecap="round"/>
                    <circle cx="126" cy="59" r="17" fill="#22c55e"/>
                    <circle cx="119" cy="69" r="13" fill="#4ade80"/>
                    <ellipse cx="43" cy="128" rx="17" ry="8" fill="#22c55e"/>
                </svg>
            </div>
            <h3>Certificate of Residency</h3>
            <p>Certification confirming that a person is a resident of the barangay.</p>
            <span class="request-online">Request Online</span>
        </a>

        <a href="service_request.php?service=Certificate%20of%20Indigency"
           class="service-card service-card-link online-service">
            <div class="service-topline">
                <div class="service-icon illustration-orange">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
                <span class="service-category">Financial Assistance</span>
            </div>
            <div class="service-art art-indigency" aria-hidden="true">
                <svg viewBox="0 0 160 160" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="80" cy="80" r="64" fill="#ffedd5"/>
                    <path d="M49 19h45l23 23v80a8 8 0 0 1-8 8H49a8 8 0 0 1-8-8V27a8 8 0 0 1 8-8Z" fill="#fff"/>
                    <path d="M94 19v23h23" fill="#fed7aa"/>
                    <path d="M54 54h42M54 65h42M54 76h31M54 87h37" stroke="#fdba74" stroke-width="5" stroke-linecap="round"/>
                    <circle cx="104" cy="111" r="23" fill="#f59e0b"/>
                    <circle cx="104" cy="108" r="15" fill="#fef3c7"/>
                    <path d="m104 96 4 8 8 1-6 6 1 9-7-5-7 5 2-9-7-6 9-1Z" fill="#f59e0b"/>
                    <path d="m94 128-3 18 13-7 10 9 1-20" fill="#0f9f6e"/>
                    <path d="M30 131q-10-14 1-24 15 7 14 21M130 136q-4-16 12-22 7 18-4 26" fill="#34d399"/>
                </svg>
            </div>
            <h3>Certificate of Indigency</h3>
            <p>Assistance for residents who need certification for social and other official purposes.</p>
            <span class="request-online">Request Online</span>
        </a>

        <a href="service_request.php?service=Blotter%20Assistance"
           class="service-card service-card-link online-service">
            <div class="service-topline">
                <div class="service-icon illustration-red">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <span class="service-category">Public Safety</span>
            </div>
            <div class="service-art art-blotter" aria-hidden="true">
                <svg viewBox="0 0 160 160" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="80" cy="80" r="63" fill="#ffe4e6"/>
                    <path d="M80 25 126 42v34c0 29-18 47-46 61C52 123 34 105 34 76V42Z" fill="#fb7185"/>
                    <path d="M80 36 115 49v27c0 22-13 36-35 48Z" fill="#e11d48"/>
                    <path d="m56 79 16 16 34-36" fill="none" stroke="#fff" stroke-width="11" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h3>Blotter Assistance</h3>
            <p>Start an online request for blotter assistance and provide incident details to the barangay.</p>
            <span class="request-online">Request Online</span>
        </a>

        <a href="announcements_public.php"
           class="service-card service-card-link">
            <div class="service-topline">
                <div class="service-icon illustration-blue">
                    <i class="fa-solid fa-bullhorn"></i>
                </div>
                <span class="service-category">Community Updates</span>
            </div>
            <div class="service-art art-announcements" aria-hidden="true">
                <svg viewBox="0 0 160 160" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="80" cy="80" r="63" fill="#dbeafe"/>
                    <path d="M31 65h25l56-25v72L56 87H31a8 8 0 0 1-8-8V73a8 8 0 0 1 8-8Z" fill="#2563eb"/>
                    <path d="M56 87 68 126H48L40 87" fill="#60a5fa"/>
                    <path d="M119 58q17 22 0 43" fill="none" stroke="#fbbf24" stroke-width="8" stroke-linecap="round"/>
                    <path d="m126 38 10-10M133 78h15M124 117l11 10" fill="none" stroke="#fbbf24" stroke-width="7" stroke-linecap="round"/>
                </svg>
            </div>
            <h3>Public Announcements</h3>
            <p>Stay updated with the latest barangay announcements and community information.</p>
            <span class="request-online">View Announcements</span>
        </a>

        <a href="service_request.php?service=Community%20Assistance"
           class="service-card service-card-link online-service">
            <div class="service-topline">
                <div class="service-icon illustration-green">
                    <i class="fa-solid fa-people-group"></i>
                </div>
                <span class="service-category">Community Support</span>
            </div>
            <div class="service-art art-community" aria-hidden="true">
                <svg viewBox="0 0 160 160" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="80" cy="80" r="63" fill="#d1fae5"/>
                    <circle cx="49" cy="57" r="17" fill="#34d399"/>
                    <circle cx="111" cy="57" r="17" fill="#fbbf24"/>
                    <circle cx="80" cy="43" r="19" fill="#0f766e"/>
                    <path d="M20 111a29 29 0 0 1 58 0v13H20Z" fill="#10b981"/>
                    <path d="M82 111a29 29 0 0 1 58 0v13H82Z" fill="#f59e0b"/>
                    <path d="M43 126a37 37 0 0 1 74 0v8H43Z" fill="#0f766e"/>
                </svg>
            </div>
            <h3>Community Assistance</h3>
            <p>Request general information or assistance from the barangay online.</p>
            <span class="request-online">Request Online</span>
        </a>

    </div>

</section>

<?php endif; ?>



<!-- =========================
     FOOTER
========================= -->

<footer>

    © <?= date("Y") ?>
    Barangay Management System.
    All rights reserved.
</footer>



    <!-- ADDITION: BACK TO TOP -->
    <button
        type="button"
        id="backToTop"
        aria-label="Back to top"
        title="Back to top"
    >↑</button>

    <script>
        (function () {
            const button = document.getElementById("backToTop");

            if (!button) return;

            function toggleBackToTop() {
                button.classList.toggle("show", window.scrollY > 450);
            }

            window.addEventListener("scroll", toggleBackToTop, { passive: true });
            toggleBackToTop();

            button.addEventListener("click", function () {
                window.scrollTo({ top: 0, behavior: "smooth" });
            });
        })();
    </script>


<script>
(function () {

    const status =
        document.getElementById("onlineServiceStatus");

    const serviceLinks =
        document.querySelectorAll(".online-service");


    function updateOnlineServices() {

        if (!status) {
            return;
        }


        if (navigator.onLine) {

            status.classList.remove("offline");
            status.classList.add("online");

            status.innerHTML = `
                <strong>🟢 Online Services Available</strong>
                <span>
                    You may submit an online barangay service request.
                </span>
            `;


            serviceLinks.forEach(function (link) {

                link.classList.remove("offline-disabled");

                link.removeAttribute("aria-disabled");

                link.removeAttribute("title");

            });


        } else {

            status.classList.remove("online");
            status.classList.add("offline");

            status.innerHTML = `
                <strong>🔴 Online Services Temporarily Unavailable</strong>
                <span>
                    Internet connection is unavailable.
                    Please try again when the connection is restored.
                </span>
            `;


            serviceLinks.forEach(function (link) {

                link.classList.add("offline-disabled");

                link.setAttribute(
                    "aria-disabled",
                    "true"
                );

                link.setAttribute(
                    "title",
                    "Internet connection required"
                );

            });

        }

    }


    /* BLOCK REQUEST SERVICE CLICK WHEN OFFLINE */

    serviceLinks.forEach(function (link) {

        link.addEventListener(
            "click",
            function (event) {

                if (!navigator.onLine) {

                    event.preventDefault();

                    alert(
                        "Online service requests are currently unavailable because there is no Internet connection. Please reconnect to the Internet and try again."
                    );

                }

            }
        );

    });


    window.addEventListener(
        "online",
        updateOnlineServices
    );


    window.addEventListener(
        "offline",
        updateOnlineServices
    );


    updateOnlineServices();

})();
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const input = document.getElementById("serviceSearch");
    const results = document.getElementById("serviceSearchResults");
    const clearButton = document.getElementById("clearServiceSearch");

    if (!input || !results || !clearButton) return;

    const cards = Array.from(
        document.querySelectorAll(
            ".quick-access-section .quick-card, " +
            ".services-section .service-card"
        )
    );

    if (!cards.length) {
        console.warn("Search: Service cards were not found.");
        return;
    }

    const services = cards.map(function (card) {
        const titleElement = card.querySelector(
            "h2, h3, h4, .service-title, strong"
        );

        const title = titleElement
            ? titleElement.textContent.trim()
            : card.textContent.trim().split("\n")[0];

        const description = card.textContent.trim();

        const link = card.matches("a[href]")
            ? card.href
            : card.querySelector("a[href]")?.href || "";

        return { card, title, description, link };
    });

    let activeIndex = -1;

    function closeResults() {
        results.hidden = true;
        input.setAttribute("aria-expanded", "false");
        activeIndex = -1;
    }

    function updateActiveItem(items) {
        items.forEach(function (item, index) {
            item.classList.toggle("selected", index === activeIndex);
            item.setAttribute(
                "aria-selected",
                index === activeIndex ? "true" : "false"
            );
        });

        if (items[activeIndex]) {
            items[activeIndex].scrollIntoView({
                block: "nearest"
            });
        }
    }

    function renderResults() {
        const query = input.value.trim().toLowerCase();

        results.replaceChildren();
        activeIndex = -1;
        clearButton.hidden = query.length === 0;

        if (!query) {
            closeResults();
            return;
        }

        const matches = services.filter(function (service) {
            return (
                service.title.toLowerCase().includes(query) ||
                service.description.toLowerCase().includes(query)
            );
        }).slice(0, 8);

        if (!matches.length) {
            const empty = document.createElement("div");
            empty.className = "service-search-empty";
            empty.textContent =
                "No services match your search.";
            results.appendChild(empty);
        } else {
            matches.forEach(function (service, index) {
                const item = document.createElement("button");
                item.type = "button";
                item.className = "service-search-result";
                item.setAttribute("role", "option");
                item.setAttribute("aria-selected", "false");

                const title = document.createElement("strong");
                title.textContent = service.title;

                const description = document.createElement("small");
                description.textContent = service.description;

                item.append(title, description);

                item.addEventListener("click", function () {
                    if (service.link) {
                        window.location.href = service.link;
                    } else {
                        service.card.scrollIntoView({
                            behavior: "smooth",
                            block: "center"
                        });
                        closeResults();
                        input.blur();
                    }
                });

                results.appendChild(item);
            });
        }

        results.hidden = false;
        input.setAttribute("aria-expanded", "true");
    }

    input.addEventListener("input", renderResults);

    clearButton.addEventListener("click", function () {
        input.value = "";
        renderResults();
        input.focus();
    });

    input.addEventListener("keydown", function (event) {
        const items = Array.from(
            results.querySelectorAll(".service-search-result")
        );

        if (event.key === "ArrowDown" && !results.hidden) {
            event.preventDefault();
            if (items.length) {
                activeIndex = (activeIndex + 1) % items.length;
                updateActiveItem(items);
            }
        }

        if (event.key === "ArrowUp" && !results.hidden) {
            event.preventDefault();
            if (items.length) {
                activeIndex = activeIndex <= 0
                    ? items.length - 1
                    : activeIndex - 1;
                updateActiveItem(items);
            }
        }

        if (event.key === "Enter" && activeIndex >= 0 && items[activeIndex]) {
            event.preventDefault();
            items[activeIndex].click();
        }

        if (event.key === "Escape") {
            closeResults();
        }
    });

    document.addEventListener("click", function (event) {
        if (!event.target.closest(".nav-search-wrap")) {
            closeResults();
        }
    });
});
</script>

</body>

</html>
