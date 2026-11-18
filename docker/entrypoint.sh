#!/bin/sh
set -e

DATA_DIR="${DATA_DIR:-/data}"
SQLITE_PATH="${SQLITE_PATH:-$DATA_DIR/sesame.sqlite}"
PORT="${PORT:-8080}"

mkdir -p "$DATA_DIR"
# Garantit un fichier SQLite accessible (volume Render / Docker)
touch "$SQLITE_PATH" 2>/dev/null || true
chmod -R u+rwX "$DATA_DIR" 2>/dev/null || true

export SQLITE_PATH
export DB_DRIVER="${DB_DRIVER:-sqlite}"
export APP_ENV="${APP_ENV:-production}"

echo "Sesame Campus — SQLite=$SQLITE_PATH PORT=$PORT"
exec php -S "0.0.0.0:${PORT}" -t /app/public /app/docker/router.php
