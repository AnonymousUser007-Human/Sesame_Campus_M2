# Sesame Campus — image PHP pour Render / Docker (SQLite sur volume /data)
FROM php:8.3-cli-alpine

RUN apk add --no-cache sqlite-libs \
    && docker-php-ext-install pdo_sqlite

WORKDIR /app

COPY . /app

RUN mkdir -p /data /app/data \
    && chmod +x /app/docker/entrypoint.sh

ENV APP_ENV=production \
    DB_DRIVER=sqlite \
    SQLITE_PATH=/data/sesame.sqlite \
    DEMO_SHOW_RESET_LINK=true \
    PORT=8080

# Volume persistant attendu : /data (Render Disk ou docker volume)
VOLUME ["/data"]

EXPOSE 8080

ENTRYPOINT ["/app/docker/entrypoint.sh"]
