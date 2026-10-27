
<?php

// Shared admin authentication, role check, and session timeout.
require_once __DIR__ . "/../config/admin_auth.php";

require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/audit.php";


function officialNameIsRegisteredResident($conn, string $fullName): bool
{
    $stmt = $conn->prepare(
        "SELECT id
         FROM residents
         WHERE LOWER(TRIM(CONCAT_WS(' ',
             NULLIF(TRIM(first_name), ''),
             NULLIF(TRIM(middle_name), ''),
             NULLIF(TRIM(last_name), ''),
             NULLIF(TRIM(suffix), '')
         ))) = LOWER(?)
         LIMIT 1"
    );

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("s", $fullName);
    $found = $stmt->execute() && $stmt->get_result()->num_rows > 0;
    $stmt->close();

    return $found;
}

$message = "";
$messageType = "";


/* =========================
   CSRF PROTECTION
========================= */

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(
        random_bytes(32)
    );
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
   DELETE OFFICIAL
   POST + CSRF PROTECTED
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_id"])
) {

    $deleteId = (int) $_POST["delete_id"];

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        $submittedToken === "" ||
        !hash_equals($csrf_token, $submittedToken)
    ) {

        $message = "Invalid security token. Please try again.";
        $messageType = "error";

    } elseif ($deleteId <= 0) {

        $message = "Invalid official record.";
        $messageType = "error";

    } else {

        $stmt = $conn->prepare(
            "DELETE FROM officials WHERE id = ?"
        );

        $stmt->bind_param(
            "i",
            $deleteId
        );

        if ($stmt->execute()) {

            addAuditLog(
                $conn,
                "DELETE",
                "Officials",
                "Deleted official record.",
                $deleteId
            );

            $_SESSION["flash_message"] =
                "Official deleted successfully.";

            $_SESSION["flash_type"] =
                "success";

            header("Location: officials.php#official-records");
            exit;
        } else {

            $message =
                "Unable to delete official.";

            $messageType = "error";
        }

        $stmt->close();
    }
}


/* =========================
   ADD / UPDATE OFFICIAL
========================= */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    !isset($_POST["delete_id"])
) {

    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        $submittedToken === "" ||
        !hash_equals($csrf_token, $submittedToken)
    ) {

        $message =
            "Invalid security token. Please refresh the page and try again.";

        $messageType = "error";

    } else {

        $id = isset($_POST["id"])
            ? (int) $_POST["id"]
            : 0;

        $full_name = trim(
            $_POST["full_name"] ?? ""
        );

        $position = trim(
            $_POST["position"] ?? ""
        );

        $term_start =
            $_POST["term_start"] ?? "";

        $term_end =
            $_POST["term_end"] ?? "";


        /* =========================
           VALIDATION
        ========================= */

        if (
            $full_name === "" ||
            $position === "" ||
            $term_start === "" ||
            $term_end === ""
        ) {

            $message =
                "Full name, position, term start, and term end are required.";

            $messageType = "error";

        } elseif (
            !preg_match(
                "/^[A-Za-zÀ-ÿÑñ.' -]+$/u",
                $full_name
            )
        ) {

            $message =
                "Full name must contain letters only.";

            $messageType = "error";

        } elseif (
            $id === 0 &&
            !officialNameIsRegisteredResident($conn, $full_name)
        ) {

            $message = "Please choose a full name from the registered residents.";
            $messageType = "error";

        } elseif (
            !in_array(
                $position,
                [
                    "Barangay Captain",
                    "Barangay Kagawad",
                    "SK Chairperson",
                    "Barangay Secretary",
                    "Barangay Treasurer",
                    "SK Kagawad",
                    "Other"
                ],
                true
            )
        ) {

            $message =
                "Please select a valid position.";

            $messageType = "error";

        } elseif (
            !preg_match("/^\d{4}-\d{2}-\d{2}$/", $term_start) ||
            ($termStartDate = DateTime::createFromFormat("!Y-m-d", $term_start)) === false ||
            $termStartDate->format("Y-m-d") !== $term_start
        ) {

            $message =
                "Please enter a valid term start date.";

            $messageType = "error";

        } elseif (
            !preg_match("/^\d{4}-\d{2}-\d{2}$/", $term_end) ||
            ($termEndDate = DateTime::createFromFormat("!Y-m-d", $term_end)) === false ||
            $termEndDate->format("Y-m-d") !== $term_end
        ) {

            $message =
                "Please enter a valid term end date.";

            $messageType = "error";

        } elseif (
            $term_end < $term_start
        ) {

            $message =
                "Term end cannot be earlier than term start.";

            $messageType = "error";

        }


        /* =========================
           UNIQUE SINGLE-SEAT
           POSITIONS
        ========================= */

        if ($message === "") {

            $singleSeatPositions = [
                "Barangay Captain",
                "SK Chairperson",
                "Barangay Secretary",
                "Barangay Treasurer"
            ];

            if (
                in_array(
                    $position,
                    $singleSeatPositions,
                    true
                )
            ) {

                $checkStmt = $conn->prepare(
                    "SELECT id
                     FROM officials
                     WHERE position = ?
                     AND id != ?
                     LIMIT 1"
                );

                $checkStmt->bind_param(
                    "si",
                    $position,
                    $id
                );

                $checkStmt->execute();

                $checkResult =
                    $checkStmt->get_result();

                if (
                    $checkResult->num_rows > 0
                ) {

                    $message =
                        "This position is already assigned to another official.";

                    $messageType = "error";
                }

                $checkStmt->close();
            }
        }


        /* =========================
           SAVE ONLY IF VALID
        ========================= */

        if ($message === "") {


            /* =========================
               UPDATE
            ========================= */

            if ($id > 0) {

                $stmt = $conn->prepare(
                    "UPDATE officials
                     SET full_name = ?,
                         position = ?,
                         term_start = NULLIF(?, ''),
                         term_end = NULLIF(?, '')
                     WHERE id = ?"
                );

                $stmt->bind_param(
                    "ssssi",
                    $full_name,
                    $position,
                    $term_start,
                    $term_end,
                    $id
                );

                if ($stmt->execute()) {

                    addAuditLog(
                        $conn,
                        "UPDATE",
                        "Officials",
                        "Updated official information.",
                        $id
                    );

                    $_SESSION["flash_message"] =
                        "Official updated successfully.";

                    $_SESSION["flash_type"] =
                        "success";

                    header("Location: officials.php#official-records");
                    exit;
                } else {

                    $message =
                        "Unable to update official.";

                    $messageType = "error";
                }

                $stmt->close();


            } else {


                /* =========================
                   INSERT
                ========================= */

                $stmt = $conn->prepare(
                    "INSERT INTO officials
                    (
                        full_name,
                        position,
                        term_start,
                        term_end
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        NULLIF(?, ''),
                        NULLIF(?, '')
                    )"
                );

                $stmt->bind_param(
                    "ssss",
                    $full_name,
                    $position,
                    $term_start,
                    $term_end
                );

                if ($stmt->execute()) {

                    $official_id = $stmt->insert_id;

                    addAuditLog(
                        $conn,
                        "ADD",
                        "Officials",
                        "Added a new official.",
                        $official_id
                    );

                    $_SESSION["flash_message"] =
                        "Official added successfully.";

                    $_SESSION["flash_type"] =
                        "success";

                    header("Location: officials.php#official-records");
                    exit;
                } else {

                    $message =
                        "Unable to add official.";

                    $messageType = "error";
                }

                $stmt->close();
            }
        }
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
   COUNT TOTAL RECORDS
========================= */

if ($search !== "") {

    $searchValue = "%" . $search . "%";

    $countStmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM officials
         WHERE full_name LIKE ?
            OR position LIKE ?"
    );

    $countStmt->bind_param(
        "ss",
        $searchValue,
        $searchValue
    );

    $countStmt->execute();

    $countResult = $countStmt->get_result();

    $totalRecords = (int) $countResult->fetch_assoc()["total"];

    $countStmt->close();

} else {

    $countResult = $conn->query(
        "SELECT COUNT(*) AS total
         FROM officials"
    );

    $totalRecords = (int) $countResult->fetch_assoc()["total"];
}


/* =========================
   CALCULATE PAGES
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
   GET CURRENT PAGE RECORDS
========================= */

if ($search !== "") {

    $searchValue = "%" . $search . "%";

    $stmt = $conn->prepare(
        "SELECT *
         FROM officials
         WHERE full_name LIKE ?
            OR position LIKE ?
         ORDER BY position ASC, full_name ASC
         LIMIT ? OFFSET ?"
    );

    $stmt->bind_param(
        "ssii",
        $searchValue,
        $searchValue,
        $recordsPerPage,
        $offset
    );

    $stmt->execute();

    $officials = $stmt->get_result();

} else {

    $stmt = $conn->prepare(
        "SELECT *
         FROM officials
         ORDER BY position ASC, full_name ASC
         LIMIT ? OFFSET ?"
    );

    $stmt->bind_param(
        "ii",
        $recordsPerPage,
        $offset
    );

    $stmt->execute();

    $officials = $stmt->get_result();
}


/* =========================
   EDIT OFFICIAL
========================= */

$editOfficial = null;

if (isset($_GET["edit"])) {

    $editId = (int) $_GET["edit"];

    if ($editId > 0) {

        $stmt = $conn->prepare(
            "SELECT *
             FROM officials
             WHERE id = ?"
        );

        $stmt->bind_param("i", $editId);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $editOfficial = $result->fetch_assoc();
        }

        $stmt->close();
    }
}
$officialOriginalValues = [];
if ($editOfficial) {
    foreach (["full_name", "position", "term_start", "term_end"] as $field) {
        $officialOriginalValues[$field] = (string) ($editOfficial[$field] ?? "");
    }
}

$registeredResidentNames = [];
$residentNamesResult = $conn->query(
    "SELECT first_name, middle_name, last_name, suffix
     FROM residents
     ORDER BY last_name ASC, first_name ASC"
);
if ($residentNamesResult) {
    while ($residentRow = $residentNamesResult->fetch_assoc()) {
        $residentNameParts = array_filter([
            trim((string)($residentRow["first_name"] ?? "")),
            trim((string)($residentRow["middle_name"] ?? "")),
            trim((string)($residentRow["last_name"] ?? "")),
            trim((string)($residentRow["suffix"] ?? ""))
        ], static fn($part) => $part !== "");
        $residentFullName = implode(" ", $residentNameParts);
        if ($residentFullName !== "") {
            $registeredResidentNames[] = $residentFullName;
        }
    }
    $residentNamesResult->free();
}


/* =========================
   SHARED HEADER
   SAME SIDEBAR AS CERTIFICATES
========================= */

require_once "../includes/header.php";

?>
<style>
/* Keep the Officials form visually consistent with Resident Records. */
.content-section:has(#officialForm) {
    background: #fff;
    border: 1px solid #e4e9f2;
    border-top: 5px solid #2563eb;
    border-radius: 14px;
    box-shadow: 0 5px 20px rgba(15, 23, 42, .07);
    padding: clamp(23px, 3vw, 35px);
    margin-bottom: 26px;
}
#official-records {
    border-top: 5px solid #2563eb;
}
.content-section:has(#officialForm) .section-header {
    margin-bottom: 26px;
}
.content-section:has(#officialForm) .section-header h2 {
    color: #17233d;
    font-size: clamp(1.25rem, 2vw, 1.65rem);
    font-weight: 750;
    letter-spacing: -.025em;
    margin: 0 0 6px;
}
.content-section:has(#officialForm) .section-header p {
    color: #64748b;
    margin: 0;
    line-height: 1.55;
}
#officialForm {
    background: transparent;
    border: 0;
    border-radius: 0;
    padding: 0;
}
#officialForm .form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px 22px;
    align-items: start;
}
#officialForm .form-group { min-width: 0; margin: 0; }
#officialForm .form-group.full-width { grid-column: 1 / -1; }
#officialForm label {
    display: block;
    color: #26354d;
    font-size: .9rem;
    font-weight: 650;
    margin: 0 0 8px;
}
#officialForm .required { color: #dc2626; }
#officialForm input:not([type="hidden"]),
#officialForm select {
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
#officialForm input::placeholder { color: #94a3b8; }
.official-name-hint { color: #64748b; display: block; font-size: 12px; line-height: 1.4; margin-top: 7px; }
#officialForm input:focus,
#officialForm select:focus {
    outline: none;
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .15);
}
#officialForm .form-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1px solid #e3eaf3;
    padding-top: 20px;
    margin-top: 20px;
}
#officialForm .form-actions .btn {
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
#officialForm .form-actions .btn-secondary { background: #f1f5f9; border: 1px solid #e2e8f0; color: #334155; }
#officialForm .form-actions .btn-primary { background: #2563eb; border: 1px solid #2563eb; color: #fff; }
#officialForm .form-actions .btn-primary:disabled { cursor: not-allowed; filter: grayscale(.2); opacity: .55; transform: none; box-shadow: none; }
#officialForm .form-actions .btn:hover { transform: translateY(-1px); filter: brightness(.98); }
#officialForm .form-actions .btn-primary { box-shadow: 0 4px 10px rgba(37, 99, 235, .18); }
@media (max-width: 900px) {
    #officialForm .form-grid { grid-template-columns: minmax(0, 1fr); gap: 17px; }
    #officialForm .form-group.full-width { grid-column: 1; }
    #officialForm .form-actions { justify-content: stretch; }
    #officialForm .form-actions .btn { flex: 1 1 140px; }
}
@media (max-width: 900px) {
    #official-records { min-width: 0; }
    #official-records .table-container { max-width: 100%; overflow-x: auto; }
    #official-records table { min-width: 760px; }
    #official-records .resident-list-header { align-items: flex-start; flex-wrap: wrap; gap: 14px; }
    #official-records .resident-list-header .search-form { display: flex; flex: 1 1 100%; flex-wrap: wrap; gap: 9px; width: 100%; }
    #official-records .resident-list-header .search-form input { box-sizing: border-box; flex: 1 1 100%; min-width: 0; width: 100%; }
    #official-records .resident-list-header .search-form button,
    #official-records .resident-list-header .search-form a { flex: 0 1 auto; }
}
@media (max-width: 700px) {
    body:has(#officialForm) .sidebar { width: 64px !important; }
    body:has(#officialForm) .sidebar-header { justify-content: center; gap: 0; padding: 12px 6px; }
    body:has(#officialForm) .sidebar-header > div:not(.barangay-logo) { display: none; }
    body:has(#officialForm) .sidebar-menu { padding: 12px 6px 90px; }
    body:has(#officialForm) .sidebar-menu a,
    body:has(#officialForm) .sidebar-bottom a { justify-content: center; gap: 0; padding: 12px 4px; }
    body:has(#officialForm) .sidebar-menu a > span:not(.menu-icon),
    body:has(#officialForm) .sidebar-bottom a > span:not(.menu-icon) { display: none; }
    body:has(#officialForm) .sidebar-menu a .menu-icon,
    body:has(#officialForm) .sidebar-bottom a .menu-icon { flex: 0 0 24px; width: 24px; }
    body:has(#officialForm) .sidebar-bottom { width: 64px !important; padding: 12px 6px; }
    body:has(#officialForm) .main-content { box-sizing: border-box; margin-left: 64px !important; min-width: 0; padding: 16px 12px; width: calc(100% - 64px) !important; }
    body:has(#officialForm) .topbar { align-items: flex-start; flex-wrap: wrap; gap: 12px; }
    .content-section:has(#officialForm) { padding: 20px 16px; }
    #officialForm .form-actions .btn { flex: 1 1 100%; }
}
@media (prefers-reduced-motion: reduce) {
    #officialForm input, #officialForm select, #officialForm .form-actions .btn { transition: none; }
}
</style>


<!-- =========================
     TOP BAR
========================= -->

<header class="topbar">

    <div>

        <h1>
            Barangay Officials
        </h1>

        <p>
            Manage barangay officials and their terms
        </p>

    </div>


    <div class="admin-profile">

        <div class="admin-avatar">

            <?= strtoupper(
                substr(
                    $_SESSION["username"],
                    0,
                    1
                )
            ) ?>

        </div>


        <div>

            <strong>
                <?= htmlspecialchars(
                    $_SESSION["username"]
                ) ?>
            </strong>

            <small>
                <?= htmlspecialchars(
                    ($_SESSION["role"] ?? "") === "admin"
                        ? "Administrator"
                        : ($_SESSION["role"] ?? "User")
                ) ?>
            </small>

        </div>

    </div>

</header>


<!-- =========================
     MESSAGE
========================= -->

<?php if ($message !== ""): ?>

    <div class="crud-popup-overlay" id="crudPopup">

        <div class="crud-popup-box <?= $messageType ?>">

            <div class="crud-popup-icon">
                <?= $messageType === "success" ? "✓" : "!" ?>
            </div>

            <h3>
                <?= $messageType === "success" ? "Success!" : "Error!" ?>
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
     FORM
========================= -->

<section class="content-section">

    <div class="section-header">

        <div>

            <h2>

                <?= $editOfficial
                    ? "Edit Official"
                    : "Add New Official"
                ?>

            </h2>

            <p>
                Add or update an official's name, position, and term dates.
            </p>

        </div>

    </div>


    <form
        method="POST"
        class="resident-form"
        id="officialForm"
        <?= $editOfficial
            ? 'data-original-values="' . htmlspecialchars(json_encode($officialOriginalValues), ENT_QUOTES, "UTF-8") . '"'
            : ""
        ?>
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($csrf_token) ?>"
        >

        <?php if ($editOfficial): ?>
            <input
                type="hidden"
                name="id"
                value="<?= (int) $editOfficial["id"] ?>"
            >
        <?php endif; ?>


        <div class="form-grid">


            <!-- FULL NAME -->

            <div class="form-group">

                <label for="official_full_name">
                    Full Name <span class="required">*</span>
                </label>

                <input
                    type="text"
                    id="official_full_name"
                    name="full_name"
                    list="registered-resident-names"
                    placeholder="Type to find a registered resident"
                    maxlength="100"
                    autocomplete="off"
                    required
                    value="<?= htmlspecialchars(
                        $editOfficial["full_name"] ?? ""
                    ) ?>"
                >
                <datalist id="registered-resident-names">
                    <?php foreach ($registeredResidentNames as $residentName): ?>
                        <option value="<?= htmlspecialchars($residentName, ENT_QUOTES, "UTF-8") ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <small class="official-name-hint">Choose a name from the registered residents list.</small>

            </div>


            <!-- POSITION -->

            <div class="form-group">

                <label for="official_position">
                    Position <span class="required">*</span>
                </label>

                <select
                    id="official_position"
                    name="position"
                    required
                >

                    <option value="">
                        Select Position
                    </option>


                    <?php

                    $positions = [
                        "Barangay Captain",
                        "Barangay Kagawad",
                        "SK Chairperson",
                        "Barangay Secretary",
                        "Barangay Treasurer",
                        "SK Kagawad",
                        "Other"
                    ];

                    foreach ($positions as $position):

                    ?>

                        <option
                            value="<?= htmlspecialchars($position) ?>"
                            <?= (
                                ($editOfficial["position"] ?? "")
                                === $position
                            )
                                ? "selected"
                                : ""
                            ?>
                        >

                            <?= htmlspecialchars($position) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- TERM START -->

            <div class="form-group">

                <label for="term_start">
                    Term Start <span class="required">*</span>
                </label>

                <input
                    type="date"
                    id="term_start"
                    name="term_start"
                    required
                    value="<?= htmlspecialchars(
                        $editOfficial["term_start"] ?? ""
                    ) ?>"
                >

            </div>


            <!-- TERM END -->

            <div class="form-group">

                <label for="term_end">
                    Term End <span class="required">*</span>
                </label>

                <input
                    type="date"
                    id="term_end"
                    name="term_end"
                    required
                    value="<?= htmlspecialchars(
                        $editOfficial["term_end"] ?? ""
                    ) ?>"
                >

            </div>


        </div>


        <!-- FORM ACTIONS -->

        <div class="form-actions">


            <?php if ($editOfficial): ?>

                <a
                    href="officials.php"
                    class="btn btn-secondary"
                >
                    Cancel
                </a>


                <button
                    type="submit"
                    class="btn btn-primary"
                    id="updateOfficialButton"
                >
                    Update Official
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
                    Save Official
                </button>

            <?php endif; ?>


        </div>

    </form>

</section>


<!-- =========================
     OFFICIALS LIST
========================= -->

<section class="content-section" id="official-records">

    <div class="section-header resident-list-header">

        <div>

            <h2>
                Official Records
            </h2>

            <p>
                List of registered barangay officials
            </p>

        </div>


        <!-- SEARCH -->

        <form
            method="GET"
            action="officials.php#official-records"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                placeholder="Search official..."
                value="<?= htmlspecialchars($search) ?>"
            >


            <button type="submit">
                Search
            </button>


            <?php if ($search !== ""): ?>

                <a href="officials.php#official-records">
                    Clear
                </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- =========================
         TABLE
    ========================= -->

    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>
                        Official ID
                    </th>

                    <th>
                        Full Name
                    </th>

                    <th>
                        Position
                    </th>

                    <th>
                        Term Start
                    </th>

                    <th>
                        Term End
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php if (
                $officials &&
                $officials->num_rows > 0
            ): ?>


                <?php while (
                    $official = $officials->fetch_assoc()
                ): ?>


                    <tr>


                        <!-- ID -->

                        <td>

                            OFF-<?= str_pad((string) (int) $official["id"], 6, "0", STR_PAD_LEFT) ?>

                        </td>


                        <!-- FULL NAME -->

                        <td>

                            <strong>

                                <?= htmlspecialchars(
                                    $official["full_name"]
                                ) ?>

                            </strong>

                        </td>


                        <!-- POSITION -->

                        <td>

                            <?= htmlspecialchars(
                                $official["position"]
                            ) ?>

                        </td>


                        <!-- TERM START -->

                        <td>

                            <?= htmlspecialchars(
                                $official["term_start"]
                            ) ?>

                        </td>


                        <!-- TERM END -->

                        <td>

                            <?= htmlspecialchars(
                                $official["term_end"]
                            ) ?>

                        </td>


                        <!-- ACTION -->

                        <td>

                            <div class="table-actions">


                                <a
                                    href="officials.php?edit=<?= $official["id"] ?>"
                                    class="btn-small edit"
                                >
                                    Edit
                                </a>


                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="return showDeletePopup(this);"
                                >
                                    <input
                                        type="hidden"
                                        name="delete_id"
                                        value="<?= (int) $official["id"] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars($csrf_token) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn-small delete"
                                    >
                                        Delete
                                    </button>
                                </form>


                            </div>

                        </td>


                    </tr>


                <?php endwhile; ?>


            <?php else: ?>


                <tr>

                    <td
                        colspan="6"
                        class="empty-table"
                    >

                        No official records found.

                    </td>

                </tr>


            <?php endif; ?>


            </tbody>

        </table>

        </div>


        <!-- =========================
             PAGINATION
        ========================= -->

        <?php if ($totalRecords > 0): ?>

            <?php
            $startRecord = $offset + 1;

            $endRecord = min(
                $offset + $recordsPerPage,
                $totalRecords
            );
            ?>

            <div class="pagination-wrapper">

                <div class="pagination-info">

                    Showing
                    <strong><?= $startRecord ?></strong>
                    –
                    <strong><?= $endRecord ?></strong>
                    of
                    <strong><?= $totalRecords ?></strong>
                    records

                </div>


                <div class="pagination">

                    <?php if ($currentPage > 1): ?>

                        <a
                            href="officials.php?<?= http_build_query([
                                "search" => $search,
                                "page" => $currentPage - 1
                            ]) ?>#official-records"
                            class="page-link"
                        >
                            Previous
                        </a>

                    <?php endif; ?>


                    <?php for (
                        $page = 1;
                        $page <= $totalPages;
                        $page++
                    ): ?>

                        <a
                            href="officials.php?<?= http_build_query([
                                "search" => $search,
                                "page" => $page
                            ]) ?>#official-records"
                            class="page-link <?= $page === $currentPage ? "active" : "" ?>"
                        >
                            <?= $page ?>
                        </a>

                    <?php endfor; ?>


                    <?php if ($currentPage < $totalPages): ?>

                        <a
                            href="officials.php?<?= http_build_query([
                                "search" => $search,
                                "page" => $currentPage + 1
                            ]) ?>#official-records"
                            class="page-link"
                        >
                            Next
                        </a>

                    <?php endif; ?>

                </div>

            </div>

        <?php endif; ?>

</section>

<!-- DELETE CONFIRMATION POPUP -->
<div class="delete-popup-overlay" id="deletePopup">
    <div class="delete-popup-box">

        <div class="delete-popup-icon">
            ⚠️
        </div>

        <h3>Confirm Delete</h3>

        <p>
            Are you sure you want to delete this official?
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
const officialNameInput = document.getElementById("official_full_name");
if (officialNameInput) {
    function capitalizeOfficialName() {
        if (this.value.length > 0) {
            this.value = this.value.charAt(0).toLocaleUpperCase() + this.value.slice(1);
        }
    }

    capitalizeOfficialName.call(officialNameInput);
    officialNameInput.addEventListener("input", capitalizeOfficialName);
}

const officialEditForm = document.getElementById("officialForm");
const updateOfficialButton = document.getElementById("updateOfficialButton");
if (officialEditForm && updateOfficialButton && officialEditForm.dataset.originalValues) {
    const originalOfficialValues = JSON.parse(officialEditForm.dataset.originalValues);
    const officialEditableControls = Array.from(officialEditForm.elements).filter(function (control) {
        return control.name && Object.prototype.hasOwnProperty.call(originalOfficialValues, control.name);
    });

    function updateOfficialButtonState() {
        const hasChanges = officialEditableControls.some(function (control) {
            const currentValue = String(control.value ?? "");
            const originalValue = String(originalOfficialValues[control.name] ?? "");
            return control.name === "full_name"
                ? currentValue.toLocaleLowerCase() !== originalValue.toLocaleLowerCase()
                : currentValue !== originalValue;
        });
        updateOfficialButton.disabled = !hasChanges;
        updateOfficialButton.title = hasChanges
            ? "Save the changes made to this official."
            : "Make a change to enable updating this official.";
    }

    officialEditableControls.forEach(function (control) {
        control.addEventListener("input", updateOfficialButtonState);
        control.addEventListener("change", updateOfficialButtonState);
    });
    updateOfficialButtonState();
}

function closeCrudPopup() {

    const popup = document.getElementById("crudPopup");

    if (popup) {
        popup.remove();
    }

}

setTimeout(function () {

    closeCrudPopup();

}, 3000);


/* =========================
   DELETE CONFIRMATION POPUP
========================= */

let deleteForm = null;


function showDeletePopup(form) {

    deleteForm = form;

    const popup = document.getElementById("deletePopup");

    if (popup) {
        popup.style.display = "flex";
    }

    return false;
}


function closeDeletePopup() {

    const popup = document.getElementById("deletePopup");

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


<?php

/* =========================
   SHARED FOOTER
========================= */

require_once "../includes/footer.php";

?>
