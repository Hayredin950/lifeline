<?php
require_once __DIR__ . '/functions.php';
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($_SESSION['locale'] ?? 'en'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="LifeLine Blood Network - Connect blood donors with hospitals in emergencies. Find compatible donors, create urgent requests, save lives.">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>LifeLine Blood Network</title>
    <link rel="stylesheet" href="<?php echo assetUrl('assets/css/style.css'); ?>">
    <!-- jQuery: self-hosted (mandated interactivity layer; no CDN for CSP/SRI hygiene) -->
    <script src="<?php echo assetUrl('assets/vendor/jquery-3.7.1.min.js'); ?>"></script>
    <!-- PWA -->
    <link rel="manifest" href="<?php echo baseUrl(); ?>/manifest.json">
    <meta name="theme-color" content="#b91c1c">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="LifeLine">
</head>
<body>
<!-- Skip navigation link (WCAG 2.4.1) -->
<a href="#main-content" class="skip-link">Skip to main content</a>

<header role="banner">
    <div class="container nav-container">
        <a href="<?php echo baseUrl(); ?>/index.php" class="logo" aria-label="LifeLine Blood Network — home">
            <span class="logo-icon" aria-hidden="true">&#9764;</span>
            LifeLine
        </a>
        <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="primary-nav">
            <span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>
        </button>
        <nav class="nav-links" id="primary-nav" aria-label="Primary navigation">
            <a href="<?php echo baseUrl(); ?>/index.php" class="<?php echo isActivePage('index.php') ? 'active' : ''; ?>">Home</a>
            <a href="<?php echo baseUrl(); ?>/find_donors.php" class="<?php echo isActivePage('find_donors.php') ? 'active' : ''; ?>">Find Donors</a>
            <a href="<?php echo baseUrl(); ?>/blood_banks.php" class="<?php echo isActivePage('blood_banks.php') ? 'active' : ''; ?>">Blood Banks</a>
            <a href="<?php echo baseUrl(); ?>/eligibility.php" class="<?php echo isActivePage('eligibility.php') ? 'active' : ''; ?>">Eligibility</a>
            <a href="<?php echo baseUrl(); ?>/emergency.php" class="<?php echo isActivePage('emergency.php') ? 'active' : ''; ?>">Emergency SOS</a>
            <a href="<?php echo baseUrl(); ?>/leaderboard.php" class="<?php echo isActivePage('leaderboard.php') ? 'active' : ''; ?>">Leaderboard</a>
            <a href="<?php echo baseUrl(); ?>/testimonials.php" class="<?php echo isActivePage('testimonials.php') ? 'active' : ''; ?>">Stories</a>
            <?php if (isLoggedIn()): ?>
                <?php 
                    $unreadCount = getUnreadMessageCount($pdo, $_SESSION['user_id']); 
                ?>
                <a href="<?php echo baseUrl(); ?>/messages.php" class="<?php echo isActivePage('messages.php') ? 'active' : ''; ?>">
                    Messages 
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge-unread"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </a>
                <?php if (isAdmin()): ?>
                    <a href="<?php echo baseUrl(); ?>/admin/dashboard.php" class="<?php echo isActivePage('admin') ? 'active' : ''; ?>">Admin</a>
                <?php elseif (isDonor()): ?>
                    <a href="<?php echo baseUrl(); ?>/donor/dashboard.php" class="<?php echo isActivePage('donor') ? 'active' : ''; ?>">Dashboard</a>
                <?php elseif (isHospital()): ?>
                    <a href="<?php echo baseUrl(); ?>/hospital/dashboard.php" class="<?php echo isActivePage('hospital') ? 'active' : ''; ?>">Dashboard</a>
                <?php endif; ?>
                <a href="<?php echo baseUrl(); ?>/logout.php">Logout</a>
            <?php else: ?>
                <a href="<?php echo baseUrl(); ?>/login.php">Login</a>
                <a href="<?php echo baseUrl(); ?>/register.php">Register</a>
            <?php endif; ?>
            <!-- Language switcher -->
            <span class="lang-switcher fs-85">
                <?php $curLocale = $_SESSION['locale'] ?? 'en'; ?>
                <?php if ($curLocale !== 'en'): ?>
                <a href="<?php echo htmlspecialchars(localeSwitchUrl('en')); ?>" aria-label="Switch to English">EN</a>
                <?php else: ?>
                <strong aria-current="true">EN</strong>
                <?php endif; ?>
                &nbsp;|&nbsp;
                <?php if ($curLocale !== 'am'): ?>
                <a href="<?php echo htmlspecialchars(localeSwitchUrl('am')); ?>" lang="am" aria-label="አማርኛ ቋንቋ ምረጥ">አማ</a>
                <?php else: ?>
                <strong lang="am" aria-current="true">አማ</strong>
                <?php endif; ?>
            </span>
        </nav>
    </div>
</header>
<main id="main-content" class="container<?php echo basename($_SERVER['PHP_SELF']) === 'messages.php' ? ' container-wide' : ''; ?>">
    <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>" role="alert" aria-live="assertive">
            <?php echo $flash['message']; // Message can contain HTML like <br> ?>
        </div>
    <?php endif; ?>
