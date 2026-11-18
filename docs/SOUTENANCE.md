# Sesame Campus — Guide de soutenance (TP01)

**URL démo :** `http://localhost/portail_fasi_secure/sesame_campus/public/`

## Pitch (30 s)

« Sesame Campus est un portail d’authentification académique : trois rôles, hash des mots de passe, sessions protégées, limitation des essais, reset simulé, et refus d’accès admin pour un étudiant. »

## Questions typiques

1. **Où se trouve le contrôle de sécurité ?**  
   `sesame_campus/app/bootstrap.php` (`require_role`, `verify_csrf`, `enforce_session_lifetime`, cookies) et `sesame_campus/public/index.php` (`password_verify`, `session_regenerate_id`).

2. **Que se passe-t-il si on le retire ?**  
   Sans `require_role('admin')` → un étudiant ouvre l’admin. Sans hash → MDP en clair. Sans limitation → bruteforce.

3. **Comment avez-vous vérifié ?**  
   Checklist `docs/PREUVES_TESTS.md` (T1–T10) + captures.

4. **Quelles limites restent ?**  
   MFA, e-mail réel, HTTPS forcé, captcha, supervision.

## Ordre de démo

1. Mot de passe incorrect  
2. Connexion étudiant → `?r=admin` → 403  
3. Simuler une session expirée  
4. Reset simulé (lien démo)  
5. Montrer le code (chemin + fonction + ligne)  
6. Renvoyer à la grille `docs/CONFORMITE_TP01.md`
