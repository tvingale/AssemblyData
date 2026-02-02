<?php
/**
 * API: Save daily configuration overrides (shift times, slots, breaks)
 * POST JSON: { date, action: 'save_shift'|'save_slots'|'save_break'|'delete_break'|'get', ... }
 */
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

$input = getJsonInput();
$action = $input['action'] ?? '';
$date = $input['date'] ?? '';

$db = getDB();

if ($action === 'get') {
    if (!$date) jsonResponse(['error' => 'Date required'], 400);

    // Get shift override
    $stmt = $db->prepare('SELECT * FROM daily_shift_config WHERE production_date = ?');
    $stmt->execute([$date]);
    $shiftOverride = $stmt->fetch() ?: null;

    // Get slot overrides
    $stmt = $db->prepare('SELECT * FROM daily_time_slots WHERE production_date = ? ORDER BY slot_number');
    $stmt->execute([$date]);
    $slotOverrides = $stmt->fetchAll();

    // Get break overrides
    $stmt = $db->prepare('SELECT * FROM breaks WHERE production_date = ? ORDER BY start_time');
    $stmt->execute([$date]);
    $breakOverrides = $stmt->fetchAll();

    jsonResponse([
        'date' => $date,
        'shift_override' => $shiftOverride,
        'slot_overrides' => $slotOverrides,
        'break_overrides' => $breakOverrides,
    ]);
}

if ($action === 'save_shift') {
    $shiftStart = $input['shift_start'] ?? '';
    $shiftEnd = $input['shift_end'] ?? '';
    $notes = trim($input['notes'] ?? '');

    if (!$date || !$shiftStart || !$shiftEnd) {
        jsonResponse(['error' => 'Date, start and end times required'], 400);
    }

    $stmt = $db->prepare('INSERT INTO daily_shift_config (production_date, shift_start, shift_end, notes)
                          VALUES (?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE shift_start = VALUES(shift_start), shift_end = VALUES(shift_end), notes = VALUES(notes)');
    $stmt->execute([$date, $shiftStart, $shiftEnd, $notes]);
    jsonResponse(['success' => true]);
}

if ($action === 'delete_shift') {
    if (!$date) jsonResponse(['error' => 'Date required'], 400);
    $stmt = $db->prepare('DELETE FROM daily_shift_config WHERE production_date = ?');
    $stmt->execute([$date]);
    jsonResponse(['success' => true]);
}

if ($action === 'save_slots') {
    $slots = $input['slots'] ?? [];
    if (!$date) jsonResponse(['error' => 'Date required'], 400);

    // Delete existing overrides for this date, then insert new ones
    $stmt = $db->prepare('DELETE FROM daily_time_slots WHERE production_date = ?');
    $stmt->execute([$date]);

    if (!empty($slots)) {
        $insert = $db->prepare('INSERT INTO daily_time_slots (production_date, slot_number, start_time, end_time, label) VALUES (?, ?, ?, ?, ?)');
        foreach ($slots as $s) {
            $insert->execute([$date, $s['slot_number'], $s['start_time'], $s['end_time'], $s['label'] ?? '']);
        }
    }

    jsonResponse(['success' => true]);
}

if ($action === 'save_break') {
    $breakType = $input['break_type'] ?? 'lunch';
    $label = trim($input['label'] ?? '');
    $startTime = $input['start_time'] ?? '';
    $endTime = $input['end_time'] ?? '';

    if (!$date || !$startTime || !$endTime) {
        jsonResponse(['error' => 'Date and times required'], 400);
    }

    $stmt = $db->prepare('INSERT INTO breaks (break_type, label, is_default, production_date, start_time, end_time) VALUES (?, ?, 0, ?, ?, ?)');
    $stmt->execute([$breakType, $label, $date, $startTime, $endTime]);
    jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
}

if ($action === 'delete_break') {
    $id = (int)($input['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID required'], 400);

    $stmt = $db->prepare('DELETE FROM breaks WHERE id = ? AND is_default = 0');
    $stmt->execute([$id]);
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Unknown action'], 400);
