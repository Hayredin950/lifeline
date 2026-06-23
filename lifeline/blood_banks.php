<?php
require_once 'includes/functions.php';
requireAuth();
$pageTitle = 'Hospitals';

$name  = trim($_GET['name']  ?? '');
$city  = trim($_GET['city']  ?? '');
$state = trim($_GET['state'] ?? '');

$sql = "
    SELECT hp.user_id, hp.hospital_name, hp.phone, hp.address, hp.city, hp.state,
           hp.is_verified, hp.verification_status
    FROM hospital_profiles hp
    JOIN users u ON u.id = hp.user_id AND u.is_active = 1
    WHERE 1=1
";
$params = [];
if ($name)  { $sql .= " AND hp.hospital_name LIKE ?"; $params[] = "%$name%"; }
if ($city)  { $sql .= " AND hp.city LIKE ?";          $params[] = "%$city%"; }
if ($state) { $sql .= " AND hp.state LIKE ?";          $params[] = "%$state%"; }
$sql .= " ORDER BY hp.is_verified DESC, hp.state, hp.city, hp.hospital_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$hospitals = $stmt->fetchAll();

$extraHead = <<<'CSS'
<style>
.hosp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-top: 24px;
}
.hosp-card {
    background: var(--card-bg, #1a1a2e);
    border: 1px solid rgba(255,255,255,.08);
    border-radius: 16px;
    padding: 24px;
    display: flex;
    flex-direction: column;
    gap: 0;
    transition: border-color .2s, transform .2s, box-shadow .2s;
    position: relative;
    overflow: hidden;
}
.hosp-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, #b91c1c, #ef4444);
    opacity: 0;
    transition: opacity .2s;
}
.hosp-card:hover { border-color: rgba(185,28,28,.35); transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,.3); }
.hosp-card:hover::before { opacity: 1; }
.hosp-card-name { font-size: 1rem; font-weight: 700; color: #e2e8f0; margin-bottom: 14px; line-height: 1.35; }
.hosp-meta { display: flex; flex-direction: column; gap: 8px; flex: 1; }
.hosp-row { display: flex; align-items: flex-start; gap: 10px; font-size: .88rem; color: rgba(255,255,255,.6); }
.hosp-row-icon { width: 18px; text-align: center; flex-shrink: 0; margin-top: 1px; color: rgba(185,28,28,.8); }
.hosp-row a { color: rgba(255,255,255,.7); text-decoration: none; }
.hosp-row a:hover { color: #ef4444; }
.hosp-verified {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: .72rem; font-weight: 700;
    padding: 3px 10px;
    border-radius: 999px;
    background: rgba(16,185,129,.12);
    border: 1px solid rgba(16,185,129,.25);
    color: #10b981;
    margin-bottom: 14px;
}
.hosp-actions { display: flex; gap: 8px; margin-top: 18px; padding-top: 14px; border-top: 1px solid rgba(255,255,255,.06); }
.hosp-btn-call {
    flex: 1; display: flex; align-items: center; justify-content: center;
    gap: 6px;
    padding: 9px 14px;
    border-radius: 8px;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.1);
    color: rgba(255,255,255,.75);
    font-size: .85rem; font-weight: 600;
    text-decoration: none;
    transition: background .15s, border-color .15s;
}
.hosp-btn-call:hover { background: rgba(255,255,255,.1); border-color: rgba(255,255,255,.2); color: #fff; }
.hosp-btn-msg {
    flex: 1; display: flex; align-items: center; justify-content: center;
    gap: 6px;
    padding: 9px 14px;
    border-radius: 8px;
    background: rgba(185,28,28,.15);
    border: 1px solid rgba(185,28,28,.35);
    color: #ef4444;
    font-size: .85rem; font-weight: 600;
    text-decoration: none;
    transition: background .15s, border-color .15s;
}
.hosp-btn-msg:hover { background: rgba(185,28,28,.25); border-color: rgba(185,28,28,.55); color: #fca5a5; }
</style>
CSS;

include 'includes/header.php';
?>

<section class="hero pad-card-lg" style="margin-bottom: 24px;">
    <h1>Hospital Directory</h1>
    <p>Find registered hospitals and connect with them directly through LifeLine.</p>
</section>

<div class="card">
    <form method="GET" class="flex flex-wrap gap-16 items-center mb-0">
        <div class="form-group flex-1 minw-180 mb-0">
            <label for="name">Hospital Name</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" placeholder="e.g. Tikur Anbesa">
        </div>
        <div class="form-group flex-1 minw-180 mb-0">
            <label for="city">City</label>
            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($city); ?>" placeholder="e.g. Addis Ababa">
        </div>
        <div class="form-group flex-1 minw-180 mb-0">
            <label for="state">State / Region</label>
            <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($state); ?>" placeholder="e.g. Oromia">
        </div>
        <div class="mt-24">
            <button type="submit" class="btn">Search</button>
            <a href="<?php echo baseUrl(); ?>/blood_banks.php" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</div>

<div class="section-divider">
    <h2><?php echo count($hospitals); ?> Hospital<?php echo count($hospitals) !== 1 ? 's' : ''; ?> Found</h2>
</div>

<?php if (empty($hospitals)): ?>
<div class="card text-center">
    <p>No hospitals found matching your search. Try a different city or region.</p>
</div>
<?php else: ?>
<div class="hosp-grid">
    <?php foreach ($hospitals as $h):
        $phone = preg_replace('/\s+/', '', $h['phone'] ?? '');
    ?>
    <div class="hosp-card">
        <?php if ($h['is_verified']): ?>
            <div class="hosp-verified">&#10003; Verified Hospital</div>
        <?php endif; ?>

        <div class="hosp-card-name"><?php echo htmlspecialchars($h['hospital_name']); ?></div>

        <div class="hosp-meta">
            <?php if ($h['address']): ?>
            <div class="hosp-row">
                <span class="hosp-row-icon">&#128205;</span>
                <span><?php echo htmlspecialchars($h['address']); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($h['city'] || $h['state']): ?>
            <div class="hosp-row">
                <span class="hosp-row-icon">&#127969;</span>
                <span><?php echo htmlspecialchars(implode(', ', array_filter([$h['city'], $h['state']]))); ?></span>
            </div>
            <?php endif; ?>

            <?php if ($h['phone']): ?>
            <div class="hosp-row">
                <span class="hosp-row-icon">&#128222;</span>
                <a href="tel:<?php echo htmlspecialchars($phone); ?>"><?php echo htmlspecialchars($h['phone']); ?></a>
            </div>
            <?php endif; ?>
        </div>

        <div class="hosp-actions">
            <?php if ($h['phone']): ?>
            <a href="tel:<?php echo htmlspecialchars($phone); ?>" class="hosp-btn-call">
                &#128222; Call
            </a>
            <?php endif; ?>

            <a href="<?php echo baseUrl(); ?>/messages.php?conversation=<?php echo (int)$h['user_id']; ?>" class="hosp-btn-msg">
                &#128172; Message
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
