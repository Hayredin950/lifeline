<?php
require_once 'includes/functions.php';
$pageTitle = 'Terms of Service & Privacy Policy';
$fromRole = in_array($_GET['role'] ?? '', ['donor', 'hospital']) ? $_GET['role'] : null;
$backUrl  = $fromRole ? baseUrl() . '/register.php?role=' . $fromRole : baseUrl() . '/register.php';
include 'includes/header.php';
?>

<div class="card maxw-800 mx-auto my-40">
    <h1 class="mb-4">Terms of Service &amp; Privacy Policy</h1>
    <p class="text-muted fs-85 mb-32">Version <?php echo htmlspecialchars(TERMS_VERSION); ?> &mdash; Effective June 2025</p>

    <section class="mb-32">
        <h2 class="fs-110 mb-12">1. About LifeLine Blood Network</h2>
        <p>LifeLine Blood Network ("LifeLine", "we", "our") is a voluntary blood donation coordination platform connecting donors with hospitals and emergency blood requests in Ethiopia. By creating an account, you agree to these Terms and our Privacy Policy.</p>
    </section>

    <section class="mb-32">
        <h2 class="fs-110 mb-12">2. Eligibility</h2>
        <ul class="pl-20">
            <li>You must be at least 18 years of age to register as a donor.</li>
            <li>Hospital accounts must represent a licensed medical institution.</li>
            <li>You must provide accurate and truthful information during registration.</li>
        </ul>
    </section>

    <section class="mb-32">
        <h2 class="fs-110 mb-12">3. Donor Responsibilities</h2>
        <ul class="pl-20">
            <li>Donors must keep their availability status up to date.</li>
            <li>Donors must honestly represent their health status and donation history.</li>
            <li>Blood donation is entirely voluntary. LifeLine does not compensate donors.</li>
            <li>Donors agree to comply with a minimum 90-day cooldown between donations.</li>
            <li>Responding to emergency SOS alerts is voluntary but encouraged.</li>
        </ul>
    </section>

    <section class="mb-32">
        <h2 class="fs-110 mb-12">4. Hospital Responsibilities</h2>
        <ul class="pl-20">
            <li>Hospitals must only submit blood requests for genuine medical needs.</li>
            <li>Hospitals are responsible for verifying donor eligibility at the point of donation.</li>
            <li>Misuse of the emergency SOS feature may result in account suspension.</li>
        </ul>
    </section>

    <section class="mb-32">
        <h2 class="fs-110 mb-12">5. Privacy &amp; Data Use</h2>
        <p class="mb-12">We collect only the information necessary to match donors with blood requests:</p>
        <ul class="pl-20">
            <li><strong>Identity data:</strong> name, email address, phone number.</li>
            <li><strong>Health data:</strong> blood type, donation history, eligibility status.</li>
            <li><strong>Location data:</strong> city, state, and approximate GPS coordinates (used for distance-based matching only).</li>
            <li><strong>Usage data:</strong> login timestamps, IP addresses (for security rate limiting).</li>
        </ul>
        <p class="mt-12">Your data is never sold to third parties. Location coordinates are used exclusively for distance matching within the platform.</p>
    </section>

    <section class="mb-32">
        <h2 class="fs-110 mb-12">6. Consent &amp; Consent Log</h2>
        <p>By checking the consent checkbox during registration, you confirm that you have read and agree to these terms. We record the version you agreed to, the timestamp, and your IP address as an immutable audit record, as required by Ethiopian data protection guidelines.</p>
    </section>

    <section class="mb-32">
        <h2 class="fs-110 mb-12">7. Data Retention &amp; Your Rights</h2>
        <ul class="pl-20">
            <li>You may request deletion of your account and personal data at any time by contacting us.</li>
            <li>Donation history linked to hospital records may be retained for medical audit purposes for up to 5 years.</li>
            <li>Consent records are retained permanently as required by law.</li>
        </ul>
    </section>

    <section class="mb-32">
        <h2 class="fs-110 mb-12">8. Prohibited Use</h2>
        <ul class="pl-20">
            <li>Creating fake donor or hospital accounts.</li>
            <li>Submitting fraudulent blood requests.</li>
            <li>Attempting to access other users' data without authorization.</li>
            <li>Any activity that disrupts the platform or endangers patient safety.</li>
        </ul>
    </section>

    <section class="mb-32">
        <h2 class="fs-110 mb-12">9. Disclaimer of Liability</h2>
        <p>LifeLine is a coordination platform only. We do not guarantee the availability, eligibility, or health status of any donor. Hospitals are solely responsible for medical screening at the point of donation. LifeLine is not liable for medical outcomes arising from donations arranged through this platform.</p>
    </section>

    <section class="mb-32">
        <h2 class="fs-110 mb-12">10. Changes to These Terms</h2>
        <p>We may update these terms periodically. Registered users will be notified by email and will be required to re-consent if the version changes materially. Continued use of the platform after notification constitutes acceptance.</p>
    </section>

    <section class="mb-32">
        <h2 class="fs-110 mb-12">11. Contact</h2>
        <p>For questions about these terms or to submit a data deletion request, contact us at <a href="mailto:admin@bloodsystem.com">admin@bloodsystem.com</a>.</p>
    </section>

    <div class="mt-32 pt-20" style="border-top: 1px solid var(--glass-border);">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-secondary">&larr; Back to Registration</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
