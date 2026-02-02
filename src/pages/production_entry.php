<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';

$date = $_GET['date'] ?? date('Y-m-d');
$groups = getActiveGroups();
$reasons = getActiveReasons();
$shiftTimes = getShiftTimes($date);

$pageTitle = 'Production Entry';
$baseUrl = '..';
$pageScripts = ['production_entry.js'];
include APP_ROOT . '/includes/header.php';
?>

<style>
/* Mobile-first styles for production entry */
.entry-form {
    background: var(--neutral-100);
    border-radius: var(--radius-lg);
    padding: 1rem;
    margin-bottom: 1rem;
}
.entry-form .form-group {
    margin-bottom: 0.75rem;
}
.entry-form label {
    display: block;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--neutral-700);
    margin-bottom: 0.25rem;
}
.entry-form input[type="number"],
.entry-form input[type="text"],
.entry-form select {
    width: 100%;
    padding: 0.75rem;
    font-size: 1rem;
    border: 1px solid var(--neutral-300);
    border-radius: var(--radius);
}
.entry-form input[type="number"]:focus,
.entry-form input[type="text"]:focus,
.entry-form select:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(197, 58, 58, 0.1);
}
.entry-form .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}
.entry-form .form-row-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 0.75rem;
}
.slot-dropdown {
    background: white;
    cursor: pointer;
}
.slot-dropdown:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(197, 58, 58, 0.2);
}
.slot-info {
    background: white;
    padding: 0.75rem;
    border-radius: var(--radius);
    margin-bottom: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.slot-info .slot-name {
    font-weight: 700;
    font-size: 1.1rem;
}
.slot-info .slot-time {
    color: var(--neutral-600);
    font-size: 0.9rem;
}
.calc-display {
    background: white;
    padding: 0.75rem;
    border-radius: var(--radius);
    margin-bottom: 1rem;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
    text-align: center;
}
.calc-display .calc-item {
    padding: 0.5rem;
}
.calc-display .calc-label {
    font-size: 0.7rem;
    color: var(--neutral-600);
    text-transform: uppercase;
}
.calc-display .calc-value {
    font-size: 1.2rem;
    font-weight: 700;
}
.calc-display .calc-value.positive { color: var(--success); }
.calc-display .calc-value.negative { color: var(--error); }

.section-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--neutral-600);
    margin: 1rem 0 0.5rem;
    padding-bottom: 0.25rem;
    border-bottom: 1px solid var(--neutral-200);
}

/* History table */
.history-table {
    width: 100%;
    font-size: 0.8rem;
    border-collapse: collapse;
}
.history-table th {
    background: var(--neutral-200);
    padding: 0.5rem 0.25rem;
    text-align: left;
    font-weight: 600;
    font-size: 0.7rem;
}
.history-table td {
    padding: 0.5rem 0.25rem;
    border-bottom: 1px solid var(--neutral-200);
}
.history-table .num {
    text-align: right;
}
.history-table .edit-btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.7rem;
}

/* Day summary */
.day-summary {
    background: linear-gradient(135deg, var(--neutral-100), white);
    border: 1px solid var(--neutral-200);
    border-radius: var(--radius-lg);
    padding: 1rem;
    margin-bottom: 1rem;
}
.day-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}
.day-summary-item {
    text-align: center;
}
.day-summary-item .label {
    font-size: 0.7rem;
    color: var(--neutral-600);
    text-transform: uppercase;
}
.day-summary-item .value {
    font-size: 1.1rem;
    font-weight: 700;
}

@media (min-width: 768px) {
    .entry-form .form-row {
        grid-template-columns: repeat(4, 1fr);
    }
    .entry-form .form-row-3 {
        grid-template-columns: repeat(3, 1fr);
    }
    .day-summary-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}
</style>

<div class="page-header">
    <h1>Production Entry</h1>
</div>

<div class="date-bar">
    <label for="prod-date">Date:</label>
    <input type="date" id="prod-date" value="<?= h($date) ?>">
    <span class="shift-info" id="shift-info">
        <?= formatTime($shiftTimes['start']) ?> - <?= formatTime($shiftTimes['end']) ?>
        (<?= getDayName($date) ?>)
    </span>
</div>

<?php if (empty($groups)): ?>
    <div class="alert alert-warning">No production groups configured. <a href="manage_groups.php">Add groups</a> first.</div>
<?php else: ?>

<div class="tabs" id="group-tabs">
    <?php foreach ($groups as $i => $g): ?>
        <button class="tab-btn <?= $i === 0 ? 'active' : '' ?>" data-tab="group-<?= $g['id'] ?>"><?= h($g['name']) ?></button>
    <?php endforeach; ?>
</div>

<?php foreach ($groups as $i => $g): ?>
<div id="group-<?= $g['id'] ?>" class="tab-content <?= $i === 0 ? 'active' : '' ?>"
     data-group-id="<?= $g['id'] ?>"
     data-default-cells="<?= $g['default_cells'] ?>"
     data-rate="<?= $g['expected_output_per_cell_per_hour'] ?>">

    <!-- Day Summary -->
    <div class="day-summary" id="day-summary-<?= $g['id'] ?>">
        <div class="day-summary-grid">
            <div class="day-summary-item">
                <div class="label">Day Target</div>
                <div class="value" id="ds-target-<?= $g['id'] ?>">0</div>
            </div>
            <div class="day-summary-item">
                <div class="label">Day Actual</div>
                <div class="value" id="ds-actual-<?= $g['id'] ?>">0</div>
            </div>
            <div class="day-summary-item">
                <div class="label">Variance</div>
                <div class="value" id="ds-variance-<?= $g['id'] ?>">0</div>
            </div>
            <div class="day-summary-item">
                <div class="label">Time Lost</div>
                <div class="value" id="ds-downtime-<?= $g['id'] ?>">0</div>
            </div>
        </div>
    </div>

    <!-- Slot Selector -->
    <div class="section-title">Select Time Slot</div>
    <div class="form-group" style="margin-bottom:1rem;">
        <select id="slot-selector-<?= $g['id'] ?>" class="slot-dropdown" style="width:100%;padding:0.75rem;font-size:1rem;border:2px solid var(--primary);border-radius:var(--radius);font-weight:600;">
            <!-- Populated by JS -->
        </select>
    </div>

    <!-- Entry Form for Selected Slot -->
    <div class="entry-form" id="entry-form-<?= $g['id'] ?>">
        <div class="slot-info" id="slot-info-<?= $g['id'] ?>">
            <span class="slot-name">Select a slot</span>
            <span class="slot-time"></span>
        </div>

        <!-- Calculated Values Display -->
        <div class="calc-display">
            <div class="calc-item">
                <div class="calc-label">Eff. Min</div>
                <div class="calc-value" id="calc-effmin-<?= $g['id'] ?>">0</div>
            </div>
            <div class="calc-item">
                <div class="calc-label">Target</div>
                <div class="calc-value" id="calc-target-<?= $g['id'] ?>">0</div>
            </div>
            <div class="calc-item">
                <div class="calc-label">Variance</div>
                <div class="calc-value" id="calc-variance-<?= $g['id'] ?>">0</div>
            </div>
        </div>

        <!-- Production Inputs -->
        <div class="section-title">Production Data</div>
        <div class="form-row">
            <div class="form-group">
                <label>Cells Operative</label>
                <input type="number" id="inp-cells-<?= $g['id'] ?>" min="0" max="50" value="<?= $g['default_cells'] ?>">
            </div>
            <div class="form-group">
                <label>Manpower</label>
                <input type="number" id="inp-manpower-<?= $g['id'] ?>" min="0" value="0">
            </div>
            <div class="form-group">
                <label>Actual Output</label>
                <input type="number" id="inp-actual-<?= $g['id'] ?>" min="0" value="0">
            </div>
        </div>

        <!-- Deficit Reason (if target not met) -->
        <div class="section-title">Variance Reason (if target not met)</div>
        <div class="form-group">
            <label>Reason</label>
            <select id="inp-reason-<?= $g['id'] ?>">
                <option value="">-- Target Met / No Issue --</option>
                <?php foreach ($reasons as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= h($r['reason_text']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Minutes Lost</label>
                <input type="number" id="inp-minutes-lost-<?= $g['id'] ?>" min="0" max="999" value="0" placeholder="0">
            </div>
        </div>
        <div class="form-group">
            <label>Additional Notes</label>
            <input type="text" id="inp-reason-notes-<?= $g['id'] ?>" placeholder="Details about the issue...">
        </div>

        <!-- Save Button -->
        <div style="margin-top:1rem;">
            <button class="btn btn-primary btn-block save-slot-btn" data-group-id="<?= $g['id'] ?>" style="width:100%;padding:1rem;font-size:1rem;">
                Save Slot Entry
            </button>
            <div class="save-status" id="status-<?= $g['id'] ?>" style="text-align:center;margin-top:0.5rem;font-size:0.85rem;"></div>
        </div>
    </div>

    <!-- History Table -->
    <div class="section-title">Today's Entries</div>
    <div class="card" style="padding:0;overflow-x:auto;">
        <table class="history-table" id="history-<?= $g['id'] ?>">
            <thead>
                <tr>
                    <th>Slot</th>
                    <th class="num">Cells</th>
                    <th class="num">MP</th>
                    <th class="num">Target</th>
                    <th class="num">Actual</th>
                    <th class="num">Var</th>
                    <th class="num">Lost</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <!-- Populated by JS -->
            </tbody>
        </table>
    </div>

</div>
<?php endforeach; ?>

<!-- Hidden data for JS -->
<script>
    window.PE_CONFIG = {
        reasons: <?= json_encode($reasons) ?>,
        apiBase: '<?= $baseUrl ?>/api'
    };
</script>

<?php endif; ?>

<?php include APP_ROOT . '/includes/footer.php'; ?>
