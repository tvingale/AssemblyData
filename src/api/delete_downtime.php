<?php
/**
 * API: Delete a downtime record
 * POST JSON: { id: int }
 */
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required'], 405);
}

$input = getJsonInput();
$id = (int)($input['id'] ?? 0);

if (!$id) {
    jsonResponse(['error' => 'ID required'], 400);
}

$db = getDB();

// Get record info before deleting (for summary recomputation)
$stmt = $db->prepare('SELECT production_date, group_id FROM downtimes WHERE id = ?');
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    jsonResponse(['error' => 'Record not found'], 404);
}

$stmt = $db->prepare('DELETE FROM downtimes WHERE id = ?');
$stmt->execute([$id]);

// Recompute daily summary
computeDailySummary($record['production_date'], $record['group_id']);

jsonResponse(['success' => true]);
