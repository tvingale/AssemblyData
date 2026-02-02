<?php
/**
 * Database Reset Tool
 * Drops all tables and clears the database for a fresh install.
 * Access via browser: /tools/reset.php
 *
 * WARNING: This is destructive! All data will be permanently deleted.
 */
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/app.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Reset - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand"><a href="../index.php"><?= APP_NAME ?></a></div>
    </nav>
    <main class="container" style="max-width:700px;">
        <h1 style="margin-bottom:1rem;">Database Reset Tool</h1>

<?php
$step = $_POST['step'] ?? 'confirm';
$errors = [];
$success = [];

// Tables in reverse dependency order (children first)
$tables = [
    'daily_summaries',
    'production_entries',
    'downtimes',
    'daily_time_slots',
    'daily_shift_config',
    'breaks',
    'deficit_reasons',
    'default_time_slots',
    'production_groups',
    'settings',
];

if ($step === 'confirm') {
?>
        <div class="alert alert-danger" style="font-size:1rem;">
            <strong>WARNING:</strong> This will permanently delete ALL data from the database, including:
            <ul style="margin:0.5rem 0 0 1rem;">
                <li>All production entries</li>
                <li>All downtime records</li>
                <li>All daily summaries</li>
                <li>All groups, time slots, breaks, and reasons</li>
                <li>All settings</li>
            </ul>
            <p style="margin-top:0.75rem;"><strong>This action cannot be undone.</strong></p>
        </div>

        <div class="card">
            <div class="card-header">Choose Reset Action</div>

            <div style="display:flex;flex-direction:column;gap:1rem;">
                <!-- Option 1: Delete records only -->
                <form method="POST">
                    <input type="hidden" name="step" value="delete_records">
                    <input type="hidden" name="confirm" value="yes">
                    <div style="padding:1rem;border:1px solid var(--gray-200);border-radius:var(--radius);">
                        <h3 style="margin-bottom:0.5rem;">Option 1: Delete All Records</h3>
                        <p style="font-size:0.875rem;color:var(--gray-600);margin-bottom:0.75rem;">
                            Empties all tables but keeps the table structure intact. You can re-run the installer to seed default data.
                        </p>
                        <label style="display:block;margin-bottom:0.5rem;">
                            <input type="checkbox" name="confirm_check" required>
                            I understand all data will be permanently deleted
                        </label>
                        <button type="submit" class="btn btn-warning">Delete All Records</button>
                    </div>
                </form>

                <!-- Option 2: Drop tables -->
                <form method="POST">
                    <input type="hidden" name="step" value="drop_tables">
                    <input type="hidden" name="confirm" value="yes">
                    <div style="padding:1rem;border:1px solid var(--danger);border-radius:var(--radius);">
                        <h3 style="margin-bottom:0.5rem;color:var(--danger);">Option 2: Drop All Tables</h3>
                        <p style="font-size:0.875rem;color:var(--gray-600);margin-bottom:0.75rem;">
                            Completely removes all tables from the database. You must run the installer again to recreate everything.
                        </p>
                        <label style="display:block;margin-bottom:0.5rem;">
                            <input type="checkbox" name="confirm_check" required>
                            I understand all tables and data will be permanently removed
                        </label>
                        <button type="submit" class="btn btn-danger">Drop All Tables</button>
                    </div>
                </form>
            </div>
        </div>

        <div style="margin-top:1rem;">
            <a href="../index.php" class="btn btn-secondary">Cancel - Back to Dashboard</a>
        </div>

<?php
} elseif ($step === 'delete_records' && ($_POST['confirm'] ?? '') === 'yes') {
    // Delete all records from all tables
    try {
        $dbConfig = require APP_ROOT . '/config/database.php';
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tables as $table) {
            try {
                $pdo->exec("TRUNCATE TABLE `$table`");
                $success[] = "Table <code>$table</code> truncated.";
            } catch (PDOException $e) {
                // Table might not exist
                $errors[] = "Table <code>$table</code>: " . htmlspecialchars($e->getMessage());
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    } catch (PDOException $e) {
        $errors[] = 'Database connection failed: ' . htmlspecialchars($e->getMessage());
    }
?>
        <div class="card">
            <div class="card-header">Delete Records - Results</div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-warning">
                    <strong>Warnings:</strong>
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

            <div style="margin-top:1rem;">
                <a href="install.php" class="btn btn-primary">Run Installer (Seed Defaults)</a>
                <a href="../index.php" class="btn btn-secondary">Go to Dashboard</a>
            </div>
        </div>

<?php
} elseif ($step === 'drop_tables' && ($_POST['confirm'] ?? '') === 'yes') {
    // Drop all tables
    try {
        $dbConfig = require APP_ROOT . '/config/database.php';
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($tables as $table) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS `$table`");
                $success[] = "Table <code>$table</code> dropped.";
            } catch (PDOException $e) {
                $errors[] = "Table <code>$table</code>: " . htmlspecialchars($e->getMessage());
            }
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    } catch (PDOException $e) {
        $errors[] = 'Database connection failed: ' . htmlspecialchars($e->getMessage());
    }
?>
        <div class="card">
            <div class="card-header">Drop Tables - Results</div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-warning">
                    <strong>Warnings:</strong>
                    <ul style="margin:0.5rem 0 0 1rem;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= $err ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <strong>All tables dropped:</strong>
                    <ul style="margin:0.5rem 0 0 1rem;">
                        <?php foreach ($success as $msg): ?>
                            <li><?= $msg ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div style="margin-top:1rem;">
                <p style="margin-bottom:0.75rem;color:var(--gray-600);">Database is now empty. Run the installer to set up fresh tables and seed data.</p>
                <a href="install.php" class="btn btn-primary">Run Installer</a>
            </div>
        </div>

<?php } else { ?>
        <div class="alert alert-danger">Invalid request. <a href="?">Try again</a>.</div>
<?php } ?>

    </main>
</body>
</html>
