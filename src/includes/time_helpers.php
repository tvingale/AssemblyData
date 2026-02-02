<?php
/**
 * Slot resolution, break overlap, effective minutes calculations
 */

require_once __DIR__ . '/settings.php';

/**
 * Resolve time slots for a given date.
 * 1. Check daily_time_slots for the date (all-or-nothing override)
 * 2. Otherwise use default_time_slots based on day_type
 */
function resolveTimeSlots(string $date): array {
    $db = getDB();

    // Check for daily overrides
    $stmt = $db->prepare('SELECT slot_number, start_time, end_time, label FROM daily_time_slots WHERE production_date = ? ORDER BY slot_number');
    $stmt->execute([$date]);
    $overrides = $stmt->fetchAll();

    if (!empty($overrides)) {
        return $overrides;
    }

    // Use defaults based on day type
    $dayType = getDayType($date);
    $stmt = $db->prepare('SELECT slot_number, start_time, end_time, label FROM default_time_slots WHERE day_type = ? ORDER BY slot_number');
    $stmt->execute([$dayType]);
    return $stmt->fetchAll();
}

/**
 * Resolve breaks for a given date and optionally a specific group.
 * - Date-specific lunch replaces default lunch; other break types accumulate.
 */
function resolveBreaks(string $date, ?int $groupId = null): array {
    $db = getDB();
    $dayType = getDayType($date);

    // Get date-specific breaks
    $sql = 'SELECT * FROM breaks WHERE production_date = ?';
    $params = [$date];
    if ($groupId !== null) {
        $sql .= ' AND (group_id IS NULL OR group_id = ?)';
        $params[] = $groupId;
    } else {
        $sql .= ' AND group_id IS NULL';
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $dateBreaks = $stmt->fetchAll();

    // Get default breaks matching day_type
    $sql = 'SELECT * FROM breaks WHERE is_default = 1 AND (day_type = ? OR day_type = \'all\')';
    $params = [$dayType];
    if ($groupId !== null) {
        $sql .= ' AND (group_id IS NULL OR group_id = ?)';
        $params[] = $groupId;
    } else {
        $sql .= ' AND group_id IS NULL';
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $defaultBreaks = $stmt->fetchAll();

    // Merge: if date-specific lunch exists, replace default lunch
    $hasDateLunch = false;
    foreach ($dateBreaks as $b) {
        if ($b['break_type'] === 'lunch') {
            $hasDateLunch = true;
            break;
        }
    }

    $result = [];

    // Add date-specific breaks
    foreach ($dateBreaks as $b) {
        $result[] = $b;
    }

    // Add default breaks, skipping default lunch if date-specific lunch exists
    foreach ($defaultBreaks as $b) {
        if ($hasDateLunch && $b['break_type'] === 'lunch') {
            continue;
        }
        $result[] = $b;
    }

    return $result;
}

/**
 * Calculate overlap in minutes between two time ranges.
 * Times are strings like "08:00:00" or "08:00".
 */
function calculateOverlapMinutes(string $start1, string $end1, string $start2, string $end2): float {
    $s1 = strtotime($start1);
    $e1 = strtotime($end1);
    $s2 = strtotime($start2);
    $e2 = strtotime($end2);

    $overlapStart = max($s1, $s2);
    $overlapEnd = min($e1, $e2);

    if ($overlapStart >= $overlapEnd) {
        return 0.0;
    }

    return ($overlapEnd - $overlapStart) / 60.0;
}

/**
 * Calculate effective minutes for a slot after subtracting break overlaps.
 */
function calculateEffectiveMinutes(string $slotStart, string $slotEnd, array $breaks): float {
    $slotDuration = calculateOverlapMinutes($slotStart, $slotEnd, $slotStart, $slotEnd);
    // slotDuration is just the full slot length
    $s = strtotime($slotStart);
    $e = strtotime($slotEnd);
    $slotDuration = ($e - $s) / 60.0;

    $totalBreakOverlap = 0.0;
    foreach ($breaks as $brk) {
        $overlap = calculateOverlapMinutes($slotStart, $slotEnd, $brk['start_time'], $brk['end_time']);
        $totalBreakOverlap += $overlap;
    }

    return max(0, $slotDuration - $totalBreakOverlap);
}

/**
 * Get slot duration in minutes
 */
function getSlotDurationMinutes(string $startTime, string $endTime): float {
    $s = strtotime($startTime);
    $e = strtotime($endTime);
    return ($e - $s) / 60.0;
}
