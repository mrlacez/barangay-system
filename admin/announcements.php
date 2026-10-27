
<?php

require_once __DIR__ . "/../config/announcements_auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/audit.php";

/* =========================
   NORMALIZED ROLE
========================= */

$currentRole = strtolower(
    trim((string) ($_SESSION["role"] ?? ""))
);

$canManageAnnouncements = in_array(
    $currentRole,
    ["admin", "administrator"],
    true
);

$message = "";
$messageType = "";

/* =========================
   CSRF PROTECTION
========================= */

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["csrf_token"];

/* =========================
   FLASH MESSAGE
========================= */

if (isset($_SESSION["flash_message"])) {
    $message = $_SESSION["flash_message"];
    $messageType = $_SESSION["flash_type"] ?? "success";

    unset($_SESSION["flash_message"]);
    unset($_SESSION["flash_type"]);
}

/* =========================
   DELETE ANNOUNCEMENT
   ADMIN ONLY + CSRF
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_id"])
) {
    // Server-side authorization: Admin/Administrator only.
    if (!$canManageAnnouncements) {
        http_response_code(403);
        exit(
            "403 Forbidden: Only administrators can delete announcements."
        );
    }

    $id = filter_var(
        $_POST["delete_id"],
        FILTER_VALIDATE_INT
    );

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        $submittedToken === "" ||
        !hash_equals($csrf_token, $submittedToken)
    ) {
        http_response_code(403);
        exit("403 Forbidden: Invalid security token.");
    }

    if ($id === false || $id <= 0) {
        http_response_code(400);
        exit("Invalid announcement record.");
    }

    $stmt = $conn->prepare(
        "DELETE FROM announcements WHERE id = ?"
    );

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        addAuditLog(
            $conn,
            "DELETE",
            "Announcements",
            "Deleted announcement record.",
            $id
        );

        $_SESSION["flash_message"] =
            "Announcement deleted successfully.";
        $_SESSION["flash_type"] = "success";

        $stmt->close();

        header(
            "Location: announcements.php#announcement-records"
        );
        exit;
    }

    $stmt->close();

    http_response_code(500);
    exit("Unable to delete announcement.");
}

/* =========================
   ADD / UPDATE ANNOUNCEMENT
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    !isset($_POST["delete_id"])
) {
    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        $submittedToken === "" ||
        !hash_equals($csrf_token, $submittedToken)
    ) {
        $message = "Invalid security token. Please refresh the page and try again.";
        $messageType = "error";

    } else {
        $id = isset($_POST["id"])
            ? (int) $_POST["id"]
            : 0;

        $title = trim($_POST["title"] ?? "");
        $content = trim($_POST["content"] ?? "");

        /* =========================
           FORM RESTRICTIONS
        ========================= */

        if ($title === "" || $content === "") {
            $message = "Please complete all required fields.";
            $messageType = "error";

        } elseif (strlen($title) < 3 || strlen($title) > 150) {
            $message =
                "Announcement title must be between 3 and 150 characters.";
            $messageType = "error";

        } elseif (
            !preg_match(
                "/^[A-Za-z0-9À-ÿÑñ'.,!?()\/&:#%+\- ]+$/u",
                $title
            )
        ) {
            $message =
                "Announcement title contains invalid characters.";
            $messageType = "error";

        } elseif (strlen($content) < 5 || strlen($content) > 5000) {
            $message =
                "Announcement content must be between 5 and 5000 characters.";
            $messageType = "error";

        } else {

            /* =========================
               UPDATE
            ========================= */

            if ($id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE announcements
                     SET title = ?, content = ?
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    "ssi",
                    $title,
                    $content,
                    $id
                );

                if ($stmt->execute()) {

                    addAuditLog(
                        $conn,
                        "UPDATE",
                        "Announcements",
                        "Updated announcement information.",
                        $id
                    );

                    $_SESSION["flash_message"] =
                        "Announcement updated successfully.";
                    $_SESSION["flash_type"] = "success";

                    header(
                        "Location: announcements.php#announcement-records"
                    );
                    exit;
                } else {
                    $message =
                        "Unable to update announcement.";
                    $messageType = "error";
                }

                $stmt->close();

            } else {

                /* =========================
                   INSERT
                ========================= */

                $stmt = $conn->prepare(
                    "INSERT INTO announcements
                     (title, content)
                     VALUES (?, ?)"
                );

                $stmt->bind_param(
                    "ss",
                    $title,
                    $content
                );

                if ($stmt->execute()) {

                    $announcement_id = $stmt->insert_id;

                    addAuditLog(
                        $conn,
                        "ADD",
                        "Announcements",
                        "Added a new announcement.",
                        $announcement_id
                    );

                    $_SESSION["flash_message"] =
                        "Announcement added successfully.";
                    $_SESSION["flash_type"] = "success";

                    header(
                        "Location: announcements.php#announcement-records"
                    );
                    exit;
                } else {
                    $message =
                        "Unable to add announcement.";
                    $messageType = "error";
                }

                $stmt->close();
            }
        }
    }
}

/* =========================
   EDIT
========================= */

$edit_announcement = null;

if (isset($_GET["edit"])) {

    $id = (int) $_GET["edit"];

    if ($id > 0) {
        $stmt = $conn->prepare(
            "SELECT *
             FROM announcements
             WHERE id = ?
             LIMIT 1"
        );

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $edit_announcement = $result->fetch_assoc();
        }

        $stmt->close();
    }
}

/* =========================
   SEARCH + PAGINATION
========================= */

$search = trim($_GET["search"] ?? "");

$recordsPerPage = 10;

$currentPage = isset($_GET["page"])
    ? (int) $_GET["page"]
    : 1;

if ($currentPage < 1) {
    $currentPage = 1;
}

/* =========================
   COUNT RECORDS
========================= */

if ($search !== "") {

    $like = "%" . $search . "%";

    $countStmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM announcements
         WHERE title LIKE ?
            OR content LIKE ?"
    );

    $countStmt->bind_param(
        "ss",
        $like,
        $like
    );

    $countStmt->execute();

    $countResult = $countStmt->get_result();

    $totalRecords = (int) $countResult->fetch_assoc()["total"];

    $countStmt->close();

} else {

    $countResult = $conn->query(
        "SELECT COUNT(*) AS total
         FROM announcements"
    );

    $totalRecords = (int) $countResult->fetch_assoc()["total"];
}

/* =========================
   PAGINATION COMPUTATION
========================= */

$totalPages = max(
    1,
    (int) ceil($totalRecords / $recordsPerPage)
);

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $recordsPerPage;

/* =========================
   FETCH RECORDS
========================= */

if ($search !== "") {

    $like = "%" . $search . "%";

    $stmt = $conn->prepare(
        "SELECT *
         FROM announcements
         WHERE title LIKE ?
            OR content LIKE ?
         ORDER BY id DESC
         LIMIT ? OFFSET ?"
    );

    $stmt->bind_param(
        "ssii",
        $like,
        $like,
        $recordsPerPage,
        $offset
    );

    $stmt->execute();

    $announcements = $stmt->get_result();

} else {

    $stmt = $conn->prepare(
        "SELECT *
         FROM announcements
         ORDER BY id DESC
         LIMIT ? OFFSET ?"
    );

    $stmt->bind_param(
        "ii",
        $recordsPerPage,
        $offset
    );

    $stmt->execute();

    $announcements = $stmt->get_result();
}

/* =========================
   HEADER
========================= */

require_once "../includes/header.php";

?>
<style>
/* Keep the Announcements form visually consistent with the Blotter form. */
.content-section:has(#announcementForm) {
    background: #fff;
    border: 1px solid #e4e9f2;
    border-top: 5px solid #2563eb;
    border-radius: 18px;
    box-shadow: 0 8px 28px rgba(15, 23, 42, .055);
    padding: clamp(18px, 2.5vw, 30px);
    margin-bottom: 26px;
}
#announcement-records {
    border-top: 5px solid #2563eb;
}
.content-section:has(#announcementForm) .section-header {
    margin-bottom: 22px;
    padding-bottom: 18px;
    border-bottom: 1px solid #e8edf5;
}
.content-section:has(#announcementForm) .section-header h2 {
    color: #17233d;
    font-size: clamp(1.25rem, 2vw, 1.65rem);
    font-weight: 750;
    letter-spacing: -.025em;
    margin: 0 0 6px;
}
.content-section:has(#announcementForm) .section-header p {
    color: #64748b;
    margin: 0;
    line-height: 1.55;
}
#announcementForm {
    background: #fbfcfe;
    border: 1px solid #e8edf5;
    border-radius: 15px;
    padding: clamp(16px, 2.2vw, 26px);
}
#announcementForm .form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px 22px;
    align-items: start;
}
#announcementForm .form-group { min-width: 0; margin: 0; }
#announcementForm .form-group.full-width { grid-column: 1 / -1; }
#announcementForm label {
    display: block;
    color: #26354d;
    font-size: .9rem;
    font-weight: 650;
    margin: 0 0 8px;
}
#announcementForm input:not([type="hidden"]),
#announcementForm select {
    box-sizing: border-box;
    display: block;
    width: 100%;
    min-width: 0;
    min-height: 46px;
    padding: 11px 13px;
    border: 1px solid #d4dce8;
    border-radius: 9px;
    background: #fff;
    color: #182337;
    font: inherit;
    font-size: .95rem;
    line-height: 1.4;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .025);
    transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
}
#announcementForm input::placeholder { color: #94a3b8; }
#announcementForm input:focus,
#announcementForm select:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
}
#announcementForm .form-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1px solid #e3eaf3;
    padding-top: 20px;
    margin-top: 20px;
}
#announcementForm .form-actions .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 10px 20px;
    border-radius: 9px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    transition: transform .15s ease, box-shadow .15s ease, filter .15s ease;
}
#announcementForm .form-actions .btn:hover { transform: translateY(-1px); filter: brightness(.98); }
#announcementForm .form-actions .btn-primary { box-shadow: 0 4px 10px rgba(37, 99, 235, .18); }
@media (max-width: 760px) {
    #announcementForm .form-grid { grid-template-columns: minmax(0, 1fr); gap: 17px; }
    #announcementForm .form-group.full-width { grid-column: 1; }
    #announcementForm .form-actions { justify-content: stretch; }
    #announcementForm .form-actions .btn { flex: 1 1 140px; }
}
@media (prefers-reduced-motion: reduce) {
    #announcementForm input, #announcementForm select, #announcementForm .form-actions .btn { transition: none; }
}
#announcementForm .form-grid > .form-group:last-child { grid-column: 1 / -1; }
#announcementForm textarea {
    box-sizing: border-box; display: block; width: 100%; min-width: 0; min-height: 160px;
    padding: 11px 13px; border: 1px solid #d4dce8; border-radius: 9px; background: #fff;
    color: #182337; font: inherit; font-size: .95rem; line-height: 1.5; resize: vertical;
    transition: border-color .18s ease, box-shadow .18s ease, background .18s ease;
}
#announcementForm textarea::placeholder { color: #94a3b8; }
#announcementForm textarea:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, .15); }

/* Edit Announcement - disabled until changes are made */
#announcementForm #announcementSubmitButton:disabled {
    background: #2563eb !important;
    border-color: #2563eb !important;
    color: #fff !important;
    cursor: not-allowed !important;
    opacity: 0.45;
    box-shadow: none !important;
    transform: none !important;
    filter: none !important;
}

#announcementForm #announcementSubmitButton:not(:disabled) {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
    cursor: pointer;
    opacity: 1;
}

#announcementForm #announcementSubmitButton:not(:disabled):hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
}

</style>



<!-- =========================
     TOP BAR
========================= -->

<div class="topbar">

    <div>

        <h1>Announcements</h1>

        <p>
            Manage barangay announcements
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

            <small>
                <?= htmlspecialchars(
                    $currentRole === "admin"
                        ? "Administrator"
                        : ($_SESSION["role"] ?? "User")
                ) ?>
            </small>

        </div>

    </div>

</div>


<!-- =========================
     CRUD SUCCESS / ERROR POPUP
========================= -->

<?php if ($message !== ""): ?>

    <div class="crud-popup-overlay" id="crudPopup">

        <div class="crud-popup-box <?= $messageType ?>">

            <div class="crud-popup-icon">
                <?= $messageType === "success" ? "✓" : "!" ?>
            </div>

            <h3>
                <?= $messageType === "success"
                    ? "Success!"
                    : "Error!" ?>
            </h3>

            <p>
                <?= htmlspecialchars($message) ?>
            </p>

            <button
                type="button"
                class="crud-popup-button"
                onclick="closeCrudPopup()"
            >
                OK
            </button>

        </div>

    </div>

<?php endif; ?>


<!-- =========================
     ADD / EDIT
========================= -->

<section class="content-section">

    <div class="section-header">

        <div>

            <h2>

                <?= $edit_announcement
                    ? 'Edit Announcement'
                    : 'Add New Announcement'
                ?>

            </h2>

            <p>

                <?= $edit_announcement
                    ? 'Update the selected announcement.'
                    : 'Create a new barangay announcement.'
                ?>

            </p>

        </div>

    </div>


    <form method="POST" id="announcementForm" data-auto-capitalize>

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($csrf_token) ?>"
        >

        <?php if ($edit_announcement): ?>

            <input
                type="hidden"
                name="id"
                value="<?= $edit_announcement['id'] ?>"
            >

        <?php endif; ?>


        <div class="form-grid">


            <!-- TITLE -->

            <div class="form-group">

                <label for="title">
                    Announcement Title *
                </label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    placeholder="Enter announcement title"
                    maxlength="150"
                    value="<?= htmlspecialchars(
                        $edit_announcement['title'] ?? ''
                    ) ?>"
                    required
                >

            </div>


            <!-- CONTENT -->

            <div class="form-group">

                <label for="content">
                    Announcement Content *
                </label>

                <textarea
                    id="content"
                    name="content"
                    rows="6"
                    maxlength="5000"
                    placeholder="Enter announcement details..."
                    required
                ><?= htmlspecialchars(
                    $edit_announcement['content'] ?? ''
                ) ?></textarea>

            </div>

        </div>


        <!-- BUTTONS -->

        <div class="form-actions">

            <?php if ($edit_announcement): ?>

                <a
                    href="announcements.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="btn btn-primary"
                    id="announcementSubmitButton"
                    disabled
                    title="No changes to update"
                >
                    Update Announcement
                </button>

            <?php else: ?>

                <button
                    type="reset"
                    class="btn btn-secondary"
                >
                    Clear
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Add Announcement
                </button>

            <?php endif; ?>

        </div>

    </form>

</section>


<!-- =========================
     ANNOUNCEMENT RECORDS
========================= -->

<section class="content-section" id="announcement-records">

    <div class="section-header">

        <div>

            <h2>
                Announcement Records
            </h2>

            <p>
                View and manage all barangay announcements.
            </p>

        </div>

    </div>


    <!-- SEARCH -->

    <form
        method="GET"
        action="announcements.php#announcement-records"
        class="search-form"
    >

        <input
            type="text"
            name="search"
            placeholder="Search announcement..."
            value="<?= htmlspecialchars($search) ?>"
        >

        <button
            type="submit"
            class="btn btn-primary"
        >
            Search
        </button>

        <?php if ($search !== ''): ?>

            <a
                href="announcements.php#announcement-records"
                class="btn btn-secondary"
            >
                Clear
            </a>

        <?php endif; ?>

    </form>


    <!-- TABLE -->

    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>Announcement ID</th>

                    <th>Title</th>

                    <th>Content</th>

                    <th>Date Posted</th>

                    <th>Actions</th>

                </tr>

            </thead>


            <tbody>

            <?php if (
                $announcements &&
                $announcements->num_rows > 0
            ): ?>

                <?php $counter = $offset + 1; ?>


                <?php while (
                    $row = $announcements->fetch_assoc()
                ): ?>

                    <tr>

                        <td>
                            ANN-<?= str_pad((string) $row['id'], 6, '0', STR_PAD_LEFT) ?>
                        </td>

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $row['title']
                                ) ?>
                            </strong>

                        </td>


                        <td>

                            <?= nl2br(
                                htmlspecialchars(
                                    $row['content']
                                )
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


                        <td>

                            <div class="action-buttons">

                                <a
                                    href="announcements.php?edit=<?= (int) $row['id'] ?>"
                                    class="btn btn-secondary"
                                >
                                    Edit
                                </a>


                                <?php if ($canManageAnnouncements): ?>
                                    <form method="POST" style="display:inline;"
                                          onsubmit="return showDeletePopup(this);">

                                        <input
                                            type="hidden"
                                            name="delete_id"
                                            value="<?= (int) $row['id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars($csrf_token) ?>"
                                        >

                                        <button type="submit" class="btn btn-danger">
                                            Delete
                                        </button>
                                    </form>
                                <?php endif; ?>

                            </div>

                        </td>

                    </tr>

                <?php endwhile; ?>


            <?php else: ?>

                <tr>

                    <td
                        colspan="5"
                        style="text-align:center; padding:30px;"
                    >

                        No announcements found.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    <?php if ($totalRecords > 0): ?>

        <div class="pagination-wrapper">

            <div class="pagination-info">

                <?php
                $startRecord = $offset + 1;
                $endRecord = min(
                    $offset + $recordsPerPage,
                    $totalRecords
                );
                ?>

                Showing
                <?= $startRecord ?>
                –
                <?= $endRecord ?>
                of
                <?= $totalRecords ?>
                records

            </div>


            <?php if ($totalPages > 1): ?>

                <div class="pagination">

                    <?php if ($currentPage > 1): ?>

                        <a
                            href="announcements.php?search=<?= urlencode($search) ?>&page=<?= $currentPage - 1 ?>#announcement-records"
                            class="page-link"
                        >
                            Previous
                        </a>

                    <?php endif; ?>


                    <?php for ($page = 1; $page <= $totalPages; $page++): ?>

                        <a
                            href="announcements.php?search=<?= urlencode($search) ?>&page=<?= $page ?>#announcement-records"
                            class="page-link <?= $page === $currentPage ? 'active' : '' ?>"
                        >
                            <?= $page ?>
                        </a>

                    <?php endfor; ?>


                    <?php if ($currentPage < $totalPages): ?>

                        <a
                            href="announcements.php?search=<?= urlencode($search) ?>&page=<?= $currentPage + 1 ?>#announcement-records"
                            class="page-link"
                        >
                            Next
                        </a>

                    <?php endif; ?>

                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</section>


<!-- =========================
     DELETE CONFIRMATION POPUP
========================= -->

<div
    class="delete-popup-overlay"
    id="deletePopup"
>
    <div class="delete-popup-box">

        <div class="delete-popup-icon">
            ⚠️
        </div>

        <h3>
            Confirm Delete
        </h3>

        <p>
            Are you sure you want to delete this announcement?
        </p>

        <div class="delete-popup-actions">

            <button
                type="button"
                class="delete-cancel-button"
                onclick="closeDeletePopup()"
            >
                Cancel
            </button>

            <button
                type="button"
                class="delete-confirm-button"
                onclick="confirmDelete()"
            >
                Delete
            </button>

        </div>

    </div>
</div>


<script>

function closeCrudPopup() {

    const popup =
        document.getElementById("crudPopup");

    if (popup) {
        popup.remove();
    }

}

setTimeout(function () {
    closeCrudPopup();
}, 3000);


let deleteForm = null;


function showDeletePopup(form) {

    deleteForm = form;

    const popup =
        document.getElementById("deletePopup");

    if (popup) {
        popup.style.display = "flex";
    }

    return false;

}


function closeDeletePopup() {

    const popup =
        document.getElementById("deletePopup");

    if (popup) {
        popup.style.display = "none";
    }

    deleteForm = null;

}


function confirmDelete() {

    if (deleteForm) {
        deleteForm.submit();
    }

}

</script>

<script src="../assets/js/auto-capitalize.js" defer></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("announcementForm");
    const submitButton = document.getElementById("announcementSubmitButton");

    const isEditing = <?= $edit_announcement ? "true" : "false" ?>;

    if (!form || !submitButton || !isEditing) {
        return;
    }

    const fields = form.querySelectorAll(
        "input:not([type='hidden']), select, textarea"
    );

    const originalValues = new Map();

    // Save original values
    fields.forEach(function (field) {
        originalValues.set(field, field.value);
    });

    function hasChanges() {
        for (const field of fields) {
            const originalValue = originalValues.get(field) ?? "";
            const currentValue = field.value;

            if (currentValue !== originalValue) {
                return true;
            }
        }

        return false;
    }

    function updateButtonState() {
        const changed = hasChanges();

        submitButton.disabled = !changed;

        if (changed) {
            submitButton.title = "Update the changes";
        } else {
            submitButton.title = "No changes to update";
        }
    }

    fields.forEach(function (field) {
        field.addEventListener("input", updateButtonState);
        field.addEventListener("change", updateButtonState);
    });

    // Initial state
    updateButtonState();
});
</script>

<?php

require_once "../includes/footer.php";

?>
