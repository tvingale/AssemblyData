<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';

$date = $_GET['date'] ?? date('Y-m-d');
$groups = getActiveGroups();
$selectedGroup = isset($_GET['group_id']) ? (int)$_GET['group_id'] : ($groups[0]['id'] ?? 0);

$pageTitle = 'Downtime Entry';
$baseUrl = '..';
$pageScripts = ['downtime.js'];
include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
    <h1>Downtime Entry</h1>
</div>

<div class="date-bar">
    <label for="dt-date">Date:</label>
    <input type="date" id="dt-date" value="<?= h($date) ?>">
    <label for="dt-group">Group:</label>
    <select id="dt-group">
        <?php foreach ($groups as $g): ?>
            <option value="<?= $g['id'] ?>" <?= $g['id'] == $selectedGroup ? 'selected' : '' ?>><?= h($g['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div class="card">
    <div class="card-header">Add Downtime Event</div>
    <form id="downtime-form">
        <div class="form-row">
            <div class="form-group">
                <label>Start Time</label>
                <input type="time" id="dt-start" required>
            </div>
            <div class="form-group">
                <label>End Time (leave blank if ongoing)</label>
                <input type="time" id="dt-end">
            </div>
            <div class="form-group">
                <label>Category</label>
                <select id="dt-category">
                    <option value="mechanical">Mechanical</option>
                    <option value="electrical">Electrical</option>
                    <option value="material">Material</option>
                    <option value="manpower">Manpower</option>
                    <option value="quality">Quality</option>
                    <option value="other">Other</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Reason / Description</label>
            <textarea id="dt-reason" rows="2" placeholder="Describe the downtime reason..."></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Add Downtime</button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        Downtime Log
        <span id="total-downtime" style="float:right;font-weight:normal;font-size:0.9rem;">Total: 0 min</span>
    </div>
    <div class="table-responsive">
        <table id="downtime-table">
            <thead>
                <tr>
                    <th>Start</th>
                    <th>End</th>
                    <th class="num">Duration (min)</th>
                    <th>Category</th>
                    <th>Reason</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Populated by JS -->
            </tbody>
        </table>
    </div>
</div>

<script>
    window.DT_CONFIG = {
        apiBase: '<?= $baseUrl ?>/api'
    };
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
