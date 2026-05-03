# 🚀 Deploy Karsa ke VPS 72.60.196.21

Saya gak punya SSH access langsung ke VPS, jadi script ready dijalankan dari VPS.

## ⚡ Quick Deploy (1 perintah)

SSH ke VPS, lalu run:

```bash
ssh root@72.60.196.21

# Di VPS:
curl -O https://raw.githubusercontent.com/nauvalZulfikar/karsa/master/scripts/deploy.sh
chmod +x deploy.sh
./deploy.sh
```

Script akan otomatis:
1. Install PHP 8.2, MariaDB, Nginx, Composer, Node, Certbot
2. Clone repo dari GitHub ke `/root/projects/karsa`
3. Setup database `karsa` + user dengan password random
4. Configure `.env` (dengan password DB auto-generated)
5. Run `composer install --no-dev`
6. Run migrations + seeders (admin user, master data, hari libur)
7. Generate PWA icons + storage symlink
8. Set permissions www-data:www-data
9. Cache config, route, view
10. Setup Nginx site config (sub `karsa.aureonforge.com`)
11. Setup systemd service untuk queue worker
12. Setup cron untuk Laravel scheduler

**Estimasi waktu:** 5-10 menit (tergantung speed VPS).

## 🔧 Setelah Deploy

### Setup DNS
Di Cloudflare/DNS provider, tambah:
```
Type: A
Name: karsa (atau apapun)
Value: 72.60.196.21
TTL: Auto
```

### Isi API Keys
```bash
nano /root/projects/karsa/.env
```
Isi:
- `OPENAI_API_KEY=sk-...` (untuk AI chat & PDF parser)
- `WA_GATEWAY_TOKEN=...` (untuk notif WA, optional)

Lalu reload config:
```bash
cd /root/projects/karsa && php artisan config:cache
```

### Enable HTTPS (Optional tapi Recommended)
```bash
certbot --nginx -d karsa.aureonforge.com
```

## 🔐 Login Default

- URL: http://karsa.aureonforge.com (atau IP)
- Email: `admin@dputr.go.id`
- Password: `password`

**WAJIB ganti password setelah login pertama** lewat Pengaturan → Pengguna.

## 📦 Import Data Excel (Optional)

Setelah deploy, kalau mau langsung punya data 72 pekerjaan dari Excel:

```bash
# Upload Excel ke VPS
scp "1_PEKERJAAN 2026.xlsx" root@72.60.196.21:/root/projects/karsa/

# Lalu di VPS, jalankan import script (tidak include di repo, perlu copy manual)
# Atau pakai fitur Import Kontrak dari dashboard untuk PDF kontrak satu-satu
```

## 🔄 Update Code (Setelah Push Baru)

```bash
cd /root/projects/karsa
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
systemctl restart karsa-queue
```

## 🆘 Troubleshooting

| Masalah | Solusi |
|---|---|
| 502 Bad Gateway | `systemctl status php8.2-fpm` → restart kalau down |
| 500 Internal | Cek `tail -f /root/projects/karsa/storage/logs/laravel-*.log` |
| Permission denied | `chown -R www-data:www-data storage bootstrap/cache` |
| Queue stuck | `systemctl restart karsa-queue` + `tail -f /var/log/karsa-queue.log` |
| Cron gak jalan | `crontab -l` → check ada line karsa, `systemctl restart cron` |
| DB connection refused | `systemctl status mariadb` |
| Database password lupa | `cat /root/.karsa_db_pass` |

## 🌐 Multi-Project di VPS yang Sama

Kalau VPS sudah punya project lain (POS, Sibedas, dll) di `/root/projects/`, deploy ini aman karena:
- Subdomain berbeda (`karsa.aureonforge.com` vs `pos.aureonforge.com` vs `sibedas.aureonforge.com`)
- Database berbeda (`karsa` vs `pos` vs `sibedas`)
- PHP-FPM shared (8.2)
- Nginx config terpisah di `/etc/nginx/sites-enabled/karsa`
- Queue worker terpisah systemd service

## 📊 Resource Estimate

Untuk 1 instance (DPUTR data + low traffic):
- RAM: ~512 MB
- CPU: minimal (idle most of time, spike saat AI query)
- Disk: ~500 MB code + ~100 MB DB + storage tergantung dokumen
- AI cost: ~Rp 50K/bulan (50 query/hari)
