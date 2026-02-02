<?php
/**
 * API: Save or list downtime records
 * POST JSON: { action: 'add'|'list', ... }
 */
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';

$input = getJsonInput();
$action = $input['action'] ?? 'add';

$db = getDB();

if ($action === 'list') {
    $date = $input['date'] ?? date('Y-m-d');
    $groupId = (int)($input['group_id'] ?? 0);

    if (!$groupId) {
        jsonResponse(['error' => 'group_id required'], 400);
    }

    $downtimes = getDowntimes($date, $groupId);
    $totalMin = 0;
    foreach ($downtimes as $d) {
        $totalMin += (float)($d['duration_minutes'] ?? 0);
    }

    jsonResponse([
        'downtimes' => $downtimes,
        'total_minutes' => round($totalMin, 2),
    ]);
}

if ($action === 'add') {
    $date = $input['date'] ?? '';
    $groupId = (int)($input['group_id'] ?? 0);
    $startTime = $input['start_time'] ?? '';
    $endTime = $input['end_time'] ?? null;
    $reason = trim($input['reason'] ?? '');
    $category = $input['category'] ?? 'other';

    if (!$date || !$groupId || !$startTime) {
        jsonResponse(['error' => 'Date, group, and start time are required'], 400);
    }

    $durationMin = null;
    if ($endTime) {
        $s = strtotime($startTime);
        $e = strtotime($endTime);
        if ($e > $s) {
            $durationMin = round(($e - $s) / 60, 2);
        }
    }

    $stmt = $db->prepare('INSERT INTO downtimes (production_date, group_id, start_time, end_time, duration_minutes, reason, category)
                          VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$date, $groupId, $startTime, $endTime ?: null, $durationMin, $reason, $category]);

    // Recompute daily summary
    computeDailySummary($date, $groupId);

    jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
}

if ($action === 'update') {
    $id = (int)($input['id'] ?? 0);
    $endTime = $input['end_time'] ?? null;
    $reason = trim($input['reason'] ?? '');
    $category = $input['category'] ?? 'other';

    if (!$id) {
        jsonResponse(['error' => 'ID required'], 400);
    }

    // Get existing record to calculate duration
    $stmt = $db->prepare('SELECT * FROM downtimes WHERE id = ?');
    $stmt->execute([$id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        jsonResponse(['error' => 'Record not found'], 404);
    }

    $durationMin = null;
    if ($endTime) {
        $s = strtotime($existing['start_time']);
        $e = strtotime($endTime);
        if ($e > $s) {
            $durationMin = round(($e - $s) / 60, 2);
        }
    }

    $stmt = $db->prepare('UPDATE downtimes SET end_time = ?, duration_minutes = ?, reason = ?, category = ? WHERE id = ?');
    $stmt->execute([$endTime ?: null, $durationMin, $reason, $category, $id]);

    computeDailySummary($existing['production_date'], $existing['group_id']);

    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Unknown action'], 400);
