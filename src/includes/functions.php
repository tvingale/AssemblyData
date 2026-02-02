<?php
/**
 * Shared utility functions and daily summary computation
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/time_helpers.php';
require_once __DIR__ . '/target_calculator.php';

/**
 * Get all active production groups ordered by display_order
 */
function getActiveGroups(): array {
    $db = getDB();
    return $db->query('SELECT * FROM production_groups WHERE is_active = 1 ORDER BY display_order, id')->fetchAll();
}

/**
 * Get all production groups (including inactive)
 */
function getAllGroups(): array {
    $db = getDB();
    return $db->query('SELECT * FROM production_groups ORDER BY display_order, id')->fetchAll();
}

/**
 * Get a single production group
 */
function getGroup(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM production_groups WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Get active deficit reasons
 */
function getActiveReasons(): array {
    $db = getDB();
    return $db->query('SELECT * FROM deficit_reasons WHERE is_active = 1 ORDER BY display_order, id')->fetchAll();
}

/**
 * Get production entries for a group on a date
 */
function getProductionEntries(string $date, int $groupId): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT pe.*, dr.reason_text
                          FROM production_entries pe
                          LEFT JOIN deficit_reasons dr ON pe.deficit_reason_id = dr.id
                          WHERE pe.production_date = ? AND pe.group_id = ?
                          ORDER BY pe.slot_number');
    $stmt->execute([$date, $groupId]);
    return $stmt->fetchAll();
}

/**
 * Get downtimes for a group on a date
 */
function getDowntimes(string $date, int $groupId): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM downtimes WHERE production_date = ? AND group_id = ? ORDER BY start_time');
    $stmt->execute([$date, $groupId]);
    return $stmt->fetchAll();
}

/**
 * Calculate and store daily summary for a group on a date
 */
function computeDailySummary(string $date, int $groupId): array {
    $db = getDB();

    // Get production entries
    $entries = getProductionEntries($date, $groupId);

    $totalTarget = 0;
    $totalActual = 0;
    $totalManHours = 0;
    $totalManpower = 0;
    $totalDowntime = 0;
    $slotCount = 0;

    foreach ($entries as $entry) {
        $totalTarget += (float)$entry['target_output'];
        $totalActual += (int)$entry['actual_output'];
        $effectiveHours = (float)$entry['effective_minutes'] / 60.0;
        $totalManHours += (int)$entry['manpower_headcount'] * $effectiveHours;
        $totalManpower += (int)$entry['manpower_headcount'];
        // Downtime is now per slot in production_entries
        $totalDowntime += (float)($entry['downtime_minutes'] ?? 0);
        $slotCount++;
    }

    $totalDeficit = max(0, $totalTarget - $totalActual);
    $totalExcess = max(0, $totalActual - $totalTarget);
    $avgManpower = $slotCount > 0 ? $totalManpower / $slotCount : 0;
    $seatsPerPerson = $totalManHours > 0 ? $totalActual / $totalManHours : 0;

    $summary = [
        'production_date' => $date,
        'group_id' => $groupId,
        'total_target' => round($totalTarget, 2),
        'total_actual' => $totalActual,
        'total_deficit' => round($totalDeficit, 2),
        'total_excess' => round($totalExcess, 2),
        'total_downtime_minutes' => round($totalDowntime, 2),
        'total_man_hours' => round($totalManHours, 2),
        'total_manpower_avg' => round($avgManpower, 2),
        'seats_per_person' => round($seatsPerPerson, 2),
    ];

    // Upsert into daily_summaries
    $stmt = $db->prepare('INSERT INTO daily_summaries
        (production_date, group_id, total_target, total_actual, total_deficit, total_excess, total_downtime_minutes, total_man_hours, total_manpower_avg, seats_per_person)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            total_target = VALUES(total_target),
            total_actual = VALUES(total_actual),
            total_deficit = VALUES(total_deficit),
            total_excess = VALUES(total_excess),
            total_downtime_minutes = VALUES(total_downtime_minutes),
            total_man_hours = VALUES(total_man_hours),
            total_manpower_avg = VALUES(total_manpower_avg),
            seats_per_person = VALUES(seats_per_person)');
    $stmt->execute([
        $summary['production_date'],
        $summary['group_id'],
        $summary['total_target'],
        $summary['total_actual'],
        $summary['total_deficit'],
        $summary['total_excess'],
        $summary['total_downtime_minutes'],
        $summary['total_man_hours'],
        $summary['total_manpower_avg'],
        $summary['seats_per_person'],
    ]);

    return $summary;
}

/**
 * Escape HTML output
 */
function h($str): string {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Format time for display (H:i)
 */
function formatTime(string $time): string {
    return date('H:i', strtotime($time));
}

/**
 * Format date for display
 */
function formatDate(string $date): string {
    return date('D, d M Y', strtotime($date));
}

/**
 * Get day name
 */
function getDayName(string $date): string {
    return date('l', strtotime($date));
}

/**
 * JSON response helper for API endpoints
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Read JSON request body
 */
function getJsonInput(): array {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}
