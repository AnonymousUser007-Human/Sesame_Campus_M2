# Sesame Campus — image PHP pour Render / Docker (SQLite sur volume /data)
FROM php:8.3-cli-alpine

# sqlite-dev requis pour compiler pdo_sqlite (sqlite-libs seul ne suffit pas)
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS sqlite-dev \
    && docker-php-ext-install pdo_sqlite \
    && apk del .build-deps \
    && apk add --no-cache sqlite-libs

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
