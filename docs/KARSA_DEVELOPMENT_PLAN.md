# 📐 Karsa Development Plan — Master Roadmap

**Konteks:** Transformasi dari DPUTR-PM (single-tenant Laravel/Filament) menjadi multi-tenant SaaS untuk 7+ industri.
**Tujuan:** Cover semua aspek dari foundation sampai market launch sampai scale.
**Format:** 18 Tier · 100+ Phase. Tier diurutkan by priority/dependency. Dalam tier, phase bisa parallel atau sequential.

---

## 🎯 5 Killer Differentiators

1. **AI Operator, Bukan Cuma AI Chat** — AI ngeksekusi action, bukan cuma drafting text
2. **Document Intelligence** — Upload PDF/Excel → auto-create projects (hemat ~30 min/proyek)
3. **WhatsApp-Native Communication** — Notif & input via WA (channel native Indonesia)
4. **Vendor/External Hub** — Portal vendor terpisah, mobile-first, GPS+foto auto
5. **Industry Templates Day-1 Ready** — 7+ template dengan domain real, bukan generic tag

---

## 🏭 7 Industry Templates

| Template | Field Native | Workflow |
|---|---|---|
| 🏗️ Construction & Infrastructure | SPK, SPMK, Termin, BAST, PHO/FHO, Pagu | Tender → Kontrak → Pelaksanaan → Termin → PHO → FHO |
| ⚡ Electrical & Utility | Work Order, Load Calculation, Inspection | Survey → Design → Installation → Test & Commission |
| 💧 Water Treatment | System Spec, Filter Type, Capacity (LPM) | Design → Procurement → Installation → Acceptance Test |
| 💻 IT / Software | Sprint, Story Points, Bug, PR/MR, Deploy | Backlog → In Progress → Review → Done → Deployed |
| 🤖 Industrial Automation | PLC Program, Sensor List, FAT/SAT | Design → Build → FAT → Install → SAT → Handover |
| 📦 Procurement & Supply | PO, Supplier, Lead Time, Receiving Note | RFQ → PO → Production → Shipment → Received → Verified |
| 📡 Electronics Manufacturing | BOM, Assembly Line, QC Pass, Lot Number | Order → BOM → Production → QC → Pack → Ship |

Plus 🌐 **Generic** template untuk catch-all.

---

## 🏗️ TIER 1 — MULTI-TENANCY FOUNDATION

| Phase | Nama | Goal | Effort |
|---|---|---|---|
| 1.1 | Tenant Model & Migration | `tenants` table (id, name, slug, plan, status, trial_ends_at, settings JSON) | 4h |
| 1.2 | Tenant ID Migration | Tambah `tenant_id` ke 30+ tabel + index + FK | 6h |
| 1.3 | Tenant Middleware | Resolve tenant dari subdomain (`{tenant}.karsa.app`) atau path | 4h |
| 1.4 | Global Scope Tenant | Auto-scope semua Eloquent query by current tenant | 4h |
| 1.5 | Auth Tenant-aware | Login redirect ke subdomain tenant user | 4h |
| 1.6 | Existing Data Migration | Backfill DPUTR data ke `tenant_id = 1` | 3h |
| 1.7 | Tenant Isolation Test | Bikin tenant ke-2 dummy, verify no data leak | 4h |
| 1.8 | Tenant CRUD (Internal) | Halaman admin Karsa (super-super-admin) | 4h |
| 1.9 | Tenant Settings JSON | Schema settings per tenant | 3h |
| 1.10 | Branding Dynamic | Filament panel brand name/color dari tenant settings | 3h |

**Tier 1 Total:** ~40 jam

---

## 🌱 TIER 2 — INDUSTRY TEMPLATE ENGINE

| Phase | Nama | Goal | Effort |
|---|---|---|---|
| 2.1 | Template Schema Design | `industry_templates`, `template_fields`, `template_workflows`, `template_ai_prompts` | 5h |
| 2.2 | Template Engine Service | Service class load template config, apply ke tenant | 6h |
| 2.3 | Generic Pekerjaan Refactor | Rename `pekerjaan` → `projects`, kolom domain jadi flexible custom_fields | 8h |
| 2.4 | Template: 🏗️ Construction | Existing schema migrated ke template format | 4h |
| 2.5 | Template: 💻 IT/Software | Sprint, Story Points, Bug Type, PR Link | 4h |
| 2.6 | Template: 📦 Procurement | PO Number, Supplier, Lead Time | 4h |
| 2.7 | Template: ⚡ Electrical | Work Order, Load Spec, Inspection Pass | 4h |
| 2.8 | Template: 💧 Water Treatment | System Spec, Filter Type, Capacity | 3h |
| 2.9 | Template: 🤖 Industrial Automation | PLC Program, Sensor Spec, FAT/SAT | 4h |
| 2.10 | Template: 📡 Electronics | BOM, Lot Number, QC Pass, Serial No. | 4h |
| 2.11 | Template: 🌐 Generic Custom | Empty template, user define semua sendiri | 2h |
| 2.12 | Template Switch UI | Tenant bisa ganti template | 3h |

**Tier 2 Total:** ~51 jam

---

## 🚪 TIER 3 — SIGNUP & ONBOARDING

| Phase | Nama | Goal | Effort |
|---|---|---|---|
| 3.1 | Public Signup Page | `karsa.app/signup` | 4h |
| 3.2 | Tenant Auto-provision | Submit signup → bikin tenant + admin user | 4h |
| 3.3 | Onboarding Wizard Step 1 | Pilih industri (7 cards visual) | 3h |
| 3.4 | Onboarding Wizard Step 2 | Setting branding | 3h |
| 3.5 | Onboarding Wizard Step 3 | Add team (invite via email) | 3h |
| 3.6 | Onboarding Wizard Step 4 | Import data (skip/Excel/PDF/sample) | 4h |
| 3.7 | Sample Data Seeder per Template | Bikin 5-10 dummy projek per industri | 6h |
| 3.8 | First-Time Tour | Driver.js highlight tombol penting | 4h |
| 3.9 | Welcome Email | Email otomatis dengan link login + tutorial video | 2h |
| 3.10 | Setup Completion Tracker | Progress bar di dashboard | 3h |

**Tier 3 Total:** ~36 jam

---

## 💰 TIER 4 — SUBSCRIPTION & BILLING

| Phase | Nama | Goal | Effort |
|---|---|---|---|
| 4.1 | Plan Tier Definition | Free, Starter, Pro, Enterprise + limits | 3h |
| 4.2 | Usage Tracker | Service track per tenant: jumlah proyek, user, AI query/bulan | 5h |
| 4.3 | Plan Limit Enforcement | Block create kalau over limit + CTA upgrade | 4h |
| 4.4 | Midtrans Integration | Snap + Recurring subscription | 8h |
| 4.5 | Billing Portal | Halaman lihat plan, history invoice, ubah payment method | 5h |
| 4.6 | Trial Logic | 30 hari trial Pro, countdown, email reminder | 4h |
| 4.7 | Trial → Paid Conversion | Otomasi: trial habis → demote ke Free | 3h |
| 4.8 | Invoice Generation | Generate PDF invoice dengan PPN | 4h |
| 4.9 | Failed Payment Handling | Retry logic 3x, suspend setelah 7 hari | 4h |
| 4.10 | Plan Upgrade/Downgrade | Self-serve dengan prorate | 5h |
| 4.11 | Annual Discount | -20% kalau bayar tahunan | 2h |
| 4.12 | Coupon/Promo Code | Sistem promo code | 4h |

**Tier 4 Total:** ~51 jam

---

## 🎨 TIER 5 — BRANDING & WHITE-LABEL

| Phase | Nama | Goal | Effort |
|---|---|---|---|
| 5.1 | Logo Upload + Preview | Auto-resize, validate dimensions | 3h |
| 5.2 | Color Theme Customization | Primary/secondary picker, apply ke Filament theme | 4h |
| 5.3 | Custom Domain Support | Tenant bisa pakai `app.dinaspu.id` (CNAME) | 6h |
| 5.4 | Email Branding | Email pakai logo + nama tenant | 3h |
| 5.5 | Login Page Branding | Show logo & nama tenant di login | 3h |
| 5.6 | Favicon Custom | Per tenant favicon | 2h |
| 5.7 | PWA Manifest Dynamic | manifest.json generate per tenant | 3h |
| 5.8 | Footer "Powered by Karsa" | Free/Starter ada, Pro/Enterprise bisa hilangkan | 2h |
| 5.9 | Full White-label (Enterprise) | Hilangkan semua referensi Karsa | 4h |

**Tier 5 Total:** ~30 jam

---

## 🤖 TIER 6 — AI LAYER (Per-Tenant Smart)

| Phase | Nama | Goal | Effort |
|---|---|---|---|
| 6.1 | Per-Tenant AI Config | API key sendiri (Pro+) atau pool Karsa (Starter) | 4h |
| 6.2 | Industry-Aware Prompts | System prompt berbeda per template | 5h |
| 6.3 | AI Tool Catalog per Template | Tools available beda per industri | 8h |
| 6.4 | AI Usage Tracking | Count token, cost calculation, monthly cap | 4h |
| 6.5 | AI Cost Optimization | Cache, fallback ke gpt-4o-mini, prompt compression | 5h |
| 6.6 | AI Document Parser per Template | Parser PDF prompt berbeda per industri | 6h |
| 6.7 | AI Voice Input | Whisper API → diktekan ke chat | 5h |
| 6.8 | AI Photo Analysis (Vision) | Foto laporan vendor → AI describe & extract | 6h |
| 6.9 | AI Insight Generator | Background job kirim insight | 8h |
| 6.10 | AI Agent Autonomous | Setup task: "tiap senin, kirim summary" | 6h |

**Tier 6 Total:** ~57 jam

---

## 🌐 TIER 7 — VENDOR/EXTERNAL HUB

| Phase | Nama | Goal | Effort |
|---|---|---|---|
| 7.1 | Vendor Multi-tenant Portal | `/vendor` jadi tenant-aware | 4h |
| 7.2 | Vendor Cross-tenant Account | 1 vendor multi-org | 5h |
| 7.3 | Vendor Invitation Flow | Tenant invite via email → vendor signup | 4h |
| 7.4 | Vendor Scoring System | Auto-score: on-time, quality, response time | 6h |
| 7.5 | Vendor Performance Dashboard | Ranking vendor, history, complaint | 5h |
| 7.6 | Vendor Self-update Profile | Edit profile, upload sertifikat | 3h |
| 7.7 | Vendor Marketplace (future) | Direktori vendor public | 8h |
| 7.8 | Vendor Communication Channel | Inbox vendor-tenant, threaded message | 5h |

**Tier 7 Total:** ~40 jam

---

## 📱 TIER 8 — WHATSAPP INTEGRATION

| Phase | Nama | Goal | Effort |
|---|---|---|---|
| 8.1 | Per-Tenant WA Config | Tenant input Fonnte/Wablas token sendiri | 3h |
| 8.2 | WA Template per Industry | Template pesan beda per industri | 4h |
| 8.3 | Outbound WA Notif (existing) | Refactor jadi tenant-aware | 3h |
| 8.4 | Weekly Digest per Industry | Format report beda per industri | 4h |
| 8.5 | Inbound WA Webhook | Receive WA dari vendor, route ke tenant | 5h |
| 8.6 | Vendor Submit Laporan via WA | Vendor kirim foto+caption ke bot | 8h |
| 8.7 | WA Reply for Actions | Admin reply "approve" → status berubah | 6h |
| 8.8 | WA Rate Limit & Queue | Antrian, retry logic | 4h |
| 8.9 | WA Cost per Tenant | Track jumlah pesan, billing kalau over | 3h |

**Tier 8 Total:** ~40 jam

---

## 🛡️ TIER 9 — KARSA ADMIN & OPERATIONS

| Phase | Nama | Goal | Effort |
|---|---|---|---|
| 9.1 | Karsa Super Admin Panel | URL `admin.karsa.app` untuk Karsa team | 4h |
| 9.2 | Tenant List & Search | Filter by plan/status/created | 3h |
| 9.3 | Tenant Detail & Override | Manual upgrade/downgrade plan | 3h |
| 9.4 | Impersonate Tenant Admin | Login sebagai tenant admin untuk debug | 5h |
| 9.5 | Per-Tenant Usage Analytics | Active users, AI queries, projects | 5h |
| 9.6 | Revenue Dashboard | MRR, ARR, churn, conversion rate | 5h |
| 9.7 | Support Ticket System | Tenant submit, Karsa reply | 6h |
| 9.8 | Email Communication | Bulk email ke tenant | 4h |
| 9.9 | System Health Monitor | Uptime, queue, error rate | 4h |
| 9.10 | Audit Log Karsa-side | Log semua action staff Karsa | 3h |

**Tier 9 Total:** ~42 jam

---

## 🧪 TIER 10 — QUALITY & SECURITY

| Phase | Nama | Goal | Effort |
|---|---|---|---|
| 10.1 | Test Suite Foundation | Pest setup, model + feature tests | 6h |
| 10.2 | Multi-tenant Isolation Tests | 2 tenant, semua endpoint, no leak | 8h |
| 10.3 | RBAC Test per Role | 6 role × tenant context | 6h |
| 10.4 | API Endpoint Tests | Smoke test semua route | 5h |
| 10.5 | Browser Tests (Playwright) | E2E flow critical | 10h |
| 10.6 | Load Test | k6: 50 tenant × 10 user concurrent | 6h |
| 10.7 | Security Audit Internal | SQL injection, XSS, CSRF, IDOR | 8h |
| 10.8 | Security Audit External | Hire pentester | budget |
| 10.9 | OWASP Top 10 Review | Checklist + dokumentasi mitigation | 5h |
| 10.10 | Encryption at Rest | DB + file storage encryption | 4h |
| 10.11 | Backup Automation | Daily DB dump per tenant | 5h |
| 10.12 | Disaster Recovery Plan | Runbook, RTO/RPO, restore drill | 4h |

**Tier 10 Total:** ~67 jam

---

## ⚡ TIER 11 — PERFORMANCE & SCALE

| Phase | Nama | Goal | Effort |
|---|---|---|---|
| 11.1 | DB Index Audit | Index FK, kolom search/sort | 4h |
| 11.2 | Eager Loading Audit | Fix N+1 dengan Telescope | 5h |
| 11.3 | Cache Strategy | Redis cache for master data (TTL 1 jam) | 4h |
| 11.4 | Per-Tenant Cache Namespace | Prefix cache key dengan tenant_id | 3h |
| 11.5 | Queue Worker Tuning | Horizon, multiple priority, supervisor | 5h |
| 11.6 | Image Optimization | Compress, thumbnail, lazy load | 3h |
| 11.7 | CDN for Assets | Cloudflare CDN | 3h |
| 11.8 | Search Engine | Meilisearch fulltext search | 6h |
| 11.9 | Real-time Updates | Laravel Reverb websocket | 8h |
| 11.10 | DB Sharding Plan | Strategy untuk >500 tenant | research |
| 11.11 | Auto-scaling Setup | Docker scale, load balancer | 6h |
| 11.12 | Health Check Endpoints | `/health` for monitoring | 2h |

**Tier 11 Total:** ~49 jam

---

## 📣 TIER 12 — MARKETING & GROWTH

| Phase | Nama | Goal | Effort |
|---|---|---|---|
| 12.1 | Karsa Landing Page | Hero, value prop, features, CTA | 8h |
| 12.2 | Pricing Page | 4 plan card, comparison table, FAQ | 5h |
| 12.3 | Industry Landing Pages | Per-industry (7 page) | 14h |
| 12.4 | Demo Video Generic | 90-second product overview | budget |
| 12.5 | Demo Video per Industry | 60-second per industri | budget |
| 12.6 | Customer Testimonial | Setelah pilot ada 3 customer | budget |
| 12.7 | Documentation Site | docs.karsa.app | 20h |
| 12.8 | Help Center / FAQ | help.karsa.app searchable | 10h |
| 12.9 | Blog (Content Marketing) | blog.karsa.app, 1 post/minggu | ongoing |
| 12.10 | SEO Optimization | Meta, sitemap, schema.org, perf 90+ | 5h |
| 12.11 | Social Media Setup | Twitter/X, LinkedIn, Instagram | 3h |
| 12.12 | Email Drip Campaign | Auto sequence trial day 1/7/14/21/28 | 5h |
| 12.13 | Paid Ads Setup | Google Ads + LinkedIn Ads | budget |
| 12.14 | Referral Program | Invite tenant lain → 1 bulan free | 5h |
| 12.15 | Affiliate Program | Konsultan dapet komisi | 5h |

**Tier 12 Total:** ~80 jam + budget

---

## 🏢 TIER 13 — ENTERPRISE FEATURES

| Phase | Nama | Goal | Effort |
|---|---|---|---|
| 13.1 | Multi-Org Hierarchy | Group perusahaan punya banyak subsidiary | 10h |
| 13.2 | Department/Division | Sub-organisasi dalam tenant | 8h |
| 13.3 | Custom Role Builder | Create role dengan permission granular | 8h |
| 13.4 | Approval Workflow Builder | Custom approval chain | 12h |
| 13.5 | Custom Field Builder | Tambah field tanpa coding | 10h |
| 13.6 | Report Builder Drag-Drop | User bikin report custom | 16h |
| 13.7 | Dashboard Widget Builder | Pilih & susun widget | 10h |
| 13.8 | SSO Integration | Google Workspace, Microsoft 365, Azure AD, SAML | 12h |
| 13.9 | 2FA Enforcement | Org-wide policy: TOTP/SMS | 4h |
| 13.10 | IP Whitelist | Restrict login dari IP tertentu | 3h |
| 13.11 | Session Policy | Auto-logout, max concurrent | 3h |
| 13.12 | Data Export Compliance | GDPR/PDP-style export | 4h |
| 13.13 | On-Premise Deployment Package | Docker compose + install script | 16h |
| 13.14 | License Server (Enterprise) | Validate license periodik | 8h |

**Tier 13 Total:** ~124 jam

---

## 🇮🇩 TIER 14 — INDONESIA-SPECIFIC INTEGRATIONS

| Phase | Nama | Goal | Effort |
|---|---|---|---|
| 14.1 | LPSE Integration | Pull data tender dari LPSE | 16h |
| 14.2 | SiKaP Integration | Sync vendor data dari SiKaP | 12h |
| 14.3 | e-Katalog Integration | Browse e-Katalog dari Karsa | 12h |
| 14.4 | NPWP Validation | Validate via DJP API | 4h |
| 14.5 | KTP/NIK Validation | Validate identitas personil | 4h |
| 14.6 | DJP e-Faktur | Generate faktur pajak | 8h |
| 14.7 | Indonesia Bank Transfer | Disbursement ke vendor via bank API | 12h |
| 14.8 | Hari Libur Auto-Sync | Sync libur nasional dari API | 3h |
| 14.9 | KBLI Code Database | Klasifikasi industri pakai KBLI | 4h |
| 14.10 | PPN Auto-Calculate | PPN 11% auto-include | 3h |

**Tier 14 Total:** ~78 jam

---

## 🔌 TIER 15 — INTEGRATION ECOSYSTEM

| Phase | Nama | Goal | Effort |
|---|---|---|---|
| 15.1 | Public REST API | API endpoint dengan API key auth | 12h |
| 15.2 | API Documentation | OpenAPI spec, Swagger UI | 6h |
| 15.3 | Webhook System | Subscribe ke event | 8h |
| 15.4 | Zapier Integration | Public app di Zapier marketplace | 16h |
| 15.5 | Make.com Integration | Public scenario template | 8h |
| 15.6 | Google Drive Sync | Auto-upload dokumen ke Drive | 6h |
| 15.7 | Google Calendar Sync | Milestone & deadline sync | 6h |
| 15.8 | Outlook/Microsoft 365 | Email + Calendar | 8h |
| 15.9 | Slack Integration | Notif ke Slack channel | 5h |
| 15.10 | Telegram Bot | Notif & update via Telegram | 6h |

**Tier 15 Total:** ~81 jam

---

## 🌍 TIER 16 — INTERNATIONALIZATION

| Phase | Nama | Goal | Effort |
|---|---|---|---|
| 16.1 | i18n Foundation | Laravel localization | 8h |
| 16.2 | English Translation | Translate semua UI ke EN | 16h |
| 16.3 | Malaysia Localization | MYR currency, Malay language | 12h |
| 16.4 | Multi-currency | USD, MYR, SGD, etc. | 8h |
| 16.5 | Multi-timezone | Auto-detect, display in user's TZ | 4h |
| 16.6 | Date Format Locale | dd/mm/yy vs mm/dd/yy | 3h |
| 16.7 | Localized Pricing | PPP-based per country | 4h |
| 16.8 | Localized Payment | Stripe + Razorpay + alternatives | 12h |

**Tier 16 Total:** ~67 jam

---

## 🚀 TIER 17 — ADVANCED AI & INNOVATION

| Phase | Nama | Goal | Effort |
|---|---|---|---|
| 17.1 | Voice Interface | Speech-to-text + TTS | 10h |
| 17.2 | Computer Vision OCR | PDF scan support via Tesseract | 8h |
| 17.3 | Predictive Analytics | ML model predict project delay | 16h |
| 17.4 | Anomaly Detection | Auto-detect spending/schedule anomaly | 12h |
| 17.5 | AI Risk Scoring | Per-project risk score 0-100 | 8h |
| 17.6 | Auto Status Update | AI summarize laporan harian → update progres | 8h |
| 17.7 | AI Project Estimator | Suggest budget, durasi, personil | 12h |
| 17.8 | Smart Recommendations | "Vendor terbaik untuk proyek ini" | 10h |
| 17.9 | Multi-modal Input | Foto + voice + text combined | 10h |
| 17.10 | AI Email Assistant | Draft email balasan ke vendor | 6h |

**Tier 17 Total:** ~100 jam

---

## 🤝 TIER 18 — COMMUNITY & ECOSYSTEM

| Phase | Nama | Goal | Effort |
|---|---|---|---|
| 18.1 | Template Marketplace | User submit template, share/jual | 16h |
| 18.2 | Plugin/Extension System | Third-party plugin | 20h |
| 18.3 | Partner Program | Konsultan implement = revenue share | 8h |
| 18.4 | Karsa Academy | Course tutorial bersertifikat | 24h |
| 18.5 | User Conference | Annual event | budget |
| 18.6 | Public Roadmap | Feature voting | 4h |
| 18.7 | Open Source Components | Beberapa lib jadi OSS | 12h |
| 18.8 | Karsa Forum/Discord | Community channel | 3h |
| 18.9 | Bug Bounty Program | HackerOne | budget |
| 18.10 | Customer Advisory Board | 5-7 power users | ongoing |

**Tier 18 Total:** ~87 jam + budget/ongoing

---

# 📊 RINGKASAN TIMELINE

## Sprint Mapping (1 sprint = 2 minggu = ~80 jam)

| Sprint | Tier yang Dieksekusi | MVP Status |
|---|---|---|
| Sprint 1 | Tier 1 (Multi-tenancy) | Foundation done |
| Sprint 2 | Tier 2 (Industry Templates: 3 first) | 3 industri ready |
| Sprint 3 | Tier 3 (Onboarding) + Tier 5 (Branding) | Self-serve signup ready |
| Sprint 4 | Tier 4 (Subscription/Billing) | Monetization ready |
| Sprint 5 | Tier 9 (Karsa Admin) + Tier 10 partial | Internal ops ready |
| Sprint 6 | Tier 12 (Marketing) | **🚀 PUBLIC LAUNCH** |
| Sprint 7-8 | Tier 6 (AI) + Tier 7 (Vendor Hub) | Differentiation polish |
| Sprint 9-10 | Tier 8 (WhatsApp full) + Tier 11 (Performance) | Scale ready |
| Sprint 11-13 | Tier 13 (Enterprise) | Enterprise-ready |
| Sprint 14-16 | Tier 14 + Tier 15 | Indonesia dominator |
| Sprint 17+ | Tier 17 (Advanced AI) | Stay ahead |
| Year 2 | Tier 16 (i18n) + Tier 18 (Ecosystem) | Regional expansion |

---

## 📈 Total Effort Estimate

| Tier | Total Jam | Sprint |
|---|---|---|
| 1 — Multi-tenancy | 40 | 0.5 |
| 2 — Industry Templates | 51 | 0.7 |
| 3 — Onboarding | 36 | 0.5 |
| 4 — Subscription | 51 | 0.7 |
| 5 — Branding | 30 | 0.4 |
| 6 — AI Layer | 57 | 0.7 |
| 7 — Vendor Hub | 40 | 0.5 |
| 8 — WhatsApp | 40 | 0.5 |
| 9 — Karsa Admin | 42 | 0.5 |
| 10 — Quality/Security | 67 | 0.8 |
| 11 — Performance | 49 | 0.6 |
| 12 — Marketing | 80 | 1 |
| 13 — Enterprise | 124 | 1.6 |
| 14 — Indonesia Integration | 78 | 1 |
| 15 — Integration Ecosystem | 81 | 1 |
| 16 — Internationalization | 67 | 0.8 |
| 17 — Advanced AI | 100 | 1.3 |
| 18 — Community | 87 | 1.1 |
| **TOTAL** | **~1120 jam** | **~14 sprint** |

= **~7 bulan** kerja solo full-time, atau **~3 bulan** kalau tim 2-3 orang.

---

## 🎯 MVP Definition (Public Launch ~3 bulan)

**Yang WAJIB di MVP:**
- ✅ Tier 1 (Multi-tenancy)
- ✅ Tier 2 (3 template: Construction + IT + Procurement)
- ✅ Tier 3 (Onboarding)
- ✅ Tier 4 (Subscription)
- ✅ Tier 5 (Branding basic)
- ✅ Tier 9 (Karsa admin internal)
- ✅ Tier 10.1-10.5 (Tests basic)
- ✅ Tier 12.1-12.3 (Landing + pricing)

**Yang DELAYED ke Post-MVP:**
- 4 template industri lainnya (Sprint 7-8)
- Vendor Hub advanced
- WhatsApp inbound
- Enterprise features
- Integration ecosystem

---

## ⚠️ Critical Path & Dependencies

```
Tier 1 → Tier 2 → Tier 3 → Tier 4 → MVP Launch
            ↓
         Tier 6, 7, 8 (parallel after Tier 2)
            ↓
         Tier 11, 13, 14, 15, 17 (post-launch)
```

---

## 💸 Budget Items (Selain Effort Coding)

| Item | Estimasi |
|---|---|
| Domain `karsa.app` / `karsa.id` | Rp 150K-1jt/tahun |
| VPS upgrade (4 GB RAM) | Rp 500K/bulan |
| Logo + brand identity (designer) | Rp 5-15jt one-time |
| Demo video production (8 video) | Rp 10-30jt |
| Pentest external | Rp 15-50jt |
| Google Ads launch budget | Rp 5-20jt/bulan |
| OpenAI API (50 tenant pool) | Rp 2-5jt/bulan |
| Midtrans setup fee | ~Rp 2jt |
| **Year 1 Marketing + Infra** | **~Rp 100-200jt** |

---

## 💰 Pricing Plan

| Plan | Harga/bulan | Target | Limitations |
|---|---|---|---|
| **Free** (Trial) | Rp 0 | Coba | 5 proyek, 3 user, 30 hari |
| **Starter** | Rp 499K | Konsultan kecil | 50 proyek, 10 user, AI 100 query/bulan |
| **Pro** | Rp 1.5jt | Kontraktor menengah | 500 proyek, 50 user, AI unlimited, WA notif |
| **Enterprise** | Rp 5jt+ | Dinas / Kementerian | Unlimited, dedicated support, SSO, on-prem |

---

## 🎤 Brand & Tagline

**Nama:** Karsa (Sanskrit: intention/will)

**Tagline:** "Karsa — Where Intention Becomes Action"

**Manifesto:**
> Daripada belajar tool baru, suruh aja tool-nya kerjain.
> Daripada input data manual, upload aja dokumen yang udah ada.
> Daripada kirim email yang gak dibuka, kirim WA yang langsung dibaca.
> Daripada vendor pakai akun guest yang clunky, kasih portal sendiri.
> Daripada ngeset semua dari nol, pilih template industri yang udah kebayang.
>
> = Karsa.

---

## 📝 Notes

- Plan ini draft awal, akan terus di-iterate berdasarkan customer feedback
- Estimasi effort konservatif, real bisa lebih cepat/lambat
- Path A+B Hybrid: vertical-first dengan multi-industry template
- Existing DPUTR-PM = baseline, akan jadi tenant pertama di Karsa platform
