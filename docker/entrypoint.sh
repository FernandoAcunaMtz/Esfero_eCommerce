#!/bin/bash
# ============================================================
# Esfero Marketplace — Entrypoint
# Maneja: puerto dinámico (Railway), espera de DB, migraciones
# ============================================================
set -e

# ── 1. Mapear variables Railway → variables de la app ────────
#       Railway MySQL plugin expone MYSQLHOST, MYSQLPORT, etc.
#       Si ya están definidas DB_HOST, etc., tienen prioridad.
export DB_HOST="${DB_HOST:-${MYSQLHOST:-localhost}}"
export DB_PORT="${DB_PORT:-${MYSQLPORT:-3306}}"
export DB_NAME="${DB_NAME:-${MYSQLDATABASE:-esfero}}"
export DB_USER="${DB_USER:-${MYSQLUSER:-root}}"
export DB_PASSWORD="${DB_PASSWORD:-${MYSQLPASSWORD:-}}"

# ── 2. Puerto dinámico (Railway inyecta $PORT) ────────────────
APP_PORT="${PORT:-80}"

echo "[entrypoint] Puerto: ${APP_PORT}"

# Actualizar ports.conf de Apache
sed -i "s/^Listen 80$/Listen ${APP_PORT}/" /etc/apache2/ports.conf
# Actualizar VirtualHost
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${APP_PORT}>/" \
    /etc/apache2/sites-available/000-default.conf

# ── 3. APP_URL dinámica en Railway ────────────────────────────
if [ -n "${RAILWAY_PUBLIC_DOMAIN:-}" ]; then
    export APP_URL="https://${RAILWAY_PUBLIC_DOMAIN}"
    export PAYPAL_RETURN_URL="https://${RAILWAY_PUBLIC_DOMAIN}/checkout.php?success=true"
    export PAYPAL_CANCEL_URL="https://${RAILWAY_PUBLIC_DOMAIN}/checkout.php?canceled=true"
    export API_BASE_URL="http://localhost:${APP_PORT}/esfero/backend/cgi-bin"
    echo "[entrypoint] APP_URL = ${APP_URL}"
fi

# ── 4. Esperar a que MySQL esté disponible ────────────────────
echo "[entrypoint] Esperando MySQL en ${DB_HOST}:${DB_PORT}..."
MAX_TRIES=30
COUNT=0
until mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" \
      -e "SELECT 1" > /dev/null 2>&1; do
    COUNT=$((COUNT + 1))
    if [ "$COUNT" -ge "$MAX_TRIES" ]; then
        echo "[entrypoint] ERROR: MySQL no respondió tras ${MAX_TRIES} intentos."
        exit 1
    fi
    echo "[entrypoint]   ... intento ${COUNT}/${MAX_TRIES}"
    sleep 2
done
echo "[entrypoint] MySQL listo."

# ── 5. Migraciones automáticas ────────────────────────────────
# Contar tablas en la BD (sin contar information_schema)
TABLE_COUNT=$(mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" \
    --skip-column-names -e \
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}';" \
    2>/dev/null || echo "0")

if [ "${TABLE_COUNT}" -lt "5" ]; then
    echo "[entrypoint] BD vacía — aplicando schema.sql..."
    mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" \
        "${DB_NAME}" < /var/www/esfero/sql/schema.sql
    echo "[entrypoint] schema.sql aplicado."
fi

# Aplicar todos los patches (CREATE TABLE IF NOT EXISTS → idempotente)
echo "[entrypoint] Aplicando patches..."
for PATCH in /var/www/esfero/sql/patch_*.sql; do
    echo "[entrypoint]   ${PATCH}"
    mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" \
        "${DB_NAME}" < "${PATCH}" 2>/dev/null || true
done
echo "[entrypoint] Migraciones completas."

# ── 6. Permisos ───────────────────────────────────────────────
chown -R www-data:www-data /var/www/esfero
chmod -R 755 /var/www/esfero
chmod 600 /var/www/esfero/backend/keys/jwt_private.pem 2>/dev/null || true

# ── 7. Arrancar Apache ────────────────────────────────────────
echo "[entrypoint] Iniciando Apache en :${APP_PORT}..."
exec apache2-foreground
