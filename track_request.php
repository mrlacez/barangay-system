<?php

require_once "config/database.php";

$request = null;
$error = "";

/* =========================
   TRACK REQUEST
========================= */

if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET["reference_no"])) {

    $reference_no = trim($_GET["reference_no"]);

    if ($reference_no === "") {
        $error = "Please enter your reference number.";
    } else {

        $stmt = $conn->prepare("
            SELECT
                reference_no,
                service_type,
                full_name,
                status,
                admin_remarks,
                created_at
            FROM service_requests
            WHERE reference_no = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $reference_no);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $request = $result->fetch_assoc();
        } else {
            $error = "Reference number not found. Please check your reference number and try again.";
        }

        $stmt->close();
    }
}

/* =========================
   STATUS CLASS
========================= */

function statusClass($status)
{
    switch ($status) {
        case "Pending":
            return "pending";

        case "Under Review":
            return "review";

        case "Approved":
            return "approved";

        case "Rejected":
            return "rejected";

        case "Ready for Pickup":
            return "ready";

        case "Completed":
            return "completed";

        default:
            return "pending";
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

    <title>Track Service Request - Barangay Management System</title>


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #1f2937;
        }


        /* =========================
           MAIN
        ========================= */
        .container {
            width: 100%;
            max-width: 850px;
            margin: 45px auto;
            padding: 0 20px;
            flex: 1 0 auto;
        }

        .page-title {
            text-align: center;
            margin-bottom: 25px;
        }

        .page-title h2 {
            margin: 0;
            color: #172554;
            font-size: 32px;
        }

        .page-title p {
            color: #64748b;
            margin-top: 10px;
        }

        /* =========================
           TRACK FORM
        ========================= */

        .track-card {
            background: white;
            border-radius: 14px;
            padding: 30px;

            box-shadow: 0 6px 25px rgba(0, 0, 0, .08);

            border-top: 5px solid #2563eb;
        }

        .form-label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #172554;
        }

        .reference-input {
            width: 100%;
            padding: 14px;

            border: 1px solid #cbd5e1;
            border-radius: 8px;

            font-size: 16px;
            outline: none;
        }

        .reference-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
        }

        .track-button {
            width: 100%;
            margin-top: 15px;

            padding: 13px;

            border: none;
            border-radius: 8px;

            background: #2563eb;
            color: white;

            font-weight: bold;
            font-size: 15px;

            cursor: pointer;
        }

        .track-button:hover {
            background: #1d4ed8;
        }

        /* =========================
           ERROR
        ========================= */

        .error-message {
            margin-top: 20px;

            background: #fef2f2;
            border: 1px solid #fecaca;

            color: #b91c1c;

            padding: 13px;
            border-radius: 8px;

            text-align: center;
        }

        /* =========================
           RESULT
        ========================= */

        .result-card {
            margin-top: 25px;

            background: white;

            border-radius: 14px;

            padding: 30px;

            box-shadow: 0 6px 25px rgba(0, 0, 0, .08);
        }

        .result-title {
            margin-top: 0;
            color: #172554;
        }

        .reference-box {
            background: #eff6ff;

            border: 1px solid #bfdbfe;

            padding: 18px;

            border-radius: 10px;

            text-align: center;

            margin-bottom: 20px;
        }

        .reference-label {
            color: #64748b;
            font-size: 13px;
        }

        .reference-number {
            display: block;

            margin-top: 5px;

            color: #1d4ed8;

            font-size: 21px;

            font-weight: bold;
        }

        .details {
            display: grid;

            grid-template-columns: repeat(2, 1fr);

            gap: 15px;
        }

        .detail-box {
            background: #f8fafc;

            border: 1px solid #e2e8f0;

            border-radius: 8px;

            padding: 14px;
        }

        .detail-label {
            display: block;

            font-size: 12px;

            color: #64748b;

            margin-bottom: 5px;
        }

        .detail-value {
            color: #172554;

            font-weight: bold;
        }

        /* =========================
           STATUS
        ========================= */

        .status-box {
            margin-top: 20px;

            text-align: center;

            padding: 18px;

            border-radius: 10px;

            background: #f8fafc;
        }

        .status-label {
            display: block;

            color: #64748b;

            font-size: 13px;

            margin-bottom: 8px;
        }

        .status {
            display: inline-block;

            padding: 8px 18px;

            border-radius: 20px;

            font-weight: bold;
        }

        .pending {
            background: #fef3c7;
            color: #92400e;
        }

        .review {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .approved {
            background: #dcfce7;
            color: #166534;
        }

        .rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .ready {
            background: #ede9fe;
            color: #6d28d9;
        }

        .completed {
            background: #d1fae5;
            color: #065f46;
        }

        /* =========================
           REMARKS
        ========================= */

        .remarks {
            margin-top: 20px;

            padding: 15px;

            background: #f8fafc;

            border-left: 4px solid #2563eb;

            border-radius: 6px;
        }

        .remarks strong {
            display: block;

            margin-bottom: 7px;

            color: #172554;
        }

        .no-remarks {
            color: #64748b;
        }

        /* =========================
           FOOTER
        ========================= */




        /* FOOTER — MATCH INDEX.PHP */
        footer {
            width: 100%;
            min-height: 44px;
            margin-top: auto;
            padding: 10px 16px;

            display: flex;
            align-items: center;
            justify-content: center;

            background: linear-gradient(
                135deg,
                #0b1e3d,
                #102a56
            );

            color: #cbd5e1;
            font-size: 12px;
            line-height: normal;

            border-top: 1px solid rgba(255, 255, 255, .05);
            flex-shrink: 0;
        }

        @media (max-width: 700px) {
            footer {
                min-height: 48px;
                padding: 14px 12px;
                text-align: center;
                line-height: 1.5;
                font-size: 11px;
            }
        }




        /* =========================
           MOBILE
        ========================= */

        @media (max-width: 700px) {
            .container {
                margin-top: 30px;
            }

            .track-card,
            .result-card {
                padding: 20px;
            }

            .details {
                grid-template-columns: 1fr;
            }

            .page-title h2 {
                font-size: 26px;
            }

        }


/* Shared BONGDO navbar layout */
.main-header {
    width: 100%;
    min-height: 94px;
    padding: 0;
    margin: 0;
    background: linear-gradient(110deg, #173f82, #194b91);
    color: #fff;
    box-shadow: 0 5px 20px rgba(15, 35, 70, .16);
    position: relative;
    z-index: 1000;
}

.main-header .main-nav {
    width: calc(100% - 56px);
    max-width: 1600px;
    min-height: 94px;
    margin: 0 auto;
    padding: 0;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 22px;
}

.main-header .brand {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 14px;
    min-width: 260px;
    color: #fff;
}

.main-header .brand-logo {
    width: 72px;
    height: 72px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 15px;
    background: #fff;
    color: #1768ed;
    font-size: 34px;
}

.main-header .brand-text h1 {
    margin: 0;
    color: #fff;
    font-size: 23px;
    line-height: 1.2;
    font-weight: 900;
}

.main-header .brand-text span {
    display: block;
    margin-top: 5px;
    color: #fff;
    font-size: 13px;
    opacity: .95;
}

.main-header .main-nav-links {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

.main-header .nav-item {
    min-height: 48px;
    padding: 0 13px;
    display: inline-flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #fff;
    background: transparent;
    border-radius: 13px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    white-space: nowrap;
}

.main-header .nav-item:hover {
    color: #fff;
    background: rgba(255, 255, 255, .13);
}

.main-header .nav-item.active {
    color: #fff;
    background: #2782f4;
}

.main-header .nav-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: inherit;
    font-size: 17px;
}

.main-header .nav-icon svg {
    display: block;
    width: 18px;
    height: 18px;
}

.main-header .nav-right {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 18px;
    flex-shrink: 0;
}

.main-header .nav-search-wrap {
    position: relative;
    width: clamp(180px, 20vw, 300px);
}

.main-header .nav-search {
    min-height: 46px;
    padding: 0 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid rgba(255, 255, 255, .1);
    border-radius: 25px;
    background: rgba(255, 255, 255, .13);
    color: #fff;
}

.main-header .nav-search input {
    width: 100%;
    min-width: 0;
    padding: 8px 0;
    border: 0;
    outline: none;
    background: transparent;
    color: #fff;
}

.main-header .nav-search input::placeholder {
    color: rgba(255, 255, 255, .8);
}

.main-header .admin-menu {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 9px;
    color: #fff;
    white-space: nowrap;
}

.main-header .admin-avatar {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #edf4ff;
    color: #214b88;
    font-size: 21px;
}

.main-header .admin-name strong {
    color: #fff;
    font-size: 14px;
}

.main-header .admin-arrow {
    color: #fff;
    font-size: 12px;
}

@media (max-width: 1100px) {
    .main-header .main-nav {
        width: calc(100% - 24px);
        gap: 10px;
    }

    .main-header .brand {
        min-width: auto;
    }

    .main-header .brand-logo {
        width: 50px;
        height: 50px;
        font-size: 25px;
    }

    .main-header .brand-text h1 {
        font-size: 18px;
    }

    .main-header .brand-text span {
        display: none;
    }

    .main-header .nav-item {
        padding: 0 8px;
        font-size: 12px;
    }

    .main-header .nav-right {
        gap: 8px;
    }
}

@media (max-width: 760px) {
    .main-header .main-nav {
        padding: 10px 0;
        flex-wrap: wrap;
    }

    .main-header .brand {
        flex: 1 1 100%;
    }

    .main-header .brand-text span {
        display: block;
    }

    .main-header .main-nav-links {
        order: 2;
        flex: 1 1 100%;
        justify-content: flex-start;
        overflow-x: auto;
    }

    .main-header .nav-item {
        flex-shrink: 0;
    }

    .main-header .nav-right {
        display: none;
    }
}



/* =========================================================
   BONGDO NAVBAR — MATCH index.php
   Keep the same 88px height, spacing, active state and controls.
   ========================================================= */
.main-header {
    width: 100%;
    height: 88px;
    min-height: 88px;
    padding: 0;
    margin: 0;
    background: linear-gradient(135deg, #123873 0%, #16448f 48%, #0f3d82 100%);
    box-shadow: 0 5px 22px rgba(15, 23, 42, .18);
    position: relative;
    z-index: 1000;
    color: #fff;
}
.main-header .main-nav {
    width: min(1540px, calc(100% - 55px));
    height: 88px;
    min-height: 88px;
    margin: 0 auto;
    padding: 0;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: initial;
    gap: 25px;
    box-sizing: border-box;
}
.main-header .brand {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 13px;
    min-width: 315px;
    flex-shrink: 0;
    margin: 0;
    padding: 0;
    color: #fff;
}
.main-header .brand-logo {
    width: 66px;
    height: 66px;
    min-width: 66px;
    min-height: 66px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
    background: #fff;
    border-radius: 14px;
    color: #1768e8;
    font-size: 34px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, .18);
}
.main-header .brand-text { min-width: 0; }
.main-header .brand-text h1 {
    margin: 0;
    color: #fff;
    font-size: 23px;
    line-height: 1.1;
    font-weight: 900;
    letter-spacing: .3px;
}
.main-header .brand-text span {
    display: block;
    margin-top: 5px;
    color: rgba(255, 255, 255, .86);
    font-size: 13px;
    white-space: nowrap;
}
.main-header .main-nav-links {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 5px;
}
.main-header .nav-item {
    position: relative;
    height: 46px;
    min-height: 46px;
    padding: 0 15px;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 0;
    border-radius: 12px;
    color: #fff;
    background: transparent;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    white-space: nowrap;
    transition: background .2s ease, transform .2s ease, box-shadow .2s ease;
    box-sizing: border-box;
}
.main-header .nav-item:hover {
    color: #fff;
    background: rgba(255, 255, 255, .14);
    transform: translateY(-1px);
}
.main-header .nav-item.active {
    color: #fff;
    background: linear-gradient(135deg, #3b91f7, #1473ed);
    box-shadow: 0 7px 18px rgba(0, 0, 0, .16);
}
.main-header .nav-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: inherit;
    font-size: 18px;
    line-height: 1;
}
.main-header .nav-icon svg {
    display: block;
    width: 18px;
    height: 18px;
}
.main-header .announcement-nav { position: relative; }
.main-header .notification-dot {
    position: absolute;
    top: 6px;
    right: 7px;
    width: 9px;
    height: 9px;
    border-radius: 50%;
    background: #ef4444;
    border: 2px solid #17458b;
    box-shadow: 0 0 0 1px rgba(255, 255, 255, .08);
}
.main-header .nav-right {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 20px;
    flex-shrink: 0;
}
.main-header .nav-search-wrap {
    position: relative;
    width: 282px;
    flex-shrink: 0;
}
.main-header .nav-search {
    width: 100%;
    height: 43px;
    min-height: 43px;
    padding: 0 15px;
    display: flex;
    align-items: center;
    gap: 9px;
    border: 1px solid rgba(255, 255, 255, .08);
    border-radius: 24px;
    background: rgba(255, 255, 255, .13);
    color: #fff;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, .04);
}
.main-header .nav-search span {
    flex: 0 0 auto;
    color: #fff;
    font-size: 22px;
    line-height: 1;
}
.main-header .nav-search input {
    width: 100%;
    min-width: 0;
    padding: 0;
    border: 0;
    outline: none;
    background: transparent;
    color: #fff;
    font-size: 13px;
}
.main-header .nav-search input::placeholder { color: rgba(255, 255, 255, .75); }
.main-header .nav-search:focus-within {
    background: rgba(255, 255, 255, .18);
    box-shadow: 0 0 0 3px rgba(255, 255, 255, .08);
}
.main-header .admin-menu {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 9px;
    color: #fff;
    cursor: pointer;
    white-space: nowrap;
    user-select: none;
}
.main-header .admin-avatar {
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #eaf2ff;
    color: #214c8d;
    font-size: 21px;
    border: 2px solid rgba(255, 255, 255, .55);
    box-shadow: 0 3px 10px rgba(0, 0, 0, .10);
}
.main-header .admin-name strong {
    color: #fff;
    font-size: 14px;
    font-weight: 800;
}
.main-header .admin-arrow {
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
    vertical-align: middle;
}
.main-header .admin-arrow i { display: block; line-height: 1; }

@media (max-width: 1200px) {
    .main-header .main-nav { width: calc(100% - 30px); gap: 14px; }
    .main-header .brand { min-width: auto; }
    .main-header .nav-search-wrap { width: 200px; }
    .main-header .nav-item { padding: 0 10px; }
}
@media (max-width: 900px) {
    .main-header { height: auto; min-height: 88px; }
    .main-header .main-nav {
        width: calc(100% - 30px);
        height: auto;
        min-height: 88px;
        flex-wrap: wrap;
        padding: 10px 0;
        gap: 12px;
    }
    .main-header .brand { min-width: 0; }
    .main-header .main-nav-links {
        width: 100%;
        flex: 1 1 100%;
        flex-wrap: wrap;
        justify-content: flex-start;
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

</style>

</head>

<body>


<?php
$current_view = 'track_request';
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
?>

<?php require __DIR__ . '/includes/navbar.php'; ?>



<main class="container">

    <div class="page-title">

        <h2>🔎 Track Your Service Request</h2>

        <p>
            Enter your reference number to check the status
            of your barangay service request.
        </p>

    </div>


    <div class="track-card">

        <form method="GET" action="track_request.php">

            <label class="form-label">
                Reference Number
            </label>

            <input
                type="text"
                name="reference_no"
                class="reference-input"
                placeholder="Example: BRGY-20260920-000001"
                value="<?= htmlspecialchars($_GET["reference_no"] ?? "", ENT_QUOTES, "UTF-8") ?>"
                required
            >

            <button
                type="submit"
                class="track-button"
            >
                🔎 Track Request
            </button>

        </form>

        <?php if ($error): ?>

            <div class="error-message">
                <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
            </div>

        <?php endif; ?>

    </div>


    <?php if ($request): ?>

        <div class="result-card">

            <h2 class="result-title">
                📋 Request Details
            </h2>

            <div class="reference-box">

                <span class="reference-label">
                    Reference Number
                </span>

                <span class="reference-number">
                    <?= htmlspecialchars(
                        $request["reference_no"],
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </span>

            </div>


            <div class="details">

                <div class="detail-box">

                    <span class="detail-label">
                        Service Requested
                    </span>

                    <span class="detail-value">
                        <?= htmlspecialchars(
                            $request["service_type"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </span>

                </div>


                <div class="detail-box">

                    <span class="detail-label">
                        Resident
                    </span>

                    <span class="detail-value">
                        <?= htmlspecialchars(
                            $request["full_name"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>
                    </span>

                </div>


                <div class="detail-box">

                    <span class="detail-label">
                        Date Submitted
                    </span>

                    <span class="detail-value">
                        <?= date(
                            "F d, Y h:i A",
                            strtotime($request["created_at"])
                        ) ?>
                    </span>

                </div>

            </div>


            <div class="status-box">

                <span class="status-label">
                    Current Request Status
                </span>

                <span class="status <?= statusClass($request["status"]) ?>">

                    <?= htmlspecialchars(
                        $request["status"],
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                </span>

            </div>


            <div class="remarks">

                <strong>
                    📌 Barangay Remarks
                </strong>

                <?php if (!empty($request["admin_remarks"])): ?>

                    <?= nl2br(
                        htmlspecialchars(
                            $request["admin_remarks"],
                            ENT_QUOTES,
                            "UTF-8"
                        )
                    ) ?>

                <?php else: ?>

                    <span class="no-remarks">
                        No remarks or instructions have been provided yet.
                    </span>

                <?php endif; ?>

            </div>

        </div>

    <?php endif; ?>

</main>


<footer>

    © <?= date("Y") ?>
    Barangay Management System.
    All rights reserved.

</footer>


</body>

</html>
