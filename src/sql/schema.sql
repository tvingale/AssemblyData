-- Assembly Line Production Tracking - Database Schema
-- Run this file to create all tables

-- Update database name below to match your hosting environment
-- CREATE DATABASE IF NOT EXISTS kaizenap_assyline_db
--     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- USE kaizenap_assyline_db;

-- 1. Global key-value configuration
CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Named production groups
CREATE TABLE IF NOT EXISTS production_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    default_cells TINYINT UNSIGNED NOT NULL DEFAULT 1,
    expected_output_per_cell_per_hour DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Default time slot intervals
CREATE TABLE IF NOT EXISTS default_time_slots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    day_type ENUM('sun_fri','sat') NOT NULL,
    slot_number TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    label VARCHAR(50),
    UNIQUE KEY uq_day_slot (day_type, slot_number)
) ENGINE=InnoDB;

-- 4. Per-date slot overrides
CREATE TABLE IF NOT EXISTS daily_time_slots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    production_date DATE NOT NULL,
    slot_number TINYINT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    label VARCHAR(50),
    UNIQUE KEY uq_date_slot (production_date, slot_number)
) ENGINE=InnoDB;

-- 5. Per-date shift time overrides
CREATE TABLE IF NOT EXISTS daily_shift_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    production_date DATE NOT NULL UNIQUE,
    shift_start TIME NOT NULL,
    shift_end TIME NOT NULL,
    notes VARCHAR(255)
) ENGINE=InnoDB;

-- 6. Breaks (defaults + overrides)
CREATE TABLE IF NOT EXISTS breaks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    break_type ENUM('lunch','tea','other') NOT NULL,
    label VARCHAR(50),
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    day_type ENUM('sun_fri','sat','all') DEFAULT 'all',
    production_date DATE DEFAULT NULL,
    group_id INT UNSIGNED DEFAULT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    CONSTRAINT fk_breaks_group FOREIGN KEY (group_id) REFERENCES production_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 7. Predefined deficit reason dropdown
CREATE TABLE IF NOT EXISTS deficit_reasons (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reason_text VARCHAR(200) NOT NULL,
    display_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- 8. Core production entries (includes per-slot downtime)
CREATE TABLE IF NOT EXISTS production_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    production_date DATE NOT NULL,
    group_id INT UNSIGNED NOT NULL,
    slot_number TINYINT UNSIGNED NOT NULL,
    cells_operative TINYINT UNSIGNED NOT NULL DEFAULT 1,
    manpower_headcount SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    actual_output INT UNSIGNED NOT NULL DEFAULT 0,
    target_output DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    effective_minutes DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    deficit_reason_id INT UNSIGNED DEFAULT NULL,
    deficit_reason_other TEXT DEFAULT NULL,
    downtime_minutes DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    downtime_category ENUM('none','mechanical','electrical','material','manpower','quality','other') NOT NULL DEFAULT 'none',
    downtime_reason TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_date_group_slot (production_date, group_id, slot_number),
    CONSTRAINT fk_pe_group FOREIGN KEY (group_id) REFERENCES production_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_pe_reason FOREIGN KEY (deficit_reason_id) REFERENCES deficit_reasons(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 9. Downtime events per group
CREATE TABLE IF NOT EXISTS downtimes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    production_date DATE NOT NULL,
    group_id INT UNSIGNED NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME DEFAULT NULL,
    duration_minutes DECIMAL(6,2) DEFAULT NULL,
    reason TEXT,
    category ENUM('mechanical','electrical','material','manpower','quality','other') NOT NULL DEFAULT 'other',
    CONSTRAINT fk_dt_group FOREIGN KEY (group_id) REFERENCES production_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10. Materialized daily summary cache
CREATE TABLE IF NOT EXISTS daily_summaries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    production_date DATE NOT NULL,
    group_id INT UNSIGNED NOT NULL,
    total_target DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_actual INT UNSIGNED NOT NULL DEFAULT 0,
    total_deficit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_excess DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_downtime_minutes DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    total_man_hours DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    total_manpower_avg DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    seats_per_person DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    UNIQUE KEY uq_date_group (production_date, group_id),
    CONSTRAINT fk_ds_group FOREIGN KEY (group_id) REFERENCES production_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB;
