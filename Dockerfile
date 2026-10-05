FROM php:8.2-apache

# Change Debian repositories from HTTP to HTTPS
RUN sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        libpq-dev \
        unzip \
        curl \
        tar \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Enable Apache rewrite module
RUN a2enmod rewrite

WORKDIR /var/www/html

# Copy Composer files first
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --optimize-autoloader

# Copy project
COPY . /var/www/html

# Download GeoLite2-Country database using Render secret
RUN --mount=type=secret,id=maxmind_credentials,dst=/etc/secrets/maxmind_credentials,required=true \
    set -a \
    && . /etc/secrets/maxmind_credentials \
    && set +a \
    && mkdir -p /var/www/html/geoip \
    && curl -fL \
        -u "$MAXMIND_ACCOUNT_ID:$MAXMIND_LICENSE_KEY" \
        "https://download.maxmind.com/geoip/databases/GeoLite2-Country/download?suffix=tar.gz" \
        -o /tmp/GeoLite2-Country.tar.gz \
    && mkdir -p /tmp/geolite \
    && tar -xzf /tmp/GeoLite2-Country.tar.gz -C /tmp/geolite \
    && find /tmp/geolite -name "GeoLite2-Country.mmdb" -exec cp {} /var/www/html/geoip/GeoLite2-Country.mmdb \; \
    && test -f /var/www/html/geoip/GeoLite2-Country.mmdb \
    && rm -rf /tmp/GeoLite2-Country.tar.gz /tmp/geolite

# Allow .htaccess
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Allow 40 MB PDF uploads
RUN printf '%s\n' \
    'upload_max_filesize=40M' \
    'post_max_size=50M' \
    'max_execution_time=300' \
    'max_input_time=300' \
    > /usr/local/etc/php/conf.d/convergence.ini

# Render uses port 10000
RUN sed -i 's/^Listen 80$/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:10000>/' /etc/apache2/sites-available/000-default.conf

EXPOSE 10000

CMD ["apache2-foreground"]