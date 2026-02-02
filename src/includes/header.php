<?php
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/db.php';

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?><?= isset($pageTitle) ? ' - ' . $pageTitle : '' ?></title>
    <link rel="stylesheet" href="<?= $baseUrl ?? '' ?>/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body>
    <nav class="navbar">
        <div class="nav-brand">
            <a href="<?= $baseUrl ?? '' ?>/index.php">
                <img src="<?= $baseUrl ?? '' ?>/assets/images/logo.png" alt="<?= APP_NAME ?>" class="nav-logo">
                <span class="nav-title"><?= APP_NAME ?></span>
            </a>
        </div>
        <button class="nav-toggle" onclick="document.querySelector('.nav-links').classList.toggle('open')">&#9776;</button>
        <div class="nav-links">
            <a href="<?= $baseUrl ?? '' ?>/index.php" class="<?= $currentPage === 'index' ? 'active' : '' ?>">Dashboard</a>
            <a href="<?= $baseUrl ?? '' ?>/pages/production_entry.php" class="<?= in_array($currentPage, ['production_entry', 'downtime_entry']) ? 'active' : '' ?>">Data Entry</a>
            <a href="<?= $baseUrl ?? '' ?>/pages/daily_summary.php" class="<?= $currentPage === 'daily_summary' ? 'active' : '' ?>">Daily Summary</a>
            <div class="nav-dropdown">
                <a href="#" class="<?= $currentDir === 'reports' ? 'active' : '' ?>">Reports &#9662;</a>
                <div class="dropdown-content">
                    <a href="<?= $baseUrl ?? '' ?>/pages/reports/deficit_analysis.php">Deficit Analysis</a>
                    <a href="<?= $baseUrl ?? '' ?>/pages/reports/downtime_analysis.php">Downtime Analysis</a>
                    <a href="<?= $baseUrl ?? '' ?>/pages/reports/weekly_trend.php">Weekly Trend</a>
                    <a href="<?= $baseUrl ?? '' ?>/pages/reports/monthly_summary.php">Monthly Summary</a>
                    <a href="<?= $baseUrl ?? '' ?>/pages/reports/line_comparison.php">Line Comparison</a>
                    <a href="<?= $baseUrl ?? '' ?>/pages/reports/manpower_efficiency.php">Manpower Efficiency</a>
                </div>
            </div>
            <div class="nav-dropdown">
                <a href="#" class="<?= in_array($currentPage, ['manage_groups','manage_time_slots','manage_breaks','manage_reasons','settings_page','daily_config']) ? 'active' : '' ?>">Admin &#9662;</a>
                <div class="dropdown-content">
                    <a href="<?= $baseUrl ?? '' ?>/pages/manage_groups.php">Manage Groups</a>
                    <a href="<?= $baseUrl ?? '' ?>/pages/manage_time_slots.php">Time Slots</a>
                    <a href="<?= $baseUrl ?? '' ?>/pages/manage_breaks.php">Breaks</a>
                    <a href="<?= $baseUrl ?? '' ?>/pages/manage_reasons.php">Deficit Reasons</a>
                    <a href="<?= $baseUrl ?? '' ?>/pages/daily_config.php">Daily Config</a>
                    <a href="<?= $baseUrl ?? '' ?>/pages/settings_page.php">Settings</a>
                </div>
            </div>
            <?php if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['admin_authenticated'])): ?>
                <a href="?logout=1" style="color:var(--neutral-600);font-size:0.8rem;">Logout</a>
            <?php endif; ?>
        </div>
    </nav>
    <main class="container">
