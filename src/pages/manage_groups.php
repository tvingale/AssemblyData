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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $defaultCells = (int)($_POST['default_cells'] ?? 1);
        $rate = (float)($_POST['rate'] ?? 0);
        $displayOrder = (int)($_POST['display_order'] ?? 0);

        if ($name === '') {
            $message = 'Group name is required.';
            $messageType = 'danger';
        } else {
            $stmt = $db->prepare('INSERT INTO production_groups (name, default_cells, expected_output_per_cell_per_hour, display_order) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $defaultCells, $rate, $displayOrder]);
            $message = 'Group added successfully.';
            $messageType = 'success';
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $defaultCells = (int)($_POST['default_cells'] ?? 1);
        $rate = (float)($_POST['rate'] ?? 0);
        $displayOrder = (int)($_POST['display_order'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            $message = 'Group name is required.';
            $messageType = 'danger';
        } else {
            $stmt = $db->prepare('UPDATE production_groups SET name = ?, default_cells = ?, expected_output_per_cell_per_hour = ?, display_order = ?, is_active = ? WHERE id = ?');
            $stmt->execute([$name, $defaultCells, $rate, $displayOrder, $isActive, $id]);
            $message = 'Group updated successfully.';
            $messageType = 'success';
        }
    }
}

$groups = getAllGroups();
$editGroup = null;
if (isset($_GET['edit'])) {
    $editGroup = getGroup((int)$_GET['edit']);
}

$pageTitle = 'Manage Groups';
$baseUrl = '..';
include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
    <h1>Manage Production Groups</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= h($message) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><?= $editGroup ? 'Edit Group' : 'Add New Group' ?></div>
    <form method="POST">
        <input type="hidden" name="action" value="<?= $editGroup ? 'edit' : 'add' ?>">
        <?php if ($editGroup): ?>
            <input type="hidden" name="id" value="<?= $editGroup['id'] ?>">
        <?php endif; ?>

        <div class="form-row">
            <div class="form-group">
                <label>Group Name</label>
                <input type="text" name="name" value="<?= h($editGroup['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Default Cells</label>
                <input type="number" name="default_cells" min="1" max="50" value="<?= h($editGroup['default_cells'] ?? 1) ?>" required>
            </div>
            <div class="form-group">
                <label>Output per Cell per Hour</label>
                <input type="number" name="rate" step="0.01" min="0" value="<?= h($editGroup['expected_output_per_cell_per_hour'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label>Display Order</label>
                <input type="number" name="display_order" min="0" value="<?= h($editGroup['display_order'] ?? 0) ?>">
            </div>
        </div>

        <?php if ($editGroup): ?>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" <?= $editGroup['is_active'] ? 'checked' : '' ?>>
                    Active
                </label>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <button type="submit" class="btn btn-primary"><?= $editGroup ? 'Update Group' : 'Add Group' ?></button>
            <?php if ($editGroup): ?>
                <a href="manage_groups.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">Production Groups</div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Name</th>
                    <th class="num">Default Cells</th>
                    <th class="num">Rate/Cell/Hour</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groups as $g): ?>
                <tr>
                    <td><?= h($g['display_order']) ?></td>
                    <td><?= h($g['name']) ?></td>
                    <td class="num"><?= h($g['default_cells']) ?></td>
                    <td class="num"><?= h($g['expected_output_per_cell_per_hour']) ?></td>
                    <td>
                        <span class="badge <?= $g['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                            <?= $g['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <a href="?edit=<?= $g['id'] ?>" class="btn btn-sm btn-outline">Edit</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($groups)): ?>
                <tr><td colspan="6">No groups configured yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
