<?php
/**
 * Weekly Production Trend (R7)
 *
 * Line chart of daily target vs actual for a selected week,
 * detail table with variance and downtime, totals row.
 */
define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';

$db = getDB();

// ── Filter parameters ──────────────────────────────────────────────────
// Default to current week's Monday
$weekInput = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
// Snap to Monday (start of ISO week)
$weekStart = date('Y-m-d', strtotime('monday this week', strtotime($weekInput)));
$weekEnd   = date('Y-m-d', strtotime($weekStart . ' +6 days'));
$groupId   = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0; // 0 = all

$groups = getActiveGroups();

// ── Build 7-day date range ─────────────────────────────────────────────
$weekDates = [];
for ($i = 0; $i < 7; $i++) {
    $weekDates[] = date('Y-m-d', strtotime($weekStart . " +{$i} days"));
}

// ── Query daily_summaries for the week ─────────────────────────────────
$whereParts = ['ds.production_date BETWEEN ? AND ?'];
$params     = [$weekStart, $weekEnd];

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
        SUM(ds.total_downtime_minutes)  AS day_downtime
    FROM daily_summaries ds
    WHERE {$whereSQL}
    GROUP BY ds.production_date
    ORDER BY ds.production_date
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Index results by date
$dataByDate = [];
foreach ($rows as $r) {
    $dataByDate[$r['production_date']] = $r;
}

// ── If no summaries exist for some dates, try to compute them ──────────
$activeGroupIds = $groupId > 0
    ? [$groupId]
    : array_column($groups, 'id');

foreach ($weekDates as $d) {
    if (!isset($dataByDate[$d])) {
        // Attempt to compute summaries for this date
        $dayTarget = 0; $dayActual = 0; $dayDowntime = 0;
        $hasData = false;
        foreach ($activeGroupIds as $gid) {
            $entries = getProductionEntries($d, (int)$gid);
            if (!empty($entries)) {
                $hasData = true;
                $summary = computeDailySummary($d, (int)$gid);
                $dayTarget   += $summary['total_target'];
                $dayActual   += $summary['total_actual'];
                $dayDowntime += $summary['total_downtime_minutes'];
            }
        }
        if ($hasData) {
            $dataByDate[$d] = [
                'production_date' => $d,
                'day_target'      => $dayTarget,
                'day_actual'      => $dayActual,
                'day_downtime'    => $dayDowntime,
            ];
        }
    }
}

// ── Build display data ─────────────────────────────────────────────────
$tableRows    = [];
$chartLabels  = [];
$chartTargets = [];
$chartActuals = [];
$grandTarget  = 0;
$grandActual  = 0;
$grandDowntime = 0;

foreach ($weekDates as $d) {
    $row = $dataByDate[$d] ?? null;
    $target   = $row ? round((float)$row['day_target'], 2) : 0;
    $actual   = $row ? (int)$row['day_actual'] : 0;
    $downtime = $row ? round((float)$row['day_downtime'], 2) : 0;
    $variance = $actual - $target;
    $variancePct = $target > 0 ? round(($variance / $target) * 100, 1) : 0;

    $tableRows[] = [
        'date'         => $d,
        'day'          => date('l', strtotime($d)),
        'target'       => $target,
        'actual'       => $actual,
        'variance'     => $variance,
        'variance_pct' => $variancePct,
        'downtime'     => $downtime,
        'has_data'     => $row !== null,
    ];

    $chartLabels[]  = date('d-M', strtotime($d));
    $chartTargets[] = round($target);
    $chartActuals[] = $actual;

    $grandTarget   += $target;
    $grandActual   += $actual;
    $grandDowntime += $downtime;
}

$grandVariance    = $grandActual - $grandTarget;
$grandVariancePct = $grandTarget > 0 ? round(($grandVariance / $grandTarget) * 100, 1) : 0;

$pageTitle = 'Weekly Production Trend';
$baseUrl   = '../..';
include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
    <h1>Weekly Production Trend</h1>
    <button class="btn btn-outline no-print" onclick="window.print()">Print</button>
</div>

<!-- Filter Bar -->
<form method="get" class="filter-bar no-print">
    <div class="form-group">
        <label for="week_start">Week Starting (Monday)</label>
        <input type="date" id="week_start" name="week_start" value="<?= h($weekStart) ?>">
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

<p style="color:var(--gray-500);font-size:0.9rem;margin-bottom:1rem;">
    Showing: <strong><?= formatDate($weekStart) ?></strong> to <strong><?= formatDate($weekEnd) ?></strong>
    <?php if ($groupId > 0): ?>
        | Group: <strong><?= h(array_values(array_filter($groups, fn($g) => $g['id'] == $groupId))[0]['name'] ?? 'Unknown') ?></strong>
    <?php else: ?>
        | <strong>All Groups</strong>
    <?php endif; ?>
</p>

<?php
$hasAnyData = false;
foreach ($tableRows as $tr) { if ($tr['has_data']) { $hasAnyData = true; break; } }
?>

<?php if (!$hasAnyData): ?>
    <div class="alert alert-info">No production data found for this week.</div>
<?php else: ?>

<!-- Summary Cards -->
<div class="summary-cards">
    <div class="summary-card">
        <h3>Week Target</h3>
        <div class="value"><?= number_format($grandTarget) ?></div>
    </div>
    <div class="summary-card <?= $grandActual >= $grandTarget ? 'success' : 'danger' ?>">
        <h3>Week Actual</h3>
        <div class="value"><?= number_format($grandActual) ?></div>
    </div>
    <div class="summary-card <?= $grandVariance >= 0 ? 'success' : 'danger' ?>">
        <h3>Week Variance</h3>
        <div class="value"><?= ($grandVariance >= 0 ? '+' : '') . number_format($grandVariance) ?></div>
    </div>
    <div class="summary-card <?= $grandVariancePct >= 0 ? 'success' : 'warning' ?>">
        <h3>Achievement</h3>
        <div class="value"><?= $grandTarget > 0 ? number_format(($grandActual / $grandTarget) * 100, 1) : '0' ?>%</div>
    </div>
</div>

<!-- Line Chart -->
<div class="card">
    <div class="card-header">Daily Target vs Actual</div>
    <div class="chart-container" style="max-width:100%;height:400px;">
        <canvas id="weeklyChart"></canvas>
    </div>
</div>

<!-- Detail Table -->
<div class="card">
    <div class="card-header">Daily Breakdown</div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th class="num">Target</th>
                    <th class="num">Actual</th>
                    <th class="num">Variance</th>
                    <th class="num">Variance %</th>
                    <th class="num">Downtime (min)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableRows as $tr): ?>
                <tr<?= !$tr['has_data'] ? ' style="color:var(--gray-400);"' : '' ?>>
                    <td><?= formatDate($tr['date']) ?></td>
                    <td><?= h($tr['day']) ?></td>
                    <td class="num"><?= number_format($tr['target']) ?></td>
                    <td class="num"><?= number_format($tr['actual']) ?></td>
                    <td class="num">
                        <?php if ($tr['has_data']): ?>
                            <span class="<?= $tr['variance'] >= 0 ? 'variance-positive' : 'variance-negative' ?>">
                                <?= ($tr['variance'] >= 0 ? '+' : '') . number_format($tr['variance']) ?>
                            </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="num">
                        <?php if ($tr['has_data'] && $tr['target'] > 0): ?>
                            <?= ($tr['variance_pct'] >= 0 ? '+' : '') . $tr['variance_pct'] ?>%
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= $tr['has_data'] ? number_format($tr['downtime'], 0) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="cumulative-row">
                    <td colspan="2"><strong>Week Total</strong></td>
                    <td class="num"><strong><?= number_format($grandTarget) ?></strong></td>
                    <td class="num"><strong><?= number_format($grandActual) ?></strong></td>
                    <td class="num">
                        <strong>
                            <span class="<?= $grandVariance >= 0 ? 'variance-positive' : 'variance-negative' ?>">
                                <?= ($grandVariance >= 0 ? '+' : '') . number_format($grandVariance) ?>
                            </span>
                        </strong>
                    </td>
                    <td class="num">
                        <strong><?= ($grandVariancePct >= 0 ? '+' : '') . $grandVariancePct ?>%</strong>
                    </td>
                    <td class="num"><strong><?= number_format($grandDowntime, 0) ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php endif; /* end hasAnyData check */ ?>

<!-- Chart.js Rendering -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('weeklyChart');
    if (!canvas) return;

    const labels  = <?= json_encode($chartLabels) ?>;
    const targets = <?= json_encode($chartTargets) ?>;
    const actuals = <?= json_encode($chartActuals) ?>;

    if (labels.length === 0) return;

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Target',
                    data: targets,
                    borderColor: '#2c5282',
                    backgroundColor: 'rgba(44, 82, 130, 0.1)',
                    borderWidth: 2,
                    pointRadius: 5,
                    pointBackgroundColor: '#2c5282',
                    fill: false,
                    tension: 0.2
                },
                {
                    label: 'Actual',
                    data: actuals,
                    borderColor: '#38a169',
                    backgroundColor: 'rgba(56, 161, 105, 0.1)',
                    borderWidth: 2,
                    pointRadius: 5,
                    pointBackgroundColor: '#38a169',
                    fill: false,
                    tension: 0.2
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
                        label: function(context) {
                            return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                x: {
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
