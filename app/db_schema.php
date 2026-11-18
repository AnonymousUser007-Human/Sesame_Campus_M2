<?php
/**
 * Initialisation du schéma (MySQL complémentaire ou SQLite complet) + seed démo.
 */
declare(strict_types=1);

function db_driver(): string
{
    return (string) (app_config('driver') ?? 'mysql');
}

function sql_now(): string
{
    return db_driver() === 'sqlite' ? "datetime('now')" : 'NOW()';
}

function sql_datetime_plus_minutes(int $minutes): string
{
    $m = max(1, $minutes);
    return db_driver() === 'sqlite'
        ? "datetime('now', '+{$m} minutes')"
        : "DATE_ADD(NOW(), INTERVAL {$m} MINUTE)";
}

function ensure_app_schema(PDO $pdo): void
{
    if (db_driver() === 'sqlite') {
        ensure_sqlite_schema($pdo);
        seed_demo_users_if_empty($pdo);
        return;
    }
    ensure_mysql_extra_schema($pdo);
}

function ensure_mysql_extra_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `login_attempts` (
          `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
          `email` varchar(190) NOT NULL,
          `ip` varchar(45) NOT NULL DEFAULT \'\',
          `attempted_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_email_ip_time` (`email`, `ip`, `attempted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `password_resets` (
          `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
          `user_id` int UNSIGNED NOT NULL,
          `token_hash` char(64) NOT NULL,
          `expires_at` datetime NOT NULL,
          `used_at` datetime DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `token_hash` (`token_hash`),
          KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function ensure_sqlite_schema(PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          name TEXT NOT NULL,
          email TEXT NOT NULL UNIQUE,
          role TEXT NOT NULL CHECK(role IN (\'admin\',\'teacher\',\'student\')),
          password_hash TEXT NOT NULL,
          active INTEGER NOT NULL DEFAULT 1,
          created_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS audit_logs (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id INTEGER NULL,
          action TEXT NOT NULL,
          target TEXT NOT NULL DEFAULT \'\',
          ip TEXT NOT NULL DEFAULT \'\',
          created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS login_attempts (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          email TEXT NOT NULL,
          ip TEXT NOT NULL DEFAULT \'\',
          attempted_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )'
    );
    $pdo->exec(
        'CREATE INDEX IF NOT EXISTS idx_login_attempts_email_ip_time
         ON login_attempts(email, ip, attempted_at)'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS password_resets (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          user_id INTEGER NOT NULL,
          token_hash TEXT NOT NULL UNIQUE,
          expires_at TEXT NOT NULL,
          used_at TEXT NULL,
          created_at TEXT NOT NULL DEFAULT (datetime(\'now\')),
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )'
    );
}

/** Comptes fictifs TP01 (identiques au labo local). */
function seed_demo_users_if_empty(PDO $pdo): void
{
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count > 0) {
        return;
    }
    $accounts = [
        ['Administrateur FASI', 'admin@fasi.test', 'admin', 'b0e45442011c2e28d1e039c6!'],
        ['Enseignant FASI', 'enseignant@fasi.test', 'teacher', '525269b7f79647bbf0a955b0!'],
        ['Étudiant A', 'etudiant.a@fasi.test', 'student', 'a1eda104e40f12f666bd12cf!'],
        ['Étudiant B', 'etudiant.b@fasi.test', 'student', '5eea03565c287c68d7018957!'],
    ];
    $q = $pdo->prepare('INSERT INTO users(name, email, role, password_hash) VALUES(?,?,?,?)');
    foreach ($accounts as [$name, $email, $role, $pass]) {
        $q->execute([$name, $email, $role, password_hash($pass, PASSWORD_DEFAULT)]);
    }
}
