<?php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';

$date = $_GET['date'] ?? date('Y-m-d');
$groups = getActiveGroups();
$shiftTimes = getShiftTimes($date);

// Build summaries
$summaries = [];
$plantTarget = 0; $plantActual = 0; $plantDowntime = 0;
$plantManHours = 0; $plantManpower = 0; $groupCount = 0;

foreach ($groups as $g) {
    $s = computeDailySummary($date, $g['id']);
    $s['group_name'] = $g['name'];
    $s['group_id'] = $g['id'];

    $variance = $s['total_actual'] - $s['total_target'];
    $s['variance'] = round($variance, 2);
    $s['variance_pct'] = $s['total_target'] > 0
        ? round(($variance / $s['total_target']) * 100, 1) : 0;

    // Slot details
    $entries = getProductionEntries($date, $g['id']);
    $slots = resolveTimeSlots($date);
    $slotMap = [];
    foreach ($slots as $sl) { $slotMap[(int)$sl['slot_number']] = $sl; }
    $s['slot_details'] = [];
    foreach ($entries as $e) {
        $slotInfo = $slotMap[(int)$e['slot_number']] ?? null;
        $s['slot_details'][] = [
            'slot_number' => $e['slot_number'],
            'time' => $slotInfo ? formatTime($slotInfo['start_time']) . '-' . formatTime($slotInfo['end_time']) : '',
            'label' => $slotInfo['label'] ?? '',
            'cells' => $e['cells_operative'],
            'manpower' => $e['manpower_headcount'],
            'eff_min' => $e['effective_minutes'],
            'target' => $e['target_output'],
            'actual' => $e['actual_output'],
            'variance' => round($e['actual_output'] - $e['target_output'], 2),
            'time_lost' => $e['downtime_minutes'] ?? 0,
            'reason' => $e['reason_text'] ?? '',
            'reason_other' => $e['deficit_reason_other'] ?? '',
        ];
    }

    $summaries[] = $s;
    $plantTarget += $s['total_target'];
    $plantActual += $s['total_actual'];
    $plantDowntime += $s['total_downtime_minutes'];
    $plantManHours += $s['total_man_hours'];
    $plantManpower += $s['total_manpower_avg'];
    $groupCount++;
}

$plantVariance = $plantActual - $plantTarget;
$plantVariancePct = $plantTarget > 0 ? round(($plantVariance / $plantTarget) * 100, 1) : 0;
$plantSeatsPerPerson = $plantManHours > 0 ? round($plantActual / $plantManHours, 2) : 0;

$pageTitle = 'Daily Summary';
$baseUrl = '..';
include APP_ROOT . '/includes/header.php';
?>

<div class="page-header">
    <h1>Daily Production Summary</h1>
    <button class="btn btn-outline no-print" onclick="window.print()">Print</button>
</div>

<div class="date-bar no-print">
    <label for="sum-date">Date:</label>
    <input type="date" id="sum-date" value="<?= h($date) ?>" onchange="window.location='?date='+this.value">
    <span class="shift-info">
        <?= formatDate($date) ?> | Shift: <?= formatTime($shiftTimes['start']) ?> - <?= formatTime($shiftTimes['end']) ?>
    </span>
</div>

<!-- Linewise summary table -->
<div class="card">
    <div class="card-header">Linewise Summary - <?= formatDate($date) ?></div>
    <div class="table-responsive">
        <table style="table-layout:fixed;">
            <colgroup>
                <col style="width:22%;">
                <col style="width:11%;">
                <col style="width:11%;">
                <col style="width:11%;">
                <col style="width:11%;">
                <col style="width:11%;">
                <col style="width:12%;">
                <col style="width:11%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Group</th>
                    <th class="num">Target</th>
                    <th class="num">Actual</th>
                    <th class="num">Variance</th>
                    <th class="num">Var %</th>
                    <th class="num">Avg MP</th>
                    <th class="num">Seats/Person</th>
                    <th class="num">Time Lost</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($summaries as $idx => $s): ?>
                <tr class="expandable-toggle" data-target="detail-<?= $s['group_id'] ?>">
                    <td><strong><?= h($s['group_name']) ?></strong></td>
                    <td class="num"><?= number_format($s['total_target']) ?></td>
                    <td class="num"><?= number_format($s['total_actual']) ?></td>
                    <td class="num <?= $s['variance'] >= 0 ? 'variance-cell-positive' : 'variance-cell-negative' ?>">
                        <span class="<?= $s['variance'] >= 0 ? 'variance-positive' : 'variance-negative' ?>">
                            <?= ($s['variance'] >= 0 ? '+' : '') . number_format($s['variance']) ?>
                        </span>
                    </td>
                    <td class="num"><?= ($s['variance_pct'] >= 0 ? '+' : '') . $s['variance_pct'] ?>%</td>
                    <td class="num"><?= number_format($s['total_manpower_avg'], 1) ?></td>
                    <td class="num"><?= number_format($s['seats_per_person'], 2) ?></td>
                    <td class="num"><?= number_format($s['total_downtime_minutes'], 0) ?></td>
                </tr>
                <!-- Expandable detail row -->
                <tr id="detail-<?= $s['group_id'] ?>" class="expandable-detail">
                    <td colspan="8" style="padding:0;">
                        <?php if (!empty($s['slot_details'])): ?>
                        <table class="table-compact" style="margin:0.5rem 1rem 0.5rem 2rem; width:calc(100% - 3rem);">
                            <thead>
                                <tr>
                                    <th>Slot</th>
                                    <th>Time</th>
                                    <th class="num">Cells</th>
                                    <th class="num">MP</th>
                                    <th class="num">Eff Min</th>
                                    <th class="num">Target</th>
                                    <th class="num">Actual</th>
                                    <th class="num">Var</th>
                                    <th class="num">Lost</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($s['slot_details'] as $sd): ?>
                                <tr>
                                    <td><?= h($sd['label']) ?></td>
                                    <td><?= h($sd['time']) ?></td>
                                    <td class="num"><?= $sd['cells'] ?></td>
                                    <td class="num"><?= $sd['manpower'] ?></td>
                                    <td class="num"><?= number_format($sd['eff_min'], 1) ?></td>
                                    <td class="num"><?= number_format($sd['target'], 1) ?></td>
                                    <td class="num"><?= number_format($sd['actual']) ?></td>
                                    <td class="num <?= $sd['variance'] >= 0 ? 'variance-cell-positive' : 'variance-cell-negative' ?>">
                                        <?= ($sd['variance'] >= 0 ? '+' : '') . number_format($sd['variance'], 1) ?>
                                    </td>
                                    <td class="num"><?= $sd['time_lost'] > 0 ? number_format($sd['time_lost'], 0) : '-' ?></td>
                                    <td>
                                        <?= h($sd['reason']) ?>
                                        <?= $sd['reason_other'] ? ' - ' . h($sd['reason_other']) : '' ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                            <p style="padding:1rem;color:var(--gray-500);">No entries for this group.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="cumulative-row">
                    <td><strong>Plant Total</strong></td>
                    <td class="num"><strong><?= number_format($plantTarget) ?></strong></td>
                    <td class="num"><strong><?= number_format($plantActual) ?></strong></td>
                    <td class="num <?= $plantVariance >= 0 ? 'variance-cell-positive' : 'variance-cell-negative' ?>">
                        <strong><?= ($plantVariance >= 0 ? '+' : '') . number_format($plantVariance) ?></strong>
                    </td>
                    <td class="num"><strong><?= ($plantVariancePct >= 0 ? '+' : '') . $plantVariancePct ?>%</strong></td>
                    <td class="num"><?= $groupCount > 0 ? number_format($plantManpower / $groupCount, 1) : '-' ?></td>
                    <td class="num"><strong><?= number_format($plantSeatsPerPerson, 2) ?></strong></td>
                    <td class="num"><strong><?= number_format($plantDowntime, 0) ?></strong></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Metric Explanations -->
<div class="card no-print" style="margin-top:1rem;">
    <div class="card-header">Metric Calculations</div>
    <div style="font-size:0.85rem;line-height:1.6;">
        <table class="table-compact">
            <tr>
                <td style="width:140px;"><strong>Effective Minutes</strong></td>
                <td>= Slot Duration - Break Time (during that slot)</td>
            </tr>
            <tr>
                <td><strong>Target</strong></td>
                <td>= Rate per Cell per Hour &times; (Effective Minutes &divide; 60) &times; Cells Operative</td>
            </tr>
            <tr>
                <td><strong>Variance</strong></td>
                <td>= Actual Output - Target Output</td>
            </tr>
            <tr>
                <td><strong>Variance %</strong></td>
                <td>= (Variance &divide; Target) &times; 100</td>
            </tr>
            <tr>
                <td><strong>Avg Manpower</strong></td>
                <td>= Sum of Manpower across all slots &divide; Number of slots entered</td>
            </tr>
            <tr>
                <td><strong>Man Hours</strong></td>
                <td>= Sum of (Manpower &times; Effective Hours) for each slot</td>
            </tr>
            <tr>
                <td><strong>Seats/Person</strong></td>
                <td>= Total Actual Output &divide; Total Man Hours</td>
            </tr>
            <tr>
                <td><strong>Time Lost</strong></td>
                <td>= Sum of minutes lost across all slots (entered during data entry)</td>
            </tr>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => App.initExpandable());
</script>

<?php include APP_ROOT . '/includes/footer.php'; ?>
