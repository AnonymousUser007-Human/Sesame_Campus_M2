<?php
/**
 * Sesame Campus — TP01 Authentification sécurisée (étudiants / enseignants / admin)
 * Routes : login, logout, register, reset, dashboard par rôle, admin, users, audit.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$r = $_GET['r'] ?? 'dashboard';
$allowed = [
    'login', 'logout', 'register', 'reset', 'reset_confirm',
    'dashboard', 'profile', 'users', 'admin', 'audit', 'expire_demo',
];
if (!is_string($r) || !in_array($r, $allowed, true)) {
    http_response_code(404);
    exit('Page introuvable');
}

/* ========== CONNEXION ========== */
if ($r === 'login') {
    if (current_user()) {
        redirect('dashboard');
    }
    $error = '';
    $f = flash();
    if ($f) {
        $error = $f;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $email = trim((string) ($_POST['email'] ?? ''));
        $pass = (string) ($_POST['password'] ?? '');

        // PÉDAGOGIE : limitation des essais (anti-bruteforce) avant même password_verify.
        if ($email !== '' && is_locked_out($email)) {
            // Message non révélateur : ne dit pas si l’e-mail existe.
            $error = 'Trop de tentatives. Réessayez dans quelques minutes.';
            usleep(250000);
        } else {
            $q = db()->prepare('SELECT id, name, email, role, password_hash FROM users WHERE email = ? AND active = 1');
            $q->execute([$email]);
            $u = $q->fetch();

            // PÉDAGOGIE : password_verify compare le hash ; jamais de comparaison en clair.
            if ($u && password_verify($pass, $u['password_hash'])) {
                clear_login_attempts($email);
                // PÉDAGOGIE : régénération de l’ID de session après auth → anti-fixation.
                session_regenerate_id(true);
                unset($u['password_hash']);
                $_SESSION['user'] = $u;
                $_SESSION['last_activity'] = time();
                log_event('login');
                redirect('dashboard');
            }

            if ($email !== '') {
                record_login_attempt($email);
            }
            // PÉDAGOGIE : message générique — ne révèle ni « e-mail inconnu » ni « mauvais mot de passe ».
            $error = 'Identifiants invalides';
            usleep(250000);
            log_event('login_failed', mb_substr($email, 0, 190));
        }
    }

    page_start('Connexion', ['auth' => true]);
    echo '<div class="auth-card reveal"><p class="eyebrow">TP01 · Connexion</p><h2>Accéder au portail</h2>';
    if ($error) {
        message($error, 'error');
    }
    echo '<form method="post" class="stack" autocomplete="on">'
        . csrf_field()
        . '<label>Adresse électronique<input name="email" type="email" required maxlength="190" autofocus></label>'
        . '<label>Mot de passe<input name="password" type="password" required></label>'
        . '<button type="submit" class="btn btn-primary">Se connecter</button></form>'
        . '<p class="muted-links"><a href="' . esc(url('register')) . '">Créer un compte étudiant</a>'
        . ' · <a href="' . esc(url('reset')) . '">Mot de passe oublié</a></p></div>';
    page_end(['auth' => true]);
    exit;
}

/* ========== DÉCONNEXION ========== */
if ($r === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('POST requis');
    }
    require_login();
    verify_csrf();
    log_event('logout');
    $_SESSION = [];
    session_destroy();
    redirect('login');
}

/* ========== INSCRIPTION ÉTUDIANT ========== */
if ($r === 'register') {
    if (current_user()) {
        redirect('dashboard');
    }
    $msg = '';
    $ok = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $pass = (string) ($_POST['password'] ?? '');
        $pass2 = (string) ($_POST['password_confirm'] ?? '');

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Nom ou e-mail invalide.';
        } elseif (strlen($pass) < 12) {
            $msg = 'Mot de passe : 12 caractères minimum.';
        } elseif (!hash_equals($pass, $pass2)) {
            $msg = 'La confirmation du mot de passe ne correspond pas.';
        } else {
            try {
                // PÉDAGOGIE : password_hash (PASSWORD_DEFAULT = bcrypt/argon selon PHP) — jamais de stockage en clair.
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $q = db()->prepare('INSERT INTO users(name, email, role, password_hash) VALUES(?,?,?,?)');
                $q->execute([$name, $email, 'student', $hash]);
                log_event('user_registered', $email);
                $ok = true;
                $msg = 'Compte étudiant créé. Vous pouvez vous connecter.';
            } catch (PDOException $e) {
                // Message non révélateur détaillé (unicité e-mail).
                $msg = 'Création impossible. Vérifiez les données ou essayez une autre adresse.';
            }
        }
    }

    page_start('Inscription', ['auth' => true]);
    echo '<div class="auth-card reveal"><p class="eyebrow">TP01 · Inscription</p><h2>Compte étudiant</h2>';
    if ($msg) {
        message($msg, $ok ? 'success' : 'error');
    }
    if (!$ok) {
        echo '<form method="post" class="stack">'
            . csrf_field()
            . '<label>Nom complet<input name="name" required maxlength="120"></label>'
            . '<label>Email<input name="email" type="email" required maxlength="190"></label>'
            . '<label>Mot de passe (12+ caractères)<input name="password" type="password" minlength="12" required></label>'
            . '<label>Confirmation<input name="password_confirm" type="password" minlength="12" required></label>'
            . '<button type="submit" class="btn btn-primary">S’inscrire</button></form>';
    }
    echo '<p class="muted-links"><a href="' . esc(url('login')) . '">Déjà un compte ? Connexion</a></p></div>';
    page_end(['auth' => true]);
    exit;
}

/* ========== RÉINITIALISATION SIMULÉE — DEMANDE (aucun SMTP) ========== */
if ($r === 'reset') {
    if (current_user()) {
        redirect('dashboard');
    }
    $info = '';
    $demoLink = '';
    $demoEmail = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $email = trim((string) ($_POST['email'] ?? ''));
        // Message toujours identique (anti-énumération d’e-mails).
        $info = 'Demande enregistrée. Aucun e-mail réel n’est envoyé (pas de SMTP) : la boîte ci-dessous simule la réception.';

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $q = db()->prepare('SELECT id, name FROM users WHERE email = ? AND active = 1');
            $q->execute([$email]);
            $row = $q->fetch();
            if ($row) {
                $token = bin2hex(random_bytes(32));
                // PÉDAGOGIE : on stocke uniquement le hash du jeton, jamais le jeton en clair.
                $tokenHash = hash('sha256', $token);
                $q = db()->prepare(
                    'INSERT INTO password_resets(user_id, token_hash, expires_at) VALUES(?, ?, ' . sql_datetime_plus_minutes(30) . ')'
                );
                $q->execute([(int) $row['id'], $tokenHash]);
                log_event('password_reset_requested', $email);
                // PÉDAGOGIE : simulation locale — pas de mail()/SMTP, le « message » s’affiche à l’écran.
                if (app_config('demo_show_reset_link')) {
                    $demoLink = url('reset_confirm', ['token' => $token]);
                    $demoEmail = $email;
                }
            }
        }
        usleep(200000);
    }

    page_start('Réinitialisation', ['auth' => true]);
    echo '<div class="auth-card reveal"><p class="eyebrow">TP01 · Simulation</p><h2>Réinitialisation de mot de passe</h2>'
        . '<p class="lede">Mode laboratoire : <strong>aucun serveur SMTP</strong>. Le lien est affiché dans une boîte mail fictive.</p>';
    if ($info) {
        message($info, 'success');
    }
    if ($demoLink !== '') {
        echo '<aside class="fake-mail" aria-label="Boîte mail simulée">'
            . '<div class="fake-mail-bar"><span class="fake-dot"></span><span class="fake-dot"></span><span class="fake-dot"></span>'
            . '<strong>Boîte mail simulée</strong> · pas de SMTP</div>'
            . '<dl class="fake-mail-meta">'
            . '<div><dt>De</dt><dd>noreply@sesame-campus.local</dd></div>'
            . '<div><dt>À</dt><dd>' . esc($demoEmail) . '</dd></div>'
            . '<div><dt>Objet</dt><dd>Réinitialisation de votre mot de passe (démo)</dd></div>'
            . '</dl>'
            . '<div class="fake-mail-body">'
            . '<p>Bonjour,</p>'
            . '<p>Voici votre lien de réinitialisation (valable 30 minutes, usage unique) :</p>'
            . '<p><a class="btn btn-primary" href="' . esc($demoLink) . '">Choisir un nouveau mot de passe</a></p>'
            . '<p class="fake-mail-url"><code>' . esc($demoLink) . '</code></p>'
            . '<p>Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.</p>'
            . '</div></aside>';
    }
    echo '<form method="post" class="stack">'
        . csrf_field()
        . '<label>Adresse électronique<input name="email" type="email" required maxlength="190" placeholder="ex. etudiant.a@fasi.test"></label>'
        . '<button type="submit" class="btn btn-primary">Simuler l’envoi du lien</button></form>'
        . '<p class="muted-links"><a href="' . esc(url('login')) . '">Retour connexion</a></p></div>';
    page_end(['auth' => true]);
    exit;
}

/* ========== RÉINITIALISATION — CONFIRMATION ========== */
if ($r === 'reset_confirm') {
    if (current_user()) {
        redirect('dashboard');
    }
    $token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
    $msg = '';
    $ok = false;
    $valid = false;
    $resetId = 0;

    if ($token !== '' && strlen($token) === 64 && ctype_xdigit($token)) {
        $tokenHash = hash('sha256', $token);
        $q = db()->prepare(
            'SELECT id, user_id FROM password_resets
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > ' . sql_now() . ' LIMIT 1'
        );
        $q->execute([$tokenHash]);
        $row = $q->fetch();
        if ($row) {
            $valid = true;
            $resetId = (int) $row['id'];
            $userId = (int) $row['user_id'];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $pass = (string) ($_POST['password'] ?? '');
        $pass2 = (string) ($_POST['password_confirm'] ?? '');
        if (!$valid) {
            $msg = 'Lien invalide ou expiré.';
        } elseif (strlen($pass) < 12) {
            $msg = 'Mot de passe : 12 caractères minimum.';
        } elseif (!hash_equals($pass, $pass2)) {
            $msg = 'La confirmation ne correspond pas.';
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $upd = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $upd->execute([$hash, $userId]);
            $mark = db()->prepare('UPDATE password_resets SET used_at = ' . sql_now() . ' WHERE id = ?');
            $mark->execute([$resetId]);
            log_event('password_reset_done', 'user_id=' . $userId);
            $ok = true;
            $msg = 'Mot de passe mis à jour. Connectez-vous.';
            $valid = false;
        }
    }

    page_start('Nouveau mot de passe', ['auth' => true]);
    echo '<div class="auth-card reveal"><p class="eyebrow">TP01 · Nouveau mot de passe</p><h2>Choisir un mot de passe</h2>';
    if ($msg) {
        message($msg, $ok ? 'success' : 'error');
    }
    if ($valid) {
        echo '<form method="post" class="stack">'
            . csrf_field()
            . '<input type="hidden" name="token" value="' . esc($token) . '">'
            . '<label>Nouveau mot de passe<input name="password" type="password" minlength="12" required></label>'
            . '<label>Confirmation<input name="password_confirm" type="password" minlength="12" required></label>'
            . '<button type="submit" class="btn btn-primary">Enregistrer</button></form>';
    } elseif (!$ok) {
        echo '<p>Le lien est invalide, déjà utilisé ou expiré.</p>';
    }
    echo '<p class="muted-links"><a href="' . esc(url('login')) . '">Connexion</a></p></div>';
    page_end(['auth' => true]);
    exit;
}

/* ========== ZONE AUTHENTIFIÉE ========== */
$u = require_login();

/* Démo expiration : raccourcit la dernière activité pour la soutenance */
if ($r === 'expire_demo') {
    require_role('admin', 'teacher', 'student');
    $_SESSION['last_activity'] = time() - ((int) app_config('session_lifetime') + 5);
    flash('Session marquée comme expirée. Rechargez une page protégée.');
    redirect('dashboard');
}

/* ========== TABLEAUX DE BORD PAR RÔLE ========== */
if ($r === 'dashboard') {
    page_start('Espace ' . role_label($u['role']));
    $lifetime = (int) app_config('session_lifetime');
    echo '<section class="hero-panel reveal">'
        . '<div><p class="eyebrow">Session active</p>'
        . '<h2>Bonjour, ' . esc($u['name']) . '</h2>'
        . '<p>Rôle : <span class="role-pill role-' . esc($u['role']) . '">' . esc(role_label($u['role'])) . '</span></p>'
        . '<p class="lede">Expiration automatique après <strong>' . (int) $lifetime
        . ' s</strong> d’inactivité (réglable dans <code>app/config.php</code>).</p></div>'
        . '<form method="post" action="' . esc(url('logout')) . '">' . csrf_field()
        . '<button class="btn btn-ghost" type="submit">Déconnexion sécurisée</button></form></section>';

    if ($u['role'] === 'student') {
        echo '<section class="grid">'
            . '<article class="tile"><h3>Mes cours</h3><p>Consultation limitée à vos inscriptions.</p></article>'
            . '<article class="tile"><h3>Mes notes</h3><p>Accès horizontal : uniquement vos résultats.</p></article>'
            . '<article class="tile tile-warn"><h3>Administration</h3><p>Refusée pour le rôle étudiant (tester <a href="'
            . esc(url('admin')) . '">?r=admin</a>).</p></article></section>';
    } elseif ($u['role'] === 'teacher') {
        echo '<section class="grid">'
            . '<article class="tile"><h3>Espace enseignant</h3><p>Gestion pédagogique de vos unités.</p></article>'
            . '<article class="tile"><h3>Saisie des notes</h3><p>Réservée aux cours qui vous sont affectés.</p></article>'
            . '<article class="tile tile-warn"><h3>Administration</h3><p>Page admin refusée — tester <a href="'
            . esc(url('admin')) . '">ici</a>.</p></article></section>';
    } else {
        echo '<section class="grid">'
            . '<a class="tile" href="' . esc(url('users')) . '"><h3>Comptes</h3><p>Créer admin / enseignant / étudiant.</p></a>'
            . '<a class="tile" href="' . esc(url('admin')) . '"><h3>Administration</h3><p>Zone réservée admin.</p></a>'
            . '<a class="tile" href="' . esc(url('audit')) . '"><h3>Journal d’audit</h3><p>Connexions et actions.</p></a></section>';
    }

    echo '<section class="panel"><h3>Scénarios de soutenance</h3><ol class="steps">'
        . '<li>Mot de passe incorrect → message générique « Identifiants invalides ».</li>'
        . '<li>5 échecs → verrouillage temporaire (même message prudent).</li>'
        . '<li>Étudiant ouvre <code>?r=admin</code> → HTTP 403.</li>'
        . '<li><a href="' . esc(url('expire_demo')) . '">Simuler une session expirée</a> puis recharger.</li>'
        . '</ol></section>';
    page_end();
    exit;
}

/* ========== PROFIL ========== */
if ($r === 'profile') {
    page_start('Mon profil');
    echo '<section class="panel reveal"><dl class="kv">'
        . '<div><dt>Nom</dt><dd>' . esc($u['name']) . '</dd></div>'
        . '<div><dt>Email</dt><dd>' . esc($u['email']) . '</dd></div>'
        . '<div><dt>Rôle</dt><dd><span class="role-pill role-' . esc($u['role']) . '">'
        . esc(role_label($u['role'])) . '</span></dd></div></dl></section>';
    page_end();
    exit;
}

/* ========== ADMIN ONLY ========== */
if ($r === 'admin') {
    require_role('admin');
    page_start('Administration');
    echo '<section class="panel reveal"><h2>Console administrateur</h2>'
        . '<p>Cette page démontre le contrôle <code>require_role(\'admin\')</code>. '
        . 'Un étudiant ou enseignant reçoit une réponse 403.</p>'
        . '<ul class="checklist">'
        . '<li>Cookies de session HttpOnly + SameSite</li>'
        . '<li>CSRF sur toutes les actions POST</li>'
        . '<li>Limitation des essais de connexion</li>'
        . '<li>Réinitialisation par jeton hashé à usage unique</li>'
        . '</ul></section>';
    page_end();
    exit;
}

/* ========== CRÉATION DE COMPTES (ADMIN) ========== */
if ($r === 'users') {
    require_role('admin');
    $msg = '';
    $type = 'info';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $role = (string) ($_POST['role'] ?? '');
        $pass = (string) ($_POST['password'] ?? '');

        if (
            mb_strlen($name) < 2 || mb_strlen($name) > 120
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || !in_array($role, ['admin', 'teacher', 'student'], true)
            || strlen($pass) < 12
        ) {
            $msg = 'Données invalides (mot de passe ≥ 12 caractères).';
            $type = 'error';
        } else {
            try {
                $q = db()->prepare('INSERT INTO users(name,email,role,password_hash) VALUES(?,?,?,?)');
                $q->execute([$name, $email, $role, password_hash($pass, PASSWORD_DEFAULT)]);
                log_event('user_created', $email);
                $msg = 'Compte créé.';
                $type = 'success';
            } catch (PDOException $e) {
                $msg = 'Création impossible (adresse peut-être déjà utilisée).';
                $type = 'error';
            }
        }
    }

    page_start('Gestion des comptes');
    if ($msg) {
        message($msg, $type);
    }
    echo '<section class="panel reveal"><h2>Nouveau compte</h2><form method="post" class="form-grid">'
        . csrf_field()
        . '<label>Nom<input name="name" required maxlength="120"></label>'
        . '<label>Email<input name="email" type="email" required></label>'
        . '<label>Rôle<select name="role">'
        . '<option value="student">Étudiant</option>'
        . '<option value="teacher">Enseignant</option>'
        . '<option value="admin">Administrateur</option></select></label>'
        . '<label>Mot de passe<input name="password" type="password" minlength="12" required></label>'
        . '<div class="form-actions"><button class="btn btn-primary" type="submit">Créer</button></div></form></section>';

    $rows = db()->query('SELECT id, name, email, role, created_at FROM users ORDER BY id DESC LIMIT 50')->fetchAll();
    echo '<section class="panel"><h2>Derniers comptes</h2><div class="table-wrap"><table>'
        . '<thead><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Créé</th></tr></thead><tbody>';
    foreach ($rows as $x) {
        echo '<tr><td>' . esc($x['name']) . '</td><td>' . esc($x['email']) . '</td><td>'
            . '<span class="role-pill role-' . esc($x['role']) . '">' . esc(role_label($x['role']))
            . '</span></td><td>' . esc($x['created_at']) . '</td></tr>';
    }
    echo '</tbody></table></div></section>';
    page_end();
    exit;
}

/* ========== AUDIT ========== */
if ($r === 'audit') {
    require_role('admin');
    page_start('Journal d’audit');
    $q = db()->query(
        'SELECT l.created_at, u.email, l.action, l.target, l.ip
         FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id
         ORDER BY l.id DESC LIMIT 100'
    );
    echo '<section class="panel"><div class="table-wrap"><table>'
        . '<thead><tr><th>Date</th><th>Compte</th><th>Action</th><th>Cible</th><th>IP</th></tr></thead><tbody>';
    foreach ($q as $x) {
        echo '<tr><td>' . esc($x['created_at']) . '</td><td>' . esc($x['email'] ?? '—') . '</td><td>'
            . esc($x['action']) . '</td><td>' . esc($x['target']) . '</td><td>' . esc($x['ip']) . '</td></tr>';
    }
    echo '</tbody></table></div></section>';
    page_end();
    exit;
}
