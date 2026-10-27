
<?php
// Shared session security configuration.
// I-include kini sa dili pa magsugod ang session.

if (session_status() === PHP_SESSION_NONE) {

    // Proteksyon batok sa session fixation ug URL-based sessions.
    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");
    ini_set("session.use_trans_sid", "0");

    // HTTPS mahimong true sa live hosting ug false sa local HTTP.
    $isHttps = (
        isset($_SERVER["HTTPS"]) &&
        $_SERVER["HTTPS"] !== "" &&
        strtolower((string) $_SERVER["HTTPS"]) !== "off"
    );

    // Parehas nga cookie settings sa tanang PHP pages.
    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "domain" => "",
        "secure" => $isHttps,
        "httponly" => true,
        "samesite" => "Lax"
    ]);

    session_start();
}
