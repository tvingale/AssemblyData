<?php
/**
 * Downtime Pareto by Category (R6)
 *
 * Bar chart of downtime minutes by category, detail summary table,
 * and a full log of individual downtime events for the selected period.
 */
define('APP_ROOT', dirname(dirname(__DIR__)));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';

$db = getDB();

// ── Filter parameters ──────────────────────────────────────────────────
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate   = $_GET['end_date']   ?? date('Y-m-d');
$groupId   = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0; // 0 = all
$sortCol   = $_GET['sort'] ?? 'production_date';
$sortDir   = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc') ? 'ASC' : 'DESC';

$groups = getActiveGroups();

// Allowed sort columns for security
$allowedSorts = [
    'production_date' => 'd.production_date',
    'group_name'      => 'pg.name',
    'start_time'      => 'd.start_time',
    'duration'        => 'd.duration_minutes',
    'category'        => 'd.category',
];
$orderByCol = $allowedSorts[$sortCol] ?? 'd.production_date';

// ── Build WHERE clause ─────────────────────────────────────────────────
$whereParts = ['d.production_date BETWEEN ? AND ?'];
$params     = [$startDate, $endDate];

if ($groupId > 0) {
    $whereParts[] = 'd.group_id = ?';
    $params[]     = $groupId;
}

$whereSQL = implode(' AND ', $whereParts);

// ── 1. Category summary ────────────────────────────────────────────────
$categorySQL = "
    SELECT
        d.category,
        COUNT(*)                         AS event_count,
        COALESCE(SUM(d.duration_minutes), 0) AS total_minutes
    FROM downtimes d
    WHERE {$whereSQL}
    GROUP BY d.category
    ORDER BY total_minutes DESC
";
$stmt = $db->prepare($categorySQL);
$stmt->execute($params);
$categoryRows = $stmt->fetchAll();

// Grand totals
$grandMinutes = 0;
$grandEvents  = 0;
foreach ($categoryRows as &$c) {
    $c['total_minutes'] = (float)$c['total_minutes'];
    $c['event_count']   = (int)$c['event_count'];
    $c['avg_duration']  = $c['event_count'] > 0
        ? round($c['total_minutes'] / $c['event_count'], 1) : 0;
    $grandMinutes += $c['total_minutes'];
    $grandEvents  += $c['event_count'];
}
unset($c);

// Compute percentages
foreach ($categoryRows as &$c) {
    $c['pct'] = $grandMinutes > 0
        ? round(($c['total_minutes'] / $grandMinutes) * 100, 1) : 0;
}
unset($c);

// ── 2. Individual downtime events log ──────────────────────────────────
$logSQL = "
    SELECT
        d.id,
        d.production_date,
        d.group_id,
        pg.name AS group_name,
        d.start_time,
        d.end_time,
        d.duration_minutes,
        d.category,
        d.reason
    FROM downtimes d
    JOIN production_groups pg ON d.group_id = pg.id
    WHERE {$whereSQL}
    ORDER BY {$orderByCol} {$sortDir}, d.start_time ASC
";
$stmt = $db->prepare($logSQL);
$stmt->execute($params);
$logRows = $stmt->fetchAll();

// ── Prepare Chart.js data ──────────────────────────────────────────────
// Canonical category order
$allCategories = ['mechanical', 'electrical', 'material', 'manpower', 'quality', 'other'];
$categoryColors = [
    'mechanical' => 'rgba(229, 62, 62, 0.7)',
    'electrical' => 'rgba(214, 158, 46, 0.7)',
    'material'   => 'rgba(49, 130, 206, 0.7)',
    'manpower'   => 'rgba(56, 161, 105, 0.7)',
    'quality'    => 'rgba(128, 90, 213, 0.7)',
    'other'      => 'rgba(160, 174, 192, 0.7)',
];
$categoryBorders = [
    'mechanical' => 'rgba(229, 62, 62, 1)',
    'electrical' => 'rgba(214, 158, 46, 1)',
    'material'   => 'rgba(49, 130, 206, 1)',
    'manpower'   => 'rgba(56, 161, 105, 1)',
    'quality'    => 'rgba(128, 90, 213, 1)',
    'other'      => 'rgba(160, 174, 192, 1)',
];

// Map category data for the chart (keep the ranked order from query)
$chartLabels  = [];
$chartMinutes = [];
$chartBgColors = [];
$chartBdColors = [];
foreach ($categoryRows as $c) {
    $cat = $c['category'];
    $chartLabels[]   = ucfirst($cat);
    $chartMinutes[]  = round($c['total_minutes'], 1);
    $chartBgColors[] = $categoryColors[$cat] ?? 'rgba(160,174,192,0.7)';
    $chartBdColors[] = $categoryBorders[$cat] ?? 'rgba(160,174,192,1)';
}

// ── Helper to build sort link ──────────────────────────────────────────
function sortLink(string $col, string $label): string {
    global $sortCol, $sortDir, $startDate, $endDate, $groupId;
    $newDir = ($sortCol === $col && $sortDir === 'ASC') ? 'desc' : 'asc';
    $arrow  = '';
    if ($sortCol === $col) {
        $arrow = $sortDir === 'ASC' ? ' &#9650;' : ' &#9660;';
    }
    $qs = http_build_query([
        'start_date' => $startDate,
        'end_date'   => $endDate,
        'group_id'   => $groupId,
        'sort'       => $col,
        'dir'        => $newDir,
    ]);
    return '<a href="?' . htmlspecialchars($qs) . '" style="color:inherit;text-decoration:none;">'
         . htmlspecialchars($label) . $arrow . '</a>';
}

$pageTitle = 'Downtime Analysis';
$baseUrl   = '../..';
include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
    <h1>Downtime Pareto by Category</h1>
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

<?php if (empty($categoryRows)): ?>
    <div class="alert alert-info">No downtime events found for the selected period and group.</div>
<?php else: ?>

<!-- Summary Cards -->
<div class="summary-cards">
    <div class="summary-card danger">
        <h3>Total Downtime</h3>
        <div class="value"><?= number_format($grandMinutes, 0) ?> min</div>
    </div>
    <div class="summary-card warning">
        <h3>Total Events</h3>
        <div class="value"><?= number_format($grandEvents) ?></div>
    </div>
    <div class="summary-card info">
        <h3>Top Category</h3>
        <div class="value"><?= ucfirst(h($categoryRows[0]['category'] ?? '-')) ?></div>
    </div>
    <div class="summary-card">
        <h3>Avg Duration / Event</h3>
        <div class="value"><?= $grandEvents > 0 ? number_format($grandMinutes / $grandEvents, 1) : '0' ?> min</div>
    </div>
</div>

<!-- Downtime Category Bar Chart -->
<div class="card">
    <div class="card-header">Downtime Minutes by Category</div>
    <div class="chart-container" style="max-width:100%;height:380px;">
        <canvas id="downtimeChart"></canvas>
    </div>
</div>

<!-- Category Detail Table -->
<div class="card">
    <div class="card-header">Category Summary</div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th class="num">Total Minutes</th>
                    <th class="num">Event Count</th>
                    <th class="num">% of Total</th>
                    <th class="num">Avg Duration / Event</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categoryRows as $c): ?>
                <tr>
                    <td>
                        <span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:<?= $categoryColors[$c['category']] ?? 'gray' ?>;vertical-align:middle;margin-right:0.4rem;"></span>
                        <?= ucfirst(h($c['category'])) ?>
                    </td>
                    <td class="num"><?= number_format($c['total_minutes'], 1) ?></td>
                    <td class="num"><?= number_format($c['event_count']) ?></td>
                    <td class="num"><?= $c['pct'] ?>%</td>
                    <td class="num"><?= number_format($c['avg_duration'], 1) ?> min</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="cumulative-row">
                    <td><strong>Total</strong></td>
                    <td class="num"><strong><?= number_format($grandMinutes, 1) ?></strong></td>
                    <td class="num"><strong><?= number_format($grandEvents) ?></strong></td>
                    <td class="num"><strong>100%</strong></td>
                    <td class="num"><strong><?= $grandEvents > 0 ? number_format($grandMinutes / $grandEvents, 1) : '0' ?> min</strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Individual Downtime Events Log -->
<div class="card">
    <div class="card-header">
        Downtime Event Log
        <span style="float:right;font-weight:400;font-size:0.85rem;color:var(--gray-500);">
            <?= number_format(count($logRows)) ?> events &mdash; click column headers to sort
        </span>
    </div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th><?= sortLink('production_date', 'Date') ?></th>
                    <th><?= sortLink('group_name', 'Group') ?></th>
                    <th><?= sortLink('start_time', 'Start') ?></th>
                    <th>End</th>
                    <th class="num"><?= sortLink('duration', 'Duration (min)') ?></th>
                    <th><?= sortLink('category', 'Category') ?></th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logRows)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--gray-400);">No events.</td></tr>
                <?php else: ?>
                    <?php foreach ($logRows as $ev): ?>
                    <tr>
                        <td><?= formatDate($ev['production_date']) ?></td>
                        <td><?= h($ev['group_name']) ?></td>
                        <td><?= $ev['start_time'] ? formatTime($ev['start_time']) : '-' ?></td>
                        <td><?= $ev['end_time'] ? formatTime($ev['end_time']) : '<span class="badge badge-warning">Ongoing</span>' ?></td>
                        <td class="num"><?= $ev['duration_minutes'] !== null ? number_format($ev['duration_minutes'], 1) : '-' ?></td>
                        <td>
                            <span style="display:inline-block;width:10px;height:10px;border-radius:2px;background:<?= $categoryColors[$ev['category']] ?? 'gray' ?>;vertical-align:middle;margin-right:0.3rem;"></span>
                            <?= ucfirst(h($ev['category'])) ?>
                        </td>
                        <td><?= h($ev['reason'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; /* end empty categoryRows check */ ?>

<!-- Chart.js Rendering -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('downtimeChart');
    if (!canvas) return;

    const labels   = <?= json_encode($chartLabels) ?>;
    const minutes  = <?= json_encode($chartMinutes) ?>;
    const bgColors = <?= json_encode($chartBgColors) ?>;
    const bdColors = <?= json_encode($chartBdColors) ?>;

    if (labels.length === 0) return;

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Downtime (minutes)',
                data: minutes,
                backgroundColor: bgColors,
                borderColor: bdColors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.parsed.y.toLocaleString(undefined, {
                                minimumFractionDigits: 1,
                                maximumFractionDigits: 1
                            }) + ' minutes';
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        font: { size: 12 }
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Minutes'
                    }
                }
            }
        }
    });
});
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
