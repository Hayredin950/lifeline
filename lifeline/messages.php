<?php
require_once 'includes/functions.php';
requireAuth();

$userId = $_SESSION['user_id'];

// Load conversations — one query, no N+1
$stmt = $pdo->prepare("
    SELECT
        CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AS other_id,
        MAX(m.id)         AS last_id,
        MAX(m.created_at) AS last_message,
        COUNT(CASE WHEN m.receiver_id = ? AND m.is_read = 0 THEN 1 END) AS unread
    FROM messages m
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY other_id
    ORDER BY last_message DESC
");
$stmt->execute([$userId, $userId, $userId, $userId]);
$conversations = $stmt->fetchAll();

// Batch-resolve names and last message previews
$otherIds  = array_column($conversations, 'other_id');
$convMeta  = [];
if ($otherIds) {
    $previewIds = array_column($conversations, 'last_id');

    $in   = implode(',', array_fill(0, count($otherIds), '?'));
    $rows = $pdo->prepare("
        SELECT u.id, u.role,
               COALESCE(dp.full_name, hp.hospital_name, 'Unknown') AS display_name,
               COALESCE(hp.is_verified, 0) AS is_verified
        FROM users u
        LEFT JOIN donor_profiles   dp ON u.id = dp.user_id AND u.role = 'donor'
        LEFT JOIN hospital_profiles hp ON u.id = hp.user_id AND u.role = 'hospital'
        WHERE u.id IN ($in)
    ");
    $rows->execute($otherIds);
    foreach ($rows->fetchAll() as $r) {
        $convMeta[$r['id']] = [
            'name'        => $r['display_name'],
            'role'        => $r['role'],
            'is_verified' => (bool)$r['is_verified'],
        ];
    }

    $in2      = implode(',', array_fill(0, count($previewIds), '?'));
    $prevRow  = $pdo->prepare("SELECT id, content FROM messages WHERE id IN ($in2)");
    $prevRow->execute($previewIds);
    $lastContents = [];
    foreach ($prevRow->fetchAll() as $r) {
        $lastContents[$r['id']] = $r['content'];
    }
    foreach ($conversations as &$conv) {
        $conv['last_content'] = $lastContents[$conv['last_id']] ?? '';
    }
    unset($conv);
}

// Active conversation
$activeConversation = isset($_GET['conversation']) ? (int)$_GET['conversation'] : null;
$otherUser    = $activeConversation ? getUserById($pdo, $activeConversation) : null;
$otherName    = '';
$otherRole    = '';
$otherProfile = null;

$otherVerified = false;
if ($otherUser) {
    $otherRole = $otherUser['role'];
    if ($otherRole === 'donor') {
        $otherProfile = getDonorProfile($pdo, $activeConversation);
        $otherName    = $otherProfile['full_name'] ?? 'Donor';
    } else {
        $otherProfile  = getHospitalProfile($pdo, $activeConversation);
        $otherName     = $otherProfile['hospital_name'] ?? 'Hospital';
        $otherVerified = !empty($otherProfile['is_verified']);
    }
}

// Mark as read
if ($activeConversation) {
    $pdo->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0")
        ->execute([$userId, $activeConversation]);
}

$pageTitle = 'Messages';
$extraHead = <<<'CSS'
<style>
/* ── Messages shell ──────────────────────────────────────────────────────── */
main.container { padding: 0 !important; }

.msg-shell {
    display: grid;
    grid-template-columns: 300px 1fr;
    height: calc(100vh - 64px);
    overflow: hidden;
    background: #0b0b16;
    border-top: 1px solid rgba(255,255,255,.05);
}

/* ── Sidebar ─────────────────────────────────────────────────────────────── */
.msg-sidebar {
    display: flex;
    flex-direction: column;
    background: #0f0f1e;
    border-right: 1px solid rgba(255,255,255,.06);
}
.msg-sidebar-head {
    padding: 20px 20px 16px;
    border-bottom: 1px solid rgba(255,255,255,.06);
}
.msg-sidebar-title {
    font-size: .75rem;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: rgba(255,255,255,.35);
}
.msg-conv-list { flex: 1; overflow-y: auto; }
.msg-conv-list::-webkit-scrollbar { width: 4px; }
.msg-conv-list::-webkit-scrollbar-thumb { background: rgba(255,255,255,.08); border-radius: 2px; }

.msg-conv-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 13px 18px;
    cursor: pointer;
    text-decoration: none;
    border-left: 3px solid transparent;
    transition: background .15s, border-color .15s;
}
.msg-conv-item:hover { background: rgba(255,255,255,.04); }
.msg-conv-item.is-active {
    background: rgba(185,28,28,.1);
    border-left-color: #b91c1c;
}
.msg-conv-avatar {
    width: 42px; height: 42px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, #7f1d1d, #b91c1c);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 800; font-size: 1rem;
}
.msg-conv-avatar.hospital {
    background: linear-gradient(135deg, #1e3a5f, #2563eb);
}
.msg-conv-body { flex: 1; min-width: 0; }
.msg-conv-name {
    font-size: .9rem; font-weight: 700; color: #e2e8f0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.msg-conv-preview {
    font-size: .78rem; color: rgba(255,255,255,.35);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    margin-top: 2px;
}
.msg-conv-item.is-active .msg-conv-preview { color: rgba(255,255,255,.5); }
.msg-conv-meta {
    display: flex; flex-direction: column; align-items: flex-end; gap: 5px; flex-shrink: 0;
}
.msg-conv-time { font-size: .7rem; color: rgba(255,255,255,.2); }
.msg-unread-dot {
    width: 18px; height: 18px; border-radius: 50%;
    background: #b91c1c; color: #fff;
    font-size: .65rem; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
}
.msg-empty-sidebar {
    padding: 40px 16px; text-align: center;
    color: rgba(255,255,255,.25); font-size: .85rem; line-height: 1.65;
}
.msg-verified-badge {
    display: inline-flex; align-items: center; gap: 3px;
    font-size: .65rem; font-weight: 700; padding: 1px 6px;
    border-radius: 999px;
    background: rgba(37,99,235,.15);
    border: 1px solid rgba(37,99,235,.35);
    color: #60a5fa;
    margin-left: 5px;
    vertical-align: middle;
    white-space: nowrap;
}

/* ── Chat area ───────────────────────────────────────────────────────────── */
.msg-chat {
    display: flex;
    flex-direction: column;
    background: #0b0b16;
    min-width: 0;
}
.msg-chat-head {
    display: flex; align-items: center; gap: 14px;
    padding: 13px 22px;
    background: rgba(15,15,30,.95);
    border-bottom: 1px solid rgba(255,255,255,.06);
    backdrop-filter: blur(12px);
    flex-shrink: 0;
}
.msg-back-btn {
    display: none;
    width: 34px; height: 34px; border-radius: 50%;
    background: rgba(255,255,255,.06); border: none; cursor: pointer;
    color: rgba(255,255,255,.7); font-size: 1.1rem;
    align-items: center; justify-content: center; flex-shrink: 0;
    transition: background .15s;
}
.msg-back-btn:hover { background: rgba(255,255,255,.12); }
.msg-chat-head-avatar {
    width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(135deg, #7f1d1d, #b91c1c);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-weight: 800; font-size: 1rem;
    border: 2px solid rgba(185,28,28,.4);
    box-shadow: 0 0 0 3px rgba(185,28,28,.08);
}
.msg-chat-head-avatar.hospital {
    background: linear-gradient(135deg, #1e3a5f, #2563eb);
    border-color: rgba(37,99,235,.4);
    box-shadow: 0 0 0 3px rgba(37,99,235,.08);
}
.msg-chat-head-info { flex: 1; min-width: 0; }
.msg-chat-head-name {
    font-size: .98rem; font-weight: 800; color: #f1f5f9;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.msg-chat-head-role {
    font-size: .7rem; font-weight: 700; letter-spacing: .07em;
    text-transform: uppercase; color: rgba(255,255,255,.3); margin-top: 2px;
}

/* ── Messages scroll ─────────────────────────────────────────────────────── */
.msg-scroll {
    flex: 1; overflow-y: auto; padding: 24px;
    display: flex; flex-direction: column; gap: 3px;
    min-height: 0; scroll-behavior: smooth;
}
.msg-scroll::-webkit-scrollbar { width: 4px; }
.msg-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.07); border-radius: 2px; }

.msg-date-sep {
    display: flex; align-items: center; gap: 12px; margin: 16px 0 8px;
}
.msg-date-sep span {
    font-size: .68rem; font-weight: 700; letter-spacing: .07em;
    text-transform: uppercase; color: rgba(255,255,255,.2); white-space: nowrap;
}
.msg-date-sep::before, .msg-date-sep::after {
    content: ''; flex: 1; height: 1px; background: rgba(255,255,255,.05);
}

/* ── Bubbles ─────────────────────────────────────────────────────────────── */
.message-bubble {
    max-width: 68%;
    padding: 10px 14px 8px;
    border-radius: 16px;
    font-size: .92rem;
    line-height: 1.55;
    position: relative;
    word-wrap: break-word;
    margin-bottom: 1px;
}
.message-bubble:hover .message-actions { display: flex; }
.message-actions {
    display: none;
    position: absolute; top: -30px; right: 6px;
    background: #1c1c2e;
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 20px;
    padding: 4px 12px; gap: 12px; z-index: 10;
    box-shadow: 0 6px 20px rgba(0,0,0,.5);
}
.message-received .message-actions { right: auto; left: 6px; }
.action-btn {
    cursor: pointer; font-size: .82rem;
    opacity: .55; color: #e2e8f0; padding: 2px;
    line-height: 1; transition: opacity .15s;
}
.action-btn:hover { opacity: 1; }

.message-sent {
    align-self: flex-end;
    background: linear-gradient(135deg, #991b1b, #dc2626);
    color: #fff;
    border-bottom-right-radius: 4px;
    box-shadow: 0 4px 14px rgba(185,28,28,.22);
}
.message-received {
    align-self: flex-start;
    background: rgba(255,255,255,.06);
    color: #e2e8f0;
    border: 1px solid rgba(255,255,255,.08);
    border-bottom-left-radius: 4px;
}
.message-time {
    font-size: .68rem; opacity: .5; margin-top: 5px;
    display: flex; align-items: center; justify-content: flex-end; gap: 5px;
}
.edited-label { font-size: .66rem; opacity: .6; font-style: italic; }

/* ── Input area ──────────────────────────────────────────────────────────── */
.msg-input-wrap {
    padding: 12px 18px 16px;
    background: rgba(15,15,30,.95);
    border-top: 1px solid rgba(255,255,255,.06);
    flex-shrink: 0;
    backdrop-filter: blur(12px);
}
.msg-edit-banner {
    display: none;
    align-items: center; justify-content: space-between;
    padding: 7px 14px; margin-bottom: 10px;
    border-radius: 8px;
    background: rgba(185,28,28,.1);
    border: 1px solid rgba(185,28,28,.2);
    font-size: .82rem; color: rgba(255,120,120,.9);
}
.msg-input-row {
    display: flex; align-items: flex-end; gap: 10px;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.09);
    border-radius: 14px;
    padding: 7px 7px 7px 16px;
    transition: border-color .2s;
}
.msg-input-row:focus-within { border-color: rgba(185,28,28,.4); }
.msg-textarea {
    flex: 1; background: transparent; border: none; outline: none;
    color: #e2e8f0; font-size: .92rem; line-height: 1.5;
    resize: none; min-height: 24px; max-height: 120px;
    padding: 4px 0; font-family: inherit;
}
.msg-textarea::placeholder { color: rgba(255,255,255,.22); }
.msg-send-btn {
    width: 38px; height: 38px; border-radius: 10px; flex-shrink: 0;
    background: linear-gradient(135deg, #b91c1c, #dc2626);
    border: none; cursor: pointer;
    color: #fff; font-size: 1rem;
    display: flex; align-items: center; justify-content: center;
    transition: opacity .15s, transform .1s;
    box-shadow: 0 4px 12px rgba(185,28,28,.28);
}
.msg-send-btn:hover { opacity: .9; }
.msg-send-btn:active { transform: scale(.94); }
.msg-hint { font-size: .7rem; color: rgba(255,255,255,.18); margin-top: 7px; padding-left: 4px; }

/* ── Placeholder (no conversation selected) ──────────────────────────────── */
.msg-placeholder {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 12px; text-align: center; padding: 40px;
    color: rgba(255,255,255,.2);
}
.msg-placeholder-icon {
    width: 72px; height: 72px; border-radius: 50%;
    background: rgba(185,28,28,.07);
    border: 1.5px solid rgba(185,28,28,.18);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.8rem; margin-bottom: 8px;
}
.msg-placeholder h3 { font-size: 1.05rem; color: rgba(255,255,255,.38); }
.msg-placeholder p { font-size: .85rem; max-width: 260px; line-height: 1.65; }

/* ── Toast ───────────────────────────────────────────────────────────────── */
.toast {
    position: fixed; bottom: 28px; left: 50%;
    transform: translateX(-50%) translateY(80px);
    background: #1c1c2e; color: #e2e8f0;
    padding: 10px 20px; border-radius: 10px;
    border: 1px solid rgba(255,255,255,.1);
    box-shadow: 0 8px 32px rgba(0,0,0,.5);
    z-index: 9999; font-size: .88rem; font-weight: 600;
    display: flex; align-items: center; gap: 8px;
    transition: transform .3s cubic-bezier(.175,.885,.32,1.275);
    pointer-events: none;
}
.toast.show { transform: translateX(-50%) translateY(0); }

/* ── Modal ───────────────────────────────────────────────────────────────── */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.75);
    backdrop-filter: blur(6px);
    display: none; align-items: center; justify-content: center;
    z-index: 8000;
}
.msg-modal {
    background: #13132a;
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 20px; padding: 36px 32px;
    width: 100%; max-width: 360px; text-align: center;
    box-shadow: 0 24px 60px rgba(0,0,0,.6);
}
.msg-modal-icon { font-size: 2.4rem; margin-bottom: 14px; }
.msg-modal h3 { font-size: 1.1rem; color: #f1f5f9; margin-bottom: 8px; }
.msg-modal p { font-size: .875rem; color: rgba(255,255,255,.4); line-height: 1.6; margin-bottom: 24px; }
.msg-modal-btns { display: flex; gap: 10px; justify-content: center; }

/* ── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .msg-shell { grid-template-columns: 1fr; }
    .msg-sidebar { display: flex; }
    .msg-chat    { display: none; }
    .msg-shell.has-convo .msg-sidebar { display: none; }
    .msg-shell.has-convo .msg-chat    { display: flex; }
    .msg-back-btn { display: flex !important; }
    .msg-scroll { padding: 14px; }
    .message-bubble { max-width: 84%; }
    .msg-input-wrap { padding: 10px 12px 14px; }
}
</style>
CSS;

include 'includes/header.php';

$shellClass = $activeConversation ? 'msg-shell has-convo' : 'msg-shell';
?>

<div class="<?php echo $shellClass; ?>">

    <!-- ── Sidebar ──────────────────────────────────────────────────── -->
    <aside class="msg-sidebar">
        <div class="msg-sidebar-head">
            <div class="msg-sidebar-title">Messages</div>
        </div>

        <div class="msg-conv-list">
            <?php if ($conversations): ?>
                <?php foreach ($conversations as $conv):
                    $meta       = $convMeta[$conv['other_id']] ?? ['name' => 'Unknown', 'role' => 'donor', 'is_verified' => false];
                    $cname      = $meta['name'];
                    $crole      = $meta['role'];
                    $cVerified  = !empty($meta['is_verified']) && $crole === 'hospital';
                    $init    = strtoupper(mb_substr($cname, 0, 1));
                    $isAct   = ((int)$activeConversation === (int)$conv['other_id']);
                    $preview = mb_strimwidth($conv['last_content'] ?? '', 0, 40, '…');
                    $ago     = '';
                    if ($conv['last_message']) {
                        $diff = time() - strtotime($conv['last_message']);
                        $ago  = $diff < 60     ? 'now'
                             : ($diff < 3600   ? (int)($diff/60) . 'm'
                             : ($diff < 86400  ? (int)($diff/3600) . 'h'
                             : date('M j', strtotime($conv['last_message']))));
                    }
                ?>
                <a href="?conversation=<?php echo (int)$conv['other_id']; ?>"
                   class="msg-conv-item <?php echo $isAct ? 'is-active' : ''; ?>">

                    <div class="msg-conv-avatar <?php echo $crole === 'hospital' ? 'hospital' : ''; ?>">
                        <?php echo htmlspecialchars($init); ?>
                    </div>

                    <div class="msg-conv-body">
                        <div class="msg-conv-name">
                            <?php echo htmlspecialchars($cname); ?>
                            <?php if ($cVerified): ?>
                                <span class="msg-verified-badge">&#10003; Verified</span>
                            <?php endif; ?>
                        </div>
                        <div class="msg-conv-preview"><?php echo $preview ? htmlspecialchars($preview) : 'No messages'; ?></div>
                    </div>

                    <div class="msg-conv-meta">
                        <span class="msg-conv-time"><?php echo $ago; ?></span>
                        <?php if ($conv['unread'] > 0): ?>
                            <span class="msg-unread-dot"><?php echo min((int)$conv['unread'], 99); ?></span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="msg-empty-sidebar">
                    <div style="font-size:2.2rem;margin-bottom:10px">&#128172;</div>
                    No conversations yet.<br>
                    Message a hospital from the<br>
                    <a href="<?php echo baseUrl(); ?>/blood_banks.php" style="color:#b91c1c">Blood Banks</a> page.
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <!-- ── Chat area ────────────────────────────────────────────────── -->
    <div class="msg-chat">
        <?php if ($activeConversation && $otherUser): ?>

            <!-- Header -->
            <div class="msg-chat-head">
                <button class="msg-back-btn" onclick="history.back()" aria-label="Back">&#8592;</button>
                <div class="msg-chat-head-avatar <?php echo $otherRole === 'hospital' ? 'hospital' : ''; ?>">
                    <?php echo strtoupper(mb_substr($otherName, 0, 1)); ?>
                </div>
                <div class="msg-chat-head-info">
                    <div class="msg-chat-head-name">
                        <?php echo htmlspecialchars($otherName); ?>
                        <?php if ($otherVerified): ?>
                            <span class="msg-verified-badge">&#10003; Verified</span>
                        <?php endif; ?>
                    </div>
                    <div class="msg-chat-head-role"><?php echo ucfirst($otherRole); ?></div>
                </div>
            </div>

            <!-- Messages -->
            <div class="msg-scroll" id="messagesContainer">
                <div style="text-align:center;color:rgba(255,255,255,.2);font-size:.82rem;padding:24px 0">
                    Loading messages&hellip;
                </div>
            </div>

            <!-- Input -->
            <div class="msg-input-wrap">
                <div id="editModeIndicator" class="msg-edit-banner">
                    <span>&#9998;&ensp;Editing message</span>
                    <button type="button" id="cancelEditBtn" class="btn btn-small btn-secondary"
                            style="padding:4px 12px;font-size:.78rem">Cancel</button>
                </div>
                <form id="chatForm">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="receiver_id" value="<?php echo (int)$activeConversation; ?>">
                    <input type="hidden" name="message_id"  id="editMessageId" value="">
                    <div class="msg-input-row">
                        <textarea name="content" id="messageInput" class="msg-textarea"
                                  placeholder="Type a message…" rows="1" required></textarea>
                        <button type="submit" class="msg-send-btn" title="Send (Enter)">&#10148;</button>
                    </div>
                </form>
                <div class="msg-hint">Enter to send &middot; Shift+Enter for new line</div>
            </div>

        <?php else: ?>
            <div class="msg-placeholder">
                <div class="msg-placeholder-icon">&#9993;</div>
                <h3>Your Messages</h3>
                <p>Choose a conversation on the left, or start one from the Blood Banks page.</p>
            </div>
        <?php endif; ?>
    </div>

</div><!-- /.msg-shell -->

<!-- Toast -->
<div id="toast" class="toast">
    <span id="toastIcon"></span>
    <span id="toastMessage"></span>
</div>

<!-- Delete modal -->
<div id="deleteModal" class="modal-overlay">
    <div class="msg-modal">
        <div class="msg-modal-icon">&#128465;</div>
        <h3>Delete this message?</h3>
        <p>This action cannot be undone. The message will be removed for both parties.</p>
        <div class="msg-modal-btns">
            <button type="button" id="confirmDeleteBtn" class="btn"
                    style="background:#b91c1c;border-color:#b91c1c;min-width:90px">Delete</button>
            <button type="button" id="cancelDeleteBtn"
                    class="btn btn-secondary" style="min-width:90px">Cancel</button>
        </div>
    </div>
</div>

<script>
/* Messaging — XSS-safe via .text() only (DEF-04).
   SSE live transport with AJAX poll fallback (FR-37). */
jQuery(function ($) {
    var activeConversation = <?php echo $activeConversation ?: 'null'; ?>;
    var currentUserId      = <?php echo (int)$userId; ?>;
    var csrfToken          = <?php echo json_encode(csrfToken()); ?>;
    var base               = <?php echo json_encode(baseUrl()); ?>;

    var $container  = $('#messagesContainer');
    var $form       = $('#chatForm');
    var $input      = $('#messageInput');
    var $editId     = $('#editMessageId');
    var $editBanner = $('#editModeIndicator');
    var $toast      = $('#toast');

    var messageToDelete = null;
    var lastFingerprint = null;
    var lastCount       = 0;
    var isEditing       = false;
    var pollTimer       = null;
    var sinceMs         = 0;

    if (!activeConversation) return;

    /* ── Toast ── */
    function showToast(msg, type) {
        $('#toastIcon').html(type === 'error' ? '&#10060;' : '&#10003;');
        $('#toastMessage').text(msg);
        $toast.addClass('show');
        setTimeout(function () { $toast.removeClass('show'); }, 3000);
    }

    /* ── Helpers ── */
    function formatTime(ds) {
        var d = new Date((ds || '').replace(' ', 'T'));
        return isNaN(d) ? '' : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function scrollBottom() { $container.scrollTop($container[0].scrollHeight); }

    /* ── Auto-grow textarea ── */
    $input.on('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    /* ── Render bubble (XSS-safe) ── */
    function renderBubble(msg) {
        var mine = String(msg.sender_id) === String(currentUserId);
        var $b   = $('<div>').addClass('message-bubble')
                             .addClass(mine ? 'message-sent' : 'message-received')
                             .attr('data-id', msg.id)
                             .data('content', msg.content);

        var $actions = $('<div>').addClass('message-actions');
        $('<span>').addClass('action-btn js-copy').attr('title', 'Copy').html('&#128203;').appendTo($actions);
        if (mine) {
            $('<span>').addClass('action-btn js-edit').attr('title', 'Edit').html('&#9998;').appendTo($actions);
            $('<span>').addClass('action-btn js-delete').attr('title', 'Delete').html('&#128465;').appendTo($actions);
        }
        $b.append($actions);

        $('<div>').css({ 'white-space': 'pre-wrap', 'word-wrap': 'break-word' })
                  .text(msg.content)
                  .appendTo($b);

        var $meta = $('<div>').addClass('message-time');
        if (String(msg.is_edited) === '1') {
            $('<span>').addClass('edited-label').text('edited').appendTo($meta);
        }
        $('<span>').text(formatTime(msg.created_at)).appendTo($meta);
        $b.append($meta);
        return $b;
    }

    /* ── Load all messages ── */
    function loadMessages() {
        return $.ajax({
            url: base + '/api/get_messages.php',
            method: 'GET',
            data: { conversation: activeConversation },
            dataType: 'json'
        }).done(function (data) {
            if (!data || !data.success) return;
            var fp = data.messages.map(function (m) {
                return m.id + '|' + m.content + '|' + m.is_edited;
            }).join('~');
            if (fp === lastFingerprint) return;

            $container.empty();
            var lastDate = '';
            data.messages.forEach(function (msg) {
                var d = (msg.created_at || '').slice(0, 10);
                if (d && d !== lastDate) {
                    var label = (d === new Date().toISOString().slice(0, 10))
                        ? 'Today'
                        : new Date(d + 'T00:00:00').toLocaleDateString([], { month: 'short', day: 'numeric' });
                    $('<div>').addClass('msg-date-sep')
                              .append($('<span>').text(label))
                              .appendTo($container);
                    lastDate = d;
                }
                $container.append(renderBubble(msg));
            });

            if (data.messages.length > lastCount) scrollBottom();
            lastCount       = data.messages.length;
            lastFingerprint = fp;
        });
    }

    /* ── Delegated actions ── */
    $container.on('click', '.js-copy', function () {
        var txt = $(this).closest('.message-bubble').data('content') || '';
        if (navigator.clipboard) {
            navigator.clipboard.writeText(txt).then(function () { showToast('Copied!'); });
        }
    });

    $container.on('click', '.js-edit', function () {
        var $b = $(this).closest('.message-bubble');
        isEditing = true;
        $editId.val($b.attr('data-id'));
        $input.val($b.data('content') || '').trigger('input').focus();
        $editBanner.css('display', 'flex');
    });

    $container.on('click', '.js-delete', function () {
        messageToDelete = $(this).closest('.message-bubble').attr('data-id');
        $('#deleteModal').css('display', 'flex');
    });

    $('#confirmDeleteBtn').on('click', function () {
        if (!messageToDelete) return;
        $.ajax({
            url: base + '/api/delete_message.php', method: 'POST',
            data: { message_id: messageToDelete, csrf_token: csrfToken }, dataType: 'json'
        }).done(function (d) {
            $('#deleteModal').hide();
            messageToDelete = null;
            if (d && d.success) { showToast('Deleted'); loadMessages(); }
            else showToast((d && d.error) || 'Error', 'error');
        }).fail(function () {
            $('#deleteModal').hide();
            showToast('Network error', 'error');
        });
    });

    $('#cancelDeleteBtn').on('click', function () { $('#deleteModal').hide(); messageToDelete = null; });

    function exitEdit() {
        isEditing = false;
        $editId.val('');
        $input.val('').trigger('input');
        $editBanner.hide();
    }
    $('#cancelEditBtn').on('click', exitEdit);

    /* ── Send / edit ── */
    $form.on('submit', function (e) {
        e.preventDefault();
        var content = $.trim($input.val());
        if (!content) return;

        var editing  = isEditing;
        var endpoint = editing ? '/api/edit_message.php' : '/api/send_message.php';

        if (!editing) {
            var $tmp = $('<div>').addClass('message-bubble message-sent').css('opacity', .55);
            $('<div>').css({ 'white-space': 'pre-wrap', 'word-wrap': 'break-word' }).text(content).appendTo($tmp);
            $('<div>').addClass('message-time').text('Sending…').appendTo($tmp);
            $container.append($tmp);
            scrollBottom();
        }

        $input.val('').trigger('input');
        if (editing) exitEdit();

        $.ajax({
            url: base + endpoint, method: 'POST',
            data: {
                csrf_token:  csrfToken,
                receiver_id: <?php echo (int)($activeConversation ?? 0); ?>,
                message_id:  $editId.val(),
                content:     content
            },
            dataType: 'json'
        }).done(function (d) {
            if (d && d.success) showToast(editing ? 'Updated' : 'Sent');
            else showToast((d && d.error) || 'Error', 'error');
            loadMessages();
        }).fail(function () {
            showToast('Network error', 'error');
            loadMessages();
        });
    });

    $input.on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $form.trigger('submit'); }
    });

    /* ── SSE + poll transport ── */
    var sseSource = null;

    function applyNew(msgs) {
        if (!msgs || !msgs.length) return;
        msgs.forEach(function (msg) {
            if (!$container.find('[data-id="' + msg.id + '"]').length) {
                $container.append(renderBubble(msg));
                scrollBottom();
                lastCount++;
            }
            if (msg.created_ms > sinceMs) sinceMs = msg.created_ms;
        });
    }

    function openSSE() {
        if (!window.EventSource) return false;
        sseSource = new EventSource(
            base + '/api/stream.php?conversation=' + activeConversation + '&since=' + sinceMs
        );
        sseSource.addEventListener('messages', function (e) {
            try { var d = JSON.parse(e.data); if (d.messages) applyNew(d.messages); } catch (x) {}
        });
        sseSource.addEventListener('close', function () {
            sseSource.close(); sseSource = null; openSSE();
        });
        sseSource.onerror = function () {
            sseSource.close(); sseSource = null; startPoll();
        };
        return true;
    }

    function startPoll() {
        if (!pollTimer) pollTimer = setInterval(loadMessages, 3000);
    }
    function stopPoll() { clearInterval(pollTimer); pollTimer = null; }

    $(document).on('visibilitychange', function () {
        if (document.hidden) {
            if (sseSource) { sseSource.close(); sseSource = null; }
            stopPoll();
        } else {
            loadMessages();
            if (!openSSE()) startPoll();
        }
    });

    loadMessages().done(function () {
        if (!openSSE()) startPoll();
    });
});
</script>

<?php include 'includes/footer.php'; ?>
