# Sesame Campus — TP01 Authentification sécurisée (FASI M2)

Portail pédagogique **Sesame Campus** : authentification des étudiants, enseignants et administrateurs, conforme au **TP01** du PDF *Travaux pratiques indépendants*.

## Objectif

- Inscription / création de comptes
- Connexion / déconnexion
- Réinitialisation **simulée** de mot de passe
- Pages distinctes selon le rôle
- `password_hash` / `password_verify`
- Régénération de session
- Limitation des essais
- Cookies de session sécurisés
- Messages d’erreur **non révélateurs**

## Prérequis

- Laragon (Apache + MySQL + PHP 8+)
- Base MySQL **`database`** (déjà utilisée par `portail_fasi_secure`)

## Installation

1. Dossier :
   `C:\laragon\www\portail_fasi_secure\sesame_campus\`
2. Vérifier `app/config.php` (DSN `database`, user `root`, password Laragon `root` par défaut).
3. Tables `login_attempts` / `password_resets` : créées au premier accès  
   (ou importer `sql/tp01_extra.sql`).
4. Ouvrir :
   `http://localhost/portail_fasi_secure/sesame_campus/public/`

## Comptes de démo (jeu de données fictives)

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| admin | admin@fasi.test | voir `Nouveau document texte.txt` (racine portail) |
| teacher | enseignant@fasi.test | idem |
| student | etudiant.a@fasi.test | idem |
| student | etudiant.b@fasi.test | idem |

## Scénarios obligatoires (soutenance)

1. Mot de passe incorrect → « Identifiants invalides »
2. Session expirée → lien de simulation sur le tableau de bord
3. Étudiant sur `?r=admin` → **403**

## Livrables

| Élément | Fichier |
|---------|---------|
| Application | `public/` |
| Commentaires pédagogiques | `app/bootstrap.php`, `public/index.php` |
| SQL complémentaire | `sql/tp01_extra.sql` |
| Notice d’installation | `README.md` |
| Preuves de tests | `docs/PREUVES_TESTS.md` |
| Grille conformité | `docs/CONFORMITE_TP01.md` |
| Aide orale | `docs/SOUTENANCE.md` |

## Fichiers clés à montrer

| Contrôle | Fichier | Zone |
|----------|---------|------|
| Hash MDP | `public/index.php` | `password_hash` / `password_verify` |
| Régénération session | `public/index.php` | `session_regenerate_id(true)` |
| Limitation essais | `app/bootstrap.php` | `is_locked_out` |
| Cookies session | `app/bootstrap.php` | `session_set_cookie_params` |
| Expiration | `app/bootstrap.php` | `enforce_session_lifetime` |
| Rôles | `app/bootstrap.php` | `require_role` |

## Déploiement Render (Docker + SQLite)

Prod Render = **SQLite** sur volume persistant `/data` (pas MySQL).

```bash
# Test local Docker (volume nommé sesame_campus_data)
cd sesame_campus
docker compose up --build
# → http://localhost:8080/
```

Fichiers : `Dockerfile`, `docker-compose.yml`, `render.yaml`, guide `docs/DEPLOY_RENDER.md`.

Variables clés : `DB_DRIVER=sqlite`, `SQLITE_PATH=/data/sesame.sqlite`, disque Render monté sur `/data`.

## Documentation

| Fichier | Rôle |
|---------|------|
| `docs/CONFORMITE_TP01.md` | Checklist vs consignes PDF |
| `docs/PREUVES_TESTS.md` | Tableau de preuves (à cocher + captures) |
| `docs/SOUTENANCE.md` | Pitch, questions, ordre de démo |
| `docs/DEPLOY_RENDER.md` | Docker / Render / volume SQLite |

## Limites (soutenance)

- Pas de MFA / HTTPS forcé en local
- Reset **simulé** : boîte mail fictive à l’écran, **aucun SMTP** (`demo_show_reset_link`)
- Pas d’e-mail réel / pas de `mail()`
- Labo / démo ; disque Render requis pour ne pas perdre la SQLite
