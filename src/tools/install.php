<?php
/**
 * Installation & First-Time Setup Tool
 * Creates all database tables and seeds default data.
 * Access via browser: /tools/install.php
 */
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/app.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand"><a href="../index.php"><?= APP_NAME ?></a></div>
    </nav>
    <main class="container" style="max-width:700px;">
        <h1 style="margin-bottom:1rem;">Installation &amp; Setup</h1>

<?php
$step = $_POST['step'] ?? ($_GET['step'] ?? 'check');
$errors = [];
$success = [];

if ($step === 'check') {
    // Step 1: Check requirements
    $dbConfig = require APP_ROOT . '/config/database.php';
?>
        <div class="card">
            <div class="card-header">Pre-Installation Check</div>
            <table>
                <tr>
                    <td>PHP Version</td>
                    <td><?= PHP_VERSION ?></td>
                    <td><?= version_compare(PHP_VERSION, '7.4', '>=') ? '<span class="badge badge-success">OK</span>' : '<span class="badge badge-danger">Needs 7.4+</span>' ?></td>
                </tr>
                <tr>
                    <td>PDO Extension</td>
                    <td><?= extension_loaded('pdo') ? 'Loaded' : 'Not loaded' ?></td>
                    <td><?= extension_loaded('pdo') ? '<span class="badge badge-success">OK</span>' : '<span class="badge badge-danger">Required</span>' ?></td>
                </tr>
                <tr>
                    <td>PDO MySQL</td>
                    <td><?= extension_loaded('pdo_mysql') ? 'Loaded' : 'Not loaded' ?></td>
                    <td><?= extension_loaded('pdo_mysql') ? '<span class="badge badge-success">OK</span>' : '<span class="badge badge-danger">Required</span>' ?></td>
                </tr>
                <tr>
                    <td>Database Host</td>
                    <td><?= htmlspecialchars($dbConfig['host']) ?></td>
                    <td>-</td>
                </tr>
                <tr>
                    <td>Database Name</td>
                    <td><?= htmlspecialchars($dbConfig['dbname']) ?></td>
                    <td>-</td>
                </tr>
                <tr>
                    <td>Database User</td>
                    <td><?= htmlspecialchars($dbConfig['username']) ?></td>
                    <td>-</td>
                </tr>
                <?php
                    $connOk = false;
                    try {
                        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
                        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_TIMEOUT => 5,
                        ]);
                        $connOk = true;
                    } catch (PDOException $e) {
                        $connError = $e->getMessage();
                    }
                ?>
                <tr>
                    <td>Database Connection</td>
                    <td><?= $connOk ? 'Connected' : htmlspecialchars($connError) ?></td>
                    <td><?= $connOk ? '<span class="badge badge-success">OK</span>' : '<span class="badge badge-danger">Failed</span>' ?></td>
                </tr>
            </table>
        </div>

        <?php if ($connOk): ?>
        <form method="POST">
            <input type="hidden" name="step" value="install">
            <div class="card">
                <div class="card-header">Ready to Install</div>
                <p>This will create all required tables and insert default seed data. Existing tables will NOT be dropped.</p>
                <button type="submit" class="btn btn-primary" style="margin-top:1rem;">Run Installation</button>
            </div>
        </form>
        <?php else: ?>
        <div class="alert alert-danger">
            Cannot connect to the database. Please update <code>config/database.php</code> with valid credentials and try again.
        </div>
        <?php endif; ?>

<?php
} elseif ($step === 'install') {
    // Step 2: Run installation
    $dbConfig = require APP_ROOT . '/config/database.php';

    try {
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Create tables
        $tables = [
            'settings' => "CREATE TABLE IF NOT EXISTS settings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                description VARCHAR(255),
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'production_groups' => "CREATE TABLE IF NOT EXISTS production_groups (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                default_cells TINYINT UNSIGNED NOT NULL DEFAULT 1,
                expected_output_per_cell_per_hour DECIMAL(8,2) NOT NULL DEFAULT 0.00,
                display_order INT UNSIGNED NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'default_time_slots' => "CREATE TABLE IF NOT EXISTS default_time_slots (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                day_type ENUM('sun_fri','sat') NOT NULL,
                slot_number TINYINT UNSIGNED NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                label VARCHAR(50),
                UNIQUE KEY uq_day_slot (day_type, slot_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'daily_time_slots' => "CREATE TABLE IF NOT EXISTS daily_time_slots (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                production_date DATE NOT NULL,
                slot_number TINYINT UNSIGNED NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                label VARCHAR(50),
                UNIQUE KEY uq_date_slot (production_date, slot_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'daily_shift_config' => "CREATE TABLE IF NOT EXISTS daily_shift_config (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                production_date DATE NOT NULL UNIQUE,
                shift_start TIME NOT NULL,
                shift_end TIME NOT NULL,
                notes VARCHAR(255)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'breaks' => "CREATE TABLE IF NOT EXISTS breaks (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'deficit_reasons' => "CREATE TABLE IF NOT EXISTS deficit_reasons (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reason_text VARCHAR(200) NOT NULL,
                display_order INT UNSIGNED NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'production_entries' => "CREATE TABLE IF NOT EXISTS production_entries (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'downtimes' => "CREATE TABLE IF NOT EXISTS downtimes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                production_date DATE NOT NULL,
                group_id INT UNSIGNED NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME DEFAULT NULL,
                duration_minutes DECIMAL(6,2) DEFAULT NULL,
                reason TEXT,
                category ENUM('mechanical','electrical','material','manpower','quality','other') NOT NULL DEFAULT 'other',
                CONSTRAINT fk_dt_group FOREIGN KEY (group_id) REFERENCES production_groups(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            'daily_summaries' => "CREATE TABLE IF NOT EXISTS daily_summaries (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($tables as $name => $sql) {
            try {
                $pdo->exec($sql);
                $success[] = "Table <code>$name</code> created/verified.";
            } catch (PDOException $e) {
                $errors[] = "Table <code>$name</code>: " . htmlspecialchars($e->getMessage());
            }
        }

        // Seed data
        $seedQueries = [
            "INSERT INTO settings (setting_key, setting_value, description) VALUES
                ('default_shift_start_sun_fri', '08:30', 'Default shift start time for Sunday to Friday'),
                ('default_shift_end_sun_fri', '21:00', 'Default shift end time for Sunday to Friday'),
                ('default_shift_start_sat', '07:00', 'Default shift start time for Saturday'),
                ('default_shift_end_sat', '15:30', 'Default shift end time for Saturday'),
                ('default_lunch_start', '12:30', 'Default lunch break start'),
                ('default_lunch_end', '13:00', 'Default lunch break end')
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)" => 'Default settings',

            "INSERT INTO default_time_slots (day_type, slot_number, start_time, end_time, label) VALUES
                ('sun_fri', 1, '08:30', '10:30', 'Slot 1'),
                ('sun_fri', 2, '10:30', '12:30', 'Slot 2'),
                ('sun_fri', 3, '13:00', '15:00', 'Slot 3'),
                ('sun_fri', 4, '15:00', '17:00', 'Slot 4'),
                ('sun_fri', 5, '17:00', '19:00', 'Slot 5'),
                ('sun_fri', 6, '19:00', '21:00', 'Slot 6')
                ON DUPLICATE KEY UPDATE start_time = VALUES(start_time)" => 'Sun-Fri time slots',

            "INSERT INTO default_time_slots (day_type, slot_number, start_time, end_time, label) VALUES
                ('sat', 1, '07:00', '10:00', 'Slot 1'),
                ('sat', 2, '10:00', '12:30', 'Slot 2'),
                ('sat', 3, '13:00', '15:30', 'Slot 3')
                ON DUPLICATE KEY UPDATE start_time = VALUES(start_time)" => 'Saturday time slots',

            "INSERT INTO breaks (break_type, label, is_default, day_type, start_time, end_time)
                SELECT 'lunch', 'Lunch Break', 1, 'sun_fri', '12:30', '13:00' FROM DUAL
                WHERE NOT EXISTS (SELECT 1 FROM breaks WHERE is_default = 1 AND break_type = 'lunch' AND day_type = 'sun_fri')" => 'Default lunch (Sun-Fri)',

            "INSERT INTO breaks (break_type, label, is_default, day_type, start_time, end_time)
                SELECT 'lunch', 'Lunch Break', 1, 'sat', '12:30', '13:00' FROM DUAL
                WHERE NOT EXISTS (SELECT 1 FROM breaks WHERE is_default = 1 AND break_type = 'lunch' AND day_type = 'sat')" => 'Default lunch (Saturday)',

            "INSERT INTO production_groups (name, default_cells, expected_output_per_cell_per_hour, display_order) VALUES
                ('GROUP 1 (ROMAN)', 4, 6.00, 1),
                ('GROUP 2 (GANESH)', 6, 8.00, 2),
                ('GROUP 3 (PAWAR)', 4, 6.00, 3),
                ('GROUP 4 (GAIKWAD MADAM)', 4, 6.00, 4)
                ON DUPLICATE KEY UPDATE default_cells = VALUES(default_cells)" => 'Production groups',

            "INSERT INTO deficit_reasons (reason_text, display_order) VALUES
                ('Cover Shortage', 1),
                ('Foam Shortage', 2),
                ('Powder Coating Shortage', 3),
                ('Fabrication Shortage', 4),
                ('Machine Breakdown', 5),
                ('Manpower Shortage', 6),
                ('Quality Issue', 7),
                ('Power Failure', 8),
                ('Other', 9)
                ON DUPLICATE KEY UPDATE display_order = VALUES(display_order)" => 'Deficit reasons',
        ];

        foreach ($seedQueries as $sql => $label) {
            try {
                $pdo->exec($sql);
                $success[] = "Seed: $label loaded.";
            } catch (PDOException $e) {
                $errors[] = "Seed ($label): " . htmlspecialchars($e->getMessage());
            }
        }

        // Ensure admin password hash setting exists
        try {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description)
                VALUES ('admin_password_hash', ?, 'Hashed admin password')
                ON DUPLICATE KEY UPDATE setting_key = setting_key");
            $stmt->execute([password_hash('admin@123', PASSWORD_DEFAULT)]);
            $success[] = 'Admin password initialized.';
        } catch (PDOException $e) {
            $errors[] = 'Admin password: ' . htmlspecialchars($e->getMessage());
        }

    } catch (PDOException $e) {
        $errors[] = 'Database connection failed: ' . htmlspecialchars($e->getMessage());
    }
?>
        <div class="card">
            <div class="card-header">Installation Results</div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>Errors:</strong>
                    <ul style="margin:0.5rem 0 0 1rem;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= $err ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <strong>Completed:</strong>
                    <ul style="margin:0.5rem 0 0 1rem;">
                        <?php foreach ($success as $msg): ?>
                            <li><?= $msg ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (empty($errors)): ?>
                <div style="margin-top:1rem;">
                    <a href="../index.php" class="btn btn-primary">Go to Dashboard</a>
                    <a href="../pages/manage_groups.php" class="btn btn-secondary">Set Up Groups</a>
                </div>
            <?php else: ?>
                <div style="margin-top:1rem;">
                    <a href="?step=check" class="btn btn-secondary">Retry Check</a>
                </div>
            <?php endif; ?>
        </div>

<?php } ?>

    </main>
</body>
</html>
