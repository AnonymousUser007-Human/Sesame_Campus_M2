# Déployer Sesame Campus sur Render (Docker + SQLite)

## Principe

| Élément | Valeur |
|---------|--------|
| Runtime | Docker (`Dockerfile`) |
| Base de données | **SQLite** (fichier) |
| Persistance | Disque Render monté sur **`/data`** |
| Fichier DB | `/data/sesame.sqlite` |

Sans disque persistant, Render efface le filesystem à chaque redeploy → **la DB disparaîtrait**.

## 1. Prérequis Render

- Compte Render
- Plan avec **Persistent Disk** (pas le free tier sans disk)
- Repo Git contenant le dossier `sesame_campus`

## 2. Créer le service

### Option A — Blueprint

1. Dashboard Render → **New** → **Blueprint**
2. Sélectionner le repo
3. **Root Directory** : `sesame_campus` (si monorepo)
4. Valider `render.yaml`

### Option B — Manuel

1. **New Web Service** → Docker
2. Root Directory : `sesame_campus`
3. Dockerfile Path : `./Dockerfile`
4. **Disk** :
   - Name : `sesame-sqlite-data`
   - Mount Path : `/data`
   - Size : `1 GB`
5. Variables d’environnement :

```
APP_ENV=production
DB_DRIVER=sqlite
SQLITE_PATH=/data/sesame.sqlite
DEMO_SHOW_RESET_LINK=true
SESSION_LIFETIME=900
```

## 3. Comptes seed (premier démarrage)

Si la DB SQLite est vide, 4 comptes sont créés automatiquement :

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| admin | admin@fasi.test | `b0e45442011c2e28d1e039c6!` |
| teacher | enseignant@fasi.test | `525269b7f79647bbf0a955b0!` |
| student | etudiant.a@fasi.test | `a1eda104e40f12f666bd12cf!` |
| student | etudiant.b@fasi.test | `5eea03565c287c68d7018957!` |

## 4. Tester en local avec Docker

```bash
cd sesame_campus
docker compose up --build
```

Ouvrir : http://localhost:8080/

Le volume Docker `sesame_campus_data` conserve `/data/sesame.sqlite`.

## 5. Local Laragon (inchangé)

Sans `DB_DRIVER=sqlite`, l’app utilise **MySQL** (`database` / root / root) comme avant.
