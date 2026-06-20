<?php
require_once 'includes/functions.php';
requireAuth();

$userId = $_SESSION['user_id'];

// Get conversations list
$stmt = $pdo->prepare("
    SELECT 
        CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END as other_id,
        MAX(m.created_at) as last_message,
        COUNT(CASE WHEN m.receiver_id = ? AND m.is_read = 0 THEN 1 END) as unread,
        (SELECT content FROM messages m2 WHERE m2.id = MAX(m.id)) as last_content
    FROM messages m
    WHERE m.sender_id = ? OR m.receiver_id = ?
    GROUP BY other_id
    ORDER BY last_message DESC
");
$stmt->execute([$userId, $userId, $userId, $userId]);
$conversations = $stmt->fetchAll();

// Active conversation
$activeConversation = isset($_GET['conversation']) ? (int)$_GET['conversation'] : null;
$otherUser = $activeConversation ? getUserById($pdo, $activeConversation) : null;
$otherName = 'Select a conversation';

if ($otherUser) {
    if ($otherUser['role'] === 'donor') {
        $op = getDonorProfile($pdo, $activeConversation);
        $otherName = $op['full_name'] ?? 'Donor';
    } else {
        $op = getHospitalProfile($pdo, $activeConversation);
        $otherName = $op['hospital_name'] ?? 'Hospital';
    }
}

$pageTitle = 'Messages';
include 'includes/header.php';
?>

<style>
    /* Full height layout for messaging */
    .messages-layout {
        display: grid; 
        grid-template-columns: 320px 1fr; 
        gap: 0; 
        height: calc(100vh - var(--header-height) - 40px); /* Adjust based on header height */
        min-height: 500px;
        background: var(--bg-dark-2);
        border-radius: var(--radius-lg);
        overflow: hidden;
        border: 1px solid var(--glass-border);
        margin-top: 20px;
    }

    @media (max-width: 768px) {
        .messages-layout {
            grid-template-columns: 1fr;
            height: calc(100vh - var(--header-height));
            margin-top: 0;
            border-radius: 0;
            border-left: none;
            border-right: none;
            border-bottom: none;
        }

        .conversations-sidebar {
            display: <?php echo $activeConversation ? 'none' : 'flex'; ?> !important;
            width: 100%;
        }

        .chat-area {
            display: <?php echo $activeConversation ? 'flex' : 'none'; ?> !important;
            width: 100%;
            height: 100%;
        }

        .back-to-conversations {
            display: flex !important;
        }
    }

    .back-to-conversations {
        display: none;
        align-items: center;
        gap: 8px;
        color: var(--crimson-light);
        text-decoration: none;
        font-size: 0.9rem;
        margin-bottom: 10px;
        padding: 10px 0;
    }

    .conversations-sidebar {
        border-right: 1px solid var(--glass-border);
        display: flex;
        flex-direction: column;
        background: rgba(10, 14, 26, 0.4);
    }

    .sidebar-header {
        padding: 20px;
        border-bottom: 1px solid var(--glass-border);
        font-weight: 700;
        font-size: 1.1rem;
    }

    .conversation-list {
        flex: 1;
        overflow-y: auto;
    }

    .chat-area {
        display: flex;
        flex-direction: column;
        background: rgba(10, 14, 26, 0.2);
        min-height: 0; /* Crucial for nested flex scrolling */
    }

    .chat-header {
        padding: 12px 16px;
        border-bottom: 1px solid var(--glass-border);
        display: flex;
        align-items: center;
        gap: 12px;
        background: rgba(10, 14, 26, 0.4);
        flex-shrink: 0;
    }

    @media (max-width: 768px) {
        .chat-header {
            padding: 10px 12px;
        }
        
        .back-to-conversations {
            padding: 8px;
            margin-bottom: 0;
            margin-right: 4px;
        }
    }

    .messages-container {
        flex: 1;
        overflow-y: auto;
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        scroll-behavior: smooth;
        min-height: 0; /* Crucial for nested flex scrolling */
    }

    .message-bubble {
        max-width: 70%;
        padding: 10px 16px;
        border-radius: 12px;
        font-size: 0.95rem;
        line-height: 1.4;
        position: relative;
        group: hover;
    }

    .message-bubble:hover .message-actions {
        display: flex;
    }

    .message-actions {
        display: none;
        position: absolute;
        top: -10px;
        right: 10px;
        background: var(--bg-dark-3);
        border: 1px solid var(--glass-border);
        border-radius: 20px;
        padding: 2px 8px;
        gap: 8px;
        z-index: 10;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    }

    .message-received .message-actions {
        left: 10px;
        right: auto;
    }

    .action-btn {
        cursor: pointer;
        font-size: 0.8rem;
        opacity: 0.7;
        transition: opacity 0.2s;
        color: var(--text-primary);
        padding: 2px;
    }

    .action-btn:hover {
        opacity: 1;
    }

    .message-sent {
        align-self: flex-end;
        background: var(--gradient-crimson);
        color: white;
        border-bottom-right-radius: 2px;
    }

    .message-received {
        align-self: flex-start;
        background: var(--bg-dark-3);
        color: var(--text-primary);
        border: 1px solid var(--glass-border);
        border-bottom-left-radius: 2px;
    }

    .message-time {
        font-size: 0.7rem;
        opacity: 0.6;
        margin-top: 4px;
        display: block;
        text-align: right;
    }

    .edited-label {
        font-size: 0.7rem;
        opacity: 0.5;
        font-style: italic;
        margin-right: 4px;
    }

    .edit-mode-indicator {
        background: rgba(230,57,70,0.1);
        padding: 8px 16px;
        border-radius: var(--radius-md);
        margin-bottom: 10px;
        display: none;
        align-items: center;
        justify-content: space-between;
        border: 1px solid rgba(230,57,70,0.2);
    }

    /* Toast Notification */
    .toast {
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%) translateY(100px);
        background: var(--bg-dark-3);
        color: white;
        padding: 12px 24px;
        border-radius: var(--radius-md);
        border: 1px solid var(--glass-border);
        box-shadow: 0 8px 32px rgba(0,0,0,0.5);
        z-index: 1000;
        transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .toast.show {
        transform: translateX(-50%) translateY(0);
    }

    /* Modal Styling */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.8);
        backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 2000;
    }

    .modal {
        background: var(--bg-dark-2);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        padding: 30px;
        width: 100%;
        max-width: 400px;
        text-align: center;
        box-shadow: 0 20px 50px rgba(0,0,0,0.5);
    }

    .modal-buttons {
        display: flex;
        gap: 12px;
        justify-content: center;
        margin-top: 24px;
    }

    .chat-input-area {
        padding: 20px 24px;
        background: rgba(10, 14, 26, 0.4);
        border-top: 1px solid var(--glass-border);
        flex-shrink: 0; /* Stay fixed at bottom */
    }

    .chat-input-form {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .chat-input-form textarea {
        flex: 1;
        background: var(--bg-dark-3);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        padding: 12px 16px;
        color: white;
        resize: none;
        height: 48px;
        line-height: 24px;
    }

    .chat-input-form button {
        height: 48px;
        width: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .unread-badge {
        background: var(--crimson);
        color: white;
        font-size: 0.75rem;
        padding: 2px 8px;
        border-radius: 10px;
        margin-left: auto;
    }

    /* Scrollbar Styling */
    .conversation-list::-webkit-scrollbar,
    .messages-container::-webkit-scrollbar {
        width: 6px;
    }
    .conversation-list::-webkit-scrollbar-thumb,
    .messages-container::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 3px;
    }
</style>

<div class="messages-layout">
    <!-- Sidebar -->
    <div class="conversations-sidebar">
        <div class="sidebar-header">Conversations</div>
        <div class="conversation-list">
            <?php if (count($conversations) > 0): ?>
                <?php foreach ($conversations as $conv): 
                    $other = getUserById($pdo, $conv['other_id']);
                    $oname = 'Unknown';
                    if ($other) {
                        if ($other['role'] === 'donor') {
                            $op = getDonorProfile($pdo, $conv['other_id']);
                            $oname = $op['full_name'] ?? 'Donor';
                        } else {
                            $op = getHospitalProfile($pdo, $conv['other_id']);
                            $oname = $op['hospital_name'] ?? 'Hospital';
                        }
                    }
                    $isActive = ($activeConversation == $conv['other_id']);
                ?>
                <a href="?conversation=<?php echo $conv['other_id']; ?>"
                   class="conversation-item <?php echo $isActive ? 'is-active' : ''; ?>">
                    <div class="avatar-initials">
                        <?php echo strtoupper(substr($oname, 0, 1)); ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="fw-600 fs-95 text-white"><?php echo htmlspecialchars($oname); ?></div>
                        <div class="fs-80 text-muted ellipsis">
                            <?php echo htmlspecialchars($conv['last_content']); ?>
                        </div>
                    </div>
                    <?php if ($conv['unread'] > 0): ?>
                        <span class="unread-badge"><?php echo $conv['unread']; ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-note">No conversations yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Chat -->
    <div class="chat-area">
        <?php if ($activeConversation && $otherUser): ?>
            <div class="chat-header">
                <a href="messages.php" class="back-to-conversations">
                    <span>&#8592;</span> Back
                </a>
                <div class="avatar-initials">
                    <?php echo strtoupper(substr($otherName, 0, 1)); ?>
                </div>
                <div>
                    <div class="fw-700 fs-110"><?php echo htmlspecialchars($otherName); ?></div>
                    <div class="fs-80 text-muted"><?php echo ucfirst($otherUser['role']); ?></div>
                </div>
            </div>

            <div class="messages-container" id="messagesContainer">
                <!-- Messages will be loaded here via AJAX -->
                <div class="text-center p-20">Loading messages...</div>
            </div>

            <div class="chat-input-area">
                <div id="editModeIndicator" class="edit-mode-indicator">
                    <span class="fs-85 text-crimson-light">Editing message...</span>
                    <button type="button" id="cancelEditBtn" class="btn btn-small btn-secondary py-2 px-8 fs-75">Cancel</button>
                </div>
                <form id="chatForm" class="chat-input-form">
                    <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                    <input type="hidden" name="receiver_id" value="<?php echo $activeConversation; ?>">
                    <input type="hidden" name="message_id" id="editMessageId" value="">
                    <textarea name="content" id="messageInput" placeholder="Type a message..." required></textarea>
                    <button type="submit" class="btn btn-primary" id="sendBtn">&#10148;</button>
                </form>
            </div>
        <?php else: ?>
            <div class="flex-1 flex flex-col items-center justify-center opacity-50">
                <div class="fs-500 mb-20">&#9993;</div>
                <h3>Your Inbox</h3>
                <p>Select a conversation to start chatting</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
/* Messaging — jQuery port with XSS-safe rendering (DEF-04).
   User content is NEVER injected as HTML: it is set with .text() and newlines
   are preserved via CSS white-space:pre-wrap. Message actions use data-* on the
   bubble + delegated handlers — no onclick string-building. */
jQuery(function ($) {
    var activeConversation = <?php echo $activeConversation ?: 'null'; ?>;
    var currentUserId = <?php echo (int)$userId; ?>;
    var csrfToken = <?php echo json_encode(csrfToken()); ?>;
    var base = <?php echo json_encode(baseUrl()); ?>;

    var $container = $('#messagesContainer');
    var $form = $('#chatForm');
    var $input = $('#messageInput');
    var $editId = $('#editMessageId');
    var $editIndicator = $('#editModeIndicator');
    var $toast = $('#toast');

    var messageToDelete = null;
    var lastMessageCount = 0;
    var lastFingerprint = null;
    var isEditing = false;
    var pollTimer = null;

    if (!activeConversation) return;

    function showToast(message, type) {
        $('#toastMessage').text(message);
        $('#toastIcon').html(type === 'error' ? '&#10060;' : '&#9989;');
        $toast.addClass('show');
        setTimeout(function () { $toast.removeClass('show'); }, 3000);
    }

    function formatTime(dateStr) {
        var d = new Date((dateStr || '').replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function scrollToBottom() { $container.scrollTop($container[0].scrollHeight); }

    // Build one message bubble safely. Content goes in via .text() only.
    function renderBubble(msg) {
        var isMine = String(msg.sender_id) === String(currentUserId);
        var $bubble = $('<div>')
            .addClass('message-bubble')
            .addClass(isMine ? 'message-sent' : 'message-received')
            .attr('data-id', msg.id)
            .data('content', msg.content);

        var $actions = $('<div>').addClass('message-actions');
        $('<span>').addClass('action-btn js-copy').attr('title', 'Copy').html('&#128203;').appendTo($actions);
        if (isMine) {
            $('<span>').addClass('action-btn js-edit').attr('title', 'Edit').html('&#9998;').appendTo($actions);
            $('<span>').addClass('action-btn js-delete').attr('title', 'Delete').html('&#128465;').appendTo($actions);
        }
        $bubble.append($actions);

        // pre-wrap preserves newlines without any HTML injection
        $('<div>').css({ 'white-space': 'pre-wrap', 'word-wrap': 'break-word' })
                  .text(msg.content)
                  .appendTo($bubble);

        var $meta = $('<div>').css({ display: 'flex', 'justify-content': 'flex-end', 'align-items': 'center' });
        if (String(msg.is_edited) === '1') {
            $('<span>').addClass('edited-label').text('(edited)').appendTo($meta);
        }
        $('<span>').addClass('message-time').text(formatTime(msg.created_at)).appendTo($meta);
        $bubble.append($meta);
        return $bubble;
    }

    function loadMessages() {
        return $.ajax({
            url: base + '/api/get_messages.php',
            method: 'GET',
            data: { conversation: activeConversation },
            dataType: 'json'
        }).done(function (data) {
            if (!data || !data.success) return;
            var fingerprint = data.messages.map(function (m) {
                return m.id + '-' + m.content + '-' + m.is_edited;
            }).join('|');
            if (fingerprint === lastFingerprint) return;

            $container.empty();
            data.messages.forEach(function (msg) { $container.append(renderBubble(msg)); });

            if (data.messages.length > lastMessageCount) scrollToBottom();
            lastMessageCount = data.messages.length;
            lastFingerprint = fingerprint;
        });
    }

    // --- Delegated message actions (no inline handlers) ---
    $container.on('click', '.js-copy', function () {
        var content = $(this).closest('.message-bubble').data('content') || '';
        if (navigator.clipboard) {
            navigator.clipboard.writeText(content).then(function () { showToast('Message copied to clipboard!'); });
        }
    });

    $container.on('click', '.js-edit', function () {
        var $bubble = $(this).closest('.message-bubble');
        isEditing = true;
        $editId.val($bubble.attr('data-id'));
        $input.val($bubble.data('content') || '').focus();
        $editIndicator.css('display', 'flex');
    });

    $container.on('click', '.js-delete', function () {
        messageToDelete = $(this).closest('.message-bubble').attr('data-id');
        $('#deleteModal').css('display', 'flex');
    });

    $('#confirmDeleteBtn').on('click', function () {
        if (!messageToDelete) return;
        $.ajax({
            url: base + '/api/delete_message.php',
            method: 'POST',
            data: { message_id: messageToDelete, csrf_token: csrfToken },
            dataType: 'json'
        }).done(function (data) {
            $('#deleteModal').hide();
            messageToDelete = null;
            if (data && data.success) { showToast('Message deleted'); loadMessages(); }
            else { showToast((data && data.error) || 'Error deleting message', 'error'); }
        }).fail(function () {
            $('#deleteModal').hide();
            showToast('Network error', 'error');
        });
    });

    $('#cancelDeleteBtn').on('click', function () {
        $('#deleteModal').hide();
        messageToDelete = null;
    });

    function exitEditMode() {
        isEditing = false;
        $editId.val('');
        $input.val('');
        $editIndicator.hide();
    }
    $('#cancelEditBtn').on('click', exitEditMode);

    // --- Send / edit ---
    $form.on('submit', function (e) {
        e.preventDefault();
        var content = $.trim($input.val());
        if (!content) return;

        var editing = isEditing;
        var endpoint = editing ? '/api/edit_message.php' : '/api/send_message.php';
        var payload = {
            csrf_token: csrfToken,
            receiver_id: <?php echo (int)$activeConversation; ?>,
            message_id: $editId.val(),
            content: content
        };

        if (!editing) {
            // Optimistic placeholder (rendered safely via .text()).
            var $temp = $('<div>').addClass('message-bubble message-sent').css('opacity', 0.7);
            $('<div>').css({ 'white-space': 'pre-wrap', 'word-wrap': 'break-word' }).text(content).appendTo($temp);
            $('<span>').addClass('message-time').text('Sending…').appendTo($temp);
            $container.append($temp);
            scrollToBottom();
        }

        $input.val('');
        if (editing) exitEditMode();

        $.ajax({ url: base + endpoint, method: 'POST', data: payload, dataType: 'json' })
            .done(function (data) {
                if (data && data.success) { showToast(editing ? 'Message updated' : 'Message sent'); }
                else { showToast((data && data.error) || 'Error saving message', 'error'); }
                loadMessages();
            })
            .fail(function () { showToast('Network error', 'error'); loadMessages(); });
    });

    // Enter to send (Shift+Enter = newline)
    $input.on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            $form.trigger('submit');
        }
    });

    // ---------------------------------------------------------------------------
    // Transport: SSE (FR-37) with AJAX poll fallback for browsers / proxies that
    // don't support EventSource or drop the connection.
    // ---------------------------------------------------------------------------

    var sseActive = false;
    var sseSource = null;
    var sinceMs = 0;   // epoch-ms of newest locally-known message; advanced by SSE events

    function applyNewMessages(msgs) {
        if (!msgs || !msgs.length) return;
        msgs.forEach(function (msg) {
            if (!$container.find('[data-id="' + msg.id + '"]').length) {
                $container.append(renderBubble(msg));
                scrollToBottom();
                lastMessageCount++;
            }
            if (msg.created_ms > sinceMs) sinceMs = msg.created_ms;
        });
    }

    function openSSE() {
        if (!window.EventSource) return false;   // browser doesn't support SSE
        sseSource = new EventSource(base + '/api/stream.php?conversation=' + activeConversation + '&since=' + sinceMs);
        sseActive = true;

        sseSource.addEventListener('messages', function (e) {
            try {
                var data = JSON.parse(e.data);
                if (data.messages) applyNewMessages(data.messages);
            } catch (ex) {}
        });

        sseSource.addEventListener('close', function () {
            // Server closed the 30-s window; reconnect with updated `since`.
            sseSource.close();
            sseSource = null;
            openSSE();
        });

        sseSource.onerror = function () {
            // SSE failed (proxy stripped, server error, etc.) — fall back to poll.
            sseSource.close();
            sseSource = null;
            sseActive = false;
            startPolling();
        };
        return true;
    }

    // Poll every 3 s — used when SSE is unavailable or as the hidden-tab throttle.
    function startPolling() { if (!pollTimer) pollTimer = setInterval(loadMessages, 3000); }
    function stopPolling()  { clearInterval(pollTimer); pollTimer = null; }

    // Pause SSE (and poll) when the tab is hidden; resume on visibility (Doc 12).
    $(document).on('visibilitychange', function () {
        if (document.hidden) {
            if (sseSource) { sseSource.close(); sseSource = null; }
            stopPolling();
        } else {
            loadMessages();   // catch up immediately
            if (!openSSE()) startPolling();
        }
    });

    // Initial load, then open SSE (fall back to poll if unavailable).
    loadMessages().done(function () {
        if (!openSSE()) startPolling();
    });
});
</script>

<?php include 'includes/footer.php'; ?>

<!-- Custom Toast -->
<div id="toast" class="toast">
    <span id="toastIcon"></span>
    <span id="toastMessage"></span>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal">
        <div class="fs-300 text-crimson-base mb-15">&#128465;</div>
        <h3 class="mb-10">Delete Message?</h3>
        <p class="text-muted fs-95">This action cannot be undone. The message will be removed for both parties.</p>
        <div class="modal-buttons">
            <button type="button" id="confirmDeleteBtn" class="btn btn-primary">Delete</button>
            <button type="button" id="cancelDeleteBtn" class="btn btn-secondary">Cancel</button>
        </div>
    </div>
</div>
