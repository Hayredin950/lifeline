<?php
require_once 'includes/functions.php';
requireAuth();
$pageTitle = 'Eligibility Check';

$extraHead = <<<'CSS'
<style>
/* ── Eligibility page premium styles ──────────────────────────────────────── */
.elig-hero {
    background: linear-gradient(135deg, #1a0505 0%, #2d0a0a 40%, #1f0808 100%);
    border: 1px solid rgba(185,28,28,.25);
    border-radius: 20px;
    padding: 56px 40px 48px;
    text-align: center;
    margin-bottom: 40px;
    position: relative;
    overflow: hidden;
}
.elig-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 60% 50% at 50% 0%, rgba(185,28,28,.18) 0%, transparent 70%);
    pointer-events: none;
}
.elig-hero-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 64px; height: 64px;
    border-radius: 50%;
    background: rgba(185,28,28,.15);
    border: 1.5px solid rgba(185,28,28,.4);
    font-size: 1.75rem;
    margin-bottom: 20px;
}
.elig-hero h1 {
    font-size: clamp(1.6rem, 4vw, 2.4rem);
    font-weight: 800;
    color: #fff;
    margin-bottom: 12px;
    letter-spacing: -.01em;
}
.elig-hero p {
    color: rgba(255,255,255,.65);
    font-size: 1.05rem;
    max-width: 500px;
    margin: 0 auto;
    line-height: 1.6;
}

/* Steps progress */
.elig-steps {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
    margin-bottom: 36px;
    padding: 0 8px;
    overflow-x: auto;
}
.elig-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    min-width: 64px;
}
.elig-step-dot {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: var(--bg-dark-2, #1e1e2e);
    border: 2px solid rgba(185,28,28,.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .75rem;
    font-weight: 700;
    color: rgba(255,255,255,.4);
    transition: all .25s;
}
.elig-step.active .elig-step-dot {
    background: #b91c1c;
    border-color: #b91c1c;
    color: #fff;
    box-shadow: 0 0 0 4px rgba(185,28,28,.2);
}
.elig-step.done .elig-step-dot {
    background: rgba(16,185,129,.2);
    border-color: rgba(16,185,129,.5);
    color: #10b981;
}
.elig-step-label { font-size: .68rem; color: rgba(255,255,255,.35); text-align: center; white-space: nowrap; }
.elig-step.active .elig-step-label { color: rgba(255,255,255,.7); }
.elig-step-line {
    flex: 1;
    height: 2px;
    background: rgba(185,28,28,.2);
    min-width: 20px;
    max-width: 48px;
    margin-bottom: 24px;
}

/* Question cards */
.elig-form { display: flex; flex-direction: column; gap: 12px; }
.elig-q {
    background: rgba(255,255,255,.03);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 14px;
    padding: 20px 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: border-color .2s, background .2s;
}
.elig-q:focus-within {
    border-color: rgba(185,28,28,.4);
    background: rgba(185,28,28,.04);
}
.elig-q-num {
    min-width: 32px; height: 32px;
    border-radius: 50%;
    background: rgba(185,28,28,.12);
    border: 1px solid rgba(185,28,28,.25);
    display: flex; align-items: center; justify-content: center;
    font-size: .78rem; font-weight: 700; color: #ef4444;
    flex-shrink: 0;
}
.elig-q-text { flex: 1; font-size: .95rem; color: var(--text-primary, #e2e8f0); font-weight: 500; }
.elig-q-select {
    flex-shrink: 0;
    width: 130px;
    padding: 8px 12px;
    border-radius: 8px;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.12);
    color: #e2e8f0;
    font-size: .88rem;
    cursor: pointer;
    outline: none;
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' fill='none'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%23888' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    padding-right: 30px;
}
.elig-q-select:focus { border-color: rgba(185,28,28,.5); }

.elig-submit-row { margin-top: 8px; }
.elig-submit {
    width: 100%;
    padding: 16px;
    font-size: 1.05rem;
    font-weight: 700;
    border-radius: 12px;
    letter-spacing: .01em;
}

/* Results */
.elig-result-eligible {
    background: rgba(16,185,129,.07);
    border: 1px solid rgba(16,185,129,.25);
    border-radius: 16px;
    padding: 36px 32px;
    text-align: center;
}
.elig-result-ineligible {
    background: rgba(239,68,68,.06);
    border: 1px solid rgba(239,68,68,.2);
    border-radius: 16px;
    padding: 36px 32px;
    text-align: center;
}
.elig-result-icon { font-size: 3rem; margin-bottom: 12px; }
.elig-result-title { font-size: 1.5rem; font-weight: 800; margin-bottom: 10px; }
.elig-reason-list { text-align: left; margin: 16px 0 24px; }
.elig-reason-list li {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255,255,255,.05);
    font-size: .9rem;
    color: rgba(255,255,255,.75);
    line-height: 1.5;
}
.elig-reason-list li::before { content: '✗'; color: #ef4444; font-weight: 700; margin-top: 1px; flex-shrink: 0; }

/* Guidelines panel */
.elig-guidelines {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-top: 40px;
}
.elig-guide-panel {
    background: rgba(255,255,255,.025);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 14px;
    padding: 24px;
}
.elig-guide-title {
    font-size: .8rem;
    font-weight: 700;
    letter-spacing: .06em;
    text-transform: uppercase;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.elig-guide-title.can  { color: #10b981; }
.elig-guide-title.cant { color: #ef4444; }
.elig-guide-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
.elig-guide-list li {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: .875rem;
    color: rgba(255,255,255,.65);
    line-height: 1.4;
}
.elig-guide-list li span.dot {
    width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; margin-top: 5px;
}
.elig-guide-panel.can  .dot { background: #10b981; }
.elig-guide-panel.cant .dot { background: #ef4444; }

@media (max-width: 640px) {
    .elig-q { flex-direction: column; align-items: flex-start; }
    .elig-q-select { width: 100%; }
    .elig-guidelines { grid-template-columns: 1fr; }
    .elig-hero { padding: 40px 20px 36px; }
}
</style>
CSS;

include 'includes/header.php';
?>

<!-- Hero -->
<div class="elig-hero">
    <div class="elig-hero-icon">&#9764;</div>
    <h1>Blood Donation Eligibility</h1>
    <p>Answer 10 questions to instantly discover if you're ready to give the gift of life today.</p>
</div>

<div class="maxw-700 mx-auto">

<!-- Steps indicator -->
<div class="elig-steps" id="eligSteps">
    <?php
    $steps = ['Health','Weight','Blood','History','Meds','Skin','Disease','Maternity','Alcohol','Chronic'];
    foreach ($steps as $i => $s):
    ?>
    <?php if ($i > 0): ?><div class="elig-step-line"></div><?php endif; ?>
    <div class="elig-step <?php echo $i === 0 ? 'active' : ''; ?>" id="step<?php echo $i; ?>">
        <div class="elig-step-dot"><?php echo $i + 1; ?></div>
        <div class="elig-step-label"><?php echo $s; ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Questions form -->
<form id="eligForm" onsubmit="return runCheck()" class="card" style="padding: 28px 32px;">
    <div class="elig-form">

        <div class="elig-q">
            <div class="elig-q-num">1</div>
            <div class="elig-q-text">Are you between <strong>18 and 65</strong> years of age?</div>
            <select class="elig-q-select" name="age" required onchange="stepProgress(0)">
                <option value="">Select</option>
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>

        <div class="elig-q">
            <div class="elig-q-num">2</div>
            <div class="elig-q-text">Do you weigh at least <strong>50 kg</strong> (110 lbs)?</div>
            <select class="elig-q-select" name="weight" required onchange="stepProgress(1)">
                <option value="">Select</option>
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>

        <div class="elig-q">
            <div class="elig-q-num">3</div>
            <div class="elig-q-text">Is your hemoglobin level <strong>12.5 g/dL or above</strong>?</div>
            <select class="elig-q-select" name="hemoglobin" required onchange="stepProgress(2)">
                <option value="">Select</option>
                <option value="yes">Yes</option>
                <option value="no">No</option>
                <option value="unknown">Not sure</option>
            </select>
        </div>

        <div class="elig-q">
            <div class="elig-q-num">4</div>
            <div class="elig-q-text">Have you donated blood in the <strong>last 3 months</strong>?</div>
            <select class="elig-q-select" name="recent_donation" required onchange="stepProgress(3)">
                <option value="">Select</option>
                <option value="no">No</option>
                <option value="yes">Yes</option>
            </select>
        </div>

        <div class="elig-q">
            <div class="elig-q-num">5</div>
            <div class="elig-q-text">Are you on <strong>antibiotics</strong> or treating an active infection?</div>
            <select class="elig-q-select" name="medication" required onchange="stepProgress(4)">
                <option value="">Select</option>
                <option value="no">No</option>
                <option value="yes">Yes</option>
            </select>
        </div>

        <div class="elig-q">
            <div class="elig-q-num">6</div>
            <div class="elig-q-text">Did you get a <strong>tattoo or piercing</strong> in the last 6 months?</div>
            <select class="elig-q-select" name="tattoo" required onchange="stepProgress(5)">
                <option value="">Select</option>
                <option value="no">No</option>
                <option value="yes">Yes</option>
            </select>
        </div>

        <div class="elig-q">
            <div class="elig-q-num">7</div>
            <div class="elig-q-text">Have you been diagnosed with <strong>HIV, Hepatitis B/C, or Syphilis</strong>?</div>
            <select class="elig-q-select" name="disease" required onchange="stepProgress(6)">
                <option value="">Select</option>
                <option value="no">No</option>
                <option value="yes">Yes</option>
            </select>
        </div>

        <div class="elig-q">
            <div class="elig-q-num">8</div>
            <div class="elig-q-text">Are you <strong>pregnant</strong> or did you give birth in the last 6 months?</div>
            <select class="elig-q-select" name="pregnant" required onchange="stepProgress(7)">
                <option value="">Select</option>
                <option value="no">No</option>
                <option value="yes">Yes</option>
                <option value="na">Not applicable</option>
            </select>
        </div>

        <div class="elig-q">
            <div class="elig-q-num">9</div>
            <div class="elig-q-text">Have you consumed <strong>alcohol</strong> in the last 24 hours?</div>
            <select class="elig-q-select" name="alcohol" required onchange="stepProgress(8)">
                <option value="">Select</option>
                <option value="no">No</option>
                <option value="yes">Yes</option>
            </select>
        </div>

        <div class="elig-q">
            <div class="elig-q-num">10</div>
            <div class="elig-q-text">Do you have a <strong>chronic illness</strong> such as diabetes, heart disease, or cancer?</div>
            <select class="elig-q-select" name="chronic" required onchange="stepProgress(9)">
                <option value="">Select</option>
                <option value="no">No</option>
                <option value="yes">Yes</option>
            </select>
        </div>

    </div>

    <div class="elig-submit-row mt-24">
        <button type="submit" class="btn elig-submit">Check My Eligibility</button>
    </div>
</form>

<!-- Result panel (hidden until submit) -->
<div id="eligResult" style="display:none; margin-top: 24px;"></div>

<!-- Guidelines -->
<div class="elig-guidelines">
    <div class="elig-guide-panel can">
        <div class="elig-guide-title can">&#10003; You CAN donate if</div>
        <ul class="elig-guide-list">
            <li><span class="dot"></span>Age 18–65 years</li>
            <li><span class="dot"></span>Weight 50 kg or more</li>
            <li><span class="dot"></span>Hemoglobin 12.5 g/dL or above</li>
            <li><span class="dot"></span>No donation in the last 90 days</li>
            <li><span class="dot"></span>Free from active infections</li>
            <li><span class="dot"></span>No tattoo or piercing in 6 months</li>
            <li><span class="dot"></span>Not pregnant or postpartum</li>
        </ul>
    </div>
    <div class="elig-guide-panel cant">
        <div class="elig-guide-title cant">&#10005; You CANNOT donate if</div>
        <ul class="elig-guide-list">
            <li><span class="dot"></span>Under 18 or over 65 years old</li>
            <li><span class="dot"></span>Weight below 50 kg</li>
            <li><span class="dot"></span>HIV, Hepatitis B/C, or Syphilis diagnosis</li>
            <li><span class="dot"></span>Currently on antibiotics</li>
            <li><span class="dot"></span>Recent tattoo or body piercing</li>
            <li><span class="dot"></span>Pregnant or gave birth recently</li>
            <li><span class="dot"></span>Consumed alcohol in last 24 hours</li>
        </ul>
    </div>
</div>

</div><!-- /maxw-700 -->

<script>
const totalSteps = 10;
let answered = new Array(totalSteps).fill(false);

function stepProgress(idx) {
    answered[idx] = true;
    // mark done up to idx, active at first unanswered after
    for (let i = 0; i < totalSteps; i++) {
        const el = document.getElementById('step' + i);
        if (!el) continue;
        if (i < idx || answered[i]) {
            el.className = 'elig-step done';
        } else if (i === idx + 1 && !answered[i]) {
            el.className = 'elig-step active';
        } else if (!answered[i]) {
            el.className = 'elig-step';
        }
    }
    // mark current as done
    const cur = document.getElementById('step' + idx);
    if (cur) cur.className = 'elig-step done';
}

function runCheck() {
    const fd = new FormData(document.getElementById('eligForm'));
    const reasons = [];

    if (fd.get('age') === 'no')             reasons.push('You must be between 18 and 65 years of age.');
    if (fd.get('weight') === 'no')          reasons.push('You must weigh at least 50 kg (110 lbs).');
    if (fd.get('hemoglobin') === 'no')      reasons.push('Your hemoglobin level is below 12.5 g/dL.');
    if (fd.get('hemoglobin') === 'unknown') reasons.push('Please get your hemoglobin checked before donating.');
    if (fd.get('recent_donation') === 'yes') reasons.push('You must wait at least 3 months between donations.');
    if (fd.get('medication') === 'yes')     reasons.push('Active infection or current antibiotics temporarily defer you.');
    if (fd.get('tattoo') === 'yes')         reasons.push('You must wait 6 months after a tattoo or body piercing.');
    if (fd.get('disease') === 'yes')        reasons.push('A diagnosis of HIV, Hepatitis, or Syphilis disqualifies you.');
    if (fd.get('pregnant') === 'yes')       reasons.push('Pregnancy or childbirth in the last 6 months defers you.');
    if (fd.get('alcohol') === 'yes')        reasons.push('You must avoid alcohol for at least 24 hours before donating.');
    if (fd.get('chronic') === 'yes')        reasons.push('Certain chronic illnesses may defer you — consult a doctor.');

    // Mark all steps done
    for (let i = 0; i < totalSteps; i++) {
        const el = document.getElementById('step' + i);
        if (el) el.className = 'elig-step done';
    }

    const panel = document.getElementById('eligResult');
    panel.style.display = 'block';

    if (reasons.length === 0) {
        panel.innerHTML = `
            <div class="elig-result-eligible">
                <div class="elig-result-icon">&#10003;</div>
                <div class="elig-result-title" style="color:#10b981;">You Appear Eligible to Donate</div>
                <p style="color:rgba(255,255,255,.65); margin-bottom:24px; max-width:440px; margin-left:auto; margin-right:auto; line-height:1.6;">
                    Based on your answers you meet the basic requirements. Visit a blood bank for a formal medical screening — it only takes 15 minutes.
                </p>
                <a href="<?php echo baseUrl(); ?>/blood_banks.php" class="btn" style="background:#10b981; border-color:#10b981;">Find a Blood Bank Near You</a>
            </div>`;
    } else {
        const list = reasons.map(r => `<li>${r}</li>`).join('');
        panel.innerHTML = `
            <div class="elig-result-ineligible">
                <div class="elig-result-icon">&#10005;</div>
                <div class="elig-result-title" style="color:#ef4444;">Not Eligible Right Now</div>
                <p style="color:rgba(255,255,255,.55); margin-bottom:4px;">The following criteria were not met:</p>
                <ul class="elig-reason-list">${list}</ul>
                <p style="color:rgba(255,255,255,.4); font-size:.82rem; line-height:1.5;">
                    This is a preliminary screen only. Final eligibility is always determined by medical staff at the donation site. Some deferrals are temporary — check back after your deferral period ends.
                </p>
            </div>`;
    }

    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    return false;
}
</script>

<?php include 'includes/footer.php'; ?>
