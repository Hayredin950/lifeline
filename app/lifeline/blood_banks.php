<?php
require_once 'includes/functions.php';
$pageTitle = 'Blood Banks';

$name = trim($_GET['name'] ?? '');
$city = trim($_GET['city'] ?? '');
$state = trim($_GET['state'] ?? '');

$sql = "SELECT * FROM blood_banks WHERE 1=1";
$params = [];
if ($name) {
    $sql .= " AND name LIKE ?";
    $params[] = "%$name%";
}
if ($city) {
    $sql .= " AND city LIKE ?";
    $params[] = "%$city%";
}
if ($state) {
    $sql .= " AND state LIKE ?";
    $params[] = "%$state%";
}
$sql .= " ORDER BY state, city, name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$banks = $stmt->fetchAll();

include 'includes/header.php';
?>

<section class="hero" style="padding: 48px 32px;">
    <h1>&#127973; Blood Banks Directory</h1>
    <p>Find blood banks near you. Contact them directly for blood availability and donation appointments.</p>
</section>

<div class="card">
    <form method="GET" class="flex flex-wrap gap-2 items-center" style="margin-bottom: 0;">
        <div class="form-group" style="flex:1; min-width:180px; margin-bottom:0;">
            <label for="name">Bank Name</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" placeholder="e.g. Red Cross">
        </div>
        <div class="form-group" style="flex:1; min-width:180px; margin-bottom:0;">
            <label for="city">City</label>
            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($city); ?>" placeholder="e.g. Mumbai">
        </div>
        <div class="form-group" style="flex:1; min-width:180px; margin-bottom:0;">
            <label for="state">State</label>
            <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($state); ?>" placeholder="e.g. Maharashtra">
        </div>
        <div style="margin-top: 24px;">
            <button type="submit" class="btn">Search</button>
            <a href="<?php echo baseUrl(); ?>/blood_banks.php" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</div>

<div class="section-divider"><h2><?php echo count($banks); ?> Blood Bank<?php echo count($banks) !== 1 ? 's' : ''; ?> Found</h2></div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 20px;">
    <?php foreach ($banks as $bank): ?>
    <div class="blood-bank-card">
        <h3><?php echo htmlspecialchars($bank['name']); ?></h3>
        <div class="bank-detail"><span class="icon">&#128205;</span> <?php echo htmlspecialchars($bank['address']); ?></div>
        <div class="bank-detail"><span class="icon">&#127969;</span> <?php echo htmlspecialchars($bank['city'] . ', ' . $bank['state']); ?></div>
        <div class="bank-detail"><span class="icon">&#128222;</span> <?php echo htmlspecialchars($bank['phone']); ?></div>
        <?php if ($bank['email']): ?>
            <div class="bank-detail"><span class="icon">&#9993;</span> <?php echo htmlspecialchars($bank['email']); ?></div>
        <?php endif; ?>
        <div class="bank-detail"><span class="icon">&#128336;</span> <?php echo htmlspecialchars($bank['working_hours']); ?></div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($banks)): ?>
    <div class="card text-center">
        <p>No blood banks found matching your search. Try a different city or state.</p>
    </div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
