# DPUTR Project Management

Laravel 12 + Filament 3 project management system for DPUTR (government procurement authority). Multi-phase platform for contract management, vendor coordination, milestone tracking, and compliance reporting.

## Features

- **Project Management:** Contract lifecycle from KAK (technical requirements) to SPK (work order), with AI-powered document parsing
- **Smart Milestone Generation:** Auto-extract deliverables from KAK + schedule from SPK; merge into rich milestones
- **Vendor-Admin Confirmation Workflow:** Vendor submits milestone completion; admin confirms or rejects with feedback
- **Role-Based Access (RBAC):** super_admin, admin_bidang, pptk, ppk, vendor, viewer with granular permissions
- **Audit Trail:** Activity logging (read-only) for all contract changes
- **Daily Report Submission:** Vendor portal for stamped photo reports with tamper-proof timestamps
- **Status Traffic Light:** Automated daily status updates and deadline notifications via WhatsApp

## Requirements

- PHP 8.2+
- MySQL 8.0+
- Node.js 18+ (for Vite)
- Composer

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
php artisan serve
```

Admin panel: http://localhost:8000/admin (default: admin@dputr.go.id / password123)

## Configuration

See `.env.example` for all env vars. Key settings:

- `DB_DATABASE=dputr_pm` — MySQL database name
- `FILESYSTEM_DISK=local` — Dev uses local; prod uses MinIO/S3
- `WA_GATEWAY_PROVIDER=fonnte` — WhatsApp notifications
- `NOTIF_DEADLINE_DAYS="14,7,3"` — Trigger deadline alerts

## Architecture

**Database:** All tables use bigint unsigned `id`, `created_at`, `updated_at`. Soft-delete tables have `deleted_at`.

**Storage Layout:**
```
storage/app/
├── dokumen/        # KAK, contract, reports → {year}/{bidang_id}/{pekerjaan_id}/{type}/{version}_{filename}
├── foto_laporan/   # Stamped vendor daily report photos → {year}/{pekerjaan_id}/{date}/{vendor_id}_{type}_{ts}.jpg
└── kickoff/        # Temp upload for document parsing
```

**Key Models:**
- `Pekerjaan` — Project/contract record
- `MilestonePekerjaan` — Deliverables with statuses: belum_mulai, sedang_berjalan, selesai, terlambat, diajukan_vendor, dikonfirmasi, ditolak
- `LaporanHarian` — Daily vendor submissions (masuk 06:00–09:00, pulang 15:00–18:00 windows)
- `TerminPembayaran` — Payment schedules tied to milestones

## Development

### Phases (15 total, Phase 1–2 complete)

- **Phase 2:** User management + RBAC roles
- **Phase 3:** Master data (Bidang, Perusahaan, TenagaAhli)
- **Phase 4:** Pekerjaan + smart milestone generation + vendor-admin workflow
- **Phase 5–15:** Reports, procurement, payment, audit, imports

### Testing

```bash
php artisan test
php artisan test:feature
```

### Code Style

Uses Laravel Pint (PSR-12). Auto-format:
```bash
composer lint:fix
```

## Deployment

See `DEPLOY.md` for production deployment to 72.60.196.21 via PM2 + NVM.

## License

Government of Indonesia — Confidential.
