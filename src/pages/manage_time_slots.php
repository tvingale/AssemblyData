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

$activeTab = $_GET['tab'] ?? 'sun_fri';
if (!in_array($activeTab, ['sun_fri', 'sat'])) $activeTab = 'sun_fri';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $dayType = $_POST['day_type'] ?? 'sun_fri';

    if ($action === 'add') {
        $slotNumber = (int)$_POST['slot_number'];
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $label = trim($_POST['label'] ?? '');

        if ($startTime && $endTime) {
            try {
                $stmt = $db->prepare('INSERT INTO default_time_slots (day_type, slot_number, start_time, end_time, label) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$dayType, $slotNumber, $startTime, $endTime, $label ?: "Slot $slotNumber"]);
                $message = 'Slot added successfully.';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Slot number already exists for this day type.';
                $messageType = 'danger';
            }
        } else {
            $message = 'Start and end times are required.';
            $messageType = 'danger';
        }
        $activeTab = $dayType;
    } elseif ($action === 'edit') {
        $id = (int)$_POST['id'];
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';
        $label = trim($_POST['label'] ?? '');

        $stmt = $db->prepare('UPDATE default_time_slots SET start_time = ?, end_time = ?, label = ? WHERE id = ?');
        $stmt->execute([$startTime, $endTime, $label, $id]);
        $message = 'Slot updated successfully.';
        $messageType = 'success';
        $activeTab = $dayType;
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare('DELETE FROM default_time_slots WHERE id = ?');
        $stmt->execute([$id]);
        $message = 'Slot deleted.';
        $messageType = 'success';
        $activeTab = $dayType;
    }
}

$sunFriSlots = $db->query("SELECT * FROM default_time_slots WHERE day_type = 'sun_fri' ORDER BY slot_number")->fetchAll();
$satSlots = $db->query("SELECT * FROM default_time_slots WHERE day_type = 'sat' ORDER BY slot_number")->fetchAll();

// Next slot number
$nextSunFri = 1;
if (!empty($sunFriSlots)) {
    $nextSunFri = max(array_column($sunFriSlots, 'slot_number')) + 1;
}
$nextSat = 1;
if (!empty($satSlots)) {
    $nextSat = max(array_column($satSlots, 'slot_number')) + 1;
}

$pageTitle = 'Manage Time Slots';
$baseUrl = '..';
include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
    <h1>Manage Default Time Slots</h1>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= h($message) ?></div>
<?php endif; ?>

<div class="tabs">
    <button class="tab-btn <?= $activeTab === 'sun_fri' ? 'active' : '' ?>" data-tab="tab-sun-fri">Sunday - Friday</button>
    <button class="tab-btn <?= $activeTab === 'sat' ? 'active' : '' ?>" data-tab="tab-sat">Saturday</button>
</div>

<?php
function renderSlotTable($slots, $dayType, $nextSlot) {
?>
<div class="card" style="margin-bottom: 1rem;">
    <div class="card-header">Add New Slot</div>
    <form method="POST">
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="day_type" value="<?= $dayType ?>">
        <div class="form-row">
            <div class="form-group">
                <label>Slot #</label>
                <input type="number" name="slot_number" min="1" value="<?= $nextSlot ?>" required>
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
                <label>Label</label>
                <input type="text" name="label" placeholder="e.g. Slot <?= $nextSlot ?>">
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Add Slot</button>
            </div>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Slot #</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Duration (min)</th>
                    <th>Label</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($slots as $s): ?>
                <tr>
                    <form method="POST">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                        <input type="hidden" name="day_type" value="<?= $dayType ?>">
                        <td><?= h($s['slot_number']) ?></td>
                        <td><input type="time" name="start_time" value="<?= h(substr($s['start_time'], 0, 5)) ?>"></td>
                        <td><input type="time" name="end_time" value="<?= h(substr($s['end_time'], 0, 5)) ?>"></td>
                        <td class="num"><?= round((strtotime($s['end_time']) - strtotime($s['start_time'])) / 60) ?></td>
                        <td><input type="text" name="label" value="<?= h($s['label']) ?>" style="width:120px"></td>
                        <td>
                            <button type="submit" class="btn btn-sm btn-primary">Save</button>
                    </form>
                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this slot?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                        <input type="hidden" name="day_type" value="<?= $dayType ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                        </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($slots)): ?>
                <tr><td colspan="6">No slots configured.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php } ?>

<div id="tab-sun-fri" class="tab-content <?= $activeTab === 'sun_fri' ? 'active' : '' ?>">
    <?php renderSlotTable($sunFriSlots, 'sun_fri', $nextSunFri); ?>
</div>

<div id="tab-sat" class="tab-content <?= $activeTab === 'sat' ? 'active' : '' ?>">
    <?php renderSlotTable($satSlots, 'sat', $nextSat); ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    App.initTabs(document.querySelector('.container'));
});
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
