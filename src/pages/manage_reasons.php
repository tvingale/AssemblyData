<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/admin_auth.php';

$baseUrl = '..';
requireAdminAuth();

$db = getDB();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $reasonText = trim($_POST['reason_text'] ?? '');
        $displayOrder = (int)($_POST['display_order'] ?? 0);

        if ($reasonText) {
            $stmt = $db->prepare('INSERT INTO deficit_reasons (reason_text, display_order) VALUES (?, ?)');
            $stmt->execute([$reasonText, $displayOrder]);
            $message = 'Reason added successfully.';
            $messageType = 'success';
        } else {
            $message = 'Reason text is required.';
            $messageType = 'danger';
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $reasonText = trim($_POST['reason_text'] ?? '');
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $db->prepare('UPDATE deficit_reasons SET reason_text = ?, display_order = ?, is_active = ? WHERE id = ?');
        $stmt->execute([$reasonText, $displayOrder, $isActive, $id]);
        $message = 'Reason updated successfully.';
        $messageType = 'success';
    }
}

$reasons = $db->query('SELECT * FROM deficit_reasons ORDER BY display_order, id')->fetchAll();

$pageTitle = 'Manage Deficit Reasons';
$baseUrl = '..';
include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
    <h1>Manage Deficit Reasons</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= h($message) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">Add New Reason</div>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="form-row">
            <div class="form-group">
                <label>Reason Text</label>
                <input type="text" name="reason_text" required>
            </div>
            <div class="form-group">
                <label>Display Order</label>
                <input type="number" name="display_order" min="0" value="0">
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Add Reason</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">Deficit Reasons</div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Reason Text</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reasons as $r): ?>
                <tr>
                    <form method="POST">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <td><input type="number" name="display_order" value="<?= h($r['display_order']) ?>" min="0" style="width:60px"></td>
                        <td><input type="text" name="reason_text" value="<?= h($r['reason_text']) ?>" style="width:250px"></td>
                        <td>
                            <label>
                                <input type="checkbox" name="is_active" <?= $r['is_active'] ? 'checked' : '' ?>>
                                Active
                            </label>
                        </td>
                        <td>
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                        </td>
                    </form>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($reasons)): ?>
                <tr><td colspan="4">No reasons configured.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
