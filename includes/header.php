<?php

/*
|--------------------------------------------------------------------------
| Session Security
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {

    // Prevent session fixation / unknown session IDs
    ini_set("session.use_strict_mode", "1");

    // Secure cookie settings
    $is_https = (
        !empty($_SERVER["HTTPS"]) &&
        $_SERVER["HTTPS"] !== "off"
    );

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => $is_https,
        "httponly" => true,
        "samesite" => "Lax"
    ]);

    session_start();
}


/*
|--------------------------------------------------------------------------
| Prevent browser caching
|--------------------------------------------------------------------------
*/

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");


/*
|--------------------------------------------------------------------------
| Require Login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["user_id"])) {

    header("Location: /barangay-system/login.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Notifications
|--------------------------------------------------------------------------
*/

$currentUserId = (int) $_SESSION["user_id"];
$currentRole = $_SESSION["role"] ?? "";

$notificationList = [];
$unreadNotificationCount = 0;

/*
 * Only Administrator and Staff can see internal notifications.
 */
if (
    $currentRole === "admin" ||
    $currentRole === "Administrator" ||
    $currentRole === "Staff"
) {

    require_once __DIR__ . "/../config/database.php";

    /* Get latest notifications */
    $notificationStmt = $conn->prepare("
        SELECT
            id,
            title,
            message,
            reference_id,
            is_read,
            created_at
        FROM notifications
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 10
    ");

    if ($notificationStmt) {

        $notificationStmt->bind_param(
            "i",
            $currentUserId
        );

        $notificationStmt->execute();

        $notificationResult = $notificationStmt->get_result();

        if ($notificationResult) {

            while ($notificationRow = $notificationResult->fetch_assoc()) {
                $notificationList[] = $notificationRow;
            }
        }

        $notificationStmt->close();
    }

    /* Count unread notifications */
    $countStmt = $conn->prepare("
        SELECT COUNT(*) AS unread_count
        FROM notifications
        WHERE user_id = ?
          AND is_read = 0
    ");

    if ($countStmt) {

        $countStmt->bind_param(
            "i",
            $currentUserId
        );

        $countStmt->execute();

        $countResult = $countStmt->get_result();

        if ($countResult) {

            $countRow = $countResult->fetch_assoc();

            $unreadNotificationCount =
                (int) ($countRow["unread_count"] ?? 0);
        }

        $countStmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Barangay Management System</title>

    <link rel="stylesheet"
          href="/barangay-system/assets/css/style.css?v=11">

    <?php if (in_array(basename($_SERVER["SCRIPT_NAME"] ?? ""), ["officials.php", "certificates.php", "blotter.php", "announcements.php"], true)): ?>
        <link rel="stylesheet" href="/barangay-system/assets/css/admin-record-search.css?v=2">
    <?php endif; ?>

</head>

<body>

    <!-- AUTOMATIC NOTIFICATION POPUP -->

    <div
        id="notificationToast"
        class="notification-toast"
        role="alert"
        aria-live="polite"
    >

        <button
            type="button"
            class="notification-toast-close"
            id="notificationToastClose"
            aria-label="Close notification"
        >
            ×
        </button>

        <div
            id="notificationToastTitle"
            class="notification-toast-title"
        >
            🔔 New Notification
        </div>

        <div
            id="notificationToastMessage"
            class="notification-toast-message"
        ></div>

        <a
            href="/barangay-system/admin/service_requests.php"
            class="notification-toast-link"
        >
            View Service Requests →
        </a>

    </div>


<div class="app-layout">

    <?php include __DIR__ . "/sidebar.php"; ?>

    <!-- NOTIFICATION BELL -->
    <?php if (
        $currentRole === "admin" ||
        $currentRole === "Administrator" ||
        $currentRole === "Staff"
    ): ?>

        <div class="notification-wrapper">

            <button
                type="button"
                class="notification-button"
                id="notificationButton"
                aria-label="Notifications"
                title="Notifications"
            >
                🔔

                <?php if ($unreadNotificationCount > 0): ?>

                    <span class="notification-badge">
                        <?= $unreadNotificationCount > 99
                            ? "99+"
                            : $unreadNotificationCount ?>
                    </span>

                <?php endif; ?>

            </button>


            <div
                class="notification-dropdown"
                id="notificationDropdown"
            >

                <div class="notification-header">
                    <strong>Notifications</strong>

                    <?php if ($unreadNotificationCount > 0): ?>
                        <span class="notification-unread-text">
                            <?= $unreadNotificationCount ?> unread
                        </span>
                    <?php endif; ?>
                </div>


                <div class="notification-list">

                    <?php if (!empty($notificationList)): ?>

                        <?php foreach ($notificationList as $notification): ?>

                            <a
                                href="/barangay-system/admin/service_requests.php"
                                class="notification-item <?= (int)$notification["is_read"] === 0 ? "unread" : "" ?>"
                            >

                                <div class="notification-icon">
                                    🆕
                                </div>

                                <div class="notification-content">

                                    <strong>
                                        <?= htmlspecialchars(
                                            $notification["title"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </strong>

                                    <p>
                                        <?= htmlspecialchars(
                                            $notification["message"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </p>

                                    <small>
                                        <?= htmlspecialchars(
                                            $notification["created_at"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </small>

                                </div>

                            </a>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <div class="no-notifications">
                            🔕 No notifications yet.
                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </div>

    <?php endif; ?>


    <main class="main-content">


            <script>

            document.addEventListener("DOMContentLoaded", function () {

                /* =========================================================
                   NOTIFICATION DROPDOWN
                ========================================================= */

                const notificationButton =
                    document.getElementById("notificationButton");

                const notificationDropdown =
                    document.getElementById("notificationDropdown");

                const notificationList =
                    document.querySelector(".notification-list");

                const notificationHeader =
                    document.querySelector(".notification-header");


                if (
                    notificationButton &&
                    notificationDropdown
                ) {

                    notificationButton.addEventListener(
                        "click",
                        function (event) {

                            event.stopPropagation();

                            notificationDropdown.classList.toggle("show");

                            document.body.classList.toggle(
                                "notifications-open",
                                notificationDropdown.classList.contains("show")
                            );

                        }
                    );


                    document.addEventListener(
                        "click",
                        function (event) {

                            if (
                                !notificationDropdown.contains(event.target) &&
                                !notificationButton.contains(event.target)
                            ) {

                                notificationDropdown.classList.remove("show");

                                document.body.classList.remove(
                                    "notifications-open"
                                );

                            }

                        }
                    );

                }


                /* =========================================================
                   AUTOMATIC NOTIFICATION POPUP
                ========================================================= */

                const toast =
                    document.getElementById("notificationToast");

                const toastTitle =
                    document.getElementById("notificationToastTitle");

                const toastMessage =
                    document.getElementById("notificationToastMessage");

                const toastClose =
                    document.getElementById("notificationToastClose");


                if (
                    !toast ||
                    !toastTitle ||
                    !toastMessage
                ) {
                    return;
                }


                /* =========================================================
                   TRACK KNOWN NOTIFICATIONS
                ========================================================= */

                let knownNotificationIds = new Set();

                let notificationsInitialized = false;


                /* =========================================================
                   CLOSE POPUP
                ========================================================= */

                if (toastClose) {

                    toastClose.addEventListener(
                        "click",
                        function () {

                            toast.classList.remove("show");

                        }
                    );

                }


                /* =========================================================
                   SHOW POPUP
                ========================================================= */

                function showNotificationPopup(notification) {

                    toastTitle.textContent =
                        "🔔 " + notification.title;

                    toastMessage.textContent =
                        notification.message;

                    toast.classList.add("show");


                    setTimeout(
                        function () {

                            toast.classList.remove("show");

                        },
                        8000
                    );

                }


                /* =========================================================
                   UPDATE BELL BADGE
                ========================================================= */

                function updateNotificationBadge(count) {

                    if (!notificationButton) {
                        return;
                    }


                    let badge =
                        notificationButton.querySelector(
                            ".notification-badge"
                        );


                    if (count > 0) {

                        if (!badge) {

                            badge =
                                document.createElement("span");

                            badge.className =
                                "notification-badge";

                            notificationButton.appendChild(
                                badge
                            );

                        }


                        badge.textContent =
                            count > 99
                                ? "99+"
                                : count;

                    } else {

                        if (badge) {
                            badge.remove();
                        }

                    }

                }


                /* =========================================================
                   UPDATE UNREAD TEXT
                ========================================================= */

                function updateUnreadText(count) {

                    if (!notificationHeader) {
                        return;
                    }


                    let unreadText =
                        notificationHeader.querySelector(
                            ".notification-unread-text"
                        );


                    if (count > 0) {

                        if (!unreadText) {

                            unreadText =
                                document.createElement("span");

                            unreadText.className =
                                "notification-unread-text";

                            notificationHeader.appendChild(
                                unreadText
                            );

                        }


                        unreadText.textContent =
                            count + " unread";

                    } else {

                        if (unreadText) {
                            unreadText.remove();
                        }

                    }

                }


                /* =========================================================
                   ESCAPE HTML
                ========================================================= */

                function escapeHtml(value) {

                    const div =
                        document.createElement("div");

                    div.textContent =
                        value ?? "";

                    return div.innerHTML;

                }


                /* =========================================================
                   RENDER NOTIFICATION DROPDOWN
                ========================================================= */

                function renderNotificationList(notifications) {

                    if (!notificationList) {
                        return;
                    }


                    if (
                        !Array.isArray(notifications) ||
                        notifications.length === 0
                    ) {

                        notificationList.innerHTML = `
                            <div class="no-notifications">
                                🔕 No notifications yet.
                            </div>
                        `;

                        return;
                    }


                    notificationList.innerHTML =
                        notifications.map(
                            function (notification) {

                                const unreadClass =
                                    Number(notification.is_read) === 0
                                        ? "unread"
                                        : "";


                                return `
                                    <a
                                        href="/barangay-system/admin/service_requests.php"
                                        class="notification-item ${unreadClass}"
                                        data-notification-id="${Number(notification.id)}"
                                    >

                                        <div class="notification-icon">
                                            🆕
                                        </div>

                                        <div class="notification-content">

                                            <strong>
                                                ${escapeHtml(notification.title)}
                                            </strong>

                                            <p>
                                                ${escapeHtml(notification.message)}
                                            </p>

                                            <small>
                                                ${escapeHtml(notification.created_at)}
                                            </small>

                                        </div>

                                    </a>
                                `;

                            }
                        ).join("");

                }


            /*
            |--------------------------------------------------------------------------
            | MARK NOTIFICATION AS READ
            |--------------------------------------------------------------------------
            */

            if (notificationList) {

                notificationList.addEventListener(
                    "click",
                    async function (event) {

                        const notificationItem =
                            event.target.closest(
                                ".notification-item"
                            );


                        if (!notificationItem) {
                            return;
                        }


                        const notificationId =
                            Number(
                                notificationItem.dataset.notificationId
                            );


                        if (!notificationId) {
                            return;
                        }


                        /*
                        | Prevent immediate navigation
                        */

                        event.preventDefault();


                        try {

                            const formData =
                                new FormData();

                            formData.append(
                                "notification_id",
                                notificationId
                            );


                            const response =
                                await fetch(
                                    "/barangay-system/mark_notification_read.php",
                                    {
                                        method: "POST",
                                        body: formData,
                                        credentials: "same-origin"
                                    }
                                );


                            if (response.ok) {

                                const data =
                                    await response.json();


                                if (data.success) {

                                    /*
                                    | Refresh notification count/list
                                    */

                                    await checkNotifications();

                                }

                            }

                        } catch (error) {

                            console.warn(
                                "Unable to mark notification as read."
                            );

                        }


                        /*
                        | Continue to Service Requests
                        */

                        window.location.href =
                            notificationItem.href;

                    }
                );

            }


                /* =========================================================
                   CHECK NOTIFICATIONS
                ========================================================= */

                async function checkNotifications() {

                    try {

                        const response =
                            await fetch(
                                "/barangay-system/notifications_poll.php",
                                {
                                    method: "GET",
                                    cache: "no-store",
                                    credentials: "same-origin"
                                }
                            );


                        if (!response.ok) {
                            return;
                        }


                        const data =
                            await response.json();


                        if (!data.success) {
                            return;
                        }


                        /* Update bell */
                        updateNotificationBadge(
                            Number(data.unread_count || 0)
                        );


                        /* Update "X unread" */
                        updateUnreadText(
                            Number(data.unread_count || 0)
                        );


                        /* Update dropdown */
                        renderNotificationList(
                            data.notifications || []
                        );


                        /* Detect new notification */

                        if (
                            Array.isArray(data.notifications)
                        ) {

                            if (!notificationsInitialized) {

                                data.notifications.forEach(
                                    function (notification) {

                                        knownNotificationIds.add(
                                            Number(notification.id)
                                        );

                                    }
                                );


                                notificationsInitialized = true;

                            } else {

                                data.notifications.forEach(
                                    function (notification) {

                                        const notificationId =
                                            Number(notification.id);


                                        if (
                                            !knownNotificationIds.has(
                                                notificationId
                                            )
                                        ) {

                                            showNotificationPopup(
                                                notification
                                            );

                                            knownNotificationIds.add(
                                                notificationId
                                            );

                                        }

                                    }
                                );

                            }

                        }

                    } catch (error) {

                        console.warn(
                            "Notification check failed."
                        );

                    }

                }


                /* =========================================================
                   INITIAL CHECK
                ========================================================= */

                checkNotifications();


                /* =========================================================
                   CHECK EVERY 5 SECONDS
                ========================================================= */

                setInterval(
                    checkNotifications,
                    5000
                );

            });

            </script>



        <div id="connectionStatus" class="connection-status online">
            🟢 Online
        </div>

        <script>
        (function () {

            const status = document.getElementById("connectionStatus");

            function updateConnectionStatus() {

                if (!status) {
                    return;
                }

                if (navigator.onLine) {

                    status.textContent = "🟢 Online";
                    status.classList.remove("offline");
                    status.classList.add("online");

                } else {

                    status.textContent = "🔴 Offline";
                    status.classList.remove("online");
                    status.classList.add("offline");

                }
            }

            window.addEventListener("online", updateConnectionStatus);
            window.addEventListener("offline", updateConnectionStatus);

            updateConnectionStatus();

        })();
        </script>
