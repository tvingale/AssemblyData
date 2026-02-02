<?php
/**
 * Read/write settings table
 */

function getSetting(string $key, string $default = ''): string {
    $db = getDB();
    $stmt = $db->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['setting_value'] : $default;
}

function setSetting(string $key, string $value): void {
    $db = getDB();
    $stmt = $db->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
    $stmt->execute([$value, $key]);
}

function getAllSettings(): array {
    $db = getDB();
    $stmt = $db->query('SELECT * FROM settings ORDER BY id');
    return $stmt->fetchAll();
}

/**
 * Get shift times for a given date (check daily override, then default settings)
 */
function getShiftTimes(string $date): array {
    $db = getDB();

    // Check daily override
    $stmt = $db->prepare('SELECT shift_start, shift_end, notes FROM daily_shift_config WHERE production_date = ?');
    $stmt->execute([$date]);
    $override = $stmt->fetch();
    if ($override) {
        return [
            'start' => $override['shift_start'],
            'end'   => $override['shift_end'],
            'notes' => $override['notes'],
            'is_override' => true,
        ];
    }

    // Use defaults based on day type
    $dayType = getDayType($date);
    $prefix = ($dayType === 'sat') ? 'default_shift_start_sat' : 'default_shift_start_sun_fri';
    $suffixEnd = ($dayType === 'sat') ? 'default_shift_end_sat' : 'default_shift_end_sun_fri';

    return [
        'start' => getSetting($prefix, '08:00'),
        'end'   => getSetting($suffixEnd, '17:00'),
        'notes' => '',
        'is_override' => false,
    ];
}

/**
 * Get day type: 'sat' or 'sun_fri'
 */
function getDayType(string $date): string {
    $dow = date('w', strtotime($date)); // 0=Sun, 6=Sat
    return ($dow == 6) ? 'sat' : 'sun_fri';
}
