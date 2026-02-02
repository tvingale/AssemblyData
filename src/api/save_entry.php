<?php
/**
 * API: Save production entries for a group on a date
 * POST JSON: { date, group_id, entries: [{ slot_number, cells_operative, manpower_headcount, actual_output, deficit_reason_id, deficit_reason_other }] }
 */
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

$input = getJsonInput();

// Handle "get" action to fetch existing entries
if (($input['action'] ?? '') === 'get') {
    $date = $input['date'] ?? '';
    $groupId = (int)($input['group_id'] ?? 0);
    if ($date && $groupId) {
        $entries = getProductionEntries($date, $groupId);
        jsonResponse(['entries' => $entries]);
    }
    jsonResponse(['entries' => []]);
}

$date = $input['date'] ?? '';
$groupId = (int)($input['group_id'] ?? 0);
$entries = $input['entries'] ?? [];

if (!$date || !$groupId || empty($entries)) {
    jsonResponse(['error' => 'Missing required fields'], 400);
}

$group = getGroup($groupId);
if (!$group) {
    jsonResponse(['error' => 'Invalid group'], 400);
}

$rate = (float)$group['expected_output_per_cell_per_hour'];
$slots = resolveTimeSlots($date);
$breaks = resolveBreaks($date, $groupId);

// Index slots by slot_number
$slotMap = [];
foreach ($slots as $s) {
    $slotMap[(int)$s['slot_number']] = $s;
}

$db = getDB();
$db->beginTransaction();

try {
    $stmt = $db->prepare('INSERT INTO production_entries
        (production_date, group_id, slot_number, cells_operative, manpower_headcount, actual_output, target_output, effective_minutes, deficit_reason_id, deficit_reason_other, downtime_minutes, downtime_category, downtime_reason)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            cells_operative = VALUES(cells_operative),
            manpower_headcount = VALUES(manpower_headcount),
            actual_output = VALUES(actual_output),
            target_output = VALUES(target_output),
            effective_minutes = VALUES(effective_minutes),
            deficit_reason_id = VALUES(deficit_reason_id),
            deficit_reason_other = VALUES(deficit_reason_other),
            downtime_minutes = VALUES(downtime_minutes),
            downtime_category = VALUES(downtime_category),
            downtime_reason = VALUES(downtime_reason),
            updated_at = CURRENT_TIMESTAMP');

    $saved = [];
    $cumTarget = 0;
    $cumActual = 0;
    $cumDowntime = 0;

    foreach ($entries as $entry) {
        $slotNum = (int)($entry['slot_number'] ?? 0);
        if (!isset($slotMap[$slotNum])) continue;

        $slot = $slotMap[$slotNum];
        $cells = (int)($entry['cells_operative'] ?? $group['default_cells']);
        $manpower = (int)($entry['manpower_headcount'] ?? 0);
        $actual = (int)($entry['actual_output'] ?? 0);
        $effMin = calculateEffectiveMinutes($slot['start_time'], $slot['end_time'], $breaks);
        $target = calculateTarget($rate, $effMin, $cells);

        $reasonId = !empty($entry['deficit_reason_id']) ? (int)$entry['deficit_reason_id'] : null;
        $reasonOther = !empty($entry['deficit_reason_other']) ? trim($entry['deficit_reason_other']) : null;

        // Downtime per slot
        $dtMinutes = (float)($entry['downtime_minutes'] ?? 0);
        $dtCategory = $entry['downtime_category'] ?? 'none';
        $dtReason = !empty($entry['downtime_reason']) ? trim($entry['downtime_reason']) : null;

        // Validate downtime category
        $validCategories = ['none', 'mechanical', 'electrical', 'material', 'manpower', 'quality', 'other'];
        if (!in_array($dtCategory, $validCategories)) {
            $dtCategory = 'none';
        }

        $stmt->execute([
            $date, $groupId, $slotNum, $cells, $manpower, $actual,
            round($target, 2), round($effMin, 2),
            $reasonId, $reasonOther,
            round($dtMinutes, 2), $dtCategory, $dtReason
        ]);

        // Accumulate for cumulative values
        $cumTarget += $target;
        $cumActual += $actual;
        $cumDowntime += $dtMinutes;

        $saved[] = [
            'slot_number' => $slotNum,
            'target_output' => round($target, 2),
            'effective_minutes' => round($effMin, 2),
            'actual_output' => $actual,
            'variance' => round($actual - $target, 2),
            'downtime_minutes' => round($dtMinutes, 2),
            'cumulative_target' => round($cumTarget, 2),
            'cumulative_actual' => $cumActual,
            'cumulative_variance' => round($cumActual - $cumTarget, 2),
            'cumulative_downtime' => round($cumDowntime, 2),
        ];
    }

    $db->commit();

    // Recompute daily summary
    $summary = computeDailySummary($date, $groupId);

    jsonResponse([
        'success' => true,
        'saved' => $saved,
        'summary' => $summary,
    ]);

} catch (Exception $e) {
    $db->rollBack();
    jsonResponse(['error' => 'Save failed: ' . $e->getMessage()], 500);
}
