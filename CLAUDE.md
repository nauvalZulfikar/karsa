# DPUTR Project Management - CLAUDE.md

## Stack
- Laravel 12 + Filament 3 (admin panel)
- MySQL (database, utf8mb4_unicode_ci)
- Spatie Laravel Permission (RBAC)
- Spatie Laravel Activitylog (audit trail)
- Queue: database driver
- Storage: local (dev) / S3 or MinIO (prod)

## Dev Setup
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```
Panel admin: http://localhost:8000/admin

## Database
- DB name: `dputr_pm`
- All tables: bigint unsigned `id`, `created_at`, `updated_at`
- Soft-deletable tables: add `deleted_at`
- No table prefix

## Roles (Phase 2)
- `super_admin` — full access
- `admin_bidang` — CRUD own bidang only
- `pptk` — input/update pekerjaan, upload dokumen, verify vendor reports
- `ppk` — approve status & payment, read-all, no delete
- `viewer` — read-only
- `vendor` — vendor portal: own projects + daily report submission

## Business Rules
- `nilai_kontrak` must not exceed `nilai_pagu`
- `tanggal_akhir` must be after `tanggal_mulai`
- `no_spk` unique per bidang per year
- Status only moves forward (except super_admin)
- Vendor daily report windows: masuk 06:00–09:00, pulang 15:00–18:00
- Photo stamps are applied server-side (tamper-proof)
- Activity log is read-only (no delete/edit by anyone)

## Storage Layout
```
storage/app/
├── dokumen/        # Project documents (KAK, contract, reports)
│   └── {year}/{bidang_id}/{pekerjaan_id}/{type}/{version}_{filename}
├── foto_laporan/   # Stamped vendor daily report photos
│   └── {year}/{pekerjaan_id}/{date}/{vendor_id}_{type}_{ts}.jpg
└── kickoff/        # Temp upload for kick-off document parsing
    └── temp/
```

## Artisan Commands (future phases)
- `pekerjaan:update-status` — nightly traffic light update (cron 00:01)
- `notifikasi:deadline` — WA deadline alerts (cron 07:00)
- `notifikasi:laporan-harian` — vendor report reminders (cron 06:30 & 15:00)

## Module Map
- Phase 2: `app/Models/User.php`, `app/Filament/Resources/UserResource.php`
- Phase 3: `app/Models/Master/` (Bidang, Perusahaan, TenagaAhli, etc.)
- Phase 4: `app/Models/Pekerjaan.php`, kick-off OCR parsing service
- Phase 5: `app/Console/Commands/UpdateStatusPekerjaan.php`
- Phase 6: `app/Models/PekerjaanPersonil.php`
- Phase 7: `app/Filament/VendorPanel/`, `app/Models/LaporanHarian.php`
- Phase 8: `app/Models/RencanaPengadaan.php`, `RealisasiPengadaan.php`
- Phase 9: `app/Models/Dokumen.php`
- Phase 10: `app/Models/TerminPembayaran.php`
- Phase 11: `app/Jobs/KirimNotifikasiDeadline.php`, `app/Services/WaGatewayService.php`
- Phase 12: `app/Filament/Widgets/`
- Phase 13: `app/Filament/Pages/AuditLog.php`
- Phase 14: `app/Services/LaporanService.php`
- Phase 15: `app/Services/ImportExcelService.php`
