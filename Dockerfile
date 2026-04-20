# ============================================================
# Esfero Marketplace — Apache + PHP 8.2 + Python 3 CGI
# ============================================================
FROM php:8.2-apache

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

# ── Apache: un solo MPM (prefork) + CGI + mod_rewrite ────────
RUN rm -f /etc/apache2/mods-enabled/mpm_event.conf \
          /etc/apache2/mods-enabled/mpm_event.load \
          /etc/apache2/mods-enabled/mpm_worker.conf \
          /etc/apache2/mods-enabled/mpm_worker.load && \
    a2enmod mpm_prefork cgi rewrite

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

# ── Entrypoint (puerto dinámico Railway + migraciones) ───────
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
CMD ["/entrypoint.sh"]
