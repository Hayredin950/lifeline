<?php
require_once '../includes/functions.php';
requireAdmin();

// Handle key creation / revocation.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name      = trim($_POST['name'] ?? '');
        $scopes    = array_filter(array_map('trim', explode(',', $_POST['scopes'] ?? '*')));
        $rateLimit = max(1, min(1000, (int)($_POST['rate_limit'] ?? 60)));
        $userId    = ($_POST['user_id'] ?? '') !== '' ? (int)$_POST['user_id'] : null;

        if ($name === '') {
            setFlash('Key name is required.', 'danger');
            redirect(baseUrl() . '/admin/api_keys.php');
        }

        $raw  = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);

        $pdo->prepare("
            INSERT INTO api_keys (name, key_hash, user_id, scopes, rate_limit)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$name, $hash, $userId, json_encode(array_values($scopes)), $rateLimit]);

        // Show the raw key once — it cannot be recovered from the stored hash.
        setFlash('Key created. Copy it now — it will not be shown again: <code>' . htmlspecialchars($raw) . '</code>', 'success');
        redirect(baseUrl() . '/admin/api_keys.php');
    }

    if ($action === 'revoke') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE api_keys SET is_active = 0 WHERE id = ?")->execute([$id]);
        setFlash('API key revoked.', 'success');
        redirect(baseUrl() . '/admin/api_keys.php');
    }
}

$keys = $pdo->query("SELECT id, name, user_id, scopes, rate_limit, is_active, last_used_at, created_at FROM api_keys ORDER BY id DESC")->fetchAll();

include '../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1>API Keys</h1>
        <a href="<?php echo baseUrl(); ?>/admin/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    <p class="text-muted fs-90 mb-20">
        Keys are stored as SHA-256 hashes. The raw key is shown only at creation time.
        See <code>docs/openapi.yaml</code> for the full API spec.
    </p>

    <h3 class="mb-12">Issue New Key</h3>
    <form method="POST" action="" class="card mb-24">
        <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
        <input type="hidden" name="action" value="create">
        <div class="grid-autofit-180 gap-12">
            <div class="form-group">
                <label>Key Name</label>
                <input type="text" name="name" required placeholder="e.g. Mobile App v1">
            </div>
            <div class="form-group">
                <label>Scopes (comma-separated)</label>
                <input type="text" name="scopes" value="*" placeholder="donors:read, requests:read">
                <small class="field-hint">* = all scopes</small>
            </div>
            <div class="form-group">
                <label>Rate limit (req/min)</label>
                <input type="number" name="rate_limit" value="60" min="1" max="1000">
            </div>
            <div class="form-group">
                <label>Linked User ID (optional)</label>
                <input type="number" name="user_id" placeholder="Hospital/donor user_id">
            </div>
        </div>
        <button type="submit" class="btn">Issue Key</button>
    </form>

    <h3 class="mb-12">Existing Keys</h3>
    <?php if (!$keys): ?>
        <p class="text-muted">No API keys yet.</p>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th><th>Name</th><th>Scopes</th>
                    <th>Rate/min</th><th>Status</th><th>Last Used</th><th>Created</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($keys as $k): ?>
                <tr>
                    <td><?php echo (int)$k['id']; ?></td>
                    <td><?php echo htmlspecialchars($k['name']); ?></td>
                    <td><code class="fs-80"><?php echo htmlspecialchars($k['scopes'] ?? '*'); ?></code></td>
                    <td><?php echo (int)$k['rate_limit']; ?></td>
                    <td><?php echo $k['is_active'] ? '<span class="text-success-dark fw-600">Active</span>' : '<span class="text-muted">Revoked</span>'; ?></td>
                    <td class="fs-85"><?php echo $k['last_used_at'] ? date('M j Y', strtotime($k['last_used_at'])) : '—'; ?></td>
                    <td class="fs-85"><?php echo date('M j Y', strtotime($k['created_at'])); ?></td>
                    <td>
                        <?php if ($k['is_active']): ?>
                        <form method="POST" action="" style="display:inline">
                            <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                            <input type="hidden" name="action" value="revoke">
                            <input type="hidden" name="id" value="<?php echo (int)$k['id']; ?>">
                            <button type="submit" class="btn btn-small bg-danger-dark"
                                    onclick="return confirm('Revoke this key?')">Revoke</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
