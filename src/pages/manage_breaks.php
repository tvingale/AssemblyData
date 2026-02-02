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
        $breakType = $_POST['break_type'] ?? 'lunch';
        $label = trim($_POST['label'] ?? '');
        $isDefault = isset($_POST['is_default']) ? 1 : 0;
        $dayType = $_POST['day_type'] ?? 'all';
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';

        if ($startTime && $endTime && $label) {
            $stmt = $db->prepare('INSERT INTO breaks (break_type, label, is_default, day_type, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$breakType, $label, $isDefault, $dayType, $startTime, $endTime]);
            $message = 'Break added successfully.';
            $messageType = 'success';
        } else {
            $message = 'All fields are required.';
            $messageType = 'danger';
        }
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $breakType = $_POST['break_type'] ?? 'lunch';
        $label = trim($_POST['label'] ?? '');
        $dayType = $_POST['day_type'] ?? 'all';
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';

        $stmt = $db->prepare('UPDATE breaks SET break_type = ?, label = ?, day_type = ?, start_time = ?, end_time = ? WHERE id = ?');
        $stmt->execute([$breakType, $label, $dayType, $startTime, $endTime, $id]);
        $message = 'Break updated successfully.';
        $messageType = 'success';
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare('DELETE FROM breaks WHERE id = ?');
        $stmt->execute([$id]);
        $message = 'Break deleted.';
        $messageType = 'success';
    }
}

$breaks = $db->query('SELECT * FROM breaks WHERE is_default = 1 ORDER BY break_type, start_time')->fetchAll();

$pageTitle = 'Manage Breaks';
$baseUrl = '..';
include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
    <h1>Manage Default Breaks</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= h($message) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">Add Default Break</div>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="is_default" value="1">
        <div class="form-row">
            <div class="form-group">
                <label>Type</label>
                <select name="break_type">
                    <option value="lunch">Lunch</option>
                    <option value="tea">Tea</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Label</label>
                <input type="text" name="label" placeholder="e.g. Lunch Break" required>
            </div>
            <div class="form-group">
                <label>Day Type</label>
                <select name="day_type">
                    <option value="all">All Days</option>
                    <option value="sun_fri">Sun-Fri</option>
                    <option value="sat">Saturday</option>
                </select>
            </div>
            <div class="form-group">
                <label>Start Time</label>
                <input type="time" name="start_time" required>
            </div>
            <div class="form-group">
                <label>End Time</label>
                <input type="time" name="end_time" required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Add Break</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">Default Breaks</div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Label</th>
                    <th>Day Type</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Duration (min)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($breaks as $b): ?>
                <tr>
                    <form method="POST">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                        <td>
                            <select name="break_type">
                                <option value="lunch" <?= $b['break_type'] === 'lunch' ? 'selected' : '' ?>>Lunch</option>
                                <option value="tea" <?= $b['break_type'] === 'tea' ? 'selected' : '' ?>>Tea</option>
                                <option value="other" <?= $b['break_type'] === 'other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </td>
                        <td><input type="text" name="label" value="<?= h($b['label']) ?>" style="width:140px"></td>
                        <td>
                            <select name="day_type">
                                <option value="all" <?= $b['day_type'] === 'all' ? 'selected' : '' ?>>All</option>
                                <option value="sun_fri" <?= $b['day_type'] === 'sun_fri' ? 'selected' : '' ?>>Sun-Fri</option>
                                <option value="sat" <?= $b['day_type'] === 'sat' ? 'selected' : '' ?>>Saturday</option>
                            </select>
                        </td>
                        <td><input type="time" name="start_time" value="<?= h(substr($b['start_time'], 0, 5)) ?>"></td>
                        <td><input type="time" name="end_time" value="<?= h(substr($b['end_time'], 0, 5)) ?>"></td>
                        <td class="num"><?= round((strtotime($b['end_time']) - strtotime($b['start_time'])) / 60) ?></td>
                        <td>
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                    </form>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this break?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                        </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($breaks)): ?>
                <tr><td colspan="7">No default breaks configured.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include APP_ROOT . '/includes/footer.php'; ?>
