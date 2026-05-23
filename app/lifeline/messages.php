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
                   style="display: flex; align-items: center; gap: 12px; padding: 16px 20px; text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.05); background: <?php echo $isActive ? 'rgba(230,57,70,0.15)' : 'transparent'; ?>;">
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--gradient-crimson); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; flex-shrink: 0;">
                        <?php echo strtoupper(substr($oname, 0, 1)); ?>
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 600; font-size: 0.95rem; color: white;"><?php echo htmlspecialchars($oname); ?></div>
                        <div style="font-size: 0.8rem; color: #9ca3af; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <?php echo htmlspecialchars($conv['last_content']); ?>
                        </div>
                    </div>
                    <?php if ($conv['unread'] > 0): ?>
                        <span class="unread-badge"><?php echo $conv['unread']; ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding: 40px 20px; text-align: center; color: #6b7280;">No conversations yet.</div>
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
                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--gradient-crimson); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; flex-shrink: 0;">
                    <?php echo strtoupper(substr($otherName, 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 700; font-size: 1.1rem;"><?php echo htmlspecialchars($otherName); ?></div>
                    <div style="font-size: 0.8rem; color: #9ca3af;"><?php echo ucfirst($otherUser['role']); ?></div>
                </div>
            </div>

            <div class="messages-container" id="messagesContainer">
                <!-- Messages will be loaded here via AJAX -->
                <div style="text-align: center; padding: 20px;">Loading messages...</div>
            </div>

            <div class="chat-input-area">
                <div id="editModeIndicator" class="edit-mode-indicator">
                    <span style="font-size: 0.85rem; color: var(--crimson-light);">Editing message...</span>
                    <button type="button" id="cancelEditBtn" class="btn btn-small btn-secondary" style="padding: 2px 8px; font-size: 0.75rem;">Cancel</button>
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
            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; opacity: 0.5;">
                <div style="font-size: 5rem; margin-bottom: 20px;">&#9993;</div>
                <h3>Your Inbox</h3>
                <p>Select a conversation to start chatting</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('messagesContainer');
    const form = document.getElementById('chatForm');
    const input = document.getElementById('messageInput');
    const editMessageIdInput = document.getElementById('editMessageId');
    const editModeIndicator = document.getElementById('editModeIndicator');
    const cancelEditBtn = document.getElementById('cancelEditBtn');
    
    const activeConversation = <?php echo $activeConversation ?: 'null'; ?>;
    const currentUserId = <?php echo $userId; ?>;
    
    const toast = document.getElementById('toast');
    const toastMsg = document.getElementById('toastMessage');
    const toastIcon = document.getElementById('toastIcon');
    const deleteModal = document.getElementById('deleteModal');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    
    let messageToDelete = null;
    let lastMessageCount = 0;
    let isEditing = false;

    if (!activeConversation) return;

    function showToast(message, type = 'success') {
        toastMsg.innerText = message;
        toastIcon.innerHTML = type === 'success' ? '&#9989;' : '&#10060;';
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3000);
    }

    function formatTime(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function scrollToBottom() {
        container.scrollTop = container.scrollHeight;
    }

    window.copyMessage = function(content) {
        navigator.clipboard.writeText(content).then(() => {
            showToast('Message copied to clipboard!');
        });
    };

    window.editMessage = function(id, content) {
        isEditing = true;
        editMessageIdInput.value = id;
        input.value = content;
        editModeIndicator.style.display = 'flex';
        input.focus();
    };

    window.deleteMessage = function(id) {
        messageToDelete = id;
        deleteModal.style.display = 'flex';
    };

    confirmDeleteBtn.addEventListener('click', function() {
        if (!messageToDelete) return;
        
        const formData = new FormData();
        formData.append('message_id', messageToDelete);
        formData.append('csrf_token', '<?php echo csrfToken(); ?>');

        fetch('<?php echo baseUrl(); ?>/api/delete_message.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            deleteModal.style.display = 'none';
            messageToDelete = null;
            if (data.success) {
                showToast('Message deleted');
                loadMessages();
            } else {
                showToast(data.error || 'Error deleting message', 'error');
            }
        });
    });

    cancelDeleteBtn.addEventListener('click', () => {
        deleteModal.style.display = 'none';
        messageToDelete = null;
    });

    cancelEditBtn.addEventListener('click', function() {
        isEditing = false;
        editMessageIdInput.value = '';
        input.value = '';
        editModeIndicator.style.display = 'none';
    });

    function loadMessages() {
        fetch('<?php echo baseUrl(); ?>/api/get_messages.php?conversation=' + activeConversation)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Create a unique fingerprint of the current messages (IDs + Content + Edit status)
                    const currentFingerprint = data.messages.map(m => `${m.id}-${m.content}-${m.is_edited}`).join('|');
                    
                    if (currentFingerprint !== window.lastMessagesFingerprint) {
                        container.innerHTML = '';
                        data.messages.forEach(msg => {
                            const isMine = msg.sender_id == currentUserId;
                            const div = document.createElement('div');
                            div.className = `message-bubble ${isMine ? 'message-sent' : 'message-received'}`;
                            
                            let actionsHtml = `
                                <div class="message-actions">
                                    <span class="action-btn" onclick="copyMessage('${msg.content.replace(/'/g, "\\'")}')" title="Copy">&#128203;</span>
                                    ${isMine ? `
                                        <span class="action-btn" onclick="editMessage(${msg.id}, '${msg.content.replace(/'/g, "\\'")}')" title="Edit">&#9998;</span>
                                        <span class="action-btn" onclick="deleteMessage(${msg.id})" title="Delete">&#128465;</span>
                                    ` : ''}
                                </div>
                            `;

                            div.innerHTML = `
                                ${actionsHtml}
                                <div>${msg.content.replace(/\n/g, '<br>')}</div>
                                <div style="display:flex; justify-content:flex-end; align-items:center;">
                                    ${msg.is_edited == 1 ? '<span class="edited-label">(edited)</span>' : ''}
                                    <span class="message-time">${formatTime(msg.created_at)}</span>
                                </div>
                            `;
                            container.appendChild(div);
                        });
                        
                        // Only scroll to bottom if a NEW message was added, not if an old one was edited
                        if (data.messages.length > lastMessageCount) {
                            scrollToBottom();
                        }
                        
                        lastMessageCount = data.messages.length;
                        window.lastMessagesFingerprint = currentFingerprint;
                    }
                }
            });
    }

    // Initial load
    loadMessages();

    // Poll for new messages every 3 seconds
    setInterval(loadMessages, 3000);

    // Handle form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const content = input.value.trim();
        if (!content) return;

        const formData = new FormData(form);
        const endpoint = isEditing ? 'api/edit_message.php' : 'api/send_message.php';
        
        if (!isEditing) {
            // Optimistically add message to UI only if sending new
            const tempDiv = document.createElement('div');
            tempDiv.className = 'message-bubble message-sent';
            tempDiv.style.opacity = '0.7';
            tempDiv.innerHTML = `<div>${content.replace(/\n/g, '<br>')}</div><span class="message-time">Sending...</span>`;
            container.appendChild(tempDiv);
            scrollToBottom();
        }
        
        input.value = '';
        if (isEditing) {
            isEditing = false;
            editMessageIdInput.value = '';
            editModeIndicator.style.display = 'none';
        }

        fetch('<?php echo baseUrl(); ?>/' + endpoint, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(isEditing ? 'Message updated' : 'Message sent');
                loadMessages();
            } else {
                showToast(data.error || 'Error saving message', 'error');
                loadMessages(); // Refresh to clean up optimistic UI
            }
        });
    });

    // Handle Enter to send
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit'));
        }
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
        <div style="font-size: 3rem; color: var(--crimson); margin-bottom: 15px;">&#128465;</div>
        <h3 style="margin-bottom: 10px;">Delete Message?</h3>
        <p style="color: var(--text-muted); font-size: 0.95rem;">This action cannot be undone. The message will be removed for both parties.</p>
        <div class="modal-buttons">
            <button type="button" id="confirmDeleteBtn" class="btn btn-primary">Delete</button>
            <button type="button" id="cancelDeleteBtn" class="btn btn-secondary">Cancel</button>
        </div>
    </div>
</div>
