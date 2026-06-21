<?php
require_once 'includes/functions.php';
requireAuth();
$pageTitle = 'Blood Banks';

$name  = trim($_GET['name']  ?? '');
$city  = trim($_GET['city']  ?? '');
$state = trim($_GET['state'] ?? '');

$sql = "SELECT * FROM blood_banks WHERE 1=1";
$params = [];
if ($name)  { $sql .= " AND name LIKE ?";  $params[] = "%$name%"; }
if ($city)  { $sql .= " AND city LIKE ?";  $params[] = "%$city%"; }
if ($state) { $sql .= " AND state LIKE ?"; $params[] = "%$state%"; }
$sql .= " ORDER BY state, city, name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$banks = $stmt->fetchAll();

// Load registered hospitals for name-based matching so donors can message them
$hospitals = $pdo->query("SELECT user_id, hospital_name FROM hospital_profiles")->fetchAll();

/**
 * Try to find a registered hospital user for a blood bank by keyword overlap.
 * Returns user_id or null.
 */
function matchBankToHospital(string $bankName, array $hospitals): ?int {
    $bankLow = strtolower($bankName);
    foreach ($hospitals as $h) {
        $words = array_filter(explode(' ', strtolower($h['hospital_name'])), fn($w) => strlen($w) >= 4);
        foreach (array_slice(array_values($words), 0, 3) as $word) {
            if (str_contains($bankLow, $word)) {
                return (int)$h['user_id'];
            }
        }
    }
    return null;
}

$extraHead = <<<'CSS'
<style>
.bb-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-top: 24px;
}
.bb-card {
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
.bb-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, #b91c1c, #ef4444);
    opacity: 0;
    transition: opacity .2s;
}
.bb-card:hover { border-color: rgba(185,28,28,.35); transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,.3); }
.bb-card:hover::before { opacity: 1; }

.bb-card-name { font-size: 1rem; font-weight: 700; color: #e2e8f0; margin-bottom: 14px; line-height: 1.35; }
.bb-meta { display: flex; flex-direction: column; gap: 8px; flex: 1; }
.bb-row { display: flex; align-items: flex-start; gap: 10px; font-size: .88rem; color: rgba(255,255,255,.6); }
.bb-row-icon { width: 18px; text-align: center; flex-shrink: 0; margin-top: 1px; color: rgba(185,28,28,.8); }
.bb-row a { color: rgba(255,255,255,.7); text-decoration: none; }
.bb-row a:hover { color: #ef4444; }
.bb-24h {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: .72rem; font-weight: 700;
    padding: 3px 10px;
    border-radius: 999px;
    background: rgba(16,185,129,.12);
    border: 1px solid rgba(16,185,129,.25);
    color: #10b981;
    margin-bottom: 14px;
}
.bb-actions { display: flex; gap: 8px; margin-top: 18px; padding-top: 14px; border-top: 1px solid rgba(255,255,255,.06); }
.bb-btn-call {
    flex: 1; display: flex; align-items: center; justify-content: center; gap-6px;
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
.bb-btn-call:hover { background: rgba(255,255,255,.1); border-color: rgba(255,255,255,.2); color: #fff; }
.bb-btn-msg {
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
.bb-btn-msg:hover { background: rgba(185,28,28,.25); border-color: rgba(185,28,28,.55); color: #fca5a5; }
</style>
CSS;

include 'includes/header.php';
?>

<section class="hero pad-card-lg" style="margin-bottom: 24px;">
    <h1>Blood Banks Directory</h1>
    <p>Find blood banks near you. Call directly or message registered hospitals through LifeLine.</p>
</section>

<div class="card">
    <form method="GET" class="flex flex-wrap gap-16 items-center mb-0">
        <div class="form-group flex-1 minw-180 mb-0">
            <label for="name">Bank Name</label>
            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" placeholder="e.g. Red Cross">
        </div>
        <div class="form-group flex-1 minw-180 mb-0">
            <label for="city">City</label>
            <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($city); ?>" placeholder="e.g. Addis Ababa">
        </div>
        <div class="form-group flex-1 minw-180 mb-0">
            <label for="state">State</label>
            <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($state); ?>" placeholder="e.g. Oromia">
        </div>
        <div class="mt-24">
            <button type="submit" class="btn">Search</button>
            <a href="<?php echo baseUrl(); ?>/blood_banks.php" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</div>

<div class="section-divider">
    <h2><?php echo count($banks); ?> Blood Bank<?php echo count($banks) !== 1 ? 's' : ''; ?> Found</h2>
</div>

<?php if (empty($banks)): ?>
<div class="card text-center">
    <p>No blood banks found matching your search. Try a different city or state.</p>
</div>
<?php else: ?>
<div class="bb-grid">
    <?php foreach ($banks as $bank):
        $hospitalUserId = matchBankToHospital($bank['name'], $hospitals);
        $phone = preg_replace('/\s+/', '', $bank['phone'] ?? '');
    ?>
    <div class="bb-card">
        <?php if ($bank['has_24h_service']): ?>
            <div class="bb-24h">&#9679; 24 / 7 Service</div>
        <?php endif; ?>

        <div class="bb-card-name"><?php echo htmlspecialchars($bank['name']); ?></div>

        <div class="bb-meta">
            <?php if ($bank['address']): ?>
            <div class="bb-row">
                <span class="bb-row-icon">&#128205;</span>
                <span><?php echo htmlspecialchars($bank['address']); ?></span>
            </div>
            <?php endif; ?>

            <div class="bb-row">
                <span class="bb-row-icon">&#127969;</span>
                <span><?php echo htmlspecialchars($bank['city'] . ', ' . $bank['state']); ?></span>
            </div>

            <?php if ($bank['phone']): ?>
            <div class="bb-row">
                <span class="bb-row-icon">&#128222;</span>
                <a href="tel:<?php echo htmlspecialchars($phone); ?>"><?php echo htmlspecialchars($bank['phone']); ?></a>
            </div>
            <?php endif; ?>

            <?php if ($bank['email']): ?>
            <div class="bb-row">
                <span class="bb-row-icon">&#9993;</span>
                <a href="mailto:<?php echo htmlspecialchars($bank['email']); ?>"><?php echo htmlspecialchars($bank['email']); ?></a>
            </div>
            <?php endif; ?>

            <?php if ($bank['working_hours']): ?>
            <div class="bb-row">
                <span class="bb-row-icon">&#128336;</span>
                <span><?php echo htmlspecialchars($bank['working_hours']); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="bb-actions">
            <?php if ($bank['phone']): ?>
            <a href="tel:<?php echo htmlspecialchars($phone); ?>" class="bb-btn-call">
                &#128222; Call
            </a>
            <?php endif; ?>

            <?php if ($hospitalUserId): ?>
            <a href="<?php echo baseUrl(); ?>/messages.php?conversation=<?php echo $hospitalUserId; ?>" class="bb-btn-msg">
                &#128172; Message
            </a>
            <?php elseif ($bank['email']): ?>
            <a href="mailto:<?php echo htmlspecialchars($bank['email']); ?>" class="bb-btn-call">
                &#9993; Email
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
