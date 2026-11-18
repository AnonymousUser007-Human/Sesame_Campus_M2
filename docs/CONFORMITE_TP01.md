# Sesame Campus — Grille de conformité TP01

| Exigence PDF | Statut | Où |
|---|---|---|
| Portail avec profils étudiant / enseignant / admin | OK | `public/index.php` (dashboards) |
| Inscription ou création des comptes | OK | routes `register` + `users` (admin) |
| Connexion / déconnexion | OK | routes `login` / `logout` |
| Réinitialisation simulée de mot de passe | OK | routes `reset` / `reset_confirm` |
| Pages distinctes selon le rôle | OK | dashboard + `require_role` |
| `password_hash` / `password_verify` | OK | inscription, login, reset |
| Régénération de session | OK | `session_regenerate_id(true)` après login |
| Limitation des essais | OK | `login_attempts` + verrouillage |
| Cookies de session sécurisés | OK | HttpOnly, SameSite, Secure si HTTPS |
| Messages d’erreur non révélateurs | OK | « Identifiants invalides » |
| Scénario : MDP incorrect | OK | démo login |
| Scénario : session expirée | OK | `expire_demo` + `session_lifetime` |
| Scénario : refus page admin | OK | `?r=admin` → 403 |
| Code commenté (PÉDAGOGIE) | OK | `app/bootstrap.php`, `public/index.php` |
| Base SQL | OK | DB `database` + `sql/tp01_extra.sql` |
| Jeu de données fictives | OK | comptes `@fasi.test` |
| Preuves de tests | OK | `docs/PREUVES_TESTS.md` |
| Notice d’installation | OK | `README.md` |
| Support présentation orale | OK | `docs/SOUTENANCE.md` |

**URL :** `http://localhost/portail_fasi_secure/sesame_campus/public/`
