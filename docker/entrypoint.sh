#!/bin/bash
# ============================================================
# Esfero Marketplace — Entrypoint
# Apache arranca primero → healthcheck pasa → DB init en bg
# ============================================================
set -e

# ── 1. Mapear variables Railway → variables de la app ────────
export DB_HOST="${DB_HOST:-${MYSQLHOST:-localhost}}"
export DB_PORT="${DB_PORT:-${MYSQLPORT:-3306}}"
export DB_NAME="${DB_NAME:-${MYSQLDATABASE:-esfero}}"
export DB_USER="${DB_USER:-${MYSQLUSER:-root}}"
export DB_PASSWORD="${DB_PASSWORD:-${MYSQLPASSWORD:-}}"

# ── 2. Forzar un solo MPM (prefork) en runtime ───────────────
rm -f /etc/apache2/mods-enabled/mpm_event.conf \
      /etc/apache2/mods-enabled/mpm_event.load \
      /etc/apache2/mods-enabled/mpm_worker.conf \
      /etc/apache2/mods-enabled/mpm_worker.load
[ -f /etc/apache2/mods-enabled/mpm_prefork.load ] || \
    ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
[ -f /etc/apache2/mods-enabled/mpm_prefork.conf ] || \
    ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

# ── 3. Puerto dinámico Railway ($PORT) ────────────────────────
APP_PORT="${PORT:-80}"
echo "[entrypoint] Puerto: ${APP_PORT}"

# Sobreescribir ports.conf directamente (más fiable que sed)
printf "Listen %s\n" "${APP_PORT}" > /etc/apache2/ports.conf

# Actualizar VirtualHost en ambas ubicaciones (patrón amplio por si acaso)
sed -i "s|<VirtualHost \*:[0-9]*>|<VirtualHost *:${APP_PORT}>|g" \
    /etc/apache2/sites-available/000-default.conf 2>/dev/null || true
sed -i "s|<VirtualHost \*:[0-9]*>|<VirtualHost *:${APP_PORT}>|g" \
    /etc/apache2/sites-enabled/000-default.conf 2>/dev/null || true

echo "[entrypoint] Apache configurado en puerto ${APP_PORT}"
# Verificar que el cambio quedó
grep "Listen" /etc/apache2/ports.conf || true

# ── 3. APP_URL dinámica en Railway ────────────────────────────
if [ -n "${RAILWAY_PUBLIC_DOMAIN:-}" ]; then
    export APP_URL="https://${RAILWAY_PUBLIC_DOMAIN}"
    export PAYPAL_RETURN_URL="https://${RAILWAY_PUBLIC_DOMAIN}/checkout.php?success=true"
    export PAYPAL_CANCEL_URL="https://${RAILWAY_PUBLIC_DOMAIN}/checkout.php?canceled=true"
    export API_BASE_URL="http://localhost:${APP_PORT}/esfero/backend/cgi-bin"
    echo "[entrypoint] APP_URL = ${APP_URL}"
fi

# ── 4. Permisos ───────────────────────────────────────────────
chown -R www-data:www-data /var/www/esfero 2>/dev/null || true
chmod -R 755 /var/www/esfero 2>/dev/null || true
chmod 600 /var/www/esfero/backend/keys/jwt_private.pem 2>/dev/null || true

# ── 5. DB init en segundo plano (no bloquea Apache) ──────────
(
    echo "[db-init] Esperando MySQL en ${DB_HOST}:${DB_PORT}..."
    MAX_TRIES=45
    COUNT=0
    until mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" \
          --skip-ssl -e "SELECT 1" > /dev/null 2>&1; do
        COUNT=$((COUNT + 1))
        if [ "$COUNT" -ge "$MAX_TRIES" ]; then
            echo "[db-init] ERROR: MySQL no respondió tras ${MAX_TRIES} intentos."
            exit 0
        fi
        echo "[db-init] ... intento ${COUNT}/${MAX_TRIES}"
        sleep 2
    done
    echo "[db-init] MySQL listo."

    # Aplicar schema si la BD está vacía
    TABLE_COUNT=$(mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" \
        --skip-column-names -e \
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';" \
        2>/dev/null || echo "0")

    if [ "${TABLE_COUNT}" -lt "5" ]; then
        echo "[db-init] BD vacía — aplicando schema.sql..."
        mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" \
            "${DB_NAME}" < /var/www/esfero/sql/schema.sql && \
            echo "[db-init] schema.sql aplicado."
    fi

    # Aplicar todos los patches (idempotentes — CREATE TABLE IF NOT EXISTS)
    echo "[db-init] Aplicando patches..."
    for PATCH in /var/www/esfero/sql/patch_*.sql; do
        echo "[db-init]   ${PATCH}"
        mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" \
            "${DB_NAME}" < "${PATCH}" 2>/dev/null || true
    done
    echo "[db-init] Migraciones completas."
) &

# ── 6. Diagnóstico Apache antes de arrancar ───────────────────
echo "[apache-diag] mods-enabled con mpm:"
ls /etc/apache2/mods-enabled/ | grep mpm || echo "ninguno"
echo "[apache-diag] configtest:"
apache2ctl configtest 2>&1 || true

# ── 7. Arrancar Apache en primer plano (healthcheck pasa) ────
echo "[entrypoint] Iniciando Apache en :${APP_PORT}..."
exec apache2-foreground
