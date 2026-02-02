<?php
/**
 * Manpower Efficiency / Seats per Person (R10)
 *
 * Line chart showing seats_per_person trend over time,
 * detail table with date/group/output/man-hours/seats-per-person,
 * summary section with averages and best/worst day.
 */
define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';

$db = getDB();

// ── Filter parameters ──────────────────────────────────────────────────
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate   = $_GET['end_date']   ?? date('Y-m-d');
$groupId   = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0; // 0 = all

$groups = getActiveGroups();

// ── Query daily_summaries ──────────────────────────────────────────────
$whereParts = ['ds.production_date BETWEEN ? AND ?'];
$params     = [$startDate, $endDate];

if ($groupId > 0) {
    $whereParts[] = 'ds.group_id = ?';
    $params[]     = $groupId;
}

$whereSQL = implode(' AND ', $whereParts);

// Detail rows: per date + group
$detailSQL = "
    SELECT
        ds.production_date,
        ds.group_id,
        pg.name AS group_name,
        ds.total_actual,
        ds.total_man_hours,
        ds.seats_per_person,
        ds.total_manpower_avg
    FROM daily_summaries ds
    JOIN production_groups pg ON ds.group_id = pg.id
    WHERE {$whereSQL}
    ORDER BY ds.production_date, pg.name
";
$stmt = $db->prepare($detailSQL);
$stmt->execute($params);
$detailRows = $stmt->fetchAll();

// Aggregated by date for chart (sum actual / sum man-hours)
$aggSQL = "
    SELECT
        ds.production_date,
        SUM(ds.total_actual)    AS day_actual,
        SUM(ds.total_man_hours) AS day_man_hours
    FROM daily_summaries ds
    WHERE {$whereSQL}
    GROUP BY ds.production_date
    ORDER BY ds.production_date
";
// Need to re-bind for group filter if active
$aggWhereParts = ['ds.production_date BETWEEN ? AND ?'];
$aggParams     = [$startDate, $endDate];
if ($groupId > 0) {
    $aggWhereParts[] = 'ds.group_id = ?';
    $aggParams[]     = $groupId;
}
$aggWhereSQL = implode(' AND ', $aggWhereParts);
$aggSQL = "
    SELECT
        ds.production_date,
        SUM(ds.total_actual)    AS day_actual,
        SUM(ds.total_man_hours) AS day_man_hours
    FROM daily_summaries ds
    WHERE {$aggWhereSQL}
    GROUP BY ds.production_date
    ORDER BY ds.production_date
";
$stmt = $db->prepare($aggSQL);
$stmt->execute($aggParams);
$aggRows = $stmt->fetchAll();

// ── Build chart data ───────────────────────────────────────────────────
$chartLabels = [];
$chartSPP    = [];  // seats per person per day
$dailyData   = [];  // for finding best/worst day

foreach ($aggRows as $r) {
    $dayActual   = (int)$r['day_actual'];
    $dayManHours = (float)$r['day_man_hours'];
    $spp = $dayManHours > 0 ? round($dayActual / $dayManHours, 2) : 0;

    $chartLabels[] = date('d-M', strtotime($r['production_date']));
    $chartSPP[]    = $spp;

    $dailyData[] = [
        'date'       => $r['production_date'],
        'actual'     => $dayActual,
        'man_hours'  => $dayManHours,
        'spp'        => $spp,
    ];
}

// ── Summary stats ──────────────────────────────────────────────────────
$totalActual    = 0;
$totalManHours  = 0;
$bestDay  = null;
$worstDay = null;
$bestSPP  = -1;
$worstSPP = PHP_FLOAT_MAX;

foreach ($dailyData as $dd) {
    $totalActual   += $dd['actual'];
    $totalManHours += $dd['man_hours'];

    if ($dd['spp'] > $bestSPP && $dd['man_hours'] > 0) {
        $bestSPP = $dd['spp'];
        $bestDay = $dd;
    }
    if ($dd['spp'] < $worstSPP && $dd['man_hours'] > 0) {
        $worstSPP = $dd['spp'];
        $worstDay = $dd;
    }
}

$avgSPP = $totalManHours > 0 ? round($totalActual / $totalManHours, 2) : 0;
$dayCount = count($dailyData);

$pageTitle = 'Manpower Efficiency';
$baseUrl   = '../..';
include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
    <h1>Manpower Efficiency / Output per Man Hour</h1>
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

<?php if (empty($detailRows)): ?>
    <div class="alert alert-info">No production data found for the selected period and group.</div>
<?php else: ?>

<!-- Summary Cards -->
<div class="summary-cards">
    <div class="summary-card info">
        <h3>Avg Output/Man Hr</h3>
        <div class="value"><?= number_format($avgSPP, 2) ?></div>
    </div>
    <div class="summary-card success">
        <h3>Best Day</h3>
        <div class="value" style="font-size:1rem;">
            <?php if ($bestDay): ?>
                <?= formatDate($bestDay['date']) ?>
                <br><strong><?= number_format($bestDay['spp'], 2) ?></strong> output/man hr
            <?php else: ?>
                -
            <?php endif; ?>
        </div>
    </div>
    <div class="summary-card danger">
        <h3>Worst Day</h3>
        <div class="value" style="font-size:1rem;">
            <?php if ($worstDay): ?>
                <?= formatDate($worstDay['date']) ?>
                <br><strong><?= number_format($worstDay['spp'], 2) ?></strong> output/man hr
            <?php else: ?>
                -
            <?php endif; ?>
        </div>
    </div>
    <div class="summary-card">
        <h3>Total Output</h3>
        <div class="value"><?= number_format($totalActual) ?></div>
    </div>
    <div class="summary-card">
        <h3>Total Man-Hours</h3>
        <div class="value"><?= number_format($totalManHours, 1) ?></div>
    </div>
    <div class="summary-card">
        <h3>Working Days</h3>
        <div class="value"><?= $dayCount ?></div>
    </div>
</div>

<!-- Line Chart: Output per Man Hour Trend -->
<div class="card">
    <div class="card-header">Output per Man Hour Trend</div>
    <div class="chart-container" style="max-width:100%;height:400px;">
        <canvas id="sppChart"></canvas>
    </div>
</div>

<!-- Detail Table -->
<div class="card">
    <div class="card-header">Daily Manpower Efficiency Detail</div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Group</th>
                    <th class="num">Total Output</th>
                    <th class="num">Man-Hours</th>
                    <th class="num">Avg Workers</th>
                    <th class="num">Output/Man Hr</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detailRows as $r): ?>
                <?php
                    $spp = (float)$r['seats_per_person'];
                    $sppClass = $spp >= $avgSPP ? 'variance-positive' : 'variance-negative';
                ?>
                <tr>
                    <td><?= formatDate($r['production_date']) ?></td>
                    <td><?= h($r['group_name']) ?></td>
                    <td class="num"><?= number_format($r['total_actual']) ?></td>
                    <td class="num"><?= number_format((float)$r['total_man_hours'], 1) ?></td>
                    <td class="num"><?= number_format((float)$r['total_manpower_avg'], 1) ?></td>
                    <td class="num">
                        <span class="<?= $sppClass ?>"><?= number_format($spp, 2) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="cumulative-row">
                    <td colspan="2"><strong>Overall</strong></td>
                    <td class="num"><strong><?= number_format($totalActual) ?></strong></td>
                    <td class="num"><strong><?= number_format($totalManHours, 1) ?></strong></td>
                    <td class="num">-</td>
                    <td class="num"><strong><?= number_format($avgSPP, 2) ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php endif; /* end empty check */ ?>

<!-- Chart.js Rendering -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('sppChart');
    if (!canvas) return;

    const labels = <?= json_encode($chartLabels) ?>;
    const spp    = <?= json_encode($chartSPP) ?>;
    const avgSPP = <?= json_encode($avgSPP) ?>;

    if (labels.length === 0) return;

    // Build average line data (constant across all days)
    const avgLine = labels.map(() => avgSPP);

    new Chart(canvas, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Output/Man Hr',
                    data: spp,
                    borderColor: '#2c5282',
                    backgroundColor: 'rgba(44, 82, 130, 0.1)',
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: '#2c5282',
                    fill: true,
                    tension: 0.2
                },
                {
                    label: 'Average (' + avgSPP.toFixed(2) + ')',
                    data: avgLine,
                    borderColor: 'rgba(229, 62, 62, 0.7)',
                    borderWidth: 2,
                    borderDash: [6, 4],
                    pointRadius: 0,
                    fill: false
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
                            return context.dataset.label + ': ' + context.parsed.y.toFixed(2);
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 0,
                        font: { size: 11 }
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Output per Man Hour'
                    }
                }
            }
        }
    });
});
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
