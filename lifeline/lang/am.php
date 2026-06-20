<?php
/**
 * Amharic (አማርኛ) strings.
 * Keys must match lang/en.php exactly.
 * Missing keys fall back to English via t().
 */
return [
    // ── Navigation ─────────────────────────────────────────────────────────
    'nav.home'             => 'መነሻ',
    'nav.find_donors'      => 'ለጋሽ ፈልግ',
    'nav.blood_banks'      => 'የደም ባንኮች',
    'nav.leaderboard'      => 'ሰሌዳ',
    'nav.dashboard'        => 'ዳሽቦርድ',
    'nav.login'            => 'ግባ',
    'nav.register'         => 'ተመዝገብ',
    'nav.logout'           => 'ውጣ',
    'nav.messages'         => 'መልዕክቶች',

    // ── Auth ───────────────────────────────────────────────────────────────
    'auth.login_title'     => 'ግባ',
    'auth.register_title'  => 'መለያ ፍጠር',
    'auth.email'           => 'ኢሜይል አድራሻ',
    'auth.password'        => 'የሚስጥር ቁጥር',
    'auth.forgot_password' => 'የሚስጥር ቁጥርዎን ረሱ?',
    'auth.no_account'      => 'መለያ የለዎትም?',
    'auth.login_btn'       => 'ግባ',
    'auth.register_btn'    => 'ተመዝገብ',
    'auth.login_success'   => 'በተሳካ ሁኔታ ገብተዋል!',
    'auth.login_failed'    => 'ኢሜይል ወይም የሚስጥር ቁጥር የተሳሳተ ነው።',
    'auth.logout_success'  => 'ወጥተዋል።',

    // ── Register ───────────────────────────────────────────────────────────
    'register.full_name'   => 'ሙሉ ስም',
    'register.role'        => 'እኔ ነኝ',
    'register.role_donor'  => 'የደም ለጋሽ',
    'register.role_hospital' => 'ሆስፒታል',
    'register.blood_type'  => 'የደም ዓይነት',
    'register.consent'     => 'የአገልግሎት ውሎችን አንብቤ ተስማምቻለሁ (ቁ.{version})',
    'register.submit'      => 'መለያ ፍጠር',
    'register.have_account' => 'ቀደም ሲል መለያ አለዎት?',

    // ── Find donors ────────────────────────────────────────────────────────
    'donors.title'         => 'የደም ለጋሾችን ፈልግ',
    'donors.search_city'   => 'ከተማ ወይም አካባቢ',
    'donors.blood_type'    => 'የደም ዓይነት',
    'donors.radius_km'     => 'ርቀት (ኪሜ)',
    'donors.search_btn'    => 'ፈልግ',
    'donors.no_results'    => 'ለፍለጋ መስፈርቶቹ የሚስማሙ ለጋሾች አልተገኙም።',
    'donors.available'     => 'ዝግጁ',
    'donors.unavailable'   => 'ዝግጁ አይደለም',
    'donors.distance_km'   => '{distance} ኪ.ሜ ርቀት',
    'donors.contact'       => 'አግኙ',

    // ── Blood requests ─────────────────────────────────────────────────────
    'request.title'        => 'የደም ጥያቄ',
    'request.blood_type'   => 'የሚፈለግ የደም ዓይነት',
    'request.units'        => 'የሚፈለጉ ዩኒቶች',
    'request.urgency'      => 'አስቸኳይነት',
    'request.urgency_routine' => 'ተራ',
    'request.urgency_urgent'  => 'አስቸኳይ',
    'request.urgency_critical' => 'ወሳኝ',
    'request.required_date'   => 'የሚፈለግበት ቀን',
    'request.notes'        => 'ተጨማሪ ማስታወሻዎች',
    'request.submit'       => 'ጥያቄ አስገባ',
    'request.posted_by'    => 'ያቀረበው {hospital} {date} ላይ',
    'request.status_open'  => 'ክፍት',
    'request.status_fulfilled' => 'ተሟልቷል',
    'request.status_cancelled' => 'ተሰርዟል',

    // ── Emergency SOS ──────────────────────────────────────────────────────
    'sos.title'            => 'አስቸኳይ የደም ጥያቄ',
    'sos.subtitle'         => 'ሁሉንም ተኳሃኝ ለጋሾች ወዲያውኑ እናሳውቃለን።',
    'sos.patient_name'     => 'የታካሚ ስም',
    'sos.blood_type'       => 'የሚፈለግ የደም ዓይነት',
    'sos.location'         => 'ሆስፒታል / ቦታ',
    'sos.contact'          => 'የስልክ ቁጥር',
    'sos.send_btn'         => 'አስቸኳይ ማንቂያ ላክ',
    'sos.sent'             => 'አስቸኳይ ማንቂያ ለ{count} ለጋሾች ተልኳል።',

    // ── Dashboard (donor) ──────────────────────────────────────────────────
    'donor_dash.title'     => 'የለጋሽ ዳሽቦርድ',
    'donor_dash.welcome'   => 'እንኳን ደህና መጡ, {name}!',
    'donor_dash.donations' => 'ጠቅላላ ልገሳዎች',
    'donor_dash.points'    => 'የልገሳ ነጥቦች',
    'donor_dash.tier'      => 'ደረጃ',
    'donor_dash.eligible'  => 'ለልገሳ ዝግጁ',
    'donor_dash.not_eligible' => 'ዝግጁ አይደለም (ማገገሚያ ጊዜ)',

    // ── Common ─────────────────────────────────────────────────────────────
    'common.save'          => 'ለውጦችን አስቀምጥ',
    'common.cancel'        => 'ሰርዝ',
    'common.back'          => 'ተመለስ',
    'common.delete'        => 'ሰርዝ',
    'common.edit'          => 'አርም',
    'common.view'          => 'ይመልከቱ',
    'common.search'        => 'ፈልግ',
    'common.loading'       => 'በመጫን ላይ…',
    'common.error'         => 'ችግር ተፈጥሯል። እንደገና ይሞክሩ።',
    'common.yes'           => 'አዎ',
    'common.no'            => 'አይ',
    'common.verified'      => 'የተረጋገጠ',
    'common.page_of'       => 'ገጽ {page} ከ {total}',
];
