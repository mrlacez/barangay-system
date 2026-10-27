
<?php
$current_view = $_GET['view'] ?? 'home';
$current_page = basename($_SERVER['PHP_SELF'] ?? '');

$navbar_barangay_name = 'BONGDO';

if (
    isset($public_barangay_name) &&
    trim((string) $public_barangay_name) !== ''
) {
    $navbar_barangay_name = $public_barangay_name;
}

$is_home = (
    $current_page === 'index.php' &&
    $current_view === 'home'
);

$is_services = (
    $current_page === 'index.php' &&
    $current_view === 'services'
);

$is_announcements = in_array(
    $current_page,
    ['announcements_public.php', 'announcement_details.php'],
    true
);

$is_about = ($current_page === 'about.php');

?>

<header class="main-header">
    <div class="main-nav">

        <!-- BRAND -->
        <div class="brand">
            <div class="brand-logo">
                <i class="fa-solid fa-landmark"></i>
            </div>

            <div class="brand-text">
                <h1>
                    <?= htmlspecialchars(
                        strtoupper($navbar_barangay_name),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </h1>

                <span>Barangay Management System</span>
            </div>
        </div>

        <!-- NAVIGATION -->
        <nav class="main-nav-links" aria-label="Main navigation">

            <a href="index.php?view=home"
               class="nav-item <?= $is_home ? 'active' : '' ?>">
                <span class="nav-icon">
                    <i class="fa-solid fa-house"></i>
                </span>
                <span>Home</span>
            </a>

            <a href="index.php?view=services"
               class="nav-item <?= $is_services ? 'active' : '' ?>">
                <span class="nav-icon services-icon">
                    <svg viewBox="0 0 18 18"
                         fill="currentColor"
                         xmlns="http://www.w3.org/2000/svg">
                        <rect x="1" y="1" width="6" height="6" rx="1"/>
                        <rect x="11" y="1" width="6" height="6" rx="1"/>
                        <rect x="1" y="11" width="6" height="6" rx="1"/>
                        <rect x="11" y="11" width="6" height="6" rx="1"/>
                    </svg>
                </span>
                <span>Services</span>
            </a>

            <a href="announcements_public.php"
               class="nav-item announcement-nav <?= $is_announcements ? 'active' : '' ?>">
                <span class="nav-icon">
                    <i class="fa-solid fa-bullhorn"></i>
                </span>
                <span>Announcements</span>
                <span class="notification-dot"></span>
            </a>

            <a href="about.php"
               class="nav-item <?= $is_about ? 'active' : '' ?>">
                <span class="nav-icon">
                    <i class="fa-solid fa-circle-info"></i>
                </span>
                <span>About</span>
            </a>

        </nav>

        <!-- RIGHT SIDE -->
        <div class="nav-right">

            <div class="nav-search-wrap">
                <label class="nav-search" for="serviceSearch">
                    <span>⌕</span>
                    <input
                        type="search"
                        id="serviceSearch"
                        placeholder="Search services..."
                        autocomplete="off"
                        aria-label="Search barangay services"
                        aria-controls="serviceSearchResults"
                        aria-expanded="false"
                    >
                    <button
                        type="button"
                        id="clearServiceSearch"
                        class="clear-service-search"
                        aria-label="Clear search"
                        hidden
                    >×</button>
                </label>

                <div
                    id="serviceSearchResults"
                    class="service-search-results"
                    role="listbox"
                    hidden
                ></div>
            </div>



        </div>

    </div>
</header>
