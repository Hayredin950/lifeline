<?php
$pageTitle = 'Find Blood Donors';
require_once 'includes/functions.php';

$bloodType = $_GET['blood_type'] ?? '';
$city      = trim($_GET['city'] ?? '');
$state     = trim($_GET['state'] ?? '');
$radius    = isset($_GET['radius']) ? max(1, (int)$_GET['radius']) : 50;

$searchLat = null;
$searchLng = null;
$geoSearch = false;

$results  = [];
$hasSearch = isset($_GET['search']) || ($bloodType || $city || $state);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $hasSearch) {
    // Try to geocode the search location for distance ranking (FR-20).
    // Falls back to city-string filter when Nominatim is unreachable.
    $searchCountry = trim($_GET['country'] ?? 'Ethiopia');
    if ($city !== '' || $state !== '') {
        $coords = geocodeLocation($city, $state, $searchCountry ?: 'Ethiopia');
        if ($coords) {
            $searchLat = $coords['latitude'];
            $searchLng = $coords['longitude'];
            $geoSearch = true;
        }
    }

    if ($geoSearch) {
        // Distance-ranked query: donors with coords sorted by km from search point;
        // coordless donors appended last so geocoding gaps never hide a willing donor.
        $sql = "
            SELECT dp.*, u.email,
                   ST_Distance_Sphere(dp.geo, POINT(?, ?)) / 1000 AS distance_km
            FROM donor_profiles dp
            JOIN users u ON dp.user_id = u.id
            WHERE u.is_active = true
              AND dp.latitude IS NOT NULL
        ";
        $params = [$searchLng, $searchLat];
    } else {
        $sql    = "SELECT dp.*, u.email, NULL AS distance_km FROM donor_profiles dp JOIN users u ON dp.user_id = u.id WHERE u.is_active = true";
        $params = [];
    }

    $showAll = isset($_GET['show_all']) && $_GET['show_all'] == '1';
    if (!$showAll) {
        $sql .= " AND (dp.last_donation_date IS NULL OR dp.last_donation_date <= DATE_SUB(CURDATE(), INTERVAL ? DAY))";
        $params[] = DONATION_COOLOFF_DAYS;
        $sql .= " AND dp.is_available = true";
    }

    if ($bloodType) {
        $sql .= " AND dp.blood_type = ?";
        $params[] = $bloodType;
    }

    if ($geoSearch) {
        // Radius pre-filter (HAVING because distance_km is a computed alias).
        $sql .= " HAVING distance_km <= ?";
        $params[] = $radius;
        $sql .= " ORDER BY distance_km ASC";
    } else {
        // No geo: fall back to city/state text filter + name sort.
        if ($city) {
            $sql .= " AND dp.city LIKE ?";
            $params[] = '%' . $city . '%';
        }
        if ($state) {
            $sql .= " AND dp.state LIKE ?";
            $params[] = '%' . $state . '%';
        }
        $sql .= " ORDER BY dp.full_name";
    }

    $pdoR = getReadPdo();
    $stmt = $pdoR->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();

    // Append coordless donors at the end when doing a geo search (never hide them).
    if ($geoSearch) {
        $fallbackSql = "
            SELECT dp.*, u.email, NULL AS distance_km
            FROM donor_profiles dp
            JOIN users u ON dp.user_id = u.id
            WHERE u.is_active = true AND u.deleted_at IS NULL AND dp.latitude IS NULL
        ";
        $fbParams = [];
        if (!$showAll) {
            $fallbackSql .= " AND (dp.last_donation_date IS NULL OR dp.last_donation_date <= DATE_SUB(CURDATE(), INTERVAL ? DAY)) AND dp.is_available = true";
            $fbParams[] = DONATION_COOLOFF_DAYS;
        }
        if ($bloodType) {
            $fallbackSql .= " AND dp.blood_type = ?";
            $fbParams[] = $bloodType;
        }
        $fallbackSql .= " ORDER BY dp.full_name";
        $fbStmt = $pdoR->prepare($fallbackSql);
        $fbStmt->execute($fbParams);
        $results = array_merge($results, $fbStmt->fetchAll());
    }
}

include 'includes/header.php';
?>

<div class="card">
    <h1><?php echo t('donors.title'); ?></h1>
    <form method="GET" class="flex flex-wrap gap-16 items-center mt-20">
        <div class="form-group flex-1 minw-180 mb-0">
            <label for="blood_type">Blood Type</label>
            <select id="blood_type" name="blood_type">
                <option value="">Any</option>
                <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'] as $bt): ?>
                    <option value="<?php echo $bt; ?>" <?php echo $bloodType === $bt ? 'selected' : ''; ?>><?php echo $bt; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group flex-1 minw-180 mb-0">
            <label for="city">City</label>
            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($city); ?>" placeholder="e.g. Addis Ababa">
        </div>
        <div class="form-group flex-1 minw-180 mb-0">
            <label for="state">State</label>
            <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($state); ?>" placeholder="e.g. Oromia">
        </div>
        <div class="form-group flex-1 minw-120 mb-0">
            <label for="radius">Radius (km)</label>
            <select id="radius" name="radius">
                <?php foreach ([10, 25, 50, 100, 200] as $r): ?>
                    <option value="<?php echo $r; ?>" <?php echo $radius === $r ? 'selected' : ''; ?>><?php echo $r; ?> km</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group flex-05 minw-120 mb-0 pt-25">
            <label class="flex items-center gap-8 cursor-pointer">
                <input type="checkbox" name="show_all" value="1" <?php echo isset($_GET['show_all']) && $_GET['show_all'] == '1' ? 'checked' : ''; ?>>
                Show Unavailable
            </label>
        </div>
        <div class="form-group flex-05 minw-120 mb-0 pt-25">
            <input type="hidden" name="search" value="1">
            <button type="submit" class="btn btn-primary w-full"><?php echo t('donors.search_btn'); ?></button>
        </div>
    </form>
</div>

<?php if ($hasSearch): ?>
<div class="card">
    <h2>Search Results (<?php echo count($results); ?> found<?php echo $geoSearch ? ', sorted by distance' : ''; ?>)</h2>
    <?php if (count($results) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Donor</th>
                        <th>Blood Type</th>
                        <th>Location</th>
                        <?php if ($geoSearch): ?><th>Distance</th><?php endif; ?>
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
                            <div class="flex items-center gap-10">
                                <img src="<?php echo getProfilePic($d); ?>" alt="Avatar" class="avatar-img-sm">
                                <?php echo htmlspecialchars($d['full_name']); ?>
                            </div>
                        </td>
                        <td><strong><?php echo htmlspecialchars($d['blood_type']); ?></strong></td>
                        <td><?php echo htmlspecialchars(($d['city'] ? $d['city'] . ', ' : '') . $d['state']); ?></td>
                        <?php if ($geoSearch): ?>
                        <td class="fs-85 text-muted">
                            <?php echo $d['distance_km'] !== null ? round($d['distance_km'], 0) . ' km' : '—'; ?>
                        </td>
                        <?php endif; ?>
                        <td>
                            <?php
                            $statusClass = 'text-muted';
                            if ($statusInfo['status'] === 'available') $statusClass = 'text-success-dark';
                            elseif ($statusInfo['status'] === 'busy') $statusClass = 'text-amber';
                            elseif ($statusInfo['status'] === 'cool_off') $statusClass = 'text-crimson';
                            ?>
                            <span class="fw-600 <?php echo $statusClass; ?>" title="<?php echo htmlspecialchars($statusInfo['label']); ?>">
                                <?php echo ucfirst($statusInfo['status'] === 'cool_off' ? 'Cool-off' : $statusInfo['status']); ?>
                            </span>
                            <?php if ($statusInfo['status'] === 'cool_off'): ?>
                                <div class="fs-75 text-muted">Avail: <?php echo $statusInfo['available_on']; ?></div>
                            <?php endif; ?>
                        </td>
                        <?php if (isLoggedIn()): ?>
                            <td>
                                <?php if ($statusInfo['status'] === 'available' || isAdmin()): ?>
                                    <?php echo htmlspecialchars($d['phone']); ?>
                                <?php else: ?>
                                    <span class="text-muted fs-85">Private</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($statusInfo['status'] === 'available' || isAdmin()): ?>
                                    <a href="<?php echo baseUrl(); ?>/messages.php?conversation=<?php echo $d['user_id']; ?>" class="btn btn-small">Message</a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!isLoggedIn()): ?>
            <p class="mt-16 fs-90 text-muted"><em>Login to view donor contact details.</em></p>
        <?php endif; ?>
    <?php else: ?>
        <p><?php echo t('donors.no_results'); ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
