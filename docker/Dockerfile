# Development image for webtrees7: PHP + Apache + composer.
# The source tree is bind-mounted at /var/www/html (see compose.yaml).
FROM php:8.5-apache

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions exif gd intl pdo_mysql pdo_pgsql zip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install --yes --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*

RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini \
    && a2enmod rewrite \
    && sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf \
    && echo 'ServerName localhost' > /etc/apache2/conf-enabled/servername.conf \
    && { \
        echo 'memory_limit = 2G'; \
        echo 'upload_max_filesize = 64M'; \
        echo 'post_max_size = 64M'; \
        echo 'max_execution_time = 300'; \
    } > /usr/local/etc/php/conf.d/webtrees.ini

# Apache, composer and all tools run as the host user, so that files in the bind mount keep their owner.
# The mount points for the named volumes are created here, so that new volumes belong to this user.
ARG UID=1000
ARG GID=1000
RUN groupadd --gid "$GID" dev \
    && useradd --uid "$UID" --gid "$GID" --create-home dev \
    && mkdir -p /var/www/html/vendor /var/www/html/data \
    && chown -R dev:dev /var/www/html /var/run/apache2 /var/lock/apache2 /var/log/apache2
ENV APACHE_RUN_USER=dev \
    APACHE_RUN_GROUP=dev \
    COMPOSER_HOME=/home/dev/.composer

# The data directory is a volume; these files protect it from web access.
COPY data/.htaccess data/index.php /opt/webtrees-data-template/
COPY docker/entrypoint.sh /usr/local/bin/

USER dev
WORKDIR /var/www/html
EXPOSE 8080
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
