FROM php:8.3.24-cli-bookworm

RUN docker-php-ext-install pdo_mysql

WORKDIR /workspace

CMD ["php", "-S", "0.0.0.0:8000", "-t", "backend/public", "backend/public/router.php"]
