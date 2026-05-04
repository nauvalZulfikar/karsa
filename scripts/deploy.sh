#!/usr/bin/env bash
# Karsa / DPUTR-PM Deploy Script
# Usage on VPS:
#   curl -O https://raw.githubusercontent.com/nauvalZulfikar/karsa/master/scripts/deploy.sh
#   chmod +x deploy.sh
#   ./deploy.sh

set -euo pipefail

PROJECT_DIR="${PROJECT_DIR:-/root/projects/karta}"
REPO_URL="${REPO_URL:-https://github.com/nauvalZulfikar/karsa.git}"
DOMAIN="${DOMAIN:-karta.aureonforge.com}"
DB_NAME="${DB_NAME:-karta}"
DB_USER="${DB_USER:-karta}"
DB_PASS_FILE="${DB_PASS_FILE:-/root/.karta_db_pass}"
PHP_VERSION="${PHP_VERSION:-8.2}"

log() { echo -e "\033[1;34m▶\033[0m $*"; }
err() { echo -e "\033[1;31m✗\033[0m $*" >&2; }

# 1. Install dependencies (Ubuntu/Debian)
log "Step 1/9: Install system dependencies"
apt-get update -qq
apt-get install -y -qq software-properties-common curl unzip git nginx mariadb-server certbot python3-certbot-nginx
add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1 || true
apt-get update -qq
apt-get install -y -qq php${PHP_VERSION} php${PHP_VERSION}-{cli,fpm,mysql,xml,mbstring,gd,curl,bcmath,zip,intl,redis,sqlite3,opcache,readline}
command -v composer >/dev/null || (curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer)
command -v node >/dev/null || (curl -fsSL https://deb.nodesource.com/setup_20.x | bash -; apt-get install -y nodejs)

# 2. Clone or pull repo
log "Step 2/9: Clone/update repo from GitHub"
mkdir -p /root/projects
if [ -d "${PROJECT_DIR}/.git" ]; then
  cd "${PROJECT_DIR}" && git pull
else
  git clone "${REPO_URL}" "${PROJECT_DIR}"
  cd "${PROJECT_DIR}"
fi

# 3. Setup MariaDB
log "Step 3/9: Setup MariaDB database"
systemctl enable --now mariadb
if [ ! -f "${DB_PASS_FILE}" ]; then
  openssl rand -base64 24 | tr -d '+/=' | head -c 24 > "${DB_PASS_FILE}"
  chmod 600 "${DB_PASS_FILE}"
fi
DB_PASS=$(cat "${DB_PASS_FILE}")
mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

# 4. Compose install
log "Step 4/9: Install PHP dependencies"
cd "${PROJECT_DIR}"
composer install --no-dev --optimize-autoloader --no-interaction

# 5. Setup .env
log "Step 5/9: Configure .env"
if [ ! -f .env ]; then
  cp .env.example .env 2>/dev/null || cat > .env <<ENV
APP_NAME="Karsa"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://${DOMAIN}
APP_LOCALE=id
APP_FALLBACK_LOCALE=id

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}

SESSION_DRIVER=database
SESSION_LIFETIME=120

CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=log

# OpenAI API (isi manual)
OPENAI_API_KEY=

# WA Gateway (isi manual)
WA_GATEWAY_PROVIDER=fonnte
WA_GATEWAY_TOKEN=
WA_GATEWAY_SENDER=

NOTIF_DEADLINE_DAYS="14,7,3"
ENV
  php artisan key:generate --force
fi
# Update DB password kalau berubah
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" .env
sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" .env

# 6. Migrate + seed
log "Step 6/9: Run migrations & seeders"
php artisan migrate --force
php artisan db:seed --force || true   # seed only if pending
php artisan generate:pwa-icons || true
php artisan storage:link

# 7. Permissions
log "Step 7/9: Fix permissions"
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 8. Cache config & routes
log "Step 8/9: Optimize"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 9. Nginx config
log "Step 9/9: Nginx config"
NGINX_CONF="/etc/nginx/sites-available/karsa"
cat > "${NGINX_CONF}" <<NGINX
server {
    listen 80;
    server_name ${DOMAIN};
    root ${PROJECT_DIR}/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;
    client_max_body_size 50M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:/var/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_read_timeout 120;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
ln -sf "${NGINX_CONF}" /etc/nginx/sites-enabled/karsa
nginx -t && systemctl reload nginx

# 10. Setup queue worker via systemd
log "Step 10/10: Setup queue worker (systemd)"
cat > /etc/systemd/system/karsa-queue.service <<UNIT
[Unit]
Description=Karsa Queue Worker
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
WorkingDirectory=${PROJECT_DIR}
ExecStart=/usr/bin/php artisan queue:work --tries=3 --timeout=120
StandardOutput=append:/var/log/karsa-queue.log
StandardError=append:/var/log/karsa-queue.log

[Install]
WantedBy=multi-user.target
UNIT
systemctl daemon-reload
systemctl enable --now karsa-queue.service

# 11. Cron for Laravel scheduler
log "Step 11/11: Setup cron scheduler"
CRON_LINE="* * * * * cd ${PROJECT_DIR} && /usr/bin/php artisan schedule:run >> /var/log/karsa-cron.log 2>&1"
(crontab -l 2>/dev/null | grep -v "karsa" ; echo "${CRON_LINE}") | crontab -

# 12. SSL via Let's Encrypt (optional, comment out if testing)
# log "Setup SSL via certbot"
# certbot --nginx -d ${DOMAIN} --non-interactive --agree-tos -m admin@${DOMAIN} --redirect

echo ""
echo -e "\033[1;32m✅ DEPLOY SELESAI\033[0m"
echo ""
echo "🔗 Akses: http://${DOMAIN}"
echo "👤 Login: admin@dputr.go.id / password"
echo ""
echo "📝 Next steps:"
echo "   1. Edit ${PROJECT_DIR}/.env → isi OPENAI_API_KEY & WA_GATEWAY_TOKEN"
echo "   2. Setup DNS A record: ${DOMAIN} → 72.60.196.21"
echo "   3. (Optional) Run: certbot --nginx -d ${DOMAIN} → enable HTTPS"
echo "   4. Run: cd ${PROJECT_DIR} && php artisan config:cache (after .env edit)"
echo ""
echo "🔧 Tools:"
echo "   - Restart queue: systemctl restart karsa-queue"
echo "   - Logs queue:    tail -f /var/log/karsa-queue.log"
echo "   - Logs Laravel:  tail -f ${PROJECT_DIR}/storage/logs/laravel-*.log"
echo "   - Update code:   cd ${PROJECT_DIR} && git pull && composer install --no-dev && php artisan migrate --force && php artisan config:cache && systemctl restart karsa-queue"
