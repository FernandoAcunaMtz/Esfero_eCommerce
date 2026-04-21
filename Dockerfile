# ============================================================
# Esfero Marketplace — Apache + PHP 8.2 + Python 3 CGI
# ============================================================
FROM php:8.2-apache
# cache-bust: 2026-04-20

# ── Sistema ──────────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    python3 \
    python3-pip \
    python3-venv \
    openssl \
    libssl-dev \
    default-mysql-client \
    curl \
    unzip \
    imagemagick \
    fonts-dejavu-core \
    && rm -rf /var/lib/apt/lists/*

# ── Composer ──────────────────────────────────────────────────
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# ── Extensiones PHP ──────────────────────────────────────────
RUN docker-php-ext-install mysqli pdo pdo_mysql

# ── Dependencias Python ──────────────────────────────────────
RUN pip3 install --break-system-packages \
    mysql-connector-python \
    PyJWT \
    bcrypt \
    requests \
    python-dotenv \
    cryptography

# ── Apache: eliminar cualquier MPM activo, forzar prefork + cgid ─
RUN rm -f /etc/apache2/mods-enabled/mpm_*.conf \
          /etc/apache2/mods-enabled/mpm_*.load && \
    ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load && \
    ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf && \
    a2enmod cgid rewrite headers && \
    apache2ctl configtest

# ── Configuración Apache ─────────────────────────────────────
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# ── Código fuente ─────────────────────────────────────────────
COPY . /var/www/esfero/

# ── Dependencias PHP (PHPMailer) ──────────────────────────────
RUN cd /var/www/esfero && composer install --no-dev --optimize-autoloader --no-interaction

# ── Permisos CGI ─────────────────────────────────────────────
RUN chmod +x /var/www/esfero/backend/cgi-bin/*.py && \
    chown -R www-data:www-data /var/www/esfero && \
    chmod -R 755 /var/www/esfero

# ── Claves RSA para JWT (generadas en build) ─────────────────
RUN mkdir -p /var/www/esfero/backend/keys && \
    openssl genrsa -out /var/www/esfero/backend/keys/jwt_private.pem 2048 && \
    openssl rsa -in /var/www/esfero/backend/keys/jwt_private.pem \
                -pubout -out /var/www/esfero/backend/keys/jwt_public.pem && \
    chown www-data:www-data /var/www/esfero/backend/keys/*.pem && \
    chmod 600 /var/www/esfero/backend/keys/jwt_private.pem && \
    chmod 644 /var/www/esfero/backend/keys/jwt_public.pem

# ── Iconos PWA (generados en build con ImageMagick) ──────────
RUN mkdir -p /var/www/esfero/frontend/assets/icons && \
    convert -size 512x512 \
        gradient:"#044E65-#0C9268" \
        -gravity Center \
        -fill white \
        -font /usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf \
        -pointsize 260 \
        -annotate 0 "E" \
        /var/www/esfero/frontend/assets/icons/icon-512.png && \
    convert /var/www/esfero/frontend/assets/icons/icon-512.png \
        -resize 192x192 \
        /var/www/esfero/frontend/assets/icons/icon-192.png && \
    convert /var/www/esfero/frontend/assets/icons/icon-512.png \
        -resize 180x180 \
        /var/www/esfero/frontend/assets/icons/apple-touch-icon.png && \
    chown www-data:www-data /var/www/esfero/frontend/assets/icons/*.png

# ── Entrypoint (puerto dinámico Railway + migraciones) ───────
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
CMD ["/entrypoint.sh"]
