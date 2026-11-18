<?php
/**
 * Sesame Campus — configuration multi-environnement.
 * Local Laragon : MySQL (défaut). Render / Docker : SQLite via variables d’environnement.
 */
declare(strict_types=1);

$driver = strtolower((string) (getenv('DB_DRIVER') ?: 'mysql'));
$appEnv = strtolower((string) (getenv('APP_ENV') ?: 'local'));

if ($driver === 'sqlite') {
    $sqlitePath = (string) (getenv('SQLITE_PATH') ?: (dirname(__DIR__) . '/data/sesame.sqlite'));
    $dir = dirname($sqlitePath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $config = [
        'driver' => 'sqlite',
        'dsn' => 'sqlite:' . $sqlitePath,
        'user' => null,
        'password' => null,
        'sqlite_path' => $sqlitePath,
    ];
} else {
    $config = [
        'driver' => 'mysql',
        'dsn' => (string) (getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;port=3306;dbname=database;charset=utf8mb4'),
        'user' => (string) (getenv('DB_USER') ?: 'root'),
        'password' => (string) (getenv('DB_PASSWORD') ?: 'root'),
    ];
}

$config['app_env'] = $appEnv;
$config['session_lifetime'] = (int) (getenv('SESSION_LIFETIME') ?: 900);
$config['max_login_attempts'] = (int) (getenv('MAX_LOGIN_ATTEMPTS') ?: 5);
$config['lockout_seconds'] = (int) (getenv('LOCKOUT_SECONDS') ?: 300);
$config['demo_show_reset_link'] = filter_var(
    getenv('DEMO_SHOW_RESET_LINK') !== false ? getenv('DEMO_SHOW_RESET_LINK') : 'true',
    FILTER_VALIDATE_BOOL
);

return $config;
