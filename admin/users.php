
<?php

require_once __DIR__ . "/../config/admin_auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/audit.php";


/*
|--------------------------------------------------------------------------
| No Cache
|--------------------------------------------------------------------------
*/
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/
if (empty($_SESSION["users_csrf_token"])) {
    $_SESSION["users_csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["users_csrf_token"];

/*
|--------------------------------------------------------------------------
| Flash Message
|--------------------------------------------------------------------------
*/
$message = $_SESSION["users_message"] ?? "";
$message_type = $_SESSION["users_message_type"] ?? "";

unset($_SESSION["users_message"]);
unset($_SESSION["users_message_type"]);

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/
function setMessage($message, $type = "success")
{
    $_SESSION["users_message"] = $message;
    $_SESSION["users_message_type"] = $type;
}

function redirectRecords()
{
    header("Location: users.php#user-records");
    exit;
}

/*
|--------------------------------------------------------------------------
| ADD / UPDATE USER
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["save_user"])) {

    $submitted_token = $_POST["csrf_token"] ?? "";

    if (
        $submitted_token === "" ||
        !hash_equals($_SESSION["users_csrf_token"], $submitted_token)
    ) {
        setMessage("Invalid security token. Please refresh the page and try again.", "error");
        redirectRecords();
    }

    $user_id = (int)($_POST["user_id"] ?? 0);
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $role = trim($_POST["role"] ?? "");
    $status = trim($_POST["status"] ?? "");

    /*
    |----------------------------------------------------------------------
    | Validation
    |----------------------------------------------------------------------
    */
    if ($username === "") {

        setMessage("Username is required.", "error");
        redirectRecords();

    }

    if (strlen($username) > 50) {

        setMessage("Username must not exceed 50 characters.", "error");
        redirectRecords();

    }

    if (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {

        setMessage(
            "Username may contain only letters, numbers, dot, underscore, and hyphen.",
            "error"
        );

        redirectRecords();
    }

    if (!in_array($role, ["Administrator", "Staff"], true)) {

        setMessage("Please select a valid role.", "error");
        redirectRecords();

    }

    if (!in_array($status, ["Active", "Inactive"], true)) {

        setMessage("Please select a valid account status.", "error");
        redirectRecords();

    }

    /*
    |----------------------------------------------------------------------
    | Check duplicate username
    |----------------------------------------------------------------------
    */
    if ($user_id > 0) {

        $stmt = $conn->prepare(
            "SELECT id
             FROM users
             WHERE username = ?
             AND id != ?
             LIMIT 1"
        );

        $stmt->bind_param("si", $username, $user_id);

    } else {

        $stmt = $conn->prepare(
            "SELECT id
             FROM users
             WHERE username = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $username);
    }

    $stmt->execute();
    $duplicate = $stmt->get_result();

    if ($duplicate->num_rows > 0) {

        $stmt->close();

        setMessage("Username already exists. Please choose another username.", "error");
        redirectRecords();
    }

    $stmt->close();

    /*
    |----------------------------------------------------------------------
    | ADD USER
    |----------------------------------------------------------------------
    */
    if ($user_id === 0) {

        if ($password === "") {

            setMessage("Password is required when creating a new user.", "error");
            redirectRecords();

        }

        if (strlen($password) < 8) {

            setMessage("Password must be at least 8 characters.", "error");
            redirectRecords();

        }

        $hashed_password = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $conn->prepare(
            "INSERT INTO users
             (username, password, role, status)
             VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssss",
            $username,
            $hashed_password,
            $role,
            $status
        );

        if ($stmt->execute()) {

            $new_user_id = $stmt->insert_id;

            addAuditLog(
                $conn,
                "ADD",
                "User Management",
                "Added a new user account.",
                $new_user_id
            );

            setMessage("User account successfully added.", "success");

        } else {

            setMessage("Unable to add user account.", "error");
        }

        $stmt->close();

        redirectRecords();
    }

    /*
    |----------------------------------------------------------------------
    | UPDATE USER
    |----------------------------------------------------------------------
    */
    $stmt = $conn->prepare(
        "UPDATE users
         SET username = ?, role = ?, status = ?
         WHERE id = ?"
    );

    $stmt->bind_param(
        "sssi",
        $username,
        $role,
        $status,
        $user_id
    );

    if ($stmt->execute()) {

        addAuditLog(
            $conn,
            "UPDATE",
            "User Management",
            "Updated user account information.",
            $user_id
        );

        setMessage("User account successfully updated.", "success");

    } else {

        setMessage("Unable to update user account.", "error");
    }

    $stmt->close();

    redirectRecords();
}


/*
|--------------------------------------------------------------------------
| CHANGE PASSWORD
|--------------------------------------------------------------------------
*/
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["change_password"])
) {
    $submitted_token = $_POST["csrf_token"] ?? "";

    if (
        $submitted_token === "" ||
        !hash_equals($_SESSION["users_csrf_token"], $submitted_token)
    ) {
        setMessage("Invalid security token. Please refresh the page and try again.", "error");
        redirectRecords();
    }

    $user_id = (int)($_POST["password_user_id"] ?? 0);
    $old_password = $_POST["old_password"] ?? "";
    $new_password = $_POST["new_password"] ?? "";
    $confirm_password = $_POST["confirm_password"] ?? "";

    if ($user_id <= 0) {
        setMessage("Invalid user account.", "error");
        redirectRecords();
    }

    if ($old_password === "") {
        setMessage("Please enter the current password.", "error");
        redirectRecords();
    }

    if (strlen($new_password) < 8) {
        setMessage("New password must be at least 8 characters.", "error");
        redirectRecords();
    }

    if (strlen($new_password) > 255) {
        setMessage("New password is too long.", "error");
        redirectRecords();
    }

    if ($new_password !== $confirm_password) {
        setMessage("New password and confirmation do not match.", "error");
        redirectRecords();
    }

    if ($old_password === $new_password) {
        setMessage("Your new password must be different from your current password.", "error");
        redirectRecords();
    }

    // Get the existing password hash of the selected account.
    $stmt = $conn->prepare(
        "SELECT password FROM users WHERE id = ? LIMIT 1"
    );

    if (!$stmt) {
        setMessage("Unable to verify the current password.", "error");
        redirectRecords();
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $account = $result->fetch_assoc();
    $stmt->close();

    if (!$account) {
        setMessage("User account not found.", "error");
        redirectRecords();
    }

    // Verify the current password before changing it.
    if (!password_verify($old_password, $account["password"])) {
        setMessage("Incorrect current password. Password was not changed.", "error");
        redirectRecords();
    }

    $hashed_password = password_hash(
        $new_password,
        PASSWORD_DEFAULT
    );

    $stmt = $conn->prepare(
        "UPDATE users SET password = ? WHERE id = ?"
    );

    if (!$stmt) {
        setMessage("Unable to prepare password update.", "error");
        redirectRecords();
    }

    $stmt->bind_param("si", $hashed_password, $user_id);

    if ($stmt->execute() && $stmt->affected_rows === 1) {
        addAuditLog(
            $conn,
            "CHANGE PASSWORD",
            "User Management",
            "Changed user account password.",
            $user_id
        );

        setMessage("Password successfully changed.", "success");
    } else {
        setMessage("Password was not changed. Please try again.", "error");
    }

    $stmt->close();
    redirectRecords();
}


/*
|--------------------------------------------------------------------------
| DELETE USER
|--------------------------------------------------------------------------
*/
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_user"])
) {

    $submitted_token = $_POST["csrf_token"] ?? "";

    if (
        $submitted_token === "" ||
        !hash_equals($_SESSION["users_csrf_token"], $submitted_token)
    ) {
        setMessage("Invalid security token. Please refresh the page and try again.", "error");
        redirectRecords();
    }

    $user_id = (int)($_POST["delete_user_id"] ?? 0);

    /*
    |----------------------------------------------------------------------
    | Prevent deleting yourself
    |----------------------------------------------------------------------
    */
    if ($user_id === (int)$_SESSION["user_id"]) {

        setMessage("You cannot delete your own account.", "error");
        redirectRecords();
    }

    if ($user_id <= 0) {

        setMessage("Invalid user account.", "error");
        redirectRecords();
    }

    /*
    |----------------------------------------------------------------------
    | Prevent deleting the last Administrator
    |----------------------------------------------------------------------
    */
    $stmt = $conn->prepare(
        "SELECT username, role
         FROM users
         WHERE id = ?
         LIMIT 1"
    );

    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();
    $target_user = $result->fetch_assoc();

    $stmt->close();

    if (!$target_user) {

        setMessage("User account not found.", "error");
        redirectRecords();
    }

    $target_role = $target_user["role"];
    $target_username = $target_user["username"] ?? "Unknown";

    if (
        $target_role === "Administrator" ||
        $target_role === "admin"
    ) {

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total_admins
             FROM users
             WHERE role IN ('Administrator', 'admin')"
        );

        $stmt->execute();

        $admin_result = $stmt->get_result();
        $admin_data = $admin_result->fetch_assoc();

        $stmt->close();

        if ((int)$admin_data["total_admins"] <= 1) {

            setMessage(
                "The last Administrator account cannot be deleted.",
                "error"
            );

            redirectRecords();
        }
    }

    /*
    |----------------------------------------------------------------------
    | Delete
    |----------------------------------------------------------------------
    */
    $stmt = $conn->prepare(
        "DELETE FROM users
         WHERE id = ?"
    );

    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {

        addAuditLog(
            $conn,
            "DELETE",
            "User Management",
            "Deleted user account: " . $target_username . ".",
            $user_id
        );

        setMessage("User account successfully deleted.", "success");

    } else {

        setMessage("Unable to delete user account.", "error");
    }

    $stmt->close();

    redirectRecords();
}

/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/
$search = trim($_GET["search"] ?? "");

if ($search !== "") {

    $stmt = $conn->prepare(
        "SELECT id, username, role, status, created_at
         FROM users
         WHERE username LIKE ?
            OR role LIKE ?
            OR status LIKE ?
         ORDER BY id DESC"
    );

    $search_value = "%" . $search . "%";

    $stmt->bind_param(
        "sss",
        $search_value,
        $search_value,
        $search_value
    );

} else {

    $stmt = $conn->prepare(
        "SELECT id, username, role, status, created_at
         FROM users
         ORDER BY id DESC"
    );
}

$stmt->execute();
$users_result = $stmt->get_result();

?>

<?php include "../includes/header.php"; ?>

<div class="page-header">

    <div>
        <h1>User Management</h1>
        <p>Manage system users, roles, passwords, and account status.</p>
    </div>

    <button
        type="button"
        class="btn btn-primary"
        onclick="openAddUserModal()">
        + Add User
    </button>

</div>


<!-- ==========================================================
     USER RECORDS
========================================================== -->

<section class="content-card" id="user-records">

    <div class="section-header">

        <div>
            <h2>User Records</h2>
            <p>List of registered system users.</p>
        </div>

        <form method="GET" class="search-form">

            <input
                type="text"
                name="search"
                value="<?= htmlspecialchars($search) ?>"
                placeholder="Search username, role, status..."
                maxlength="50">

            <button type="submit" class="btn btn-secondary">
                Search
            </button>

            <?php if ($search !== ""): ?>

                <a
                    href="users.php#user-records"
                    class="btn btn-light">
                    Clear
                </a>

            <?php endif; ?>

        </form>

    </div>


    <div class="table-responsive">

        <table class="data-table">

            <thead>

                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>

            </thead>

            <tbody>

            <?php if ($users_result->num_rows > 0): ?>

                <?php while ($user = $users_result->fetch_assoc()): ?>

                    <tr>

                        <td>
                            <?= (int)$user["id"] ?>
                        </td>

                        <td>
                            <strong>
                                <?= htmlspecialchars($user["username"]) ?>
                            </strong>
                        </td>

                        <td>

                            <?php
                            $display_role =
                                ($user["role"] === "admin")
                                ? "Administrator"
                                : $user["role"];
                            ?>

                            <span class="role-badge">
                                <?= htmlspecialchars($display_role) ?>
                            </span>

                        </td>

                        <td>

                            <span
                                class="status-badge
                                <?= $user["status"] === "Active"
                                    ? "status-active"
                                    : "status-inactive" ?>">

                                <?= htmlspecialchars($user["status"]) ?>

                            </span>

                        </td>

                        <td>
                            <?= date(
                                "M d, Y h:i A",
                                strtotime($user["created_at"])
                            ) ?>
                        </td>

                        <td>

                            <div class="action-buttons">

                                <button
                                    type="button"
                                    class="btn btn-edit"
                                    onclick='openEditUserModal(
                                        <?= json_encode([
                                            "id" => $user["id"],
                                            "username" => $user["username"],
                                            "role" =>
                                                $user["role"] === "admin"
                                                ? "Administrator"
                                                : $user["role"],
                                            "status" => $user["status"]
                                        ]) ?>
                                    )'>
                                    Edit
                                </button>


                                <button
                                    type="button"
                                    class="btn btn-password"
                                    onclick='openPasswordModal(
                                        <?= json_encode([
                                            "id" => $user["id"],
                                            "username" => $user["username"]
                                        ]) ?>
                                    )'>
                                    Password
                                </button>


                                <?php if (
                                    (int)$user["id"] !==
                                    (int)$_SESSION["user_id"]
                                ): ?>

                                    <form
                                        method="POST"
                                        class="inline-form"
                                        onsubmit="return showDeletePopup(this);">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars($csrf_token) ?>">

                                        <input
                                            type="hidden"
                                            name="delete_user_id"
                                            value="<?= (int)$user["id"] ?>">

                                        <button
                                            type="submit"
                                            name="delete_user"
                                            class="btn btn-delete">
                                            Delete
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span class="current-user-label">
                                        Current Account
                                    </span>

                                <?php endif; ?>

                            </div>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="6"
                        class="empty-state">

                        No user records found.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<!-- ==========================================================
     ADD / EDIT USER MODAL
========================================================== -->

<div
    class="user-modal-overlay"
    id="userModal"
    style="display:none;">

    <div class="user-modal-box">

        <button
            type="button"
            class="modal-close"
            onclick="closeUserModal()">
            ×
        </button>

        <h2 id="userModalTitle">
            Add User
        </h2>

        <p id="userModalSubtitle">
            Create a new system user account.
        </p>


        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrf_token) ?>">

            <input
                type="hidden"
                name="user_id"
                id="user_id"
                value="0">


            <div class="form-group">

                <label for="user_username">
                    Username
                </label>

                <input
                    type="text"
                    id="user_username"
                    name="username"
                    maxlength="50"
                    pattern="[A-Za-z0-9._-]+"
                    placeholder="Enter username"
                    required>

                <small>
                    Letters, numbers, dot, underscore, and hyphen only.
                </small>

            </div>


            <div
                class="form-group"
                id="passwordAddGroup">

                <label for="user_password">
                    Password
                </label>

                <input
                    type="password"
                    id="user_password"
                    name="password"
                    minlength="8"
                    maxlength="255"
                    placeholder="Enter password">

                <small>
                    Minimum of 8 characters.
                </small>

            </div>


            <div class="form-group">

                <label for="user_role">
                    Role
                </label>

                <select
                    id="user_role"
                    name="role"
                    required>

                    <option value="Administrator">
                        Administrator
                    </option>

                    <option value="Staff">
                        Staff
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label for="user_status">
                    Account Status
                </label>

                <select
                    id="user_status"
                    name="status"
                    required>

                    <option value="Active">
                        Active
                    </option>

                    <option value="Inactive">
                        Inactive
                    </option>

                </select>

            </div>


            <div class="modal-actions">

                <button
                    type="button"
                    class="btn btn-light"
                    onclick="closeUserModal()">
                    Cancel
                </button>

                <button
                    type="submit"
                    name="save_user"
                    class="btn btn-primary">
                    Save User
                </button>

            </div>

        </form>

    </div>

</div>


<!-- ==========================================================
     CHANGE PASSWORD MODAL
========================================================== -->

<div
    class="user-modal-overlay"
    id="passwordModal"
    style="display:none;">

    <div class="user-modal-box">

        <button
            type="button"
            class="modal-close"
            onclick="closePasswordModal()">
            ×
        </button>

        <h2>Change Password</h2>

        <p>
            Change password for
            <strong id="passwordUsername"></strong>
        </p>

        <form method="POST" autocomplete="off">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrf_token) ?>">

            <input
                type="hidden"
                name="password_user_id"
                id="password_user_id">

            <div class="form-group">
                <label for="old_password">Current Password</label>
                <input
                    type="password"
                    id="old_password"
                    name="old_password"
                    maxlength="255"
                    placeholder="Enter current password"
                    autocomplete="current-password"
                    required>
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    minlength="8"
                    maxlength="255"
                    placeholder="Enter new password"
                    autocomplete="new-password"
                    required>
                <small>Minimum of 8 characters.</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    minlength="8"
                    maxlength="255"
                    placeholder="Confirm new password"
                    autocomplete="new-password"
                    required>
            </div>

            <div class="modal-actions">

                <button
                    type="button"
                    class="btn btn-light"
                    onclick="closePasswordModal()">
                    Cancel
                </button>

                <button
                    type="submit"
                    name="change_password"
                    value="1"
                    class="btn btn-primary">
                    Change Password
                </button>

            </div>

        </form>

    </div>
</div>


<!-- ==========================================================
     SUCCESS / ERROR POPUP
========================================================== -->

<?php if ($message !== ""): ?>

<div
    class="crud-popup-overlay"
    id="crudPopup">

    <div class="crud-popup-box <?= $message_type ?>">

        <div class="crud-popup-icon">

            <?= $message_type === "success" ? "✓" : "!" ?>

        </div>

        <h3>

            <?= $message_type === "success"
                ? "Success!"
                : "Error!" ?>

        </h3>

        <p>
            <?= htmlspecialchars($message) ?>
        </p>

        <button
            type="button"
            class="crud-popup-button"
            onclick="closeCrudPopup()">
            OK
        </button>

    </div>

</div>

<?php endif; ?>


<!-- ==========================================================
     DELETE CONFIRMATION
========================================================== -->

<div
    class="delete-popup-overlay"
    id="deletePopup">

    <div class="delete-popup-box">

        <div class="delete-popup-icon">
            ⚠️
        </div>

        <h3>
            Confirm Delete
        </h3>

        <p>
            Are you sure you want to delete this user account?
        </p>

        <div class="delete-popup-actions">

            <button
                type="button"
                class="delete-cancel-button"
                onclick="closeDeletePopup()">
                Cancel
            </button>

            <button
                type="button"
                class="delete-confirm-button"
                onclick="confirmDelete()">
                Delete
            </button>

        </div>

    </div>

</div>


<style>

/* ==========================================================
   USER MANAGEMENT
========================================================== */

.search-form {
    display: flex;
    gap: 8px;
    align-items: center;
}

#user-records {
    border-top: 5px solid #2563eb;
}

.search-form input {
    min-width: 260px;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    outline: none;
}

.search-form input:focus {
    border-color: #2563eb;
}

.table-responsive {
    width: 100%;
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 13px 14px;
    border-bottom: 1px solid #e5e7eb;
    text-align: left;
    vertical-align: middle;
}

.data-table th {
    background: #f8fafc;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
}

.data-table td {
    color: #475569;
    font-size: 13px;
}

.role-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    background: #dbeafe;
    color: #1d4ed8;
    font-size: 12px;
    font-weight: 600;
}

.status-badge {
    display: inline-block;
    padding: 5px 11px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.status-active {
    background: #dcfce7;
    color: #15803d;
}

.status-inactive {
    background: #fee2e2;
    color: #b91c1c;
}

.action-buttons {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.inline-form {
    display: inline;
    margin: 0;
}

.btn-edit {
    background: #2563eb;
    color: #ffffff;
}

.btn-edit:hover {
    background: #1d4ed8;
}

.btn-password {
    background: #7c3aed;
    color: #ffffff;
}

.btn-password:hover {
    background: #6d28d9;
}

.btn-delete {
    background: #dc2626;
    color: #ffffff;
}

.btn-delete:hover {
    background: #b91c1c;
}

.current-user-label {
    padding: 6px 9px;
    border-radius: 6px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 11px;
}

.empty-state {
    text-align: center !important;
    padding: 35px !important;
    color: #94a3b8 !important;
}


/* ==========================================================
   USER MODAL
========================================================== */

.user-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.55);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9998;
    padding: 20px;
}

.user-modal-box {
    position: relative;
    width: 460px;
    max-width: 100%;
    background: #ffffff;
    border-top: 5px solid #2563eb;
    border-radius: 14px;
    padding: 30px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    animation: userModalShow 0.2s ease;
}

.user-modal-box h2 {
    margin: 0 0 7px;
    color: #1e293b;
}

.user-modal-box > p {
    margin: 0 0 22px;
    color: #64748b;
    font-size: 13px;
}

.modal-close {
    position: absolute;
    top: 12px;
    right: 15px;
    border: none;
    background: transparent;
    color: #64748b;
    font-size: 27px;
    cursor: pointer;
}

.modal-close:hover {
    color: #dc2626;
}

.form-group {
    margin-bottom: 17px;
}

.form-group label {
    display: block;
    margin-bottom: 7px;
    color: #334155;
    font-size: 13px;
    font-weight: 600;
}

.form-group input,
.form-group select {
    width: 100%;
    box-sizing: border-box;
    padding: 11px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    outline: none;
    background: #ffffff;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
}

.form-group small {
    display: block;
    margin-top: 5px;
    color: #94a3b8;
    font-size: 11px;
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 25px;
}

.btn-light {
    background: #e2e8f0;
    color: #334155;
}

.btn-light:hover {
    background: #cbd5e1;
}

@keyframes userModalShow {

    from {
        opacity: 0;
        transform: scale(0.94);
    }

    to {
        opacity: 1;
        transform: scale(1);
    }

}


/* ==========================================================
   MOBILE
========================================================== */

@media (max-width: 768px) {

    .search-form {
        width: 100%;
        flex-wrap: wrap;
    }

    .search-form input {
        min-width: 0;
        flex: 1;
    }

    .section-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .user-modal-box {
        padding: 24px 20px;
    }

}

/* Match the Residents card, search controls, and records table. */
#user-records {
    background: #fff;
    border: 1px solid #e4e9f2;
    border-top: 5px solid #2563eb;
    border-radius: 14px;
    box-shadow: 0 5px 20px rgba(15, 23, 42, .07);
    margin-bottom: 26px;
    padding: 25px 30px;
}
#user-records .section-header { align-items: flex-start; display: block; margin-bottom: 22px; }
#user-records .section-header h2 { color: #17233d; font-size: 22px; font-weight: 750; margin: 0 0 5px; }
#user-records .section-header p { color: #64748b; margin: 0; }
#user-records .search-form { align-items: center; display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px; }
#user-records .search-form input { background: #fff; border: 1px solid #cbd5e1; border-radius: 9px; box-sizing: border-box; color: #182337; flex: 0 1 575px; font: inherit; font-size: 14px; min-height: 46px; min-width: min(100%, 260px); padding: 11px 14px; }
#user-records .search-form input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, .14); outline: 0; }
#user-records .search-form .btn { align-items: center; border: 1px solid transparent; border-radius: 9px; display: inline-flex; font-size: 14px; font-weight: 700; justify-content: center; min-height: 44px; padding: 0 18px; }
#user-records .search-form .btn-secondary { background: #2563eb; color: #fff; }
#user-records .search-form .btn-secondary:hover { background: #1d4ed8; }
#user-records .search-form .btn-light { background: #f1f5f9; border-color: #e2e8f0; color: #334155; text-decoration: none; }
#user-records .table-responsive { border: 1px solid #e3eaf3; border-radius: 11px; overflow-x: auto; }
#user-records .data-table { border-collapse: separate; border-spacing: 0; min-width: 880px; width: 100%; }
#user-records .data-table th { background: #f1f5f9; border-bottom: 1px solid #dfe7f0; color: #334155; font-size: 12px; font-weight: 700; padding: 14px 12px; text-align: left; white-space: nowrap; }
#user-records .data-table td { border-bottom: 1px solid #e8edf3; color: #334155; font-size: 13px; line-height: 1.45; padding: 12px; vertical-align: middle; }
#user-records .data-table tbody tr:nth-child(even) { background: #f8fafc; }
#user-records .data-table tbody tr:hover { background: #f1f7ff; }
#user-records .data-table tbody tr:last-child td { border-bottom: 0; }
#user-records .action-buttons { align-items: center; flex-wrap: nowrap; gap: 6px; white-space: nowrap; }
#user-records .action-buttons .btn { align-items: center; border: 1px solid transparent; border-radius: 7px; display: inline-flex; font-size: 11px; font-weight: 700; justify-content: center; min-height: 30px; padding: 5px 8px; }
.user-modal-box { border: 1px solid #e4e9f2; border-top: 5px solid #2563eb; border-radius: 14px; padding: clamp(22px, 3vw, 30px); }
.user-modal-box h2 { color: #17233d; font-size: 22px; font-weight: 750; }
.user-modal-box > p { line-height: 1.5; }
.user-modal-box .form-group label { color: #26354d; font-size: 13px; font-weight: 650; margin-bottom: 8px; }
.user-modal-box .form-group input:not([type="hidden"]), .user-modal-box .form-group select { border: 1px solid #d4dce8; border-radius: 9px; font: inherit; font-size: 14px; min-height: 46px; padding: 11px 13px; }
.user-modal-box .form-group input:focus, .user-modal-box .form-group select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, .15); outline: 0; }
.user-modal-box .modal-actions { border-top: 1px solid #e3eaf3; margin-top: 20px; padding-top: 18px; }

@media (max-width: 768px) {
    #user-records { padding: 20px 16px; }
    #user-records .search-form { width: 100%; }
    #user-records .search-form input { flex: 1 1 100%; min-width: 0; width: 100%; }
    #user-records .search-form .btn { flex: 1 1 110px; }
    .user-modal-box { padding: 24px 20px; }
}

@media (max-width: 480px) {
    #user-records { padding: 18px 14px; }
    #user-records .section-header h2 { font-size: 20px; }
    .user-modal-overlay { padding: 12px; }
    .user-modal-box { padding: 22px 17px; }
    .user-modal-box .modal-actions { align-items: stretch; flex-direction: column-reverse; }
    .user-modal-box .modal-actions .btn { width: 100%; }
}

/* ==========================================================
   USER MANAGEMENT HEADER SPACING
========================================================== */

.page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px 24px;
    margin-bottom: 28px;
}

.page-header > div {
    flex: 1 1 320px;
    min-width: 0;
}

.page-header h1 {
    margin: 0 0 6px;
    line-height: 1.25;
}

.page-header p {
    margin: 0;
    line-height: 1.6;
}

.page-header > .btn {
    flex: 0 0 auto;
    margin-top: 4px;
}

@media (max-width: 600px) {
    .page-header {
        align-items: stretch;
        gap: 14px;
        margin-bottom: 24px;
    }

    .page-header > .btn {
        align-self: flex-start;
    }
}

</style>


<script>

/* ==========================================================
   ADD USER
========================================================== */

function openAddUserModal() {

    document.getElementById("userModalTitle").textContent =
        "Add User";

    document.getElementById("userModalSubtitle").textContent =
        "Create a new system user account.";

    document.getElementById("user_id").value = "0";

    document.getElementById("user_username").value = "";

    document.getElementById("user_password").value = "";

    document.getElementById("user_role").value =
        "Administrator";

    document.getElementById("user_status").value =
        "Active";

    document.getElementById("user_password").required = true;

    document.getElementById("userModal").style.display =
        "flex";
}


/* ==========================================================
   EDIT USER
========================================================== */

function openEditUserModal(user) {

    document.getElementById("userModalTitle").textContent =
        "Edit User";

    document.getElementById("userModalSubtitle").textContent =
        "Update the selected user account.";

    document.getElementById("user_id").value =
        user.id;

    document.getElementById("user_username").value =
        user.username;

    document.getElementById("user_password").value =
        "";

    document.getElementById("user_role").value =
        user.role;

    document.getElementById("user_status").value =
        user.status;

    document.getElementById("user_password").required =
        false;

    document.getElementById("userModal").style.display =
        "flex";
}


/* ==========================================================
   CLOSE USER MODAL
========================================================== */

function closeUserModal() {

    document.getElementById("userModal").style.display =
        "none";
}


/* ==========================================================
   PASSWORD MODAL
========================================================== */


function openPasswordModal(user) {
    document.getElementById("password_user_id").value = user.id;
    document.getElementById("passwordUsername").textContent = user.username;

    document.getElementById("old_password").value = "";
    document.getElementById("new_password").value = "";
    document.getElementById("confirm_password").value = "";

    document.getElementById("passwordModal").style.display = "flex";

    document.getElementById("old_password").focus();
}

function closePasswordModal() {
    document.getElementById("passwordModal").style.display = "none";

    document.getElementById("old_password").value = "";
    document.getElementById("new_password").value = "";
    document.getElementById("confirm_password").value = "";
}


function closeCrudPopup() {
    const popup = document.getElementById("crudPopup");

    if (popup) {
        popup.style.display = "none";
        popup.remove();
    }
}




setTimeout(function () {

    closeCrudPopup();

}, 3000);


/* ==========================================================
   DELETE POPUP
========================================================== */

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

        const deleteInput = document.createElement("input");

        deleteInput.type = "hidden";
        deleteInput.name = "delete_user";
        deleteInput.value = "1";

        deleteForm.appendChild(deleteInput);

        deleteForm.submit();
    }

}


/* ==========================================================
   CLOSE MODALS WHEN CLICKING OUTSIDE
========================================================== */

window.addEventListener("click", function(event) {

    const userModal =
        document.getElementById("userModal");

    const passwordModal =
        document.getElementById("passwordModal");

    if (event.target === userModal) {
        closeUserModal();
    }

    if (event.target === passwordModal) {
        closePasswordModal();
    }

});

</script>


<?php

$stmt->close();

include "../includes/footer.php";

?>
