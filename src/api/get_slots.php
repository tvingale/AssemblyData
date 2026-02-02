<?php
/**
 * API: Get resolved time slots for a date
 * GET ?date=YYYY-MM-DD
 * Returns: slots array with effective_minutes calculated, plus shift info
 */
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';

$date = $_GET['date'] ?? date('Y-m-d');
$groupId = isset($_GET['group_id']) ? (int)$_GET['group_id'] : null;

$slots = resolveTimeSlots($date);
$breaks = resolveBreaks($date, $groupId);
$shiftTimes = getShiftTimes($date);

// Calculate effective minutes for each slot
$result = [];
foreach ($slots as $slot) {
    $effMin = calculateEffectiveMinutes($slot['start_time'], $slot['end_time'], $breaks);
    $result[] = [
        'slot_number' => (int)$slot['slot_number'],
        'start_time'  => substr($slot['start_time'], 0, 5),
        'end_time'    => substr($slot['end_time'], 0, 5),
        'label'       => $slot['label'],
        'effective_minutes' => round($effMin, 2),
        'duration_minutes'  => round(getSlotDurationMinutes($slot['start_time'], $slot['end_time']), 2),
    ];
}

jsonResponse([
    'date'  => $date,
    'day_type' => getDayType($date),
    'shift' => $shiftTimes,
    'slots' => $result,
    'breaks' => array_map(function($b) {
        return [
            'type'  => $b['break_type'],
            'label' => $b['label'],
            'start' => substr($b['start_time'], 0, 5),
            'end'   => substr($b['end_time'], 0, 5),
        ];
    }, $breaks),
]);
