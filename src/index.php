<?php
define('APP_ROOT', __DIR__);
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';

$date = date('Y-m-d');
$groups = getActiveGroups();
$shiftTimes = getShiftTimes($date);

// Compute summaries for today
$summaries = [];
$plantTarget = 0;
$plantActual = 0;
$plantDowntime = 0;

foreach ($groups as $g) {
    $s = computeDailySummary($date, $g['id']);
    $s['group_name'] = $g['name'];
    $summaries[] = $s;
    $plantTarget += $s['total_target'];
    $plantActual += $s['total_actual'];
    $plantDowntime += $s['total_downtime_minutes'];
}

$plantVariance = $plantActual - $plantTarget;
$plantPct = $plantTarget > 0 ? round(($plantActual / $plantTarget) * 100, 1) : 0;

$pageTitle = 'Dashboard';
$baseUrl = '.';
include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
    <h1>Dashboard</h1>
    <span class="shift-info">
        <?= formatDate($date) ?> | Shift: <?= formatTime($shiftTimes['start']) ?> - <?= formatTime($shiftTimes['end']) ?>
    </span>
</div>

<!-- Quick Actions - Priority Access -->
<div class="quick-actions" style="margin-bottom:1.5rem;">
    <a href="pages/production_entry.php" class="btn btn-primary" style="padding:1rem 2rem;font-size:1.1rem;font-weight:600;">
        + Data Entry
    </a>
    <a href="pages/daily_summary.php" class="btn btn-success">Daily Summary</a>
    <a href="pages/reports/deficit_analysis.php" class="btn btn-outline">Deficit Analysis</a>
    <a href="pages/daily_config.php" class="btn btn-secondary">Daily Config</a>
</div>

<!-- Plant-wide summary cards -->
<div class="summary-cards">
    <div class="summary-card info">
        <h3>Total Target</h3>
        <div class="value"><?= number_format($plantTarget) ?></div>
    </div>
    <div class="summary-card <?= $plantVariance >= 0 ? 'success' : 'danger' ?>">
        <h3>Total Actual</h3>
        <div class="value"><?= number_format($plantActual) ?></div>
        <div class="progress-bar">
            <div class="progress-fill <?= $plantPct >= 100 ? 'green' : ($plantPct >= 80 ? 'yellow' : 'red') ?>"
                 style="width:<?= min(100, $plantPct) ?>%"></div>
        </div>
        <small style="color:var(--gray-500)"><?= $plantPct ?>% of target</small>
    </div>
    <div class="summary-card <?= $plantVariance >= 0 ? 'success' : 'danger' ?>">
        <h3>Variance</h3>
        <div class="value <?= $plantVariance >= 0 ? 'variance-positive' : 'variance-negative' ?>">
            <?= ($plantVariance >= 0 ? '+' : '') . number_format($plantVariance) ?>
        </div>
    </div>
    <div class="summary-card warning">
        <h3>Time Lost</h3>
        <div class="value"><?= number_format($plantDowntime, 0) ?> min</div>
    </div>
</div>

<!-- Per-group cards -->
<h2 style="margin-bottom:1rem;font-size:1.1rem;">Group Performance</h2>
<div class="summary-cards">
    <?php foreach ($summaries as $s): ?>
    <?php
        $variance = $s['total_actual'] - $s['total_target'];
        $pct = $s['total_target'] > 0 ? round(($s['total_actual'] / $s['total_target']) * 100, 1) : 0;
        $borderClass = $variance >= 0 ? 'success' : 'danger';
    ?>
    <div class="summary-card <?= $borderClass ?>">
        <h3><?= h($s['group_name']) ?></h3>
        <div style="display:flex;justify-content:space-between;margin-bottom:0.25rem;">
            <span>Target: <?= number_format($s['total_target']) ?></span>
            <span>Actual: <strong><?= number_format($s['total_actual']) ?></strong></span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill <?= $pct >= 100 ? 'green' : ($pct >= 80 ? 'yellow' : 'red') ?>"
                 style="width:<?= min(100, $pct) ?>%"></div>
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:0.35rem;font-size:0.8rem;color:var(--gray-500);">
            <span>Variance: <span class="<?= $variance >= 0 ? 'variance-positive' : 'variance-negative' ?>"><?= ($variance >= 0 ? '+' : '') . number_format($variance) ?></span></span>
            <span>Lost: <?= number_format($s['total_downtime_minutes'], 0) ?>m</span>
        </div>
        <?php if ($s['seats_per_person'] > 0): ?>
        <div style="font-size:0.8rem;color:var(--gray-500);margin-top:0.15rem;">
            Seats/Person: <?= number_format($s['seats_per_person'], 2) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php if (empty($groups)): ?>
    <div class="alert alert-info">
        No production groups configured yet. <a href="pages/manage_groups.php">Set up groups</a> to get started.
    </div>
<?php endif; ?>

<?php include APP_ROOT . '/includes/footer.php'; ?>
