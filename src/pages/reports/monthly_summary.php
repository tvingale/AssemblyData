<?php
/**
 * Monthly Summary (R8)
 *
 * Summary cards, bar chart of daily actual vs target for a month,
 * detail table with variance/downtime per working day, totals row.
 */
define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';

$db = getDB();

// ── Filter parameters ──────────────────────────────────────────────────
$month   = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year    = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0; // 0 = all

// Clamp values
$month = max(1, min(12, $month));
$year  = max(2020, min(2099, $year));

$groups = getActiveGroups();

// Month date boundaries
$startDate = sprintf('%04d-%02d-01', $year, $month);
$endDate   = date('Y-m-t', strtotime($startDate)); // last day of month
$monthName = date('F Y', strtotime($startDate));

// ── Query daily_summaries grouped by date ──────────────────────────────
$whereParts = ['ds.production_date BETWEEN ? AND ?'];
$params     = [$startDate, $endDate];

if ($groupId > 0) {
    $whereParts[] = 'ds.group_id = ?';
    $params[]     = $groupId;
}

$whereSQL = implode(' AND ', $whereParts);

$sql = "
    SELECT
        ds.production_date,
        SUM(ds.total_target)            AS day_target,
        SUM(ds.total_actual)            AS day_actual,
        SUM(ds.total_downtime_minutes)  AS day_downtime,
        SUM(ds.total_man_hours)         AS day_man_hours
    FROM daily_summaries ds
    WHERE {$whereSQL}
    GROUP BY ds.production_date
    ORDER BY ds.production_date
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ── Build display data ─────────────────────────────────────────────────
$tableRows     = [];
$chartLabels   = [];
$chartTargets  = [];
$chartActuals  = [];
$grandTarget   = 0;
$grandActual   = 0;
$grandDowntime = 0;
$grandManHours = 0;
$workingDays   = 0;

foreach ($rows as $r) {
    $target   = round((float)$r['day_target'], 2);
    $actual   = (int)$r['day_actual'];
    $downtime = round((float)$r['day_downtime'], 2);
    $variance = $actual - $target;
    $variancePct = $target > 0 ? round(($variance / $target) * 100, 1) : 0;

    $tableRows[] = [
        'date'         => $r['production_date'],
        'target'       => $target,
        'actual'       => $actual,
        'variance'     => $variance,
        'variance_pct' => $variancePct,
        'downtime'     => $downtime,
    ];

    $chartLabels[]  = date('d', strtotime($r['production_date']));
    $chartTargets[] = round($target);
    $chartActuals[] = $actual;

    $grandTarget   += $target;
    $grandActual   += $actual;
    $grandDowntime += $downtime;
    $grandManHours += (float)$r['day_man_hours'];
    $workingDays++;
}

$grandVariance    = $grandActual - $grandTarget;
$grandVariancePct = $grandTarget > 0 ? round(($grandVariance / $grandTarget) * 100, 1) : 0;
$achievementPct   = $grandTarget > 0 ? round(($grandActual / $grandTarget) * 100, 1) : 0;
$avgDailyOutput   = $workingDays > 0 ? round($grandActual / $workingDays) : 0;

$pageTitle = 'Monthly Summary';
$baseUrl   = '../..';
include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
    <h1>Monthly Summary</h1>
    <button class="btn btn-outline no-print" onclick="window.print()">Print</button>
</div>

<!-- Filter Bar -->
<form method="get" class="filter-bar no-print">
    <div class="form-group">
        <label for="month">Month</label>
        <select id="month" name="month">
            <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="year">Year</label>
        <select id="year" name="year">
            <?php for ($y = (int)date('Y') - 3; $y <= (int)date('Y') + 1; $y++): ?>
                <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="group_id">Group</label>
        <select id="group_id" name="group_id">
            <option value="0">All Groups</option>
            <?php foreach ($groups as $g): ?>
                <option value="<?= $g['id'] ?>" <?= $g['id'] == $groupId ? 'selected' : '' ?>><?= h($g['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>&nbsp;</label>
        <button type="submit" class="btn btn-primary">Apply</button>
    </div>
</form>

<?php if (empty($tableRows)): ?>
    <div class="alert alert-info">No production data found for <?= h($monthName) ?>.</div>
<?php else: ?>

<!-- Summary Cards -->
<div class="summary-cards">
    <div class="summary-card">
        <h3>Total Target</h3>
        <div class="value"><?= number_format($grandTarget) ?></div>
    </div>
    <div class="summary-card <?= $grandActual >= $grandTarget ? 'success' : 'danger' ?>">
        <h3>Total Actual</h3>
        <div class="value"><?= number_format($grandActual) ?></div>
    </div>
    <div class="summary-card <?= $grandVariance >= 0 ? 'success' : 'danger' ?>">
        <h3>Total Variance</h3>
        <div class="value"><?= ($grandVariance >= 0 ? '+' : '') . number_format($grandVariance) ?></div>
    </div>
    <div class="summary-card <?= $achievementPct >= 100 ? 'success' : ($achievementPct >= 90 ? 'warning' : 'danger') ?>">
        <h3>Achievement %</h3>
        <div class="value"><?= $achievementPct ?>%</div>
    </div>
    <div class="summary-card info">
        <h3>Avg Daily Output</h3>
        <div class="value"><?= number_format($avgDailyOutput) ?></div>
    </div>
    <div class="summary-card warning">
        <h3>Total Downtime</h3>
        <div class="value"><?= number_format($grandDowntime, 0) ?> min</div>
    </div>
</div>

<!-- Bar Chart: Daily Actual vs Target -->
<div class="card">
    <div class="card-header">Daily Actual vs Target &mdash; <?= h($monthName) ?></div>
    <div class="chart-container" style="max-width:100%;height:420px;">
        <canvas id="monthlyChart"></canvas>
    </div>
</div>

<!-- Detail Table -->
<div class="card">
    <div class="card-header">Daily Breakdown &mdash; <?= h($monthName) ?></div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th class="num">Target</th>
                    <th class="num">Actual</th>
                    <th class="num">Variance</th>
                    <th class="num">Variance %</th>
                    <th class="num">Downtime (min)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableRows as $tr): ?>
                <tr>
                    <td><?= formatDate($tr['date']) ?></td>
                    <td class="num"><?= number_format($tr['target']) ?></td>
                    <td class="num"><?= number_format($tr['actual']) ?></td>
                    <td class="num">
                        <span class="<?= $tr['variance'] >= 0 ? 'variance-positive' : 'variance-negative' ?>">
                            <?= ($tr['variance'] >= 0 ? '+' : '') . number_format($tr['variance']) ?>
                        </span>
                    </td>
                    <td class="num"><?= ($tr['variance_pct'] >= 0 ? '+' : '') . $tr['variance_pct'] ?>%</td>
                    <td class="num"><?= number_format($tr['downtime'], 0) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="cumulative-row">
                    <td><strong>Month Total (<?= $workingDays ?> working days)</strong></td>
                    <td class="num"><strong><?= number_format($grandTarget) ?></strong></td>
                    <td class="num"><strong><?= number_format($grandActual) ?></strong></td>
                    <td class="num">
                        <strong>
                            <span class="<?= $grandVariance >= 0 ? 'variance-positive' : 'variance-negative' ?>">
                                <?= ($grandVariance >= 0 ? '+' : '') . number_format($grandVariance) ?>
                            </span>
                        </strong>
                    </td>
                    <td class="num"><strong><?= ($grandVariancePct >= 0 ? '+' : '') . $grandVariancePct ?>%</strong></td>
                    <td class="num"><strong><?= number_format($grandDowntime, 0) ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php endif; /* end empty check */ ?>

<!-- Chart.js Rendering -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('monthlyChart');
    if (!canvas) return;

    const labels  = <?= json_encode($chartLabels) ?>;
    const targets = <?= json_encode($chartTargets) ?>;
    const actuals = <?= json_encode($chartActuals) ?>;

    if (labels.length === 0) return;

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Target',
                    data: targets,
                    backgroundColor: 'rgba(44, 82, 130, 0.6)',
                    borderColor: 'rgba(44, 82, 130, 1)',
                    borderWidth: 1,
                    order: 2
                },
                {
                    label: 'Actual',
                    data: actuals,
                    backgroundColor: 'rgba(56, 161, 105, 0.6)',
                    borderColor: 'rgba(56, 161, 105, 1)',
                    borderWidth: 1,
                    order: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        title: function(items) {
                            return '<?= h($monthName) ?> - Day ' + items[0].label;
                        },
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Day of Month'
                    },
                    ticks: {
                        font: { size: 11 }
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Units'
                    }
                }
            }
        }
    });
});
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
