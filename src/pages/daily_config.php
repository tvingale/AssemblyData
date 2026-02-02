<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/admin_auth.php';

$baseUrl = '..';
requireAdminAuth();

$date = $_GET['date'] ?? date('Y-m-d');
$db = getDB();

// Get current overrides
$stmt = $db->prepare('SELECT * FROM daily_shift_config WHERE production_date = ?');
$stmt->execute([$date]);
$shiftOverride = $stmt->fetch();

$stmt = $db->prepare('SELECT * FROM daily_time_slots WHERE production_date = ? ORDER BY slot_number');
$stmt->execute([$date]);
$slotOverrides = $stmt->fetchAll();

$stmt = $db->prepare('SELECT * FROM breaks WHERE production_date = ? ORDER BY start_time');
$stmt->execute([$date]);
$breakOverrides = $stmt->fetchAll();

$shiftTimes = getShiftTimes($date);
$defaultSlots = resolveTimeSlots($date);

$message = '';
$messageType = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_shift') {
        $shiftStart = $_POST['shift_start'] ?? '';
        $shiftEnd = $_POST['shift_end'] ?? '';
        $notes = trim($_POST['notes'] ?? '');

        if ($shiftStart && $shiftEnd) {
            $stmt = $db->prepare('INSERT INTO daily_shift_config (production_date, shift_start, shift_end, notes)
                                  VALUES (?, ?, ?, ?)
                                  ON DUPLICATE KEY UPDATE shift_start = VALUES(shift_start), shift_end = VALUES(shift_end), notes = VALUES(notes)');
            $stmt->execute([$date, $shiftStart, $shiftEnd, $notes]);
            $message = 'Shift override saved.';
            $messageType = 'success';
        }
    } elseif ($action === 'delete_shift') {
        $stmt = $db->prepare('DELETE FROM daily_shift_config WHERE production_date = ?');
        $stmt->execute([$date]);
        $message = 'Shift override removed.';
        $messageType = 'success';
    } elseif ($action === 'save_slots') {
        // Delete existing and re-insert
        $stmt = $db->prepare('DELETE FROM daily_time_slots WHERE production_date = ?');
        $stmt->execute([$date]);

        $slotNums = $_POST['slot_number'] ?? [];
        $startTimes = $_POST['slot_start'] ?? [];
        $endTimes = $_POST['slot_end'] ?? [];
        $labels = $_POST['slot_label'] ?? [];

        $insert = $db->prepare('INSERT INTO daily_time_slots (production_date, slot_number, start_time, end_time, label) VALUES (?, ?, ?, ?, ?)');
        for ($i = 0; $i < count($slotNums); $i++) {
            if (!empty($startTimes[$i]) && !empty($endTimes[$i])) {
                $insert->execute([$date, (int)$slotNums[$i], $startTimes[$i], $endTimes[$i], $labels[$i] ?? '']);
            }
        }
        $message = 'Slot overrides saved.';
        $messageType = 'success';
    } elseif ($action === 'delete_slots') {
        $stmt = $db->prepare('DELETE FROM daily_time_slots WHERE production_date = ?');
        $stmt->execute([$date]);
        $message = 'Slot overrides removed (will use defaults).';
        $messageType = 'success';
    } elseif ($action === 'add_break') {
        $breakType = $_POST['break_type'] ?? 'lunch';
        $label = trim($_POST['label'] ?? '');
        $startTime = $_POST['start_time'] ?? '';
        $endTime = $_POST['end_time'] ?? '';

        if ($startTime && $endTime) {
            $stmt = $db->prepare('INSERT INTO breaks (break_type, label, is_default, production_date, start_time, end_time) VALUES (?, ?, 0, ?, ?, ?)');
            $stmt->execute([$breakType, $label, $date, $startTime, $endTime]);
            $message = 'Break override added.';
            $messageType = 'success';
        }
    } elseif ($action === 'delete_break') {
        $id = (int)$_POST['id'];
        $stmt = $db->prepare('DELETE FROM breaks WHERE id = ? AND production_date = ?');
        $stmt->execute([$id, $date]);
        $message = 'Break override removed.';
        $messageType = 'success';
    }

    // Refresh data
    header("Location: ?date=$date");
    exit;
}

$pageTitle = 'Daily Config';
include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
    <h1>Daily Configuration Overrides</h1>
</div>

<div class="date-bar">
    <label for="cfg-date">Date:</label>
    <input type="date" id="cfg-date" value="<?= h($date) ?>" onchange="window.location='?date='+this.value">
    <span class="shift-info"><?= formatDate($date) ?> (<?= getDayType($date) === 'sat' ? 'Saturday' : 'Sun-Fri' ?>)</span>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= h($message) ?></div>
<?php endif; ?>

<!-- Shift Override -->
<div class="card">
    <div class="card-header">Shift Time Override</div>
    <p style="font-size:0.85rem;color:var(--gray-500);margin-bottom:0.75rem;">
        Current: <?= formatTime($shiftTimes['start']) ?> - <?= formatTime($shiftTimes['end']) ?>
        <?= $shiftTimes['is_override'] ? ' <strong>[Override active]</strong>' : ' (default)' ?>
    </p>
    <form method="POST">
        <input type="hidden" name="action" value="save_shift">
        <div class="form-row">
            <div class="form-group">
                <label>Shift Start</label>
                <input type="time" name="shift_start" value="<?= h($shiftOverride ? substr($shiftOverride['shift_start'], 0, 5) : substr($shiftTimes['start'], 0, 5)) ?>" required>
            </div>
            <div class="form-group">
                <label>Shift End</label>
                <input type="time" name="shift_end" value="<?= h($shiftOverride ? substr($shiftOverride['shift_end'], 0, 5) : substr($shiftTimes['end'], 0, 5)) ?>" required>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <input type="text" name="notes" value="<?= h($shiftOverride['notes'] ?? '') ?>">
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Save Override</button>
            </div>
        </div>
    </form>
    <?php if ($shiftOverride): ?>
    <form method="POST" style="margin-top:0.5rem;" onsubmit="return confirm('Remove shift override?')">
        <input type="hidden" name="action" value="delete_shift">
        <button type="submit" class="btn btn-sm btn-danger">Remove Override</button>
    </form>
    <?php endif; ?>
</div>

<!-- Slot Overrides -->
<div class="card">
    <div class="card-header">
        Time Slot Overrides
        <?php if (!empty($slotOverrides)): ?>
            <span class="badge badge-warning">Override active</span>
        <?php endif; ?>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="save_slots">
        <div class="table-responsive">
            <table class="table-compact" id="slot-override-table">
                <thead>
                    <tr>
                        <th>Slot #</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Label</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $displaySlots = !empty($slotOverrides) ? $slotOverrides : $defaultSlots;
                    foreach ($displaySlots as $s):
                    ?>
                    <tr>
                        <td><input type="number" name="slot_number[]" value="<?= $s['slot_number'] ?>" min="1" style="width:60px"></td>
                        <td><input type="time" name="slot_start[]" value="<?= substr($s['start_time'], 0, 5) ?>"></td>
                        <td><input type="time" name="slot_end[]" value="<?= substr($s['end_time'], 0, 5) ?>"></td>
                        <td><input type="text" name="slot_label[]" value="<?= h($s['label'] ?? '') ?>" style="width:120px"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:0.5rem;display:flex;gap:0.5rem;">
            <button type="submit" class="btn btn-primary">Save Slot Overrides</button>
            <button type="button" class="btn btn-sm btn-outline" onclick="addSlotRow()">+ Add Slot</button>
        </div>
    </form>
    <?php if (!empty($slotOverrides)): ?>
    <form method="POST" style="margin-top:0.5rem;" onsubmit="return confirm('Remove all slot overrides? Defaults will be used.')">
        <input type="hidden" name="action" value="delete_slots">
        <button type="submit" class="btn btn-sm btn-danger">Remove All Overrides</button>
    </form>
    <?php endif; ?>
</div>

<!-- Break Overrides -->
<div class="card">
    <div class="card-header">Break Overrides for <?= formatDate($date) ?></div>
    <form method="POST">
        <input type="hidden" name="action" value="add_break">
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
                <input type="text" name="label" placeholder="e.g. Extended Lunch">
            </div>
            <div class="form-group">
                <label>Start</label>
                <input type="time" name="start_time" required>
            </div>
            <div class="form-group">
                <label>End</label>
                <input type="time" name="end_time" required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Add Break</button>
            </div>
        </div>
    </form>

    <?php if (!empty($breakOverrides)): ?>
    <div class="table-responsive" style="margin-top:1rem;">
        <table class="table-compact">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Label</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($breakOverrides as $b): ?>
                <tr>
                    <td><?= h($b['break_type']) ?></td>
                    <td><?= h($b['label']) ?></td>
                    <td><?= formatTime($b['start_time']) ?></td>
                    <td><?= formatTime($b['end_time']) ?></td>
                    <td>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Delete this break override?')">
                            <input type="hidden" name="action" value="delete_break">
                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="color:var(--gray-500);font-size:0.85rem;margin-top:0.5rem;">No date-specific break overrides. Default breaks apply.</p>
    <?php endif; ?>
</div>

<script>
function addSlotRow() {
    const tbody = document.querySelector('#slot-override-table tbody');
    const rows = tbody.querySelectorAll('tr');
    const nextNum = rows.length + 1;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="number" name="slot_number[]" value="${nextNum}" min="1" style="width:60px"></td>
        <td><input type="time" name="slot_start[]"></td>
        <td><input type="time" name="slot_end[]"></td>
        <td><input type="text" name="slot_label[]" value="Slot ${nextNum}" style="width:120px"></td>
    `;
    tbody.appendChild(tr);
}
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
