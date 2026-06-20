<?php
require_once __DIR__ . '/functions.php';
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="LifeLine Blood Network - Connect blood donors with hospitals in emergencies. Find compatible donors, create urgent requests, save lives.">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?>LifeLine Blood Network</title>
    <link rel="stylesheet" href="<?php echo baseUrl(); ?>/assets/css/style.css">
    <!-- jQuery: self-hosted (mandated interactivity layer; no CDN for CSP/SRI hygiene) -->
    <script src="<?php echo baseUrl(); ?>/assets/vendor/jquery-3.7.1.min.js"></script>
</head>
<body>
<header>
    <div class="container nav-container">
        <a href="<?php echo baseUrl(); ?>/index.php" class="logo">
            <span class="logo-icon">&#9764;</span>
            LifeLine
        </a>
        <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        <nav class="nav-links">
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
        </nav>
    </div>
</header>
<main class="container<?php echo basename($_SERVER['PHP_SELF']) === 'messages.php' ? ' container-wide' : ''; ?>">
    <?php if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>">
            <?php echo $flash['message']; // Message can contain HTML like <br> ?>
        </div>
    <?php endif; ?>
