<?php
require_once '../includes/functions.php';
requireAdmin();

// Handle CSV export
if (isset($_GET['export']) && in_array($_GET['export'], ['donors', 'hospitals', 'requests'])) {
    $export = $_GET['export'];
    
    if ($export === 'donors') {
        $stmt = $pdo->query("
            SELECT dp.full_name, dp.phone, dp.blood_type, dp.city, dp.state, dp.is_available, u.email, u.is_active
            FROM donor_profiles dp JOIN users u ON dp.user_id = u.id
            ORDER BY dp.full_name
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        exportToCsv(
            ['Name', 'Phone', 'Blood Type', 'City', 'State', 'Available', 'Email', 'Active'],
            $rows,
            'donors_export_' . date('Y-m-d') . '.csv'
        );
    } elseif ($export === 'hospitals') {
        $stmt = $pdo->query("
            SELECT hp.hospital_name, hp.phone, hp.city, hp.state, hp.license_number, u.email, u.is_active
            FROM hospital_profiles hp JOIN users u ON hp.user_id = u.id
            ORDER BY hp.hospital_name
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        exportToCsv(
            ['Hospital Name', 'Phone', 'City', 'State', 'License', 'Email', 'Active'],
            $rows,
            'hospitals_export_' . date('Y-m-d') . '.csv'
        );
    } elseif ($export === 'requests') {
        $stmt = $pdo->query("
            SELECT br.id, IFNULL(hp.hospital_name, 'Emergency SOS'), br.patient_blood_type, br.units_needed, br.urgency, br.status, br.city, br.state, br.required_date, br.created_at
            FROM blood_requests br LEFT JOIN hospital_profiles hp ON br.hospital_id = hp.user_id
            ORDER BY br.created_at DESC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        exportToCsv(
            ['ID', 'Hospital', 'Blood Type', 'Units', 'Urgency', 'Status', 'City', 'State', 'Required Date', 'Created'],
            $rows,
            'requests_export_' . date('Y-m-d') . '.csv'
        );
    }
}

// Pagination for audit logs
$pagination = getPaginationParams(25);
$page = $pagination['page'];
$perPage = $pagination['per_page'];
$offset = $pagination['offset'];

// Filter by action type
$actionFilter = $_GET['action'] ?? '';
$dateFilter = $_GET['date'] ?? '';

$countSql = "SELECT COUNT(*) FROM audit_logs WHERE 1=1";
$logSql = "SELECT al.*, u.email as user_email FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id WHERE 1=1";
$filterParams = [];

if ($actionFilter) {
    $countSql .= " AND al.action = ?";
    $logSql .= " AND al.action = ?";
    $filterParams[] = $actionFilter;
}
if ($dateFilter) {
    $countSql .= " AND DATE(al.created_at) = ?";
    $logSql .= " AND DATE(al.created_at) = ?";
    $filterParams[] = $dateFilter;
}

// Get total count
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($filterParams);
$totalCount = $countStmt->fetchColumn();
$totalPages = (int)ceil($totalCount / $perPage);

// Get paginated logs
$logSql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
$logStmt = $pdo->prepare($logSql);
$logStmt->execute(array_merge($filterParams, [$perPage, $offset]));
$logs = $logStmt->fetchAll();

// Get action types for filter
$actions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

// Get recent stats
$statsToday = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = CURRENT_DATE")->fetchColumn();
$statsLogins = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'login' AND DATE(created_at) = CURRENT_DATE")->fetchColumn();
$statsFailed = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'login_failed' AND DATE(created_at) = CURRENT_DATE")->fetchColumn();
$statsDeletes = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'delete'")->fetchColumn();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1>Activity & Audit Logs</h1>
        <a href="<?php echo baseUrl(); ?>/admin/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    
    <!-- Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin: 20px 0;">
        <div style="background: #f0fdf4; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #15803d;"><?php echo $statsToday; ?></div>
            <div style="color: #6b7280; font-size: 0.85rem;">Today's Events</div>
        </div>
        <div style="background: #eff6ff; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #1d4ed8;"><?php echo $statsLogins; ?></div>
            <div style="color: #6b7280; font-size: 0.85rem;">Logins Today</div>
        </div>
        <div style="background: #fef2f2; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #991b1b;"><?php echo $statsFailed; ?></div>
            <div style="color: #6b7280; font-size: 0.85rem;">Failed Logins</div>
        </div>
        <div style="background: #fefce8; padding: 15px; border-radius: 8px; text-align: center;">
            <div style="font-size: 24px; font-weight: bold; color: #92400e;"><?php echo $statsDeletes; ?></div>
            <div style="color: #6b7280; font-size: 0.85rem;">Total Deletes</div>
        </div>
    </div>
    
    <!-- Filters -->
    <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; align-items: end; margin-bottom: 20px;">
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
            <label for="action">Action</label>
            <select id="action" name="action">
                <option value="">All Actions</option>
                <?php foreach ($actions as $a): ?>
                    <option value="<?php echo htmlspecialchars($a); ?>" <?php echo $actionFilter === $a ? 'selected' : ''; ?>><?php echo htmlspecialchars($a); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 150px;">
            <label for="date">Date</label>
            <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($dateFilter); ?>">
        </div>
        <button type="submit" class="btn btn-secondary">Filter</button>
        <a href="<?php echo baseUrl(); ?>/admin/activity.php" class="btn btn-secondary">Clear</a>
    </form>
</div>

<!-- Export Buttons -->
<div class="card" style="margin-bottom: 20px;">
    <h3>Export Data (CSV)</h3>
    <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
        <a href="<?php echo baseUrl(); ?>/admin/activity.php?export=donors" class="btn btn-secondary">Export Donors</a>
        <a href="<?php echo baseUrl(); ?>/admin/activity.php?export=hospitals" class="btn btn-secondary">Export Hospitals</a>
        <a href="<?php echo baseUrl(); ?>/admin/activity.php?export=requests" class="btn btn-secondary">Export Requests</a>
    </div>
</div>

<!-- Audit Log Table -->
<div class="card">
    <h2>Audit Log (<?php echo $totalCount; ?> entries)</h2>
    <?php if (count($logs) > 0): ?>
        <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Entity</th>
                    <th>ID</th>
                    <th>Details</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td style="white-space: nowrap; font-size: 0.85rem;"><?php echo htmlspecialchars($log['created_at']); ?></td>
                    <td><?php echo $log['user_email'] ? htmlspecialchars($log['user_email']) : '<span style="color:#6b7280;">System</span>'; ?></td>
                    <td>
                        <?php
                        $actionColors = [
                            'login' => '#1d4ed8', 'login_failed' => '#991b1b', 'delete' => '#991b1b',
                            'create' => '#15803d', 'update' => '#92400e', 'password_reset' => '#7c3aed'
                        ];
                        $color = $actionColors[$log['action']] ?? '#6b7280';
                        ?>
                        <span style="color: <?php echo $color; ?>; font-weight: 600;"><?php echo htmlspecialchars($log['action']); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($log['entity_type']); ?></td>
                    <td><?php echo $log['entity_id'] ?? '-'; ?></td>
                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; font-size: 0.85rem;">
                        <?php
                        if ($log['old_values']) {
                            echo '<span style="color:#991b1b;" title="' . htmlspecialchars($log['old_values']) . '">Old: ' . htmlspecialchars(substr($log['old_values'], 0, 60)) . '</span>';
                        }
                        if ($log['new_values']) {
                            echo '<span style="color:#15803d;" title="' . htmlspecialchars($log['new_values']) . '"> New: ' . htmlspecialchars(substr($log['new_values'], 0, 60)) . '</span>';
                        }
                        ?>
                    </td>
                    <td style="font-size: 0.85rem;"><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        
        <?php echo renderPagination($page, $totalPages, $perPage, baseUrl() . '/admin/activity.php' . ($actionFilter ? '?action=' . urlencode($actionFilter) : '') . ($dateFilter ? ($actionFilter ? '&' : '?') . 'date=' . urlencode($dateFilter) : '')); ?>
    <?php else: ?>
        <p>No audit log entries found.</p>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
