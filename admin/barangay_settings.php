
<?php

require_once __DIR__ . "/../config/admin_auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../config/audit.php";


/* =========================
   NO CACHE
========================= */

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");

/* CSRF TOKEN */
if (empty($_SESSION["settings_csrf_token"])) {
    $_SESSION["settings_csrf_token"] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION["settings_csrf_token"];

$success = "";
$error = "";

/* GET CURRENT SETTINGS */
$result = $conn->query("
    SELECT
        id,
        barangay_name,
        municipality_city,
        province,
        punong_barangay,
        barangay_secretary,
        contact_number,
        address,
        barangay_logo
        FROM barangay_settings
    ORDER BY id ASC
    LIMIT 1
");

if ($result && $result->num_rows > 0) {

    $settings = $result->fetch_assoc();

} else {

    /* DEFAULT EMPTY VALUES */
    $settings = [
        "id" => 1,
        "barangay_name" => "",
        "municipality_city" => "",
        "province" => "",
        "punong_barangay" => "",
        "barangay_secretary" => "",
        "contact_number" => "",
        "address" => "",
        "barangay_logo" => ""
    ];
}


/* UPDATE SETTINGS */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $posted_token = $_POST["csrf_token"] ?? "";

    if (
        !hash_equals(
            $_SESSION["settings_csrf_token"],
            $posted_token
        )
    ) {

        $error = "Invalid security token. Please try again.";

    } else {

        $barangay_name = trim($_POST["barangay_name"] ?? "");
        $municipality_city = trim($_POST["municipality_city"] ?? "");
        $province = trim($_POST["province"] ?? "");
        $punong_barangay = trim($_POST["punong_barangay"] ?? "");
        $barangay_secretary = trim($_POST["barangay_secretary"] ?? "");
        $contact_number = trim($_POST["contact_number"] ?? "");
        $address = trim($_POST["address"] ?? "");
        $barangay_logo = $settings["barangay_logo"] ?? "";

        if (
            isset($_FILES["barangay_logo"]) &&
            $_FILES["barangay_logo"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {
            $file = $_FILES["barangay_logo"];

            if ($file["error"] !== UPLOAD_ERR_OK) {
                $error = "Logo upload failed. Please try again.";

            } elseif (
                !is_uploaded_file($file["tmp_name"]) ||
                $file["size"] <= 0 ||
                $file["size"] > 2 * 1024 * 1024
            ) {
                $error = "Logo must be a valid image not exceeding 2 MB.";

            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file["tmp_name"]);

                $allowed_types = [
                    "image/jpeg",
                    "image/png",
                    "image/webp"
                ];

                $image_info = @getimagesize($file["tmp_name"]);

                if (
                    !in_array($mime, $allowed_types, true) ||
                    $image_info === false ||
                    $image_info["mime"] !== $mime
                ) {
                    $error = "Logo must be a valid JPG, PNG, or WEBP image.";

                } elseif (
                    $image_info[0] < 1 ||
                    $image_info[1] < 1 ||
                    $image_info[0] > 5000 ||
                    $image_info[1] > 5000 ||
                    ($image_info[0] * $image_info[1]) > 12500000
                ) {
                    $error = "Logo dimensions are too large.";

                } else {
                    // Decode the actual image, not just its filename.
                    $image_data = @file_get_contents($file["tmp_name"]);
                    $image = ($image_data !== false)
                        ? @imagecreatefromstring($image_data)
                        : false;

                    unset($image_data);

                    if ($image === false) {
                        $error = "The uploaded file is not a valid image.";

                    } else {
                        $upload_dir = __DIR__
                            . "/../assets/uploads/barangay/";

                        if (
                            !is_dir($upload_dir) &&
                            !@mkdir($upload_dir, 0755, true) &&
                            !is_dir($upload_dir)
                        ) {
                            $error = "Unable to prepare the logo upload directory.";

                        } else {
                            // Save a newly encoded PNG with a random filename.
                            $new_filename = bin2hex(random_bytes(16)) . ".png";
                            $target_path = $upload_dir . $new_filename;

                            imagesavealpha($image, true);

                            if (@imagepng($image, $target_path, 6)) {
                                $barangay_logo =
                                    "assets/uploads/barangay/" . $new_filename;
                            } else {
                                $error = "Failed to save the barangay logo.";
                            }
                        }

                        imagedestroy($image);
                    }
                }
            }
        }

        /* VALIDATION */

        function cleanText($value)
        {
            return trim(preg_replace('/\s+/', ' ', $value));
        }

        $barangay_name       = cleanText($barangay_name);
        $municipality_city   = cleanText($municipality_city);
        $province            = cleanText($province);
        $punong_barangay     = cleanText($punong_barangay);
        $barangay_secretary  = cleanText($barangay_secretary);
        $contact_number      = trim($contact_number);
        $address             = cleanText($address);


        /* REQUIRED FIELDS */

        if ($barangay_name === "") {

            $error = "Barangay name is required.";

        } elseif ($municipality_city === "") {

            $error = "Municipality/City is required.";

        } elseif ($province === "") {

            $error = "Province is required.";

        } elseif ($punong_barangay === "") {

            $error = "Punong Barangay name is required.";


        /* NAME / PLACE VALIDATION */

        } elseif (!preg_match("/^[\p{L}\p{M} .,'-]+$/u", $barangay_name)) {

            $error = "Barangay name contains invalid characters.";

        } elseif (!preg_match("/^[\p{L}\p{M} .,'-]+$/u", $municipality_city)) {

            $error = "Municipality/City contains invalid characters.";

        } elseif (!preg_match("/^[\p{L}\p{M} .,'-]+$/u", $province)) {

            $error = "Province contains invalid characters.";

        } elseif (!preg_match("/^[\p{L}\p{M} .,'-]+$/u", $punong_barangay)) {

            $error = "Punong Barangay name contains invalid characters.";

        } elseif (
            $barangay_secretary !== "" &&
            !preg_match("/^[\p{L}\p{M} .,'-]+$/u", $barangay_secretary)
        ) {

            $error = "Barangay Secretary name contains invalid characters.";


        /* LENGTH VALIDATION */

        } elseif (mb_strlen($barangay_name) > 150) {

            $error = "Barangay name must not exceed 150 characters.";

        } elseif (mb_strlen($municipality_city) > 150) {

            $error = "Municipality/City must not exceed 150 characters.";

        } elseif (mb_strlen($province) > 150) {

            $error = "Province must not exceed 150 characters.";

        } elseif (mb_strlen($punong_barangay) > 150) {

            $error = "Punong Barangay name must not exceed 150 characters.";

        } elseif (mb_strlen($barangay_secretary) > 150) {

            $error = "Barangay Secretary name must not exceed 150 characters.";

        } elseif (mb_strlen($address) > 255) {

            $error = "Barangay address must not exceed 255 characters.";


        /* CONTACT NUMBER */

        } elseif (
            $contact_number !== "" &&
            !preg_match("/^(09\d{9}|\+639\d{9})$/", $contact_number)
        ) {

            $error = "Contact number must be a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX).";

        } else {

            /*
             * CHECK IF A SETTINGS RECORD EXISTS
             */

            $check = $conn->query("
                SELECT id
                FROM barangay_settings
                ORDER BY id ASC
                LIMIT 1
            ");

            if ($check && $check->num_rows > 0) {

                $existing = $check->fetch_assoc();
                $settings_id = (int)$existing["id"];

                $stmt = $conn->prepare("
                    UPDATE barangay_settings
                    SET
                        barangay_name = ?,
                        municipality_city = ?,
                        province = ?,
                        punong_barangay = ?,
                        barangay_secretary = ?,
                        contact_number = ?,
                        address = ?,
                        barangay_logo = ?
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "ssssssssi",
                    $barangay_name,
                    $municipality_city,
                    $province,
                    $punong_barangay,
                    $barangay_secretary,
                    $contact_number,
                    $address,
                    $barangay_logo,
                    $settings_id
                );

            } else {

                $stmt = $conn->prepare("
                    INSERT INTO barangay_settings
                    (
                        barangay_name,
                        municipality_city,
                        province,
                        punong_barangay,
                        barangay_secretary,
                        contact_number,
                        address,
                        barangay_logo
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "ssssssss",
                    $barangay_name,
                    $municipality_city,
                    $province,
                    $punong_barangay,
                    $barangay_secretary,
                    $contact_number,
                    $address,
                    $barangay_logo
                );
            }

            if ($stmt->execute()) {

                addAuditLog(
                    $conn,
                    "UPDATE",
                    "Barangay Settings",
                    "Updated barangay information and system settings.",
                    $settings_id ?? $stmt->insert_id
                );

                $success = "Barangay information successfully updated.";

                $settings["barangay_name"] = $barangay_name;
                $settings["municipality_city"] = $municipality_city;
                $settings["province"] = $province;
                $settings["punong_barangay"] = $punong_barangay;
                $settings["barangay_secretary"] = $barangay_secretary;
                $settings["contact_number"] = $contact_number;
                $settings["address"] = $address;
                $settings["barangay_logo"] = $barangay_logo;

            } else {

                $error = "Unable to update barangay information.";
            }

            $stmt->close();
        }
    }
}

$displayRole = ($current_role === "admin")
    ? "Administrator"
    : $current_role;

?>

<?php include "../includes/header.php"; ?>

<style>
/* Improve spacing between the page title, subtitle, and settings card. */
.page-header {
    display: flex;
    flex-direction: column;
    gap: 7px;
    margin: 0 0 24px;
}

.page-header h1 {
    margin: 0;
    line-height: 1.2;
}

.page-header p {
    margin: 0;
    line-height: 1.55;
    color: #475569;
}

.barangay-settings-card {
    background: #fff;
    border: 1px solid #e4e9f2;
    border-top: 5px solid #2563eb;
    border-radius: 14px;
    box-shadow: 0 5px 20px rgba(15, 23, 42, .07);
    margin-bottom: 26px;
    padding: clamp(23px, 3vw, 35px);
}
.barangay-settings-card .section-header { margin-bottom: 28px; }
.barangay-settings-card .section-header h2 { color: #17233d; font-size: clamp(1.25rem, 2vw, 1.65rem); font-weight: 750; letter-spacing: -.025em; margin: 0 0 6px; }
.barangay-settings-card .section-header p { color: #64748b; line-height: 1.55; margin: 0; }
#barangaySettingsForm { align-items: start; background: transparent; border: 0; display: grid; gap: 18px; grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 0; }
#barangaySettingsForm .form-group { margin: 0; min-width: 0; }
#barangaySettingsForm .form-group-full { grid-column: 1 / -1; }
#barangaySettingsForm label { color: #26354d; display: block; font-size: .9rem; font-weight: 650; margin: 0 0 8px; }
#barangaySettingsForm input:not([type="hidden"]):not([type="file"]),
#barangaySettingsForm textarea { background: #fff; border: 1px solid #d4dce8; border-radius: 9px; box-sizing: border-box; color: #182337; display: block; font: inherit; font-size: 14px; line-height: 1.4; min-height: 46px; min-width: 0; padding: 11px 13px; transition: border-color .18s ease, box-shadow .18s ease; width: 100%; }
#barangaySettingsForm input[type="file"] { background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 9px; box-sizing: border-box; color: #475569; min-height: 46px; padding: 10px; width: 100%; }
#barangaySettingsForm textarea { min-height: 110px; resize: vertical; }
#barangaySettingsForm input:focus, #barangaySettingsForm textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, .15); outline: 0; }
#barangaySettingsForm .form-group small { color: #64748b; display: block; font-size: 12px; line-height: 1.45; margin-top: 7px; }
#barangaySettingsForm .form-actions { align-items: center; border-top: 1px solid #e3eaf3; display: flex; grid-column: 1 / -1; justify-content: flex-end; margin-top: 2px; padding-top: 20px; }
#barangaySettingsForm .form-actions .btn { align-items: center; border-radius: 9px; display: inline-flex; justify-content: center; min-height: 44px; padding: 10px 20px; }
@media (max-width: 900px) {
    #barangaySettingsForm { grid-template-columns: minmax(0, 1fr); gap: 17px; }
    #barangaySettingsForm .form-group-full, #barangaySettingsForm .form-actions { grid-column: 1; }
}
@media (max-width: 480px) {
    .barangay-settings-card { padding: 20px 16px; }
    #barangaySettingsForm .form-actions .btn { width: 100%; }
}
</style>

<div class="page-header">

    <div>
        <h1>Barangay Settings</h1>
        <p>Manage the official information used by the Barangay Management System.</p>
    </div>

</div>


<div class="content-card barangay-settings-card">

    <div class="section-header">

        <div>
            <h2>Barangay Information</h2>
            <p>Update the official barangay details below.</p>
        </div>

    </div>


    <form
        method="POST"
        class="form-grid"
        id="barangaySettingsForm"
        enctype="multipart/form-data"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars($csrf_token) ?>"
        >


        <div class="form-group">

            <label for="barangay_name">
                Barangay Name
            </label>

            <input
                type="text"
                id="barangay_name"
                name="barangay_name"
                value="<?= htmlspecialchars($settings["barangay_name"]) ?>"
                maxlength="150"
                pattern="[A-Za-zÀ-ÿÑñ .,'-]+"
                title="Letters, spaces, period, comma, apostrophe and hyphen only."
                autocomplete="organization"
                required
            >

        </div>


        <div class="form-group">

            <label for="municipality_city">
                Municipality / City
            </label>

            <input
                type="text"
                id="municipality_city"
                name="municipality_city"
                value="<?= htmlspecialchars($settings["municipality_city"]) ?>"
                maxlength="150"
                pattern="[A-Za-zÀ-ÿÑñ .,'-]+"
                title="Letters, spaces, period, comma, apostrophe and hyphen only."
                required
            >

        </div>


        <div class="form-group">

            <label for="province">
                Province
            </label>

            <input
                type="text"
                id="province"
                name="province"
                value="<?= htmlspecialchars($settings["province"]) ?>"
                maxlength="150"
                pattern="[A-Za-zÀ-ÿÑñ .,'-]+"
                title="Letters, spaces, period, comma, apostrophe and hyphen only."
                required
            >

        </div>


        <div class="form-group">

            <label for="punong_barangay">
                Punong Barangay
            </label>

            <input
                type="text"
                id="punong_barangay"
                name="punong_barangay"
                value="<?= htmlspecialchars($settings["punong_barangay"]) ?>"
                maxlength="150"
                pattern="[A-Za-zÀ-ÿÑñ .,'-]+"
                title="Please enter a valid name."
                autocomplete="name"
                required
            >

        </div>


        <div class="form-group">

            <label for="barangay_secretary">
                Barangay Secretary
            </label>

            <input
                type="text"
                id="barangay_secretary"
                name="barangay_secretary"
                value="<?= htmlspecialchars($settings["barangay_secretary"] ?? "") ?>"
                maxlength="150"
                pattern="[A-Za-zÀ-ÿÑñ .,'-]+"
                title="Please enter a valid name."
                autocomplete="name"
            >

        </div>


        <div class="form-group">

            <label for="contact_number">
                Contact Number
            </label>

            <input
                type="tel"
                id="contact_number"
                name="contact_number"
                value="<?= htmlspecialchars($settings["contact_number"] ?? "") ?>"
                maxlength="13"
                inputmode="tel"
                pattern="(09[0-9]{9}|\+639[0-9]{9})"
                placeholder="09XXXXXXXXX"
                title="Enter a valid Philippine mobile number: 09XXXXXXXXX or +639XXXXXXXXX."
                autocomplete="tel"
            >

        </div>


        <div class="form-group form-group-full">

            <label for="address">
                Barangay Address
            </label>

            <textarea
                id="address"
                name="address"
                rows="3"
                maxlength="255"
                minlength="5"
                placeholder="Enter complete barangay address"
            ><?= htmlspecialchars($settings["address"] ?? "") ?></textarea>

        </div>

        <div class="form-group form-group-full">

            <label for="barangay_logo">
                Barangay Seal / Logo
            </label>

            <input
                type="file"
                id="barangay_logo"
                name="barangay_logo"
                accept="image/jpeg,image/png,image/webp"
            >

            <small>
                Accepted: JPG, PNG, WEBP. Maximum 2 MB.
            </small>

            <?php if (!empty($settings["barangay_logo"])): ?>

                <div style="margin-top:15px;">
                    <img
                        src="../<?= htmlspecialchars($settings["barangay_logo"]) ?>"
                        alt="Barangay Logo"
                        style="
                            width:100px;
                            height:100px;
                            object-fit:contain;
                            border:1px solid #ddd;
                            border-radius:8px;
                            padding:5px;
                        "
                    >
                </div>

            <?php endif; ?>

        </div>


        <div class="form-actions form-group-full">

            <button
                type="submit"
                class="btn btn-primary"
            >
                💾 Save Changes
            </button>

        </div>

    </form>

</div>


<?php if ($success !== ""): ?>

<div class="crud-popup-overlay" id="successPopup">

    <div class="crud-popup-box success">

        <div class="crud-popup-icon">
            ✓
        </div>

        <h3>Success!</h3>

        <p>
            <?= htmlspecialchars($success) ?>
        </p>

        <button
            type="button"
            class="crud-popup-button"
            onclick="closeSuccessPopup()">
            OK
        </button>

    </div>

</div>

<?php endif; ?>


<?php if ($error !== ""): ?>

<div class="crud-popup-overlay" id="errorPopup">

    <div class="crud-popup-box error">

        <div class="crud-popup-icon">
            !
        </div>

        <h3>Error!</h3>

        <p>
            <?= htmlspecialchars($error) ?>
        </p>

        <button
            type="button"
            class="crud-popup-button"
            onclick="closeErrorPopup()">
            OK
        </button>

    </div>

</div>

<?php endif; ?>


<script>

function closeSuccessPopup() {

    const popup = document.getElementById("successPopup");

    if (popup) {
        popup.style.display = "none";
    }

}


function closeErrorPopup() {

    const popup = document.getElementById("errorPopup");

    if (popup) {
        popup.style.display = "none";
    }

}


setTimeout(function () {

    closeSuccessPopup();
    closeErrorPopup();

}, 3500);



document.addEventListener("DOMContentLoaded", function () {

    const nameFields = [
        "barangay_name",
        "municipality_city",
        "province",
        "punong_barangay",
        "barangay_secretary"
    ];

    nameFields.forEach(function (id) {

        const field = document.getElementById(id);

        if (!field) return;

        field.addEventListener("input", function () {

            this.value = this.value.replace(
                /[^\p{L}\p{M} .,'-]/gu,
                ""
            );

        });

        field.addEventListener("blur", function () {

            this.value = this.value
                .replace(/\s+/g, " ")
                .trim();

        });

    });


    const contact = document.getElementById("contact_number");

    if (contact) {

        contact.addEventListener("input", function () {

            this.value = this.value.replace(/[^0-9+]/g, "");

            if (this.value.startsWith("+")) {
                this.value =
                    "+" + this.value.substring(1).replace(/\D/g, "");
            } else {
                this.value = this.value.replace(/\D/g, "");
            }

        });

    }


    const logo = document.getElementById("barangay_logo");

    if (logo) {

        logo.addEventListener("change", function () {

            const file = this.files[0];

            if (!file) return;

            const allowedTypes = [
                "image/jpeg",
                "image/png",
                "image/webp"
            ];

            if (!allowedTypes.includes(file.type)) {

                alert("Logo must be JPG, PNG, or WEBP.");
                this.value = "";
                return;
            }

            if (file.size > 2 * 1024 * 1024) {

                alert("Logo file must not exceed 2 MB.");
                this.value = "";
            }

        });

    }

});


</script>


<?php include "../includes/footer.php"; ?>
