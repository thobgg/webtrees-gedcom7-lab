# webtrees7 for a server or a NAS: PHP + Apache.  Built by docker/production/build.sh.
FROM php:8.5-apache

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions exif gd intl opcache pdo_mysql pdo_pgsql zip

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini \
    && a2enmod rewrite \
    && echo 'ServerName localhost' > /etc/apache2/conf-enabled/servername.conf \
    && { \
        echo 'memory_limit = 1024M'; \
        echo 'upload_max_filesize = 64M'; \
        echo 'post_max_size = 64M'; \
        echo 'max_execution_time = 300'; \
    } > /usr/local/etc/php/conf.d/webtrees.ini \
    && { \
        echo '<Directory /var/www/html/docker>'; \
        echo '    Require all denied'; \
        echo '</Directory>'; \
    } > /etc/apache2/conf-enabled/webtrees7.conf

COPY --chown=www-data:www-data webtrees/ /var/www/html/

# The data directory is a volume; these files protect it from web access.
RUN mkdir -p /opt/webtrees-data-template \
    && cp /var/www/html/data/.htaccess /var/www/html/data/index.php /opt/webtrees-data-template/ \
    && find /var/www/html/docker -type f ! -name seed-database.php -delete \
    && rm -rf /var/www/html/docs /var/www/html/compose.yaml

COPY entrypoint.sh /usr/local/bin/webtrees7-entrypoint

VOLUME /var/www/html/data
ENTRYPOINT ["webtrees7-entrypoint"]
CMD ["apache2-foreground"]
