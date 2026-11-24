FROM php:8.3-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libicu-dev \
    libxml2-dev \
    libonig-dev \
    libxslt1-dev \
    libcurl4-openssl-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        intl \
        mbstring \
        exif \
        soap \
        xsl \
        opcache \
    && a2enmod rewrite headers \
    && sed -ri \
        's!/var/www/html!/var/www/html/public!g' \
        /etc/apache2/sites-available/*.conf \
    && printf '%s\n' \
        '<Directory /var/www/html/public>' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        '<FilesMatch "(?i)(^\.env|\.bak.*$|\.sql$|\.log$|\.patch$)">' \
        '    Require all denied' \
        '</FilesMatch>' \
        > /etc/apache2/conf-available/axxis.conf \
    && a2enconf axxis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-scripts \
    --no-autoloader

COPY --chown=root:root . .

RUN composer dump-autoload \
        --no-dev \
        --optimize \
        --no-interaction \
        --no-scripts \
    && chown -R root:root /var/www/html \
    && chmod 755 /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} + \
    && chmod -R a+rX,go-w /var/www/html \
    && find /var/www/html -type f -name "*.php" -exec chmod 644 {} + \
    && mkdir -p \
        storage/logs \
        storage/cache \
        storage/uploads \
        storage/test-reports \
    && chown -R www-data:www-data storage \
    && chmod -R u+rwX,g+rwX,o-rwx storage

RUN su -s /bin/sh www-data -c \
    'test -r app/Controllers/Clinica/PropietarioController.php \
    && test -r app/Services/OwnerService.php \
    && test -r app/Services/IContaService.php \
    && test -r app/Services/IdentificationValidator.php \
    && test -r app/Config/services.php \
    && test ! -w app \
    && test -w storage' \
    && php -r 'require "vendor/autoload.php"; if (!class_exists("App\\Controllers\\Clinica\\PropietarioController")) exit(1);' \
    && apache2ctl configtest

RUN printf '%s\n' \
    'display_errors=Off' \
    'display_startup_errors=Off' \
    'log_errors=On' \
    'error_reporting=E_ALL' \
    'expose_php=Off' \
    > /usr/local/etc/php/conf.d/99-axxis.ini

RUN printf "ServerName localhost\n" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername

EXPOSE 80

CMD ["apache2-foreground"]
