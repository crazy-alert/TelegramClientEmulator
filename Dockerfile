FROM php:8.3-cli-alpine

RUN apk add --no-cache sqlite-dev nginx \
    && docker-php-ext-install pdo_sqlite

WORKDIR /app

# Копируем php.ini как основной конфиг
COPY php.ini /usr/local/etc/php/php.ini

# Копируем конфиг nginx
COPY nginx.conf /etc/nginx/nginx.conf

ENV APP_HOST=0.0.0.0
ENV APP_PORT=8080
ENV DATA_DIR=/app/data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD php -r "exit(@file_get_contents('http://127.0.0.1:' . getenv('APP_PORT') . '/health') === false ? 1 : 0);"

# PHP на внутреннем порту 9000, nginx reverse proxy на 8080
CMD ["sh", "-c", "php -S 127.0.0.1:9000 -t public public/index.php & nginx -g 'daemon off;'"]
