<?php
/**
 * TP01 — Noyau de sécurité (sessions, CSRF, rôles, journalisation).
 * Les commentaires « PÉDAGOGIE » marquent les contrôles critiques pour la soutenance.
 */
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

function app_config(?string $key = null): mixed
{
    global $config;
    return $key === null ? $config : ($config[$key] ?? null);
}

require __DIR__ . '/db_schema.php';

// Derrière le proxy Render / Docker : détecter HTTPS réel.
if (
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
) {
    $_SERVER['HTTPS'] = 'on';
}

try {
    // PÉDAGOGIE : PDO avec requêtes préparées natives (pas d’émulation) → anti-injection SQL.
    $pdoOpts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if (($config['driver'] ?? '') === 'sqlite') {
        $pdo = new PDO($config['dsn'], null, null, $pdoOpts);
    } else {
        $pdo = new PDO($config['dsn'], $config['user'], $config['password'], $pdoOpts);
    }
    ensure_app_schema($pdo);
} catch (PDOException $e) {
    http_response_code(503);
    exit('Base de données indisponible. Vérifiez la configuration (MySQL local ou SQLite /data).');
}

// PÉDAGOGIE : mode strict + cookies HttpOnly / SameSite / Secure si HTTPS.
ini_set('session.use_strict_mode', '1');
$secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
// PÉDAGOGIE : CSP restrictive ; fonts Google autorisées pour l’UI du TP.
header("Content-Security-Policy: default-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");

function db(): PDO
{
    global $pdo;
    return $pdo;
}

/** PÉDAGOGIE : échappement HTML contextuel → prévention XSS à l’affichage. */
function esc(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $route = 'dashboard', array $query = []): string
{
    $params = array_merge(['r' => $route], $query);
    return 'index.php?' . http_build_query($params);
}

function redirect(string $r = 'dashboard', array $query = []): never
{
    header('Location: ' . url($r, $query));
    exit;
}

function csrf(): string
{
    // PÉDAGOGIE : jeton CSRF aléatoire lié à la session.
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . esc(csrf()) . '">';
}

function verify_csrf(): void
{
    // PÉDAGOGIE : comparaison timing-safe ; refus 403 si jeton absent/incorrect.
    if (
        !isset($_POST['csrf'])
        || !is_string($_POST['csrf'])
        || !hash_equals(csrf(), $_POST['csrf'])
    ) {
        http_response_code(403);
        exit('Jeton CSRF invalide');
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * PÉDAGOGIE : expiration de session par inactivité (scénario « session expirée »).
 */
function enforce_session_lifetime(): void
{
    $lifetime = (int) app_config('session_lifetime');
    if ($lifetime <= 0 || !current_user()) {
        return;
    }
    $now = time();
    $last = (int) ($_SESSION['last_activity'] ?? $now);
    if (($now - $last) > $lifetime) {
        // PÉDAGOGIE : destruction complète de la session expirée.
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
        session_start();
        $_SESSION['flash'] = 'Votre session a expiré pour inactivité. Reconnectez-vous.';
        redirect('login');
    }
    $_SESSION['last_activity'] = $now;
}

function require_login(): array
{
    enforce_session_lifetime();
    $u = current_user();
    if (!$u) {
        redirect('login');
    }
    return $u;
}

function require_role(string ...$roles): array
{
    // PÉDAGOGIE : contrôle d’accès par rôle côté serveur (jamais seulement en CSS/JS).
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        page_start('Accès refusé');
        echo '<section class="panel panel-danger"><h2>403 — Accès refusé</h2><p>Votre rôle (<strong>'
            . esc($u['role']) . '</strong>) ne permet pas d’ouvrir cette page d’administration.</p>'
            . '<p><a class="btn" href="' . esc(url('dashboard')) . '">Retour au tableau de bord</a></p></section>';
        page_end();
        exit;
    }
    return $u;
}

function log_event(string $action, string $target = ''): void
{
    // PÉDAGOGIE : journal sans mot de passe ni jeton en clair.
    try {
        $q = db()->prepare('INSERT INTO audit_logs(user_id,action,target,ip) VALUES(?,?,?,?)');
        $q->execute([
            current_user()['id'] ?? null,
            $action,
            mb_substr($target, 0, 190),
            $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    } catch (Throwable $e) {
        error_log('Audit failure: ' . $e->getMessage());
    }
}

function client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/** Compte les essais récents pour e-mail + IP. */
function count_login_attempts(string $email): int
{
    $window = (int) app_config('lockout_seconds');
    $email = mb_strtolower($email);
    $ip = client_ip();
    if (db_driver() === 'sqlite') {
        $q = db()->prepare(
            "SELECT COUNT(*) FROM login_attempts
             WHERE email = ? AND ip = ? AND datetime(attempted_at) > datetime('now', ?)"
        );
        $q->execute([$email, $ip, '-' . $window . ' seconds']);
    } else {
        $q = db()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE email = ? AND ip = ? AND attempted_at > (NOW() - INTERVAL ? SECOND)'
        );
        $q->execute([$email, $ip, $window]);
    }
    return (int) $q->fetchColumn();
}

function record_login_attempt(string $email): void
{
    $q = db()->prepare('INSERT INTO login_attempts(email, ip) VALUES(?, ?)');
    $q->execute([mb_strtolower($email), client_ip()]);
}

function clear_login_attempts(string $email): void
{
    $q = db()->prepare('DELETE FROM login_attempts WHERE email = ? AND ip = ?');
    $q->execute([mb_strtolower($email), client_ip()]);
}

function is_locked_out(string $email): bool
{
    return count_login_attempts($email) >= (int) app_config('max_login_attempts');
}

function flash(?string $set = null): ?string
{
    if ($set !== null) {
        $_SESSION['flash'] = $set;
        return null;
    }
    if (!empty($_SESSION['flash'])) {
        $m = (string) $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $m;
    }
    return null;
}

function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Administrateur',
        'teacher' => 'Enseignant',
        'student' => 'Étudiant',
        default => $role,
    };
}

function page_start(string $title, array $opts = []): void
{
    $u = current_user();
    $authLayout = !empty($opts['auth']);
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . esc($title) . ' | Sesame Campus</title>'
        . '<link rel="preconnect" href="https://fonts.googleapis.com">'
        . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Lexend:wght@400;500;600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="assets/style.css"></head>'
        . '<body class="' . ($authLayout ? 'auth-body' : 'app-body') . '">';

    if ($authLayout) {
        echo '<div class="auth-shell"><aside class="auth-brand" aria-hidden="false">'
            . '<p class="eyebrow">UPC · FASI · Master 2 · TP01</p>'
            . '<h1 class="brand-title">Sesame Campus</h1>'
            . '<p class="brand-lead">La clé d’accès sécurisée des étudiants et enseignants</p>'
            . '<ul class="brand-points">'
            . '<li>Hash bcrypt des mots de passe</li>'
            . '<li>Limitation des essais</li>'
            . '<li>Sessions régénérées &amp; expirables</li>'
            . '<li>Contrôle d’accès par rôle</li>'
            . '</ul></aside><div class="auth-panel"><main>';
        return;
    }

    echo '<header class="topbar"><div class="brand-block">'
        . '<strong>Sesame Campus</strong>'
        . '<small>TP01 · Authentification sécurisée · 2026–2027</small></div>';
    if ($u) {
        echo '<nav class="nav">'
            . '<a href="' . esc(url('dashboard')) . '">Espace</a>'
            . '<a href="' . esc(url('profile')) . '">Profil</a>';
        if ($u['role'] === 'admin') {
            echo '<a href="' . esc(url('users')) . '">Comptes</a>'
                . '<a href="' . esc(url('admin')) . '">Administration</a>'
                . '<a href="' . esc(url('audit')) . '">Audit</a>';
        }
        echo '</nav><div class="user-chip"><span class="role-pill role-' . esc($u['role']) . '">'
            . esc(role_label($u['role'])) . '</span><span>' . esc($u['name']) . '</span></div>';
    }
    echo '</header><main class="container"><h1 class="page-title">' . esc($title) . '</h1>';
}

function page_end(array $opts = []): void
{
    $authLayout = !empty($opts['auth']);
    if ($authLayout) {
        echo '</main><p class="auth-foot">Sesame Campus · données fictives · usage local uniquement</p></div></div>'
            . '<script src="assets/app.js" defer></script></body></html>';
        return;
    }
    echo '</main><footer class="site-foot">Sesame Campus — démonstration locale uniquement · FASI M2</footer>'
        . '<script src="assets/app.js" defer></script></body></html>';
}

function message(string $m, string $type = 'info'): void
{
    echo '<p class="notice notice-' . esc($type) . '" role="status">' . esc($m) . '</p>';
}

function int_post(string $key): int
{
    return filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
}
