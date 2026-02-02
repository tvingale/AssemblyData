# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Assembly Line Production Tracker — a PHP/MySQL web application for tracking daily production output, downtime, and performance across multiple assembly line groups. No framework; plain PHP with PDO.

All application files live under `src/`, which is the web root when deployed. The repo root contains only this file and `src/`.

## Architecture

### APP_ROOT Pattern
Every PHP entry point defines `APP_ROOT` relative to its own location before loading anything else:
- `src/index.php`: `define('APP_ROOT', __DIR__)`
- `src/pages/*.php`: `define('APP_ROOT', dirname(__DIR__))`
- `src/pages/reports/*.php`: `define('APP_ROOT', dirname(dirname(__DIR__)))`
- `src/api/*.php`: `define('APP_ROOT', dirname(__DIR__))`

### Page Template Pattern
```php
define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/app.php';
require_once APP_ROOT . '/includes/functions.php';
$baseUrl = '..';       // Relative path back to src/ root
$pageTitle = 'Name';
$pageScripts = ['optional.js'];  // Loaded by footer.php
include APP_ROOT . '/includes/header.php';
// ... page content ...
include APP_ROOT . '/includes/footer.php';
```

For admin pages, add before any output:
```php
require_once APP_ROOT . '/includes/admin_auth.php';
requireAdminAuth();
```

### API Endpoint Pattern
API endpoints in `src/api/` accept JSON POST, return JSON via `jsonResponse()`:
```php
$input = getJsonInput();
// validate, process...
jsonResponse(['success' => true, 'data' => $result]);
```

### Database
- PDO singleton via `getDB()` in `includes/db.php`
- Always FETCH_ASSOC (arrays, not objects)
- Always use prepared statements
- Upserts via `ON DUPLICATE KEY UPDATE`
- 10 tables: settings, production_groups, default_time_slots, daily_time_slots, daily_shift_config, breaks, deficit_reasons, production_entries, downtimes, daily_summaries

### Core Calculation Chain
1. **Slot resolution** (`time_helpers.php`): `resolveTimeSlots($date)` checks `daily_time_slots` overrides first, falls back to `default_time_slots` by day_type (sat vs sun_fri)
2. **Break resolution** (`time_helpers.php`): `resolveBreaks($date, $groupId)` merges date-specific and default breaks; date-specific lunch replaces default lunch
3. **Effective minutes**: `calculateEffectiveMinutes($slotStart, $slotEnd, $breaks)` = slot duration minus break overlap
4. **Target**: `calculateTarget($rate, $effectiveMinutes, $cells)` = rate × (effMin / 60) × cells
5. **Daily summary** (`functions.php`): `computeDailySummary($date, $groupId)` aggregates entries and upserts into `daily_summaries` cache table; called after every save/delete

### Key Utility Functions (includes/functions.php)
- `h($str)` — HTML escape
- `getActiveGroups()`, `getGroup($id)`, `getActiveReasons()`
- `getProductionEntries($date, $groupId)`, `getDowntimes($date, $groupId)`
- `formatDate($date)`, `formatTime($time)`, `getDayName($date)`
- `jsonResponse($data, $statusCode)`, `getJsonInput()`

### Frontend
- CSS custom properties in `assets/css/style.css` (--primary, --success, --danger, etc.)
- Global `App` object in `assets/js/app.js`: `App.post()`, `App.get()`, `App.toast()`, `App.formatNumber()`, `App.initTabs()`, `App.initExpandable()`
- Chart.js v4 loaded via CDN in header.php
- Page-specific JS files loaded via `$pageScripts` array

### Admin Auth
Session-based password gate using `includes/admin_auth.php`. Hash stored in settings table. Applied to: manage_groups, manage_time_slots, manage_breaks, manage_reasons, daily_config, settings_page.

## Setup

1. Update `src/config/database.php` with DB credentials
2. Visit `/tools/install.php` in browser — creates tables, seeds defaults
3. `/tools/reset.php` — destructive reset (truncate or drop all tables)

## Key Conventions

- `$baseUrl` must be set correctly before including header.php (determines relative paths for nav links and assets)
- Target and effective_minutes are calculated and stored at save time for historical accuracy
- Day type determination: Saturday → 'sat', all other days → 'sun_fri' (see `getDayType()`)
- Variance coloring: CSS classes `variance-positive`/`variance-negative` and `variance-cell-positive`/`variance-cell-negative`
