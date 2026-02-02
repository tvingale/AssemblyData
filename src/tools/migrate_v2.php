<?php
/**
 * Migration v2: Add per-slot downtime columns to production_entries
 * Run this if upgrading from an older version
 */
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/app.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration v2 - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand"><a href="../index.php"><?= APP_NAME ?></a></div>
    </nav>
    <main class="container" style="max-width:700px;">
        <h1 style="margin-bottom:1rem;">Migration v2: Per-Slot Downtime</h1>

<?php
$errors = [];
$success = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbConfig = require APP_ROOT . '/config/database.php';

    try {
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Check if columns already exist
        $stmt = $pdo->query("SHOW COLUMNS FROM production_entries LIKE 'downtime_minutes'");
        $exists = $stmt->fetch();

        if ($exists) {
            $success[] = "Column 'downtime_minutes' already exists. Migration may have already been applied.";
        } else {
            // Add new columns
            $alterSql = "ALTER TABLE production_entries
                ADD COLUMN downtime_minutes DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER deficit_reason_other,
                ADD COLUMN downtime_category ENUM('none','mechanical','electrical','material','manpower','quality','other') NOT NULL DEFAULT 'none' AFTER downtime_minutes,
                ADD COLUMN downtime_reason TEXT DEFAULT NULL AFTER downtime_category";

            $pdo->exec($alterSql);
            $success[] = "Added downtime columns to production_entries table.";
        }

        // Migrate existing downtime data from downtimes table (optional)
        $stmt = $pdo->query("SELECT COUNT(*) FROM downtimes");
        $dtCount = (int)$stmt->fetchColumn();

        if ($dtCount > 0) {
            $success[] = "Note: Found {$dtCount} records in the old downtimes table. These are preserved but won't be automatically migrated to per-slot format.";
        }

    } catch (PDOException $e) {
        $errors[] = 'Migration failed: ' . htmlspecialchars($e->getMessage());
    }
}
?>

        <div class="card">
            <div class="card-header">Migration Details</div>
            <p>This migration adds per-slot downtime tracking to the production_entries table:</p>
            <ul style="margin:1rem 0 0 1.5rem;">
                <li><code>downtime_minutes</code> - Downtime duration for each slot</li>
                <li><code>downtime_category</code> - Category (mechanical, electrical, etc.)</li>
                <li><code>downtime_reason</code> - Free text reason for downtime</li>
            </ul>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" style="margin-top:1rem;">
                    <strong>Errors:</strong>
                    <ul style="margin:0.5rem 0 0 1rem;">
                        <?php foreach ($errors as $err): ?>
                            <li><?= $err ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success" style="margin-top:1rem;">
                    <strong>Results:</strong>
                    <ul style="margin:0.5rem 0 0 1rem;">
                        <?php foreach ($success as $msg): ?>
                            <li><?= $msg ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (empty($success) && empty($errors)): ?>
                <form method="POST" style="margin-top:1rem;">
                    <button type="submit" class="btn btn-primary">Run Migration</button>
                </form>
            <?php else: ?>
                <div style="margin-top:1rem;">
                    <a href="../index.php" class="btn btn-primary">Go to Dashboard</a>
                </div>
            <?php endif; ?>
        </div>

    </main>
</body>
</html>
