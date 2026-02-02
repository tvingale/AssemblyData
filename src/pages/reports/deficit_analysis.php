<?php
/**
 * Deficit Reason Pareto Analysis (R5)
 *
 * Ranked bar chart of deficit reasons by total units lost with cumulative % line,
 * detail table, breakdown per reason with dates/slots, recurring reason highlighting,
 * and "Other" free-text frequency table.
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

// ── Build WHERE clause fragments ────────────────────────────────────────
$whereParts  = ['pe.actual_output < pe.target_output', 'pe.production_date BETWEEN ? AND ?'];
$params      = [$startDate, $endDate];

if ($groupId > 0) {
    $whereParts[] = 'pe.group_id = ?';
    $params[]     = $groupId;
}

$whereSQL = implode(' AND ', $whereParts);

// ── 1. Pareto data: deficit reasons ranked by total units lost ──────────
$paretoSQL = "
    SELECT
        dr.id            AS reason_id,
        dr.reason_text,
        COUNT(*)         AS occurrences,
        SUM(FLOOR(pe.target_output) - pe.actual_output) AS total_deficit
    FROM production_entries pe
    JOIN deficit_reasons dr ON pe.deficit_reason_id = dr.id
    WHERE {$whereSQL}
    GROUP BY dr.id, dr.reason_text
    ORDER BY total_deficit DESC
";
$stmt = $db->prepare($paretoSQL);
$stmt->execute($params);
$paretoRows = $stmt->fetchAll();

// Also count entries that have a deficit but NO reason selected (NULL deficit_reason_id)
$noReasonSQL = "
    SELECT
        COUNT(*)         AS occurrences,
        SUM(FLOOR(pe.target_output) - pe.actual_output) AS total_deficit
    FROM production_entries pe
    WHERE {$whereSQL}
      AND pe.deficit_reason_id IS NULL
";
$stmt = $db->prepare($noReasonSQL);
$stmt->execute($params);
$noReasonRow = $stmt->fetch();

if ($noReasonRow && $noReasonRow['occurrences'] > 0) {
    $paretoRows[] = [
        'reason_id'     => 0,
        'reason_text'   => '(No reason selected)',
        'occurrences'   => (int)$noReasonRow['occurrences'],
        'total_deficit' => (int)$noReasonRow['total_deficit'],
    ];
    // Re-sort after adding the extra row
    usort($paretoRows, fn($a, $b) => $b['total_deficit'] <=> $a['total_deficit']);
}

// Grand total for percentages
$grandTotal = 0;
foreach ($paretoRows as $r) {
    $grandTotal += (int)$r['total_deficit'];
}

// Add rank, percentages, cumulative %
$cumulative = 0;
foreach ($paretoRows as $i => &$r) {
    $r['rank']       = $i + 1;
    $r['pct']        = $grandTotal > 0 ? round(($r['total_deficit'] / $grandTotal) * 100, 1) : 0;
    $cumulative     += $r['pct'];
    $r['cum_pct']    = round($cumulative, 1);
    $r['recurring']  = ((int)$r['occurrences'] >= 3);
}
unset($r);

// ── 2. Breakdown: per reason, list dates/slots with deficit amounts ─────
$breakdownData = [];
// Only fetch breakdown for the top 10 reasons (by rank)
$topReasons = array_slice($paretoRows, 0, 10);

foreach ($topReasons as $tr) {
    $rid = (int)$tr['reason_id'];
    if ($rid === 0) {
        // Entries with no reason selected
        $bdSQL = "
            SELECT pe.production_date, pe.slot_number, pe.group_id,
                   pg.name AS group_name,
                   FLOOR(pe.target_output) - pe.actual_output AS deficit
            FROM production_entries pe
            JOIN production_groups pg ON pe.group_id = pg.id
            WHERE {$whereSQL}
              AND pe.deficit_reason_id IS NULL
            ORDER BY pe.production_date, pe.slot_number
        ";
        $bdStmt = $db->prepare($bdSQL);
        $bdStmt->execute($params);
    } else {
        $bdSQL = "
            SELECT pe.production_date, pe.slot_number, pe.group_id,
                   pg.name AS group_name,
                   FLOOR(pe.target_output) - pe.actual_output AS deficit
            FROM production_entries pe
            JOIN production_groups pg ON pe.group_id = pg.id
            WHERE {$whereSQL}
              AND pe.deficit_reason_id = ?
            ORDER BY pe.production_date, pe.slot_number
        ";
        $bdParams = array_merge($params, [$rid]);
        $bdStmt = $db->prepare($bdSQL);
        $bdStmt->execute($bdParams);
    }
    $breakdownData[$rid] = $bdStmt->fetchAll();
}

// ── 3. "Other" free-text frequency analysis ─────────────────────────────
$otherSQL = "
    SELECT
        LOWER(TRIM(pe.deficit_reason_other)) AS other_text,
        COUNT(*) AS frequency,
        SUM(FLOOR(pe.target_output) - pe.actual_output) AS total_deficit
    FROM production_entries pe
    WHERE {$whereSQL}
      AND pe.deficit_reason_other IS NOT NULL
      AND pe.deficit_reason_other != ''
    GROUP BY other_text
    ORDER BY frequency DESC
";
$stmt = $db->prepare($otherSQL);
$stmt->execute($params);
$otherRows = $stmt->fetchAll();

// ── Prepare JSON for Chart.js ───────────────────────────────────────────
$chartLabels   = [];
$chartDeficits = [];
$chartCumPct   = [];
foreach ($paretoRows as $r) {
    $chartLabels[]   = $r['reason_text'];
    $chartDeficits[] = (int)$r['total_deficit'];
    $chartCumPct[]   = (float)$r['cum_pct'];
}

$pageTitle = 'Deficit Reason Analysis';
$baseUrl   = '../..';
include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
    <h1>Deficit Reason Pareto Analysis</h1>
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

<?php if (empty($paretoRows)): ?>
    <div class="alert alert-info">No deficit entries found for the selected period and group.</div>
<?php else: ?>

<!-- Summary Cards -->
<div class="summary-cards">
    <div class="summary-card danger">
        <h3>Total Deficit Units</h3>
        <div class="value"><?= number_format($grandTotal) ?></div>
    </div>
    <div class="summary-card warning">
        <h3>Distinct Reasons</h3>
        <div class="value"><?= count($paretoRows) ?></div>
    </div>
    <div class="summary-card info">
        <h3>Top Reason</h3>
        <div class="value"><?= h($paretoRows[0]['reason_text'] ?? '-') ?></div>
    </div>
    <div class="summary-card <?= count(array_filter($paretoRows, fn($r) => $r['recurring'])) > 0 ? 'danger' : 'success' ?>">
        <h3>Recurring (3+)</h3>
        <div class="value"><?= count(array_filter($paretoRows, fn($r) => $r['recurring'])) ?></div>
    </div>
</div>

<!-- Pareto Chart -->
<div class="card">
    <div class="card-header">Deficit Reason Pareto Chart</div>
    <div class="chart-container" style="max-width:100%;height:420px;">
        <canvas id="paretoChart"></canvas>
    </div>
</div>

<!-- Detail Table -->
<div class="card">
    <div class="card-header">Deficit Reasons Ranked</div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th class="num">Rank</th>
                    <th>Reason</th>
                    <th class="num">Total Deficit</th>
                    <th class="num">Occurrences</th>
                    <th class="num">% of Total</th>
                    <th class="num">Cumulative %</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($paretoRows as $r): ?>
                <tr>
                    <td class="num"><?= $r['rank'] ?></td>
                    <td>
                        <?= h($r['reason_text']) ?>
                    </td>
                    <td class="num"><?= number_format($r['total_deficit']) ?></td>
                    <td class="num"><?= number_format($r['occurrences']) ?></td>
                    <td class="num"><?= $r['pct'] ?>%</td>
                    <td class="num"><?= $r['cum_pct'] ?>%</td>
                    <td>
                        <?php if ($r['recurring']): ?>
                            <span class="badge badge-danger">Recurring</span>
                        <?php else: ?>
                            <span class="badge badge-success">Isolated</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="cumulative-row">
                    <td></td>
                    <td><strong>Grand Total</strong></td>
                    <td class="num"><strong><?= number_format($grandTotal) ?></strong></td>
                    <td class="num"><strong><?= number_format(array_sum(array_column($paretoRows, 'occurrences'))) ?></strong></td>
                    <td class="num"><strong>100%</strong></td>
                    <td class="num"></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Breakdown per Reason -->
<div class="card">
    <div class="card-header">Breakdown by Top Reasons</div>
    <?php foreach ($topReasons as $tr): ?>
        <?php
            $rid   = (int)$tr['reason_id'];
            $items = $breakdownData[$rid] ?? [];
            if (empty($items)) continue;
        ?>
        <div style="margin-bottom:1.25rem;">
            <h3 style="font-size:0.95rem;margin:0.75rem 0 0.5rem;color:var(--gray-700);">
                #<?= $tr['rank'] ?>. <?= h($tr['reason_text']) ?>
                <span style="font-weight:400;color:var(--gray-500);font-size:0.85rem;">
                    &mdash; <?= number_format($tr['total_deficit']) ?> units lost across <?= number_format($tr['occurrences']) ?> occurrences
                </span>
                <?php if ($tr['recurring']): ?>
                    <span class="badge badge-danger" style="margin-left:0.5rem;">Recurring</span>
                <?php endif; ?>
            </h3>
            <div class="table-responsive">
                <table class="table-compact">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Slot</th>
                            <th>Group</th>
                            <th class="num">Deficit (units)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= formatDate($item['production_date']) ?></td>
                            <td><?= h($item['slot_number']) ?></td>
                            <td><?= h($item['group_name']) ?></td>
                            <td class="num variance-negative"><?= number_format($item['deficit']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- "Other" Free-Text Frequency Table -->
<?php if (!empty($otherRows)): ?>
<div class="card">
    <div class="card-header">"Other" Reason Free-Text Frequency</div>
    <div class="table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Other Reason Text</th>
                    <th class="num">Frequency</th>
                    <th class="num">Total Deficit</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($otherRows as $o): ?>
                <tr>
                    <td><?= h($o['other_text']) ?></td>
                    <td class="num"><?= number_format($o['frequency']) ?></td>
                    <td class="num"><?= number_format($o['total_deficit']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php endif; /* end empty paretoRows check */ ?>

<!-- Chart.js Rendering -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('paretoChart');
    if (!canvas) return;

    const labels   = <?= json_encode($chartLabels) ?>;
    const deficits = <?= json_encode($chartDeficits) ?>;
    const cumPct   = <?= json_encode($chartCumPct) ?>;

    if (labels.length === 0) return;

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Total Deficit (units)',
                    data: deficits,
                    backgroundColor: 'rgba(229, 62, 62, 0.7)',
                    borderColor: 'rgba(229, 62, 62, 1)',
                    borderWidth: 1,
                    yAxisID: 'y',
                    order: 2
                },
                {
                    label: 'Cumulative %',
                    data: cumPct,
                    type: 'line',
                    borderColor: '#2c5282',
                    backgroundColor: 'rgba(44, 82, 130, 0.1)',
                    borderWidth: 2,
                    pointRadius: 4,
                    pointBackgroundColor: '#2c5282',
                    fill: false,
                    yAxisID: 'y1',
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
                        label: function(context) {
                            if (context.dataset.yAxisID === 'y1') {
                                return context.dataset.label + ': ' + context.parsed.y + '%';
                            }
                            return context.dataset.label + ': ' + context.parsed.y.toLocaleString();
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
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Deficit (units)'
                    }
                },
                y1: {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    max: 100,
                    title: {
                        display: true,
                        text: 'Cumulative %'
                    },
                    grid: {
                        drawOnChartArea: false
                    },
                    ticks: {
                        callback: function(value) { return value + '%'; }
                    }
                }
            }
        }
    });
});
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
