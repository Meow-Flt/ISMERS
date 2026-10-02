<?php
// =============================================================
// GREAT SOLOMON MANPOWER SERVICES INC. — CORE TRANSACTION 4
// db.php — Singleton PDO connection factory.
// =============================================================
require_once __DIR__.'/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = 'mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Lightweight, version-compatible schema migrations.
    // Avoid MySQL/MariaDB-version-specific "ADD COLUMN IF NOT EXISTS" syntax:
    // managed hosts may run versions where that form is unsupported.
    try {
        $addColumn = static function (PDO $pdo, string $table, string $column, string $definition): void {
            $q = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $q->execute([$table, $column]);
            if ((int)$q->fetchColumn() === 0) {
                $pdo->exec('ALTER TABLE `'.str_replace('`','',$table).'` ADD COLUMN `'
                    .str_replace('`','',$column).'` '.$definition);
            }
        };

        $addIndex = static function (PDO $pdo, string $table, string $index, string $columns): void {
            $q = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
            );
            $q->execute([$table, $index]);
            if ((int)$q->fetchColumn() === 0) {
                $pdo->exec('ALTER TABLE `'.str_replace('`','',$table).'` ADD INDEX `'
                    .str_replace('`','',$index).'` ('.$columns.')');
            }
        };

        $addColumn($pdo, 'admin_notifications', 'sender_user_id', 'INT UNSIGNED NULL');
        $addIndex($pdo, 'admin_notifications', 'idx_notification_sender_user', '`sender_user_id`');

        $addColumn($pdo, 'compliance_obligations', 'report_name', 'VARCHAR(120) NULL');
        $addColumn($pdo, 'compliance_obligations', 'report_role', 'VARCHAR(120) NULL');
        $addColumn($pdo, 'compliance_obligations', 'contact_no', 'VARCHAR(60) NULL');
        $addColumn($pdo, 'compliance_obligations', 'compliance_note', 'TEXT NULL');
        $addColumn($pdo, 'compliance_obligations', 'reported_at', 'DATETIME NULL');
        $addColumn($pdo, 'assets', 'quantity', 'INT UNSIGNED NOT NULL DEFAULT 1');
    } catch (Throwable $e) {
        // Retry on the next request. A first-boot schema may not exist yet,
        // or the managed database may temporarily restrict ALTER privileges.
    }

    // Legacy roles are normalised to Staff; only Administrator and Staff are supported.
    try {
        $pdo->exec("UPDATE users SET role='Staff' WHERE role NOT IN ('Administrator','Staff')");
    } catch (Throwable $e) { /* Table may not exist during initial bootstrap */ }

    return $pdo;
}
