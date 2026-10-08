# The app: PHP 8.4 behind nginx, in one container (serversideup/php).
# The project folder is mounted in at runtime, so code edits show at once.
FROM serversideup/php:8.4-fpm-nginx

USER root

# What Laravel and Filament need beyond the image's own set (intl for Filament,
# gd for PDFs and image handling, exif and bcmath for the odd library).
RUN install-php-extensions intl gd exif bcmath

# Extra certificate authorities this machine needs, e.g. antivirus software that
# inspects HTTPS (Avast). Any *.crt in docker/certs is trusted; the folder may be empty.
COPY docker/certs/ /usr/local/share/ca-certificates/extra/
RUN update-ca-certificates

# vendor/ is a Docker volume (fast, unlike the Windows folder). A new volume takes
# the ownership of this folder, so the web user can install into it.
RUN mkdir -p /var/www/html/vendor && chown www-data:www-data /var/www/html/vendor

# Prepares the app on every start: dependencies, .env, key, migrations.
COPY --chmod=755 docker/php/entrypoint.d/ /etc/entrypoint.d/

# Uploads of a few hundred MB (bulk upload sends batches) and long imports.
ENV PHP_UPLOAD_MAX_FILE_SIZE=512M \
    PHP_POST_MAX_SIZE=512M \
    PHP_MEMORY_LIMIT=512M \
    PHP_MAX_EXECUTION_TIME=300 \
    PHP_OPCACHE_ENABLE=1 \
    SSL_MODE=off

USER www-data
