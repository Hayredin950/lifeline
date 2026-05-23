<?php
require_once 'includes/functions.php';
$pageTitle = 'Donor Eligibility Check';
include 'includes/header.php';
?>

<section class="hero" style="padding: 48px 32px;">
    <h1>&#9989; Donor Eligibility Checker</h1>
    <p>Check if you're eligible to donate blood. Answer a few quick questions to find out.</p>
</section>

<div class="card" style="max-width: 700px; margin: 0 auto;">
    <form id="eligibilityForm" onsubmit="return checkEligibility()">
        <h3 style="margin-bottom: 20px; color: var(--primary-dark);">Answer the following questions:</h3>

        <div class="form-group">
            <label>1. Are you between 18 and 65 years of age?</label>
            <select name="age" required>
                <option value="">Select...</option>
                <option value="yes">Yes</option>
                <option value="no">No (Under 18 or Over 65)</option>
            </select>
        </div>

        <div class="form-group">
            <label>2. Do you weigh at least 50 kg (110 lbs)?</label>
            <select name="weight" required>
                <option value="">Select...</option>
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>

        <div class="form-group">
            <label>3. Is your hemoglobin level 12.5 g/dL or above?</label>
            <select name="hemoglobin" required>
                <option value="">Select...</option>
                <option value="yes">Yes</option>
                <option value="no">No</option>
                <option value="unknown">I don't know</option>
            </select>
        </div>

        <div class="form-group">
            <label>4. Have you donated blood in the last 3 months?</label>
            <select name="recent_donation" required>
                <option value="">Select...</option>
                <option value="no">No</option>
                <option value="yes">Yes</option>
            </select>
        </div>

        <div class="form-group">
            <label>5. Are you currently on antibiotics or medication for an infection?</label>
            <select name="medication" required>
                <option value="">Select...</option>
                <option value="no">No</option>
                <option value="yes">Yes</option>
            </select>
        </div>

        <div class="form-group">
            <label>6. Have you had a tattoo or body piercing in the last 6 months?</label>
            <select name="tattoo" required>
                <option value="">Select...</option>
                <option value="no">No</option>
                <option value="yes">Yes</option>
            </select>
        </div>

        <div class="form-group">
            <label>7. Have you been diagnosed with HIV, Hepatitis B/C, or Syphilis?</label>
            <select name="disease" required>
                <option value="">Select...</option>
                <option value="no">No</option>
                <option value="yes">Yes</option>
            </select>
        </div>

        <div class="form-group">
            <label>8. Are you pregnant or have you given birth in the last 6 months?</label>
            <select name="pregnant" required>
                <option value="">Select...</option>
                <option value="no">No</option>
                <option value="yes">Yes</option>
                <option value="na">Not applicable</option>
            </select>
        </div>

        <div class="form-group">
            <label>9. Have you consumed alcohol in the last 24 hours?</label>
            <select name="alcohol" required>
                <option value="">Select...</option>
                <option value="no">No</option>
                <option value="yes">Yes</option>
            </select>
        </div>

        <div class="form-group">
            <label>10. Do you have any chronic illness (diabetes, heart disease, cancer)?</label>
            <select name="chronic" required>
                <option value="">Select...</option>
                <option value="no">No</option>
                <option value="yes">Yes</option>
            </select>
        </div>

        <button type="submit" class="btn btn-large" style="width: 100%;">Check My Eligibility</button>
    </form>

    <div id="eligibilityResult" style="display: none; margin-top: 24px;"></div>
</div>

<div class="card" style="max-width: 700px; margin: 24px auto 0;">
    <h3 style="color: var(--primary-dark); margin-bottom: 20px;">General Eligibility Guidelines</h3>
    <div class="eligibility-grid">
        <div class="guideline-col">
            <h4 style="color: var(--success); font-size: 1rem; margin-bottom: 12px;">&#10004; You CAN donate if:</h4>
            <ul style="padding-left: 20px; color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6;">
                <li>Age 18-65 years</li>
                <li>Weight 50+ kg</li>
                <li>Hemoglobin 12.5+ g/dL</li>
                <li>No donation in last 3 months</li>
                <li>Free from infections</li>
                <li>No tattoo/piercing in 6 months</li>
                <li>Not pregnant (last 6 months)</li>
            </ul>
        </div>
        <div class="guideline-col">
            <h4 style="color: var(--danger); font-size: 1rem; margin-bottom: 12px;">&#10008; You CANNOT donate if:</h4>
            <ul style="padding-left: 20px; color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6;">
                <li>Under 18 or over 65</li>
                <li>Weight below 50 kg</li>
                <li>HIV, Hepatitis B/C, Syphilis</li>
                <li>On antibiotics currently</li>
                <li>Recent tattoo or piercing</li>
                <li>Pregnant or recent childbirth</li>
                <li>Alcohol in last 24 hours</li>
            </ul>
        </div>
    </div>
</div>

<style>
    .eligibility-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
    }
    
    .eligibility-result {
        padding: 24px;
        border-radius: var(--radius);
        text-align: center;
        border: 1px solid var(--glass-border);
    }
    
    .eligibility-result.eligible {
        background: rgba(16, 185, 129, 0.1);
        border-color: rgba(16, 185, 129, 0.2);
    }
    
    .eligibility-result.ineligible {
        background: rgba(239, 68, 68, 0.1);
        border-color: rgba(239, 68, 68, 0.2);
    }
    
    @media (max-width: 600px) {
        .eligibility-grid {
            grid-template-columns: 1fr;
            gap: 16px;
        }
        
        .guideline-col {
            padding: 16px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: var(--radius-sm);
        }
    }
</style>

<script>
function checkEligibility() {
    const form = document.getElementById('eligibilityForm');
    const fd = new FormData(form);
    const result = document.getElementById('eligibilityResult');

    let reasons = [];

    // 1. Age (Must be Yes)
    if (fd.get('age') === 'no') {
        reasons.push('You must be between 18 and 65 years of age');
    }

    // 2. Weight (Must be Yes)
    if (fd.get('weight') === 'no') {
        reasons.push('You must weigh at least 50 kg (110 lbs)');
    }

    // 3. Hemoglobin (Must be Yes)
    if (fd.get('hemoglobin') === 'no') {
        reasons.push('Hemoglobin level below 12.5 g/dL disqualifies you');
    } else if (fd.get('hemoglobin') === 'unknown') {
        reasons.push('Please get your hemoglobin checked before donating');
    }

    // 4. Recent Donation (Must be No)
    if (fd.get('recent_donation') === 'yes') {
        reasons.push('You must wait 3 months between blood donations');
    }

    // 5. Medication (Must be No)
    if (fd.get('medication') === 'yes') {
        reasons.push('Current infection or antibiotics disqualify you temporarily');
    }

    // 6. Tattoo/Piercing (Must be No)
    if (fd.get('tattoo') === 'yes') {
        reasons.push('You must wait 6 months after getting a tattoo or body piercing');
    }

    // 7. Disease (Must be No)
    if (fd.get('disease') === 'yes') {
        reasons.push('A diagnosis of HIV, Hepatitis, or Syphilis disqualifies you permanently');
    }

    // 8. Pregnancy (Must be No)
    if (fd.get('pregnant') === 'yes') {
        reasons.push('Pregnancy and the 6 months following childbirth disqualify you');
    }

    // 9. Alcohol (Must be No)
    if (fd.get('alcohol') === 'yes') {
        reasons.push('You must not consume alcohol for at least 24 hours before donating');
    }

    // 10. Chronic Illness (Must be No)
    if (fd.get('chronic') === 'yes') {
        reasons.push('Certain chronic illnesses may disqualify you - please consult a doctor');
    }

    let html = '';
    if (reasons.length === 0) {
        html = `
            <div class="eligibility-result eligible">
                <h3 style="color: var(--success); margin-bottom: 12px;">&#10004; You Appear Eligible!</h3>
                <p style="color: var(--text-secondary); margin-bottom: 20px;">
                    Based on your answers, you meet the basic requirements to donate blood. 
                    Please visit a blood bank for a formal screening.
                </p>
                <a href="<?php echo baseUrl(); ?>/register.php?role=donor" class="btn btn-large" style="background: var(--success);">
                    Register as a Donor
                </a>
            </div>
        `;
    } else {
        html = `
            <div class="eligibility-result ineligible">
                <h3 style="color: var(--danger); margin-bottom: 12px;">&#10008; You May Not Be Eligible</h3>
                <p style="color: var(--text-secondary); margin-bottom: 16px;">Based on your answers, you may be deferred for the following reasons:</p>
                <ul style="text-align: left; color: var(--text-primary); padding-left: 20px; margin-bottom: 20px;">
                    ${reasons.map(r => `<li style="margin-bottom: 8px;">${r}</li>`).join('')}
                </ul>
                <p style="color: var(--text-muted); font-size: 0.9rem; font-style: italic;">
                    Note: This is just a preliminary check. Final eligibility is determined by medical staff at the donation site.
                </p>
            </div>
        `;
    }

    result.innerHTML = html;
    result.style.display = 'block';
    
    // Smooth scroll to result
    const yOffset = -100; 
    const y = result.getBoundingClientRect().top + window.pageYOffset + yOffset;
    window.scrollTo({top: y, behavior: 'smooth'});

    return false;
}
</script>

<?php include 'includes/footer.php'; ?>
