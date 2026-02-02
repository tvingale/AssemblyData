<?php
/**
 * API: Compute and return daily summary for all groups or a specific group
 * GET ?date=YYYY-MM-DD[&group_id=N]
 */
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';

$date = $_GET['date'] ?? date('Y-m-d');
$groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;

$groups = getActiveGroups();
$summaries = [];

foreach ($groups as $g) {
    if ($groupId !== null && $g['id'] != $groupId) continue;
    $summary = computeDailySummary($date, $g['id']);
    $summary['group_name'] = $g['name'];

    // Get slot-level detail
    $entries = getProductionEntries($date, $g['id']);
    $slots = resolveTimeSlots($date);
    $slotMap = [];
    foreach ($slots as $s) {
        $slotMap[(int)$s['slot_number']] = $s;
    }

    $slotDetails = [];
    foreach ($entries as $e) {
        $slotInfo = $slotMap[(int)$e['slot_number']] ?? null;
        $slotDetails[] = [
            'slot_number' => $e['slot_number'],
            'start_time'  => $slotInfo ? substr($slotInfo['start_time'], 0, 5) : '',
            'end_time'    => $slotInfo ? substr($slotInfo['end_time'], 0, 5) : '',
            'label'       => $slotInfo['label'] ?? '',
            'cells_operative' => $e['cells_operative'],
            'manpower_headcount' => $e['manpower_headcount'],
            'effective_minutes' => $e['effective_minutes'],
            'target_output' => $e['target_output'],
            'actual_output' => $e['actual_output'],
            'variance' => round($e['actual_output'] - $e['target_output'], 2),
            'reason_text' => $e['reason_text'] ?? '',
            'deficit_reason_other' => $e['deficit_reason_other'] ?? '',
        ];
    }
    $summary['slot_details'] = $slotDetails;

    // Downtime info
    $downtimes = getDowntimes($date, $g['id']);
    $summary['downtimes'] = $downtimes;

    $summaries[] = $summary;
}

// Compute plant-wide totals
$plantTotal = [
    'total_target' => 0,
    'total_actual' => 0,
    'total_deficit' => 0,
    'total_excess' => 0,
    'total_downtime_minutes' => 0,
    'total_man_hours' => 0,
];
foreach ($summaries as $s) {
    $plantTotal['total_target'] += $s['total_target'];
    $plantTotal['total_actual'] += $s['total_actual'];
    $plantTotal['total_deficit'] += $s['total_deficit'];
    $plantTotal['total_excess'] += $s['total_excess'];
    $plantTotal['total_downtime_minutes'] += $s['total_downtime_minutes'];
    $plantTotal['total_man_hours'] += $s['total_man_hours'];
}
$plantTotal['variance'] = $plantTotal['total_actual'] - $plantTotal['total_target'];
$plantTotal['variance_pct'] = $plantTotal['total_target'] > 0
    ? round(($plantTotal['variance'] / $plantTotal['total_target']) * 100, 1) : 0;
$plantTotal['seats_per_person'] = $plantTotal['total_man_hours'] > 0
    ? round($plantTotal['total_actual'] / $plantTotal['total_man_hours'], 2) : 0;

jsonResponse([
    'date' => $date,
    'groups' => $summaries,
    'plant_total' => $plantTotal,
]);
