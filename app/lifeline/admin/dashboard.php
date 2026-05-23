<?php
require_once '../includes/functions.php';
requireAdmin();

$donorCount = $pdo->query("SELECT COUNT(*) FROM donor_profiles")->fetchColumn();
$hospitalCount = $pdo->query("SELECT COUNT(*) FROM hospital_profiles")->fetchColumn();
$requestOpen = $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'open'")->fetchColumn();
$requestCritical = $pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'open' AND urgency = 'critical'")->fetchColumn();

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
</div>

<div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
    <a href="<?php echo baseUrl(); ?>/admin/manage_donors.php" class="card" style="text-decoration:none; color:inherit;">
        <h2>Manage Donors</h2>
        <p>View, edit, and remove donor profiles from the system.</p>
        <span class="btn" style="margin-top:10px;">Open</span>
    </a>
    <a href="<?php echo baseUrl(); ?>/admin/manage_hospitals.php" class="card" style="text-decoration:none; color:inherit;">
        <h2>Manage Hospitals</h2>
        <p>View, edit, and remove hospital profiles from the system.</p>
        <span class="btn" style="margin-top:10px;">Open</span>
    </a>
    <a href="<?php echo baseUrl(); ?>/admin/manage_requests.php" class="card" style="text-decoration:none; color:inherit;">
        <h2>Manage Requests</h2>
        <p>Oversee all blood requests and delete incorrect entries.</p>
        <span class="btn" style="margin-top:10px;">Open</span>
    </a>
    <a href="<?php echo baseUrl(); ?>/admin/activity.php" class="card" style="text-decoration:none; color:inherit;">
        <h2>Activity & Audit Logs</h2>
        <p>View system activity, login history, and export data to CSV.</p>
        <span class="btn" style="margin-top:10px;">Open</span>
    </a>
</div>

<?php include '../includes/footer.php'; ?>
