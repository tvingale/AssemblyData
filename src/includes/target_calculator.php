<?php
/**
 * Target output calculation
 *
 * Formula: target = expected_output_per_cell_per_hour × (effective_minutes / 60) × cells_operative
 */

/**
 * Calculate target output for a given slot
 */
function calculateTarget(float $ratePerCellPerHour, float $effectiveMinutes, int $cellsOperative): float {
    return $ratePerCellPerHour * ($effectiveMinutes / 60.0) * $cellsOperative;
}

/**
 * Get group's rate per cell per hour
 */
function getGroupRate(int $groupId): float {
    $db = getDB();
    $stmt = $db->prepare('SELECT expected_output_per_cell_per_hour FROM production_groups WHERE id = ?');
    $stmt->execute([$groupId]);
    $row = $stmt->fetch();
    return $row ? (float)$row['expected_output_per_cell_per_hour'] : 0.0;
}

/**
 * Get group's default cells
 */
function getGroupDefaultCells(int $groupId): int {
    $db = getDB();
    $stmt = $db->prepare('SELECT default_cells FROM production_groups WHERE id = ?');
    $stmt->execute([$groupId]);
    $row = $stmt->fetch();
    return $row ? (int)$row['default_cells'] : 1;
}
