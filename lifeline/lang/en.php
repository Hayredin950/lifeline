<?php
/**
 * English strings — the canonical reference for all i18n keys.
 * Keys are dot-separated: section.key
 * Interpolation: {name}, {count}, etc. (replaced by t() at runtime)
 */
return [
    // ── Navigation ─────────────────────────────────────────────────────────
    'nav.home'             => 'Home',
    'nav.find_donors'      => 'Find Donors',
    'nav.blood_banks'      => 'Blood Banks',
    'nav.leaderboard'      => 'Leaderboard',
    'nav.dashboard'        => 'Dashboard',
    'nav.login'            => 'Login',
    'nav.register'         => 'Register',
    'nav.logout'           => 'Logout',
    'nav.messages'         => 'Messages',

    // ── Auth ───────────────────────────────────────────────────────────────
    'auth.login_title'     => 'Login',
    'auth.register_title'  => 'Create an Account',
    'auth.email'           => 'Email Address',
    'auth.password'        => 'Password',
    'auth.forgot_password' => 'Forgot password?',
    'auth.no_account'      => "Don't have an account?",
    'auth.login_btn'       => 'Login',
    'auth.register_btn'    => 'Register',
    'auth.login_success'   => 'Login successful. Welcome back!',
    'auth.login_failed'    => 'Invalid email or password.',
    'auth.logout_success'  => 'You have been logged out.',

    // ── Register ───────────────────────────────────────────────────────────
    'register.full_name'   => 'Full Name',
    'register.role'        => 'I am a',
    'register.role_donor'  => 'Blood Donor',
    'register.role_hospital' => 'Hospital',
    'register.blood_type'  => 'Blood Type',
    'register.consent'     => 'I have read and agree to the Terms of Service (v{version})',
    'register.submit'      => 'Create Account',
    'register.have_account' => 'Already have an account?',

    // ── Find donors ────────────────────────────────────────────────────────
    'donors.title'         => 'Find Blood Donors',
    'donors.search_city'   => 'City or location',
    'donors.blood_type'    => 'Blood Type',
    'donors.radius_km'     => 'Radius (km)',
    'donors.search_btn'    => 'Search',
    'donors.no_results'    => 'No donors found matching your criteria.',
    'donors.available'     => 'Available',
    'donors.unavailable'   => 'Unavailable',
    'donors.distance_km'   => '{distance} km away',
    'donors.contact'       => 'Contact',

    // ── Blood requests ─────────────────────────────────────────────────────
    'request.title'        => 'Blood Request',
    'request.blood_type'   => 'Blood Type Needed',
    'request.units'        => 'Units Required',
    'request.urgency'      => 'Urgency',
    'request.urgency_routine' => 'Routine',
    'request.urgency_urgent'  => 'Urgent',
    'request.urgency_critical' => 'Critical',
    'request.required_date'   => 'Required By',
    'request.notes'        => 'Additional Notes',
    'request.submit'       => 'Post Request',
    'request.posted_by'    => 'Posted by {hospital} on {date}',
    'request.status_open'  => 'Open',
    'request.status_fulfilled' => 'Fulfilled',
    'request.status_cancelled' => 'Cancelled',

    // ── Emergency SOS ──────────────────────────────────────────────────────
    'sos.title'            => 'Emergency Blood Request',
    'sos.subtitle'         => 'We will immediately notify all compatible donors in your area.',
    'sos.patient_name'     => 'Patient Name',
    'sos.blood_type'       => 'Blood Type Needed',
    'sos.location'         => 'Hospital / Location',
    'sos.contact'          => 'Contact Number',
    'sos.send_btn'         => 'Send Emergency Alert',
    'sos.sent'             => 'Emergency alert sent to {count} donors.',

    // ── Dashboard (donor) ──────────────────────────────────────────────────
    'donor_dash.title'     => 'Donor Dashboard',
    'donor_dash.welcome'   => 'Welcome back, {name}!',
    'donor_dash.donations' => 'Total Donations',
    'donor_dash.points'    => 'Donation Points',
    'donor_dash.tier'      => 'Tier',
    'donor_dash.eligible'  => 'Eligible to Donate',
    'donor_dash.not_eligible' => 'Not Eligible (cooldown)',

    // ── Common ─────────────────────────────────────────────────────────────
    'common.save'          => 'Save Changes',
    'common.cancel'        => 'Cancel',
    'common.back'          => 'Back',
    'common.delete'        => 'Delete',
    'common.edit'          => 'Edit',
    'common.view'          => 'View',
    'common.search'        => 'Search',
    'common.loading'       => 'Loading…',
    'common.error'         => 'Something went wrong. Please try again.',
    'common.yes'           => 'Yes',
    'common.no'            => 'No',
    'common.verified'      => 'Verified',
    'common.page_of'       => 'Page {page} of {total}',
];
