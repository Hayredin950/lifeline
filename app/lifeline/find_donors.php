<?php
$pageTitle = 'Find Blood Donors';
require_once 'includes/functions.php';

$bloodType = $_GET['blood_type'] ?? '';
$city = trim($_GET['city'] ?? '');
$state = trim($_GET['state'] ?? '');
$radius = isset($_GET['radius']) ? (int)$_GET['radius'] : 50;

$searchLat = null;
$searchLng = null;

$results = [];
$hasSearch = isset($_GET['search']) || ($bloodType || $city || $state);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $hasSearch) {
    $sql = "SELECT dp.*, u.email FROM donor_profiles dp JOIN users u ON dp.user_id = u.id WHERE u.is_active = true";
    $params = [];
    
    // Eligibility filter: Don't show donors in cool-off unless specifically requested
    $showAll = isset($_GET['show_all']) && $_GET['show_all'] == '1';
    if (!$showAll) {
        $sql .= " AND (dp.last_donation_date IS NULL OR dp.last_donation_date <= DATE_SUB(CURDATE(), INTERVAL 90 DAY))";
        $sql .= " AND dp.is_available = true";
    }

    if ($bloodType) {
        $sql .= " AND dp.blood_type = ?";
        $params[] = $bloodType;
    }
    if ($city) {
        $sql .= " AND dp.city LIKE ?";
        $params[] = "%" . $city . "%";
    }
    if ($state) {
        $sql .= " AND dp.state LIKE ?";
        $params[] = "%" . $state . "%";
    }

    $sql .= " ORDER BY dp.full_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
}

include 'includes/header.php';
?>

<div class="card">
    <h1>Find Blood Donors</h1>
    <form method="GET" class="flex flex-wrap gap-2 items-center" style="margin-top: 20px;">
        <div class="form-group" style="flex:1; min-width:180px; margin-bottom:0;">
            <label for="blood_type">Blood Type</label>
            <select id="blood_type" name="blood_type">
                <option value="">Any</option>
                <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bt): ?>
                    <option value="<?php echo $bt; ?>" <?php echo $bloodType === $bt ? 'selected' : ''; ?>><?php echo $bt; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="flex:1; min-width:180px; margin-bottom:0;">
            <label for="city">City</label>
            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($city); ?>" placeholder="e.g. Mumbai">
        </div>
        <div class="form-group" style="flex:1; min-width:180px; margin-bottom:0;">
            <label for="state">State</label>
            <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($state); ?>" placeholder="e.g. Maharashtra">
        </div>
        <div class="form-group" style="flex:0.5; min-width:120px; margin-bottom:0; padding-top: 25px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" name="show_all" value="1" <?php echo isset($_GET['show_all']) && $_GET['show_all'] == '1' ? 'checked' : ''; ?>>
                Show Unavailable
            </label>
        </div>
        <div class="form-group" style="flex:0.5; min-width:120px; margin-bottom:0; padding-top: 25px;">
            <input type="hidden" name="search" value="1">
            <button type="submit" class="btn btn-primary" style="width:100%;">Search</button>
        </div>
    </form>
</div>

<?php if ($hasSearch): ?>
<div class="card">
    <h2>Search Results (<?php echo count($results); ?> found)</h2>
    <?php if (count($results) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Donor</th>
                        <th>Blood Type</th>
                        <th>Location</th>
                        <th>Status</th>
                        <?php if (isLoggedIn()): ?>
                            <th>Phone</th>
                            <th>Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $d): 
                        $statusInfo = getDonorCurrentStatus($pdo, $d['user_id']);
                    ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <img src="<?php echo getProfilePic($d); ?>" alt="Avatar" 
                                     style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 1px solid var(--glass-border);">
                                <?php echo htmlspecialchars($d['full_name']); ?>
                            </div>
                        </td>
                        <td><strong><?php echo htmlspecialchars($d['blood_type']); ?></strong></td>
                        <td><?php echo htmlspecialchars(($d['city'] ? $d['city'] . ', ' : '') . $d['state']); ?></td>
                        <td>
                            <?php
                            $color = '#6b7280';
                            if ($statusInfo['status'] === 'available') $color = '#15803d';
                            elseif ($statusInfo['status'] === 'busy') $color = '#b45309';
                            elseif ($statusInfo['status'] === 'cool_off') $color = '#b91c1c';
                            ?>
                            <span style="color:<?php echo $color; ?>;font-weight:600;" title="<?php echo htmlspecialchars($statusInfo['label']); ?>">
                                <?php echo ucfirst($statusInfo['status'] === 'cool_off' ? 'Cool-off' : $statusInfo['status']); ?>
                            </span>
                            <?php if ($statusInfo['status'] === 'cool_off'): ?>
                                <div style="font-size:0.75rem; color:#6b7280;">Avail: <?php echo $statusInfo['available_on']; ?></div>
                            <?php endif; ?>
                        </td>
                        <?php if (isLoggedIn()): ?>
                            <td>
                                <?php if ($statusInfo['status'] === 'available' || isAdmin()): ?>
                                    <?php echo htmlspecialchars($d['phone']); ?>
                                <?php else: ?>
                                    <span style="color:#9ca3af; font-size:0.85rem;">Private</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($statusInfo['status'] === 'available' || isAdmin()): ?>
                                    <a href="<?php echo baseUrl(); ?>/messages.php?conversation=<?php echo $d['user_id']; ?>" class="btn btn-small">Message</a>
                                <?php else: ?>
                                    <span style="color:#9ca3af;">-</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!isLoggedIn()): ?>
            <p class="mt-2" style="font-size:0.9rem;color:#6b7280;"><em>Login to view donor contact details.</em></p>
        <?php endif; ?>
    <?php else: ?>
        <p>No donors found matching your criteria.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
