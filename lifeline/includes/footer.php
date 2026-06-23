</main>
<footer role="contentinfo">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="<?php echo baseUrl(); ?>/index.php" class="logo" aria-label="LifeLine Blood Network — home"><span aria-hidden="true">&#9764;</span> LifeLine Blood Network</a>
                <p>Connecting hospitals with voluntary blood donors to save lives in emergencies. Every donation counts.</p>
            </div>
            <div>
                <h4>Quick Links</h4>
                <ul>
                    <?php if (isAdmin()): ?>
                        <li><a href="<?php echo baseUrl(); ?>/admin/dashboard.php">Admin Dashboard</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/admin/manage_requests.php">Manage Requests</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/admin/activity.php">Activity &amp; Audit</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/admin/slo_dashboard.php">SLO Dashboard</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/admin/analytics.php">Analytics</a></li>
                    <?php elseif (isHospital()): ?>
                        <li><a href="<?php echo baseUrl(); ?>/hospital/dashboard.php">My Dashboard</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/find_donors.php">Find Donors</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/emergency.php">Emergency SOS</a></li>
                    <?php elseif (isDonor()): ?>
                        <li><a href="<?php echo baseUrl(); ?>/find_donors.php">Find Donors</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/blood_banks.php">Hospitals</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/eligibility.php">Eligibility Check</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/emergency.php">Emergency SOS</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/leaderboard.php">Leaderboard</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo baseUrl(); ?>/testimonials.php">Stories</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/login.php">Login to Find Donors</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/register.php">Become a Donor</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div>
                <h4>Account</h4>
                <ul>
                    <?php if (isAdmin()): ?>
                        <li><a href="<?php echo baseUrl(); ?>/admin/dashboard.php">Admin Panel</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/messages.php">Messages</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/logout.php">Logout</a></li>
                    <?php elseif (isDonor()): ?>
                        <li><a href="<?php echo baseUrl(); ?>/donor/dashboard.php">My Dashboard</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/donor/edit_profile.php">My Profile</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/messages.php">Messages</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/logout.php">Logout</a></li>
                    <?php elseif (isHospital()): ?>
                        <li><a href="<?php echo baseUrl(); ?>/hospital/dashboard.php">My Dashboard</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/hospital/edit_profile.php">Hospital Profile</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/messages.php">Messages</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo baseUrl(); ?>/login.php">Login</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/register.php">Register</a></li>
                        <li><a href="<?php echo baseUrl(); ?>/forgot_password.php">Forgot Password</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div>
                <h4>Contact</h4>
                <ul>
                    <li>&#9993; support@lifelineblood.org</li>
                    <li>&#9742; 1800-BLOOD-SOS</li>
                    <li>&#9872; 24/7 Emergency Line</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> LifeLine Blood Network. All rights reserved. Made with &#10084; for humanity.</p>
        </div>
    </div>
</footer>
<script src="<?php echo assetUrl('assets/js/app.js'); ?>"></script>
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('<?php echo baseUrl(); ?>/sw.js')
        .catch(function(e) { console.warn('SW registration failed:', e); });
}
</script>
</body>
</html>
