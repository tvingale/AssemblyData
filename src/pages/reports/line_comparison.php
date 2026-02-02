<?php
/**
 * Line Comparison / Benchmarking (R9)
 *
 * Grouped bar chart comparing total actual output across all active groups,
 * detail table with achievement %, downtime, avg manpower, seats/person,
 * with best/worst group highlighting.
 */
define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';

$db = getDB();

// ── Filter parameters ──────────────────────────────────────────────────
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate   = $_GET['end_date']   ?? date('Y-m-d');

$groups = getActiveGroups();

// ── Query daily_summaries grouped by group_id ──────────────────────────
$sql = "
    SELECT
        ds.group_id,
        pg.name                             AS group_name,
        SUM(ds.total_target)                AS total_target,
        SUM(ds.total_actual)                AS total_actual,
        SUM(ds.total_downtime_minutes)      AS total_downtime,
        SUM(ds.total_man_hours)             AS total_man_hours,
        AVG(ds.total_manpower_avg)          AS avg_manpower,
        COUNT(DISTINCT ds.production_date)  AS working_days
    FROM daily_summaries ds
    JOIN production_groups pg ON ds.group_id = pg.id
    WHERE ds.production_date BETWEEN ? AND ?
      AND pg.is_active = 1
    GROUP BY ds.group_id, pg.name
    ORDER BY total_actual DESC
";
$stmt = $db->prepare($sql);
$stmt->execute([$startDate, $endDate]);
$groupRows = $stmt->fetchAll();

// ── Compute derived metrics and find best/worst ────────────────────────
$bestId  = null;
$worstId = null;
$bestPct  = -999;
$worstPct = 999;

foreach ($groupRows as &$g) {
    $g['total_target']   = round((float)$g['total_target'], 2);
    $g['total_actual']   = (int)$g['total_actual'];
    $g['total_downtime'] = round((float)$g['total_downtime'], 2);
    $g['total_man_hours'] = round((float)$g['total_man_hours'], 2);
    $g['avg_manpower']   = round((float)$g['avg_manpower'], 1);
    $g['working_days']   = (int)$g['working_days'];

    $g['achievement_pct'] = $g['total_target'] > 0
        ? round(($g['total_actual'] / $g['total_target']) * 100, 1) : 0;

    $g['seats_per_person'] = $g['total_man_hours'] > 0
        ? round($g['total_actual'] / $g['total_man_hours'], 2) : 0;

    if ($g['achievement_pct'] > $bestPct) {
        $bestPct = $g['achievement_pct'];
        $bestId  = $g['group_id'];
    }
    if ($g['achievement_pct'] < $worstPct) {
        $worstPct = $g['achievement_pct'];
        $worstId  = $g['group_id'];
    }
}
unset($g);

// ── Prepare Chart.js data ──────────────────────────────────────────────
$chartLabels  = [];
$chartTargets = [];
$chartActuals = [];

foreach ($groupRows as $g) {
    $chartLabels[]  = $g['group_name'];
    $chartTargets[] = round($g['total_target']);
    $chartActuals[] = $g['total_actual'];
}

// ── Grand totals ───────────────────────────────────────────────────────
$grandTarget   = array_sum(array_column($groupRows, 'total_target'));
$grandActual   = array_sum(array_column($groupRows, 'total_actual'));
$grandDowntime = array_sum(array_column($groupRows, 'total_downtime'));
$grandManHours = array_sum(array_column($groupRows, 'total_man_hours'));
$grandAchievement = $grandTarget > 0 ? round(($grandActual / $grandTarget) * 100, 1) : 0;

$pageTitle = 'Line Comparison';
$baseUrl   = '../..';
include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
    <h1>Line Comparison / Benchmarking</h1>
    <button class="btn btn-outline no-print" onclick="window.print()">Print</button>
</div>

<!-- Filter Bar -->
<form method="get" class="filter-bar no-print">
    <div class="form-group">
        <label for="start_date">Start Date</label>
        <input type="date" id="start_date" name="start_date" value="<?= h($startDate) ?>">
    </div>
    <div class="form-group">
        <label for="end_date">End Date</label>
        <input type="date" id="end_date" name="end_date" value="<?= h($endDate) ?>">
    </div>
    <div class="form-group">
        <label>&nbsp;</label>
        <button type="submit" class="btn btn-primary">Apply</button>
    </div>
</form>

<p style="color:var(--gray-500);font-size:0.9rem;margin-bottom:1rem;">
    Period: <strong><?= formatDate($startDate) ?></strong> to <strong><?= formatDate($endDate) ?></strong>
    | Comparing <strong><?= count($groupRows) ?></strong> active groups
</p>

<?php if (empty($groupRows)): ?>
    <div class="alert alert-info">No production data found for the selected period.</div>
<?php else: ?>

<!-- Summary Cards -->
<div class="summary-cards">
    <div class="summary-card">
        <h3>Plant Target</h3>
        <div class="value"><?= number_format($grandTarget) ?></div>
    </div>
    <div class="summary-card <?= $grandActual >= $grandTarget ? 'success' : 'danger' ?>">
        <h3>Plant Actual</h3>
        <div class="value"><?= number_format($grandActual) ?></div>
    </div>
    <div class="summary-card <?= $grandAchievement >= 100 ? 'success' : ($grandAchievement >= 90 ? 'warning' : 'danger') ?>">
        <h3>Plant Achievement</h3>
        <div class="value"><?= $grandAchievement ?>%</div>
    </div>
    <div class="summary-card success">
        <h3>Best Performer</h3>
        <div class="value" style="font-size:1rem;">
            <?php
            $bestGroup = array_filter($groupRows, fn($g) => $g['group_id'] == $bestId);
            $bestGroup = reset($bestGroup);
            echo h($bestGroup['group_name'] ?? '-') . ' (' . ($bestGroup['achievement_pct'] ?? 0) . '%)';
            ?>
        </div>
    </div>
</div>

<!-- Grouped Bar Chart -->
<div class="card">
    <div class="card-header">Output Comparison by Group</div>
    <div class="chart-container" style="max-width:100%;height:420px;">
        <canvas id="comparisonChart"></canvas>
    </div>
</div>

<!-- Comparison Table -->
<div class="card">
    <div class="card-header">Group Performance Details</div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Group</th>
                    <th class="num">Total Target</th>
                    <th class="num">Total Actual</th>
                    <th class="num">Achievement %</th>
                    <th class="num">Total Downtime (min)</th>
                    <th class="num">Avg Manpower</th>
                    <th class="num">Seats/Person</th>
                    <th class="num">Working Days</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($groupRows as $g): ?>
                <?php
                    $isBest  = ($g['group_id'] == $bestId && count($groupRows) > 1);
                    $isWorst = ($g['group_id'] == $worstId && count($groupRows) > 1);
                    $rowStyle = '';
                    if ($isBest)  $rowStyle = 'background:rgba(56,161,105,0.08);';
                    if ($isWorst) $rowStyle = 'background:rgba(229,62,62,0.06);';
                ?>
                <tr style="<?= $rowStyle ?>">
                    <td>
                        <strong><?= h($g['group_name']) ?></strong>
                        <?php if ($isBest): ?>
                            <span class="badge badge-success" style="margin-left:0.4rem;">Best</span>
                        <?php elseif ($isWorst): ?>
                            <span class="badge badge-danger" style="margin-left:0.4rem;">Lowest</span>
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= number_format($g['total_target']) ?></td>
                    <td class="num"><?= number_format($g['total_actual']) ?></td>
                    <td class="num">
                        <span class="<?= $g['achievement_pct'] >= 100 ? 'variance-positive' : 'variance-negative' ?>">
                            <?= $g['achievement_pct'] ?>%
                        </span>
                    </td>
                    <td class="num"><?= number_format($g['total_downtime'], 0) ?></td>
                    <td class="num"><?= number_format($g['avg_manpower'], 1) ?></td>
                    <td class="num"><?= number_format($g['seats_per_person'], 2) ?></td>
                    <td class="num"><?= $g['working_days'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="cumulative-row">
                    <td><strong>Plant Total</strong></td>
                    <td class="num"><strong><?= number_format($grandTarget) ?></strong></td>
                    <td class="num"><strong><?= number_format($grandActual) ?></strong></td>
                    <td class="num"><strong><?= $grandAchievement ?>%</strong></td>
                    <td class="num"><strong><?= number_format($grandDowntime, 0) ?></strong></td>
                    <td class="num">-</td>
                    <td class="num"><strong><?= $grandManHours > 0 ? number_format($grandActual / $grandManHours, 2) : '-' ?></strong></td>
                    <td class="num">-</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php endif; /* end empty check */ ?>

<!-- Chart.js Rendering -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('comparisonChart');
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
                    borderWidth: 1
                },
                {
                    label: 'Actual',
                    data: actuals,
                    backgroundColor: 'rgba(56, 161, 105, 0.6)',
                    borderColor: 'rgba(56, 161, 105, 1)',
                    borderWidth: 1
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
