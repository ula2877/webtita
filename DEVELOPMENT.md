# Development Guide: Ngrok Access

Panduan menjalankan aplikasi PDAM Monitoring agar dapat diakses dari internet menggunakan **ngrok** saat development.

---

## 📋 Prasyarat

- [Node.js](https://nodejs.org/) ≥ 18
- [PHP](https://www.php.net/) ≥ 8.1
- [Composer](https://getcomposer.org/)
- [MySQL](https://www.mysql.com/) / MariaDB
- [ngrok](https://ngrok.com/) (authenticated untuk custom subdomain/HTTPs)

---

## 🚀 Quick Start

### 1. Backend (Laravel)

```bash
cd backend

# Install dependencies (jika belum)
composer install

# Setup environment
cp .env.example .env
# Edit .env: DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_KEY

php artisan key:generate
php artisan migrate
php artisan db:seed  # jika ada seeder

# Jalankan server pada 0.0.0.0 agar bisa diakses dari network/ngrok
php artisan serve --host=0.0.0.0 --port=8000
```

> Server berjalan di `http://0.0.0.0:8000` (akses lokal: `http://localhost:8000`)

---

### 2. Frontend (React + Vite)

```bash
cd frontend

# Install dependencies (jika belum)
npm install

# Setup environment
cp .env.example .env
# Edit .env jika perlu (lihat bagian Environment Variables)

# Jalankan dev server pada 0.0.0.0
npm run dev -- --host 0.0.0.0
```

> Dev server berjalan di `http://0.0.0.0:5173` (akses lokal: `http://localhost:5173`)

---

### 3. Ngrok (Expose Frontend)

```bash
# Terminal terpisah
ngrok http 5173
```

Output contoh:
```
Forwarding  https://abc123.ngrok-free.app -> http://localhost:5173
```

**Buka di HP/komputer lain:** `https://abc123.ngrok-free.app`

---

## 🔧 Konfigurasi Environment Variables

### Frontend (`frontend/.env`)

| Variable | Local Development | Ngrok Backend |
|----------|-------------------|---------------|
| `VITE_API_URL` | `/api` (gunakan Vite proxy) | `https://backend-xxxx.ngrok-free.app/api` |

**Default (local + Vite proxy):**
```env
VITE_API_URL=/api
```
- Request `/api/*` → Vite proxy → `http://localhost:8000`
- Cocok untuk: frontend via ngrok, backend lokal

**Jika backend juga di-ngrok:**
```env
VITE_API_URL=https://backend-xxxx.ngrok-free.app/api
```
- Restart Vite setelah mengubah `.env`

---

### Backend (`backend/.env`)

```env
# App URL (opsional untuk development)
APP_URL=http://localhost:8000

# Tambahkan domain ngrok frontend ke Sanctum stateful domains
# Pisahkan dengan koma, tanpa spasi
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,::1,frontend-xxxx.ngrok-free.app
```

**Penting:** Jika menggunakan **bearer token (Sanctum)**, `SANCTUM_STATEFUL_DOMAINS` tidak wajib karena autentikasi menggunakan token di header. Namun disarankan ditambahkan untuk CSRF protection jika diperlukan.

---

## 🌐 Skema Akses Ngrok

### Opsi A: Hanya Frontend di-ngrok (Recommended untuk Development)

```
┌─────────────────────────────────────────────────────────┐
│                    INTERNET / HP                        │
│                     https://abc.ngrok-free.app          │
└──────────────────────────┬──────────────────────────────┘
                           │ HTTPS
                           ▼
┌─────────────────────────────────────────────────────────┐
│                    NGROK TUNNEL                         │
└──────────────────────────┬──────────────────────────────┘
                           │ HTTP
                           ▼
┌─────────────────────────────────────────────────────────┐
│  FRONTEND (Vite) :5173                                  │
│  - Serves React app                                     │
│  - Proxy /api/* → http://localhost:8000                 │
└──────────────────────────┬──────────────────────────────┘
                           │ HTTP (localhost)
                           ▼
┌─────────────────────────────────────────────────────────┐
│  BACKEND (Laravel) :8000                                │
│  - API endpoints                                        │
│  - Database                                             │
└─────────────────────────────────────────────────────────┐
```

**Kelebihan:** Backend tidak perlu di-ngrok, CORS sederhana, token auth tetap jalan.

**Cara jalan:**
1. `php artisan serve --host=0.0.0.0 --port=8000`
2. `npm run dev -- --host 0.0.0.0`
3. `ngrok http 5173`
4. Buka `https://xxx.ngrok-free.app` dari HP

---

### Opsi B: Frontend + Backend Keduanya di-ngrok

```
INTERNET/HP                          INTERNET/HP
   │                                    │
   ▼                                    ▼
https://frontend.ngrok          https://backend.ngrok
   │                                    │
   └──────────────┬─────────────────────┘
                  │ API calls
                  ▼
         ┌────────────────┐
         │ Vite (no proxy)│
         └────────────────┘
```

**Cara jalan:**
1. `php artisan serve --host=0.0.0.0 --port=8000`
2. `ngrok http 8000` → dapatkan `https://backend-xxxx.ngrok-free.app`
3. Update `frontend/.env`: `VITE_API_URL=https://backend-xxxx.ngrok-free.app/api`
4. Restart Vite: `npm run dev -- --host 0.0.0.0`
5. `ngrok http 5173` → dapatkan `https://frontend-xxxx.ngrok-free.app`
6. Update `backend/.env`: tambahkan `SANCTUM_STATEFUL_DOMAINS=...,frontend-xxxx.ngrok-free.app`
6. Buka `https://frontend-xxxx.ngrok-free.app` dari HP

---

## ✅ Checklist Verifikasi

Setelah setup, test dari **HP/komputer lain** via URL ngrok:

- [ ] Halaman login muncul
- [ ] Login nomor HP berhasil (admin & petugas)
- [ ] Dashboard menampilkan data KPI
- [ ] Data tunggakan / tugas penagihan muncul
- [ ] Detail data bisa dibuka
- [ ] Upload foto bukti kunjungan bekerja
- [ ] Upload Excel import bekerja
- [ ] Profile (ubah nama/password) bekerja
- [ ] Logout bekerja
- [ ] Tidak ada error **CORS** di console
- [ ] Tidak ada error **Mixed Content** (HTTPS frontend → HTTP API)
- [ ] Tidak ada request ke `localhost` / `127.0.0.1` di Network tab

---

## 🐛 Troubleshooting

### "Blocked request. This host is not allowed." (Vite)
- Sudah ditangani: `allowedHosts: true` di `vite.config.ts`
- Atau tambahkan host spesifik: `allowedHosts: ['xxx.ngrok-free.app']`

### CORS Error
- Backend `config/cors.php`: `'allowed_origins' => ['*']` sudah benar
- Jika pakai credentials: set `'supports_credentials' => true` dan pastikan `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`, `SANCTUM_STATEFUL_DOMAINS` benar

### Mixed Content (HTTPS → HTTP)
- **Opsi A:** Gunakan Vite proxy (`VITE_API_URL=/api`) → API lewat HTTP lokal, tidak ada mixed content
- **Opsi B:** Backend juga di-ngrok HTTPS → `VITE_API_URL=https://backend.ngrok-free.app/api`

### Login Gagal / Token Tidak Tersimpan
- Cek `localStorage` di browser → key `tita_token` harus ada setelah login
- Cek Network tab → request `/login` return `token` dan `user`
- Pastikan backend `APP_URL` benar (untuk generate URL email, dll)

### Upload File Gagal
- Cek `php.ini`: `upload_max_filesize`, `post_max_size`
- Cek Laravel: `config/filesystems.php` disk `public`, `storage:link`
- Cek CORS untuk multipart/form-data

### Session/Cookie Tidak Jalan (jika pakai cookie auth)
- `SESSION_SECURE_COOKIE=true` (wajib untuk HTTPS ngrok)
- `SESSION_DOMAIN=.ngrok-free.app` (shared domain)
- `SESSION_SAME_SITE=none` (cross-site)
- `SANCTUM_STATEFUL_DOMAINS` include ngrok domain

---

## 📝 Catatan Penting

1. **URL ngrok gratis berubah** setiap restart. Update `.env` & `SANCTUM_STATEFUL_DOMAINS` setiap kali.
2. **Jangan commit** `.env` dengan URL ngrok asli.
3. **Vite proxy** hanya jalan di development (`npm run dev`). Production build tidak pakai proxy.
4. **Bearer token** disimpan di `localStorage` → tidak bergantung pada cookie/domain.
5. **Database** tetap lokal MySQL, tidak terpengaruh ngrok.

---

## 🔄 Workflow Ringkas

```bash
# Terminal 1: Backend
cd backend && php artisan serve --host=0.0.0.0 --port=8000

# Terminal 2: Frontend
cd frontend && npm run dev -- --host 0.0.0.0

# Terminal 3: Ngrok Frontend
ngrok http 5173

# Buka https://xxx.ngrok-free.app dari HP
```