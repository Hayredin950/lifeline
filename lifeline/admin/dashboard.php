<?php
require_once '../includes/functions.php';
requireAdmin();

$donorCount = $pdo->query("SELECT COUNT(*) FROM donor_profiles")->fetchColumn();
$hospitalCount = $pdo->query("SELECT COUNT(*) FROM hospital_profiles")->fetchColumn();
$requestOpen = $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'open'")->fetchColumn();
$requestCritical        = $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'open' AND urgency = 'critical'")->fetchColumn();
$pendingTestimonials    = $pdo->query("SELECT COUNT(*) FROM testimonials WHERE is_approved = 0")->fetchColumn();

include '../includes/header.php';
?>

<div class="card">
    <h1>Administrator Dashboard</h1>
    <p>Manage donors, hospitals, blood requests, and system records.</p>
</div>

<div class="dashboard-grid">
    <div class="stat-card">
        <h3><?php echo (int)$donorCount; ?></h3>
        <p>Total Donors</p>
    </div>
    <div class="stat-card">
        <h3><?php echo (int)$hospitalCount; ?></h3>
        <p>Total Hospitals</p>
    </div>
    <div class="stat-card">
        <h3><?php echo (int)$requestOpen; ?></h3>
        <p>Open Requests</p>
    </div>
    <div class="stat-card">
        <h3><?php echo (int)$requestCritical; ?></h3>
        <p>Critical Requests</p>
    </div>
    <div class="stat-card">
        <h3><?php echo (int)$pendingTestimonials; ?></h3>
        <p>Pending Testimonials</p>
    </div>
</div>

<div class="grid-autofit-220">
    <a href="<?php echo baseUrl(); ?>/admin/manage_donors.php" class="card no-underline text-inherit">
        <h2>Manage Donors</h2>
        <p>View, edit, and remove donor profiles from the system.</p>
        <span class="btn mt-10">Open</span>
    </a>
    <a href="<?php echo baseUrl(); ?>/admin/manage_hospitals.php" class="card no-underline text-inherit">
        <h2>Manage Hospitals</h2>
        <p>View, edit, and remove hospital profiles from the system.</p>
        <span class="btn mt-10">Open</span>
    </a>
    <a href="<?php echo baseUrl(); ?>/admin/manage_requests.php" class="card no-underline text-inherit">
        <h2>Manage Requests</h2>
        <p>Oversee all blood requests and delete incorrect entries.</p>
        <span class="btn mt-10">Open</span>
    </a>
    <a href="<?php echo baseUrl(); ?>/admin/activity.php" class="card no-underline text-inherit">
        <h2>Activity & Audit Logs</h2>
        <p>View system activity, login history, and export data to CSV.</p>
        <span class="btn mt-10">Open</span>
    </a>
    <a href="<?php echo baseUrl(); ?>/admin/testimonials.php" class="card no-underline text-inherit">
        <h2>Testimonials</h2>
        <p>Review and approve donor stories before they are published.</p>
        <span class="btn mt-10">Open</span>
    </a>
</div>

<?php include '../includes/footer.php'; ?>
