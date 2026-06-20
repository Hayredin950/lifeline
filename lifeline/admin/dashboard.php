<?php
require_once '../includes/functions.php';
requireAdmin();

$donorCount          = (int)$pdo->query("SELECT COUNT(*) FROM donor_profiles")->fetchColumn();
$hospitalCount       = (int)$pdo->query("SELECT COUNT(*) FROM hospital_profiles")->fetchColumn();
$requestOpen         = (int)$pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'open'")->fetchColumn();
$requestCritical     = (int)$pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status = 'open' AND urgency = 'critical'")->fetchColumn();
$pendingTestimonials = (int)$pdo->query("SELECT COUNT(*) FROM testimonials WHERE is_approved = 0")->fetchColumn();
$pendingVerify       = (int)$pdo->query("SELECT COUNT(*) FROM hospital_profiles WHERE is_verified = 0")->fetchColumn();
try {
    $pendingDsar = (int)$pdo->query("SELECT COUNT(*) FROM dsar_requests WHERE status = 'pending'")->fetchColumn();
} catch (PDOException $e) {
    $pendingDsar = 0;
}

$hour     = (int)date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$dateStr  = date('l, F j, Y');

$extraHead = '<style>
/* ── Admin Dashboard Premium Layout ──────────────────────────────────────── */
.adm-hero {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 16px;
    background: linear-gradient(135deg, var(--bg-dark-3) 0%, var(--bg-dark-2) 100%);
    border: 1px solid var(--glass-border-light);
    border-radius: var(--radius-lg);
    padding: 28px 32px; margin-bottom: 20px;
}
.adm-hero-left h1 { font-size:1.45rem; font-weight:700; margin:0 0 4px; color:var(--text-primary); }
.adm-sub { font-size:0.82rem; color:var(--text-muted); letter-spacing:0.03em; }
.adm-badges { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.adm-badge {
    display:inline-flex; align-items:center; gap:5px;
    padding:5px 13px; border-radius:var(--radius-full);
    font-size:0.74rem; font-weight:600; border:1px solid;
}
.adm-badge-alert { background:rgba(230,57,70,.1); border-color:rgba(230,57,70,.35); color:var(--crimson-light); }
.adm-badge-warn  { background:rgba(245,158,11,.1); border-color:rgba(245,158,11,.35); color:var(--warning); }
.adm-badge-ok    { background:rgba(16,185,129,.08); border-color:rgba(16,185,129,.25); color:var(--success); }

/* KPI strip */
.adm-kpi-row { display:grid; grid-template-columns:repeat(6,1fr); gap:12px; margin-bottom:28px; }
.adm-kpi {
    background:var(--bg-dark-3); border:1px solid var(--glass-border);
    border-radius:var(--radius); padding:18px 14px 16px; text-align:center;
    transition:border-color .15s;
}
.adm-kpi:hover { border-color:var(--glass-border-light); }
.adm-kpi-alert { border-top:2px solid var(--crimson) !important; }
.adm-kpi-warn  { border-top:2px solid var(--warning) !important; }
.adm-kpi .kv { font-size:1.9rem; font-weight:700; line-height:1; margin-bottom:7px; color:var(--text-primary); }
.adm-kpi-alert .kv { color:var(--crimson); }
.adm-kpi-warn  .kv { color:var(--warning); }
.adm-kpi .kl { font-size:0.66rem; text-transform:uppercase; letter-spacing:0.08em; color:var(--text-muted); font-weight:500; line-height:1.35; }

/* Sections */
.adm-section { margin-bottom:28px; }
.adm-section-head { display:flex; align-items:center; gap:10px; margin-bottom:14px; padding-bottom:12px; border-bottom:1px solid var(--glass-border); }
.adm-dot { width:6px; height:6px; border-radius:50%; background:var(--crimson); flex-shrink:0; }
.adm-section-head h2 { font-size:0.69rem; text-transform:uppercase; letter-spacing:0.12em; font-weight:700; color:var(--text-muted); margin:0; }

/* Action cards */
.adm-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(210px,1fr)); gap:14px; }
.adm-card {
    background:var(--bg-dark-3); border:1px solid var(--glass-border);
    border-radius:var(--radius); padding:20px 18px 16px;
    text-decoration:none; color:inherit;
    display:flex; flex-direction:column; gap:6px;
    position:relative; overflow:hidden;
    transition:border-color .18s, transform .18s, box-shadow .18s;
}
.adm-card::before {
    content:""; position:absolute; top:0; left:0; right:0; height:2px;
    background:transparent; transition:background .18s;
}
.adm-card:hover {
    border-color:rgba(230,57,70,.45); transform:translateY(-3px);
    box-shadow:0 10px 30px rgba(0,0,0,.35); text-decoration:none; color:inherit;
}
.adm-card:hover::before { background:var(--gradient-crimson); }
.adm-icon { font-size:1.3rem; line-height:1; margin-bottom:4px; display:block; }
.adm-card h3 { font-size:0.9rem; font-weight:600; margin:0; color:var(--text-primary); }
.adm-card p  { font-size:0.76rem; color:var(--text-muted); margin:0; line-height:1.55; flex:1; }
.adm-open    { margin-top:10px; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--crimson); }
.adm-cbadge  {
    position:absolute; top:14px; right:14px;
    background:var(--crimson); color:#fff;
    font-size:0.68rem; font-weight:700; padding:2px 7px;
    border-radius:var(--radius-full); min-width:20px; text-align:center; line-height:1.6;
}
.adm-cbadge-warn { background:var(--warning); color:#0f172a; }

@media(max-width:900px) { .adm-kpi-row { grid-template-columns:repeat(3,1fr); } }
@media(max-width:560px) { .adm-kpi-row { grid-template-columns:repeat(2,1fr); } .adm-hero { padding:20px; } }
</style>';

include '../includes/header.php';
?>

<!-- ── Hero ──────────────────────────────────────────────────────────────── -->
<div class="adm-hero">
    <div class="adm-hero-left">
        <h1><?php echo $greeting; ?>, Administrator</h1>
        <div class="adm-sub"><?php echo $dateStr; ?> &nbsp;&middot;&nbsp; LifeLine Blood Network</div>
    </div>
    <div class="adm-badges">
        <?php if ($requestCritical > 0): ?>
            <span class="adm-badge adm-badge-alert">&#9888; <?php echo $requestCritical; ?> Critical Request<?php echo $requestCritical !== 1 ? 's' : ''; ?></span>
        <?php endif; ?>
        <?php if ($pendingVerify > 0): ?>
            <span class="adm-badge adm-badge-warn">&#9677; <?php echo $pendingVerify; ?> Pending Verification<?php echo $pendingVerify !== 1 ? 's' : ''; ?></span>
        <?php endif; ?>
        <?php if ($pendingTestimonials > 0): ?>
            <span class="adm-badge adm-badge-warn">&#9997; <?php echo $pendingTestimonials; ?> Story Review<?php echo $pendingTestimonials !== 1 ? 's' : ''; ?></span>
        <?php endif; ?>
        <?php if ($pendingDsar > 0): ?>
            <span class="adm-badge adm-badge-warn">&#128196; <?php echo $pendingDsar; ?> Pending DSAR<?php echo $pendingDsar !== 1 ? 's' : ''; ?></span>
        <?php endif; ?>
        <?php if ($requestCritical === 0 && $pendingVerify === 0 && $pendingTestimonials === 0 && $pendingDsar === 0): ?>
            <span class="adm-badge adm-badge-ok">&#10003; All clear</span>
        <?php endif; ?>
    </div>
</div>

<!-- ── KPI Strip ─────────────────────────────────────────────────────────── -->
<div class="adm-kpi-row">
    <div class="adm-kpi">
        <div class="kv"><?php echo $donorCount; ?></div>
        <div class="kl">Total<br>Donors</div>
    </div>
    <div class="adm-kpi">
        <div class="kv"><?php echo $hospitalCount; ?></div>
        <div class="kl">Total<br>Hospitals</div>
    </div>
    <div class="adm-kpi">
        <div class="kv"><?php echo $requestOpen; ?></div>
        <div class="kl">Open<br>Requests</div>
    </div>
    <div class="adm-kpi <?php echo $requestCritical > 0 ? 'adm-kpi-alert' : ''; ?>">
        <div class="kv"><?php echo $requestCritical; ?></div>
        <div class="kl">Critical<br>Requests</div>
    </div>
    <div class="adm-kpi <?php echo $pendingVerify > 0 ? 'adm-kpi-warn' : ''; ?>">
        <div class="kv"><?php echo $pendingVerify; ?></div>
        <div class="kl">Pending<br>Verif.</div>
    </div>
    <div class="adm-kpi <?php echo $pendingDsar > 0 ? 'adm-kpi-warn' : ''; ?>">
        <div class="kv"><?php echo $pendingDsar; ?></div>
        <div class="kl">Pending<br>DSARs</div>
    </div>
</div>

<!-- ── User Management ───────────────────────────────────────────────────── -->
<div class="adm-section">
    <div class="adm-section-head">
        <span class="adm-dot"></span>
        <h2>User Management</h2>
    </div>
    <div class="adm-grid">
        <a href="<?php echo baseUrl(); ?>/admin/manage_donors.php" class="adm-card">
            <span class="adm-icon">&#128101;</span>
            <h3>Manage Donors</h3>
            <p>View, edit, and suspend donor profiles across the network.</p>
            <div class="adm-open">Open &rarr;</div>
        </a>
        <a href="<?php echo baseUrl(); ?>/admin/manage_hospitals.php" class="adm-card">
            <span class="adm-icon">&#127973;</span>
            <h3>Manage Hospitals</h3>
            <p>View, edit, and manage hospital accounts and contact records.</p>
            <div class="adm-open">Open &rarr;</div>
        </a>
        <a href="<?php echo baseUrl(); ?>/admin/verify_hospitals.php" class="adm-card">
            <?php if ($pendingVerify > 0): ?><span class="adm-cbadge adm-cbadge-warn"><?php echo $pendingVerify; ?></span><?php endif; ?>
            <span class="adm-icon">&#9989;</span>
            <h3>Hospital Verification</h3>
            <p>Review license submissions and grant the verified badge.</p>
            <div class="adm-open">Open &rarr;</div>
        </a>
    </div>
</div>

<!-- ── Blood Operations ──────────────────────────────────────────────────── -->
<div class="adm-section">
    <div class="adm-section-head">
        <span class="adm-dot"></span>
        <h2>Blood Operations</h2>
    </div>
    <div class="adm-grid">
        <a href="<?php echo baseUrl(); ?>/admin/manage_requests.php" class="adm-card">
            <?php if ($requestCritical > 0): ?><span class="adm-cbadge"><?php echo $requestCritical; ?> crit.</span><?php endif; ?>
            <span class="adm-icon">&#128139;</span>
            <h3>Manage Requests</h3>
            <p>Oversee all blood requests across all hospitals and urgency levels.</p>
            <div class="adm-open">Open &rarr;</div>
        </a>
        <a href="<?php echo baseUrl(); ?>/admin/transfers.php" class="adm-card">
            <span class="adm-icon">&#128666;</span>
            <h3>Inter-Facility Transfers</h3>
            <p>Network-wide cold-chain blood unit transfer oversight and status tracking.</p>
            <div class="adm-open">Open &rarr;</div>
        </a>
        <a href="<?php echo baseUrl(); ?>/admin/component_types.php" class="adm-card">
            <span class="adm-icon">&#128137;</span>
            <h3>Component Types</h3>
            <p>Manage the donation component catalogue — plasma, platelets, bone marrow, organs.</p>
            <div class="adm-open">Open &rarr;</div>
        </a>
    </div>
</div>

<!-- ── Clinical & Analytics ──────────────────────────────────────────────── -->
<div class="adm-section">
    <div class="adm-section-head">
        <span class="adm-dot"></span>
        <h2>Clinical &amp; Analytics</h2>
    </div>
    <div class="adm-grid">
        <a href="<?php echo baseUrl(); ?>/admin/clinical_trials.php" class="adm-card">
            <span class="adm-icon">&#129514;</span>
            <h3>Clinical Trials</h3>
            <p>Create and manage consented rare-blood recruitment campaigns with eligibility matching.</p>
            <div class="adm-open">Open &rarr;</div>
        </a>
        <a href="<?php echo baseUrl(); ?>/admin/forecasting.php" class="adm-card">
            <span class="adm-icon">&#128200;</span>
            <h3>Demand Forecasting</h3>
            <p>Weighted moving-average forecasts per blood type and de-identified propensity scoring.</p>
            <div class="adm-open">Open &rarr;</div>
        </a>
        <a href="<?php echo baseUrl(); ?>/admin/shortage_analytics.php" class="adm-card">
            <span class="adm-icon">&#128202;</span>
            <h3>Shortage Analytics</h3>
            <p>De-identified public-health shortage heatmap, fulfillment rates, and time-to-fill by region.</p>
            <div class="adm-open">Open &rarr;</div>
        </a>
        <a href="<?php echo baseUrl(); ?>/admin/analytics.php" class="adm-card">
            <span class="adm-icon">&#128201;</span>
            <h3>Platform Analytics</h3>
            <p>Platform-wide KPIs — registrations, fulfillment rates, blood type demand, geo distribution.</p>
            <div class="adm-open">Open &rarr;</div>
        </a>
    </div>
</div>

<!-- ── Platform & Compliance ─────────────────────────────────────────────── -->
<div class="adm-section">
    <div class="adm-section-head">
        <span class="adm-dot"></span>
        <h2>Platform &amp; Compliance</h2>
    </div>
    <div class="adm-grid">
        <a href="<?php echo baseUrl(); ?>/admin/dpo_dashboard.php" class="adm-card">
            <?php if ($pendingDsar > 0): ?><span class="adm-cbadge adm-cbadge-warn"><?php echo $pendingDsar; ?></span><?php endif; ?>
            <span class="adm-icon">&#128274;</span>
            <h3>DPO Compliance</h3>
            <p>DSAR queue, breach incidents, BAA agreements, DPIA register — DPDP/HIPAA program.</p>
            <div class="adm-open">Open &rarr;</div>
        </a>
        <a href="<?php echo baseUrl(); ?>/admin/country_config.php" class="adm-card">
            <span class="adm-icon">&#127758;</span>
            <h3>Country Configuration</h3>
            <p>Manage active countries, per-country GDPR/HIPAA flags, cooloff days, and locale defaults.</p>
            <div class="adm-open">Open &rarr;</div>
        </a>
        <a href="<?php echo baseUrl(); ?>/admin/api_keys.php" class="adm-card">
            <span class="adm-icon">&#128273;</span>
            <h3>API Keys</h3>
            <p>Issue, view, and revoke keys for the REST API /api/v1 endpoints.</p>
            <div class="adm-open">Open &rarr;</div>
        </a>
    </div>
</div>

<!-- ── System & Security ─────────────────────────────────────────────────── -->
<div class="adm-section">
    <div class="adm-section-head">
        <span class="adm-dot"></span>
        <h2>System &amp; Security</h2>
    </div>
    <div class="adm-grid">
        <a href="<?php echo baseUrl(); ?>/admin/slo_dashboard.php" class="adm-card">
            <span class="adm-icon">&#128161;</span>
            <h3>SLO Dashboard</h3>
            <p>Real-time health: queue depth, critical request age, DB latency, replica lag, worker status.</p>
            <div class="adm-open">Open &rarr;</div>
        </a>
        <a href="<?php echo baseUrl(); ?>/admin/activity.php" class="adm-card">
            <span class="adm-icon">&#128203;</span>
            <h3>Activity &amp; Audit</h3>
            <p>Full audit trail of all system actions, login history, and CSV export.</p>
            <div class="adm-open">Open &rarr;</div>
        </a>
        <a href="<?php echo baseUrl(); ?>/admin/testimonials.php" class="adm-card">
            <?php if ($pendingTestimonials > 0): ?><span class="adm-cbadge adm-cbadge-warn"><?php echo $pendingTestimonials; ?></span><?php endif; ?>
            <span class="adm-icon">&#11088;</span>
            <h3>Testimonials</h3>
            <p>Review and approve donor stories before they are published publicly.</p>
            <div class="adm-open">Open &rarr;</div>
        </a>
        <a href="<?php echo baseUrl(); ?>/auth/setup_2fa.php" class="adm-card">
            <span class="adm-icon">&#128272;</span>
            <h3>Two-Factor Auth</h3>
            <p>Enable or manage TOTP 2FA for your admin account.</p>
            <div class="adm-open">Manage &rarr;</div>
        </a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
