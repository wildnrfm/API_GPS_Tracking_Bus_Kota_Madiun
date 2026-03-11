# API GPS Bus Tracking - Complete Documentation

> Sistem pelacakan GPS terpadu untuk manajemen transportasi sekolah dengan monitoring real-time, absensi otomatis, dan pelaporan komprehensif.

#### Daily Reports (Admin CRUD)

Admin memiliki endpoint CRUD untuk `daily-reports` yang menyimpan ringkasan harian per bus.

```
GET    /api/daily-reports           # list semua daily reports
GET    /api/daily-reports/{id}      # detail daily report
POST   /api/daily-reports           # buat laporan harian (body: tanggal, bus_id, stats...)
PUT    /api/daily-reports/{id}      # update laporan harian
DELETE /api/daily-reports/{id}      # hapus laporan harian
GET    /api/reports/generate        # generateAll - generate laporan harian untuk semua bus pada tanggal tertentu (admin)
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
Content-Type: application/json
```

**Deskripsi singkat:** `GET /api/reports/generate` akan memicu proses batch untuk menghasilkan `daily_reports` dari data attendance/GPS untuk rentang tanggal yang diberikan.

Di laporan driver PDF kolom-kolom yang sama akan muncul; selain itu status

## Table of Contents

1. [Tentang Project](#tentang-project)
2. [Teknologi Yang Digunakan](#teknologi-yang-digunakan)
3. [Instalasi & Setup](#instalasi--setup)
4. [Cara Menjalankan Project](#cara-menjalankan-project)
5. [Struktur Project](#struktur-project)
6. [Autentikasi & Otorisasi](#autentikasi--otorisasi)
7. [Panduan Alur Penggunaan](#panduan-alur-penggunaan)
8. [Dokumentasi API Endpoints](#dokumentasi-api-endpoints)
9. [Response Format & Error Handling](#response-format--error-handling)
10. [Catatan Penting & Tips](#catatan-penting--tips)

---

## Tentang Project

**API GPS Bus Tracking** adalah sistem REST API yang dirancang untuk manajemen transportasi sekolah modern dengan fitur-fitur:

- **Pelacakan GPS Real-time**: Monitor lokasi bus secara live
- **Absensi Otomatis**: Check-in/check-out berbasis barcode siswa
- **Manajemen Driver & Bus**: CRUD data sopir dan kendaraan
- **Routing & Halte**: Kelola nama rute bus (melalui endpoint bus) dan titik berhenti
- **Laporan Komprehensif**: Daily reports, attendance tracking, GPS history
- **Security Audit**: Activity logging untuk keamanan dan compliance
- **Offline Support**: Sync otomatis data saat koneksi dipulihkan

**Status**: Production Ready ✅  
**Total Endpoints**: 77+  
**Last Updated**: February 28, 2026

---

## Teknologi Yang Digunakan

| Komponen              | Teknologi             | Versi  |
| --------------------- | --------------------- | ------ |
| **Backend Framework** | Laravel               | 10.x   |
| **Database**          | MySQL                 | 8.0+   |
| **Authentication**    | Token-based API       | Custom |
| **Task Queue**        | Laravel Queue         | Jobs   |
| **Server**            | PHP Built-in / Apache | 8.1+   |
| **Language**          | PHP                   | 8.1+   |

### Dependencies Utama

```json
{
    "php": "^8.1",
    "laravel/framework": "^10.0",
    "laravel/tinker": "^2.8",
    "maatwebsite/excel": "^3.1",
    "dompdf/dompdf": "^2.0",
    "fruitcake/laravel-cors": "^2.0"
}
```

---

## Instalasi & Setup

### Prasyarat

- PHP 8.1 atau lebih tinggi
- Composer (untuk dependency management)
- MySQL 8.0 atau lebih tinggi
- Git (untuk clone repository)

#### Route-Haltes (Urutan Halte dalam Rute) - Admin

**Endpoints (Admin):**

```
POST /api/routes/{id}/haltes       # tambahkan halte ke rute (body: {halte_id, urutan})
PUT  /api/route-haltes/{id}        # update urutan/metadata route-halte
DELETE /api/route-haltes/{id}      # hapus entry route-halte
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
Content-Type: application/json
```

**Deskripsi singkat:** endpoint ini mengelola urutan halte pada sebuah rute. Gunakan `POST /api/routes/{id}/haltes` untuk menambah halte ke rute beserta urutannya.

#### Publik: Get Haltes By Route

**Endpoint (Public/Authenticated):**

```
GET /api/routes/{id}/haltes
```

**Response Sukses (200 OK):** list halte pada rute termasuk `urutan` dan koordinat.

#### 1. Clone Repository

```bash
git clone https://github.com/diskominfo-kotamadiun/api-gps-tracking-bus.git
cd api-gps-tracking-bus
```

#### 2. Install Dependencies dengan Composer

```bash
composer install
```

#### 3. Setup Environment File

```bash
# Copy file .env.example menjadi .env
cp .env.example .env

# Generate APP_KEY
php artisan key:generate
```

#### 4. Konfigurasi Database

Edit file `.env` dan atur koneksi database:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bus_tracking
DB_USERNAME=root
DB_PASSWORD=YOUR_PASSWORD
```

Kemudian buat database:

```bash
mysql -u root -p
CREATE DATABASE bus_tracking CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

#### 5. Jalankan Migrations

```bash
php artisan migrate
```

#### 6. Seed Database (Opsional - untuk data testing)

```bash
php artisan db:seed
```

Ini akan membuat data default:

- 1 Admin (admin@example.com / password123)
- 5 Drivers dengan profile lengkap
- 10 Students dengan data sekolah
- 3 Buses dengan rute

#### 7. Generate Documentation

```bash
php artisan route:list
```

Untuk melihat semua endpoint yang tersedia.

---

## Cara Menjalankan Project

### Development Server

```bash
# Jalankan development server (port 8000 default)
php artisan serve

# Atau dengan port custom
php artisan serve --host=0.0.0.0 --port=8000
```

Server akan berjalan di: `http://localhost:8000`

### Background Queue Worker (untuk jobs)

```bash
# Terminal baru, jalankan queue worker
php artisan queue:work

# Atau dengan timeout config
php artisan queue:work --timeout=0
```

### Akses API

```bash
# Test API tersedia di
http://localhost:8000/api/auth/login

# Semua endpoint dimulai dengan /api/
http://localhost:8000/api/{endpoint}
```

---

## Struktur Project

```
API_GPSTrackingBus/
├── app/
│   ├── Http/
│   │   ├── Controllers/API/       # 16 API Controllers
│   │   ├── Middleware/            # Auth & Token validation
│   │   └── Requests/              # Form validation rules
│   ├── Models/                    # 13 Eloquent Models
│   ├── Services/                  # 6 Business Logic Services
│   ├── Jobs/                      # Background jobs (queue)
│   ├── Constants/                 # AppMessages constant
│   └── Traits/                    # Reusable code (ResponseFormatter)
├── routes/
│   └── api.php                    # API routes definition
├── database/
│   ├── migrations/                # Database schema
│   └── seeders/                   # Database seeding
├── config/
│   ├── app.php                    # App configuration
│   └── database.php               # Database configuration
└── README.md                       # Documentation (this file)
```

### Models (Database Tables)

1. **User** - Pengguna (admin, driver, siswa)
2. **Driver** - Data sopir detail
3. **Student** - Data siswa detail
4. **Bus** - Data kendaraan
5. **Route** - Rute perjalanan
6. **Halte** - Titik pemberhentian
7. **BusDriver** - Assignment driver ke bus
8. **StudentBus** - Assignment siswa ke bus
9. **RouteHalte** - Urutan halte dalam rute
10. **GpsTrack** - Log pelacakan GPS
11. **Attendance** - Absensi siswa
12. **DailyReport** - Laporan harian per bus
13. **ActivityLog** - Audit trail aktivitas

---

## Autentikasi & Otorisasi

### Token-Based Authentication

Semua endpoint (kecuali login & register) memerlukan **Bearer Token** di header:

```
Authorization: Bearer YOUR_API_TOKEN
```

### Cara Mendapatkan Token

**1. Login terlebih dahulu:**

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password123"
  }'
```

**Response:**

```json
{
    "success": true,
    "message": "Login berhasil",
    "data": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@example.com",
        "role": "admin",
        "api_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "token_expires_at": "2026-03-01T08:30:45Z"
    }
}
```

**2. Gunakan token untuk request ke endpoint lain:**

```bash
curl -X GET http://localhost:8000/api/buses \
  -H "Authorization: Bearer YOUR_API_TOKEN"
```

### Token Expiration

- **Token berlaku selama**: 24 jam
- **Setelah expired**: Login ulang untuk mendapatkan token baru
- **Response jika token expired**: `401 Unauthorized`
- **Response jika akun suspended**: `403 Forbidden`

### Role-Based Authorization

Setiap user memiliki role yang menentukan akses:

| Role                | Akses                                                  |
| ------------------- | ------------------------------------------------------ |
| **admin**           | Semua endpoint (user, bus, driver, student management) |
| **driver**          | GPS tracking, personal bus data, attendance view       |
| **siswa** (student) | Barcode scan, bus tracking, attendance check-in/out    |

**Error jika authorization gagal:**

```json
{
    "success": false,
    "message": "Hanya admin yang dapat mengakses resource ini",
    "code": 403
}
```

---

## Panduan Alur Penggunaan

### Skenario 1: Admin Mengelola Data Bus & Driver

```
1. Login sebagai Admin
   POST /api/auth/login
   ↓
2. Ambil Token dari response
   ↓
3. Lihat daftar Bus
   GET /api/buses (dengan Authorization header)
   ↓
4. Buat Bus baru
   POST /api/buses
   ↓
5. Lihat daftar Driver
   GET /api/drivers
   ↓
6. Buat Driver baru
   POST /api/drivers
   ↓
7. Assign Driver ke Bus
   POST /api/buses/{busId}/drivers
   ↓
8. Assign Siswa ke Bus
   POST /api/buses/{busId}/students
   ↓
SELESAI: Bus siap operasional
```

**Contoh Code (cURL):**

```bash
# Step 1: Login
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password123"}' \
  | jq -r '.data.api_token')

# Step 3: Lihat Bus
curl -X GET http://localhost:8000/api/buses \
  -H "Authorization: Bearer $TOKEN"

# Step 4: Buat Bus baru
curl -X POST http://localhost:8000/api/buses \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "kode_bus": "BUSMADIUN001",
    "plat_nomor": "AD 5001 BA",
    "status": "operational"
  }'
```

### Skenario 2: Driver Mengirim GPS & Melihat Rute

```
1. Driver Login
   POST /api/auth/login (sebagai driver)
   ↓
2. Ambil Token
   ↓
3. Lihat Bus yang ditugaskan
   GET /api/driver/buses
   ↓
4. Kirim lokasi GPS setiap 3-10 detik
   POST /api/driver/gps
   {
     "latitude": -7.6315,
     "longitude": 111.4944,
     "speed": 45.5
   }
   ↓
5. Lihat laporan harian operasi
   GET /api/driver/report
   ↓
SELESAI: Proses operasional tercatat
```

### Skenario 3: Siswa Naik / Turun Bus (QR code)

```
1. Siswa Login
   POST /api/auth/login (sebagai siswa)
   ↓
2. Ambil Token
   ↓
3. Aktifkan GPS dan generate QR sebelum naik
   POST /api/student/barcode
   {
     "latitude": -7.6315,
     "longitude": 111.4944
   }
   — server mengembalikan `qr_id`, `qr_code_url`, dan data embed (student_id, bus_id, halte_id, tanggal, lat/lng)
   ↓
4. Driver memindai QR siswa
   POST /api/driver/attendance/scan
   {
     "qr_id": "<qr_id>",   // gunakan nama qr_id agar konsisten dengan database
     "student_id": 1,
     "bus_id": 3,
     "halte_id": 1,
     "tanggal": "2026-02-11",

     "latitude": -7.6315,    // lokasi scan GPS driver
     "longitude": 111.4944
   }
   ↓
5. Driver melakukan check-out ketika siswa turun
   PUT /api/driver/attendance/checkout
   {
     "qr_id": "<qr_id>",
     "waktu_turun": "2026-03-02 17:30:00",
     "latitude": -7.6400,
     "longitude": 111.5000
   }
   ↓
SELESAI: Absensi naik turun tercatat; QR expired segera setelah scan turun atau setelah jam 23:59.
```

---

## Dokumentasi API Endpoints

API dibagi menjadi beberapa modul berdasarkan fitur:

### A. AUTENTIKASI (Public Endpoints)

#### 1. Login

**Endpoint:**

```
POST /api/auth/login
```

**Headers:**

```
Content-Type: application/json
```

**Request Body:**

```json
{
    "email": "admin@example.com",
    "password": "password123"
}
```

**Parameter Penjelasan:**

- `email` (string, required): Email user terdaftar
- `password` (string, required): Password user

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "message": "Login berhasil",
    "data": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@example.com",
        "role": "admin",
        "api_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "token_expires_at": "2026-03-01T08:30:45Z"
    }
}
```

**Response Error - Invalid Credentials (401):**

```json
{
    "success": false,
    "message": "Email atau password salah"
}
```

**Response Error - Account Suspended (403):**

```json
{
    "success": false,
    "message": "Akun Anda telah disuspend"
}
```

**Contoh Request (cURL):**

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password123"
  }'
```

**Contoh Request (Fetch/JavaScript):**

```javascript
const response = await fetch("http://localhost:8000/api/auth/login", {
    method: "POST",
    headers: {
        "Content-Type": "application/json",
    },
    body: JSON.stringify({
        email: "admin@example.com",
        password: "password123",
    }),
});

const data = await response.json();

---

#### Admin: Process GPS Offline Queue

**Endpoint (Admin):**

```

POST /api/gps/process-offline-queue

```

**Headers:**

```

Authorization: Bearer ADMIN_TOKEN
Content-Type: application/json

````

**Request Body (contoh):**

```json
{ "queue_item_ids": [123, 124] }
````

**Deskripsi singkat:** endpoint ini dipakai oleh admin atau background worker untuk memproses item GPS yang disimpan sementara (offline queue) pada server/device dan memindahkannya ke storage utama.

const token = data.data.api_token;
localStorage.setItem("api_token", token);

```

**Alur Penggunaan:**

1. User masukkan email & password di form login
2. App kirim request POST ke endpoint ini
3. Server validasi kredensial di database
4. Jika valid: return token (simpan di localStorage/sessionStorage)
5. Gunakan token untuk semua request authenticated
6. Token berlaku 24 jam, setelah itu user harus login ulang

---

#### 2. Register

**Endpoint:**

```

POST /api/auth/register

```

**Headers:**

```

Content-Type: application/json

````

**Request Body (Siswa - Otomatis menjadi siswa, driver hanya via admin):**

```json
{
    "name": "Budi Santoso",
    "email": "budi.santoso@student.com",
    "password": "password123",
    "password_confirmation": "password123",
    "nis": "2024001",
    "sekolah": "SMA Negeri 1 Madiun",
    "alamat": "Jl. Diponegoro No. 5",
    "no_hp": "08123456789"
}
````

**Parameter Penjelasan:**

- `name` (string, required): Nama lengkap user
- `email` (string, required): Email unik untuk login
- `password` (string, required): Password minimal 8 karakter
- `password_confirmation` (string, required): Konfirmasi password (harus sama)
- `nis` (string, required): Nomor induk siswa (unik)
- `sekolah` (string, required): Nama sekolah
- `alamat` (string, required): Alamat lengkap
- `no_hp` (string, required): Nomor telepon

**Catatan:**

- `role` otomatis menjadi "siswa", tidak perlu dikirim
- Driver hanya bisa didaftarkan melalui admin endpoint

**Response Sukses (201 Created):**

```json
{
    "success": true,
    "message": "Registrasi berhasil",
    "data": {
        "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "user": {
            "id": 10,
            "name": "Budi Santoso",
            "email": "budi.santoso@student.com",
            "role": "siswa"
        },
        "student": {
            "id": 5,
            "user_id": 10,
            "nis": "2024001",
            "sekolah": "SMA Negeri 1 Madiun",
            "kelas": "Belum ditentukan",
            "alamat": "Jl. Diponegoro No. 5",
            "no_hp": "08123456789",
            "approval_status": "pending",
            "status": "active"
        }
    }
}
```

**Catatan Response:**

- `token`: Bearer token untuk login endpoints sudah terautentikasi
- `kelas`: Default "Belum ditentukan" (di-set oleh admin nanti)
- `approval_status`: Default "pending" (menunggu persetujuan admin)

**Response Error - Validation Failed (422):**

```json
{
    "message": "Validasi gagal",
    "errors": {
        "email": ["Email sudah terdaftar"],
        "nis": ["NIS sudah terdaftar"],
        "password": ["Konfirmasi password tidak cocok"]
    }
}
```

**Contoh Request (cURL):**

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Budi Santoso",
    "email": "budi.santoso@student.com",
    "password": "password123",
    "password_confirmation": "password123",
    "nis": "2024001",
    "sekolah": "SMA Negeri 1 Madiun",
    "alamat": "Jl. Diponegoro No. 5",
    "no_hp": "08123456789"
  }'
```

**Alur Penggunaan:**

1. Siswa akses form register di web/app
2. Masukkan data lengkap (name, email, password, nis, sekolah, dll)
3. App kirim request POST ke endpoint ini
4. Server validasi:
    - Email belum pernah terdaftar
    - NIS belum pernah terdaftar
    - Password minimal 8 karakter
    - Password confirmation cocok
5. Jika valid: buat user + profile siswa, return token
6. Siswa otomatis login setelah register
7. Akun status "pending" sampai admin approve

---

#### 3. Logout

**Endpoint:**

```
POST /api/auth/logout
```

**Headers:**

```
Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{}
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "message": "Logout berhasil"
}
```

**Contoh Request (cURL):**

```bash
curl -X POST http://localhost:8000/api/auth/logout \
  -H "Authorization: Bearer $TOKEN"
```

---

#### 4. Get Current User Info

**Endpoint:**

```
GET /api/auth/me
```

**Headers:**

```
Authorization: Bearer YOUR_API_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@example.com",
        "role": "admin",
        "last_login_at": "2026-02-28T08:30:45Z",
        "last_login_ip": "127.0.0.1",
        "api_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
        "token_expires_at": "2026-03-01T08:30:45Z"
    }
}
```

**Contoh Request (cURL):**

```bash
curl -X GET http://localhost:8000/api/auth/me \
  -H "Authorization: Bearer $TOKEN"
```

---

#### 5. Change Password

**Endpoint:**

```
POST /api/auth/change-password
```

**Headers:**

```
Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{
    "current_password": "oldpassword123",
    "new_password": "newpassword456",
    "new_password_confirmation": "newpassword456"
}
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "message": "Password berhasil diubah"
}
```

**Response Error (422):**

```json
{
    "message": "Validasi gagal",
    "errors": {
        "current_password": ["Password saat ini salah"]
    }
}
```

---

#### 6. Update Profile

**Endpoint:**

```
PUT /api/auth/profile
```

**Headers:**

```
Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{
    "name": "Admin Updated",
    "email": "admin.updated@example.com"
}
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "message": "Profil berhasil diperbarui",
    "data": {
        "id": 1,
        "name": "Admin Updated",
        "email": "admin.updated@example.com"
    }
}
```

---

### B. ADMIN MANAGEMENT (Admin Only)

#### 1. Get All Admins

**Endpoint:**

```
GET /api/admins
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Query Parameters:**

```
?page=1&per_page=15
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "message": "Data berhasil diambil",
    "data": [
        {
            "id": 1,
            "name": "Admin Utama",
            "email": "admin@example.com",
            "role": "admin",
            "created_at": "2026-01-01T08:00:00Z"
        }
    ],
    "pagination": {
        "total": 1,
        "per_page": 15,
        "current_page": 1,
        "last_page": 1
    }
}
```

---

#### 2. Create Admin

**Endpoint:**

```
POST /api/admins
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{
    "name": "Admin Baru",
    "email": "admin.baru@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

**Response Sukses (201 Created):**

```json
{
    "success": true,
    "message": "Data berhasil dibuat",
    "data": {
        "id": 2,
        "name": "Admin Baru",
        "email": "admin.baru@example.com",
        "role": "admin"
    }
}
```

---

#### 3. Get Admin (Admin Only)

**Endpoint:**

```
GET /api/admins/{id}
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "data": {
        "id": 2,
        "name": "Admin Baru",
        "email": "admin.baru@example.com",
        "role": "admin"
    }
}
```

#### 4. Update Admin (Admin Only)

**Endpoint:**

```
PUT /api/admins/{id}
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
Content-Type: application/json
```

**Request Body (contoh):**

```json
{
    "name": "Admin Updated",
    "email": "admin.updated@example.com"
}
```

**Response Sukses (200 OK):** Sama format response standar (success/message/data).

#### 5. Delete Admin (Admin Only)

**Endpoint:**

```
DELETE /api/admins/{id}
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "message": "Admin berhasil dihapus"
}
```

### C. USER MANAGEMENT (Admin Only)

#### 1. Get All Users

**Endpoint:**

```
GET /api/users
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "message": "Data berhasil diambil",
    "data": [
        {
            "id": 1,
            "name": "Admin User",
            "email": "admin@example.com",
            "role": "admin",
            "is_suspended": false,
            "last_login_at": "2026-02-28T08:30:45Z"
        }
    ]
}
```

---

#### 2. Get Single User (Admin Only)

**Endpoint:**

```
GET /api/users/{id}
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "data": {
        "id": 5,
        "name": "Budi Santoso",
        "email": "budi@example.com",
        "role": "siswa"
    }
}
```

---

#### 3. Delete User

**Endpoint:**

```
DELETE /api/users/{userId}
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "message": "Data berhasil dihapus"
}
```

---

### D. DRIVER MANAGEMENT (Admin + Driver)

#### 1. Get All Drivers (Admin Only)

**Endpoint:**

```
GET /api/drivers
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "message": "Data berhasil diambil",
    "data": [
        {
            "id": 1,
            "user": {
                "id": 2,
                "name": "Pak Budi",
                "email": "budi@example.com"
            },
            "nik": "1234567890123456",
            "no_hp": "081234567890",
            "alamat": "Jl. Merdeka No. 5",
            "created_at": "2026-01-15T08:00:00Z"
        }
    ]
}
```

---

#### 2. Create Driver (Admin Only)

**Endpoint:**

```
POST /api/drivers
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{
    "name": "Pak Budi Santoso",
    "email": "budi.santoso@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "nik": "1234567890123456",
    "no_hp": "081234567890",
    "alamat": "Jl. Merdeka No. 5"
}
```

**Response Sukses (201 Created):**

```json
{
    "success": true,
    "message": "Driver berhasil dibuat",
    "data": {
        "user": {
            "id": 2,
            "name": "Pak Budi Santoso",
            "email": "budi.santoso@example.com",
            "role": "driver"
        },
        "driver": {
            "id": 1,
            "nik": "1234567890123456",
            "no_hp": "081234567890",
            "alamat": "Jl. Merdeka No. 5"
        }
    }
}
```

---

#### 3. Get Driver (Admin Only)

**Endpoint:**

```
GET /api/drivers/{id}
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "user": { "id": 2, "name": "Pak Budi" },
        "nik": "1234567890123456",
        "no_hp": "081234567890",
        "alamat": "Jl. Merdeka No. 5"
    }
}
```

#### 4. Get Driver History (Admin Only)

**Endpoint:**

```
GET /api/drivers/{id}/history
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):** daftar penugasan/riwayat driver dengan tanggal mulai/selesai.

---

#### 3. Driver View Own Profile

**Endpoint:**

```
GET /api/driver/profile
```

**Headers:**

```
Authorization: Bearer DRIVER_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "user": {
            "name": "Pak Budi Santoso",
            "email": "budi.santoso@example.com"
        },
        "nik": "1234567890123456",
        "no_hp": "081234567890",
        "alamat": "Jl. Merdeka No. 5"
    }
}
```

---

#### 4. Driver View Assigned Buses

**Endpoint:**

```
GET /api/driver/buses
```

**Headers:**

```
Authorization: Bearer DRIVER_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "message": "Data berhasil diambil",
    "data": [
        {
            "bus": {
                "id": 1,
                "kode_bus": "BUSMADIUN001",
                "plat_nomor": "AD 5001 BA",
                "status": "operational"
            },
            "assignment": {
                "tanggal_mulai": "2026-01-01",
                "tanggal_selesai": null,
                "gps_status": "on",
                "last_gps_update": "2026-02-28T08:30:45Z"
            }
        }
    ]
}
```

---

#### 5. Driver View Bus Detail (dengan rute & siswa)

**Endpoint:**

```
GET /api/driver/buses/{busId}
```

**Headers:**

```
Authorization: Bearer DRIVER_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "message": "Data berhasil diambil",
    "data": {
        "bus": {
            "id": 1,
            "kode_bus": "BUSMADIUN001",
            "plat_nomor": "AD 5001 BA",
            "status": "operational"
        },
        "assignment": {
            "tanggal_mulai": "2026-01-01",
            "tanggal_selesai": null,
            "gps_status": "on"
        },
        "routes": [
            {
                "id": 1,
                "nama_rute": "Rute A: Rumah - Sekolah",
                "haltes": [
                    {
                        "id": 1,
                        "nama_halte": "Halte Pusat Kota",
                        "latitude": -7.6315,
                        "longitude": 111.4944
                    }
                ]
            }
        ],
        "students": [
            {
                "id": 1,
                "nis": "2024001",
                "name": "Budi Santoso",
                "sekolah": "SMA Negeri 1",
                "kelas": "XII IPA 1",
                "pickup_halte_id": 1
            }
        ]
    }
}
```

---

### E. GPS TRACKING

#### 1. Driver Send GPS Location

**Endpoint:**

```
POST /api/driver/gps
```

**Headers:**

```
Authorization: Bearer DRIVER_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{
    "latitude": -7.6315,
    "longitude": 111.4944,
    "speed": 45.5
}
```

**Parameter Penjelasan:**

- `latitude` (number, required): Koordinat lintang (-90 hingga 90)
- `longitude` (number, required): Koordinat bujur (-180 hingga 180)
- `speed` (number, optional): Kecepatan dalam km/h

**Response Sukses (201 Created):**

```json
{
    "success": true,
    "message": "GPS berhasil dicatat",
    "data": {
        "id": 1,
        "bus_id": 1,
        "latitude": -7.6315,
        "longitude": 111.4944,
        "speed": 45.5,
        "recorded_at": "2026-02-28T08:30:45Z"
    }
}
```

**Response Error - No Active Assignment:**

```json
{
    "success": false,
    "message": "Tidak ada penugasan bus aktif untuk driver ini"
}
```

**Response Offline Mode (202 Accepted):**

```json
{
    "success": true,
    "message": "Data antri (offline)",
    "code": 202,
    "data": {
        "offline_mode": true,
        "will_retry": true,
        "message": "Lokasi GPS antri untuk pengiriman nanti (mode offline)"
    }
}
```

**Contoh Request (cURL) - Every 10 seconds:**

```bash
# Script loop bash
while true; do
  curl -X POST http://localhost:8000/api/driver/gps \
    -H "Authorization: Bearer $DRIVER_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
      "latitude": -7.6315,
      "longitude": 111.4944,
      "speed": 45.5
    }'
  sleep 10
done
```

**Contoh Request (JavaScript) - Mobile App:**

```javascript
// Gunakan Geolocation API browser
navigator.geolocation.watchPosition(
    async (position) => {
        const { latitude, longitude } = position.coords;
        const speed = position.coords.speed || 0;

        const response = await fetch("http://localhost:8000/api/driver/gps", {
            method: "POST",
            headers: {
                Authorization: `Bearer ${token}`,
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                latitude,
                longitude,
                speed: speed * 3.6, // Convert m/s to km/h
            }),
        });

        const data = await response.json();
        console.log("GPS sent:", data);
    },
    (error) => console.error("GPS Error:", error),
    { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 },
);
```

**Alur Penggunaan:**

1. Driver login dan start operasional
2. Mobile app aktifkan GPS real-time
3. Kirim lokasi setiap 3-10 detik
4. Lokasi otomatis dikirim ke server
5. Admin bisa lihat lokasi real-time di dashboard

6. Jika offline: data antri dan sync saat online kembali

---

#### Driver GPS - Offline Support & Status Endpoints (Driver)

Selain `POST /api/driver/gps` driver juga memiliki beberapa endpoint untuk mendukung mode offline dan status:

```
GET  /api/driver/gps/offline-queue    # Lihat queue lokal yang menunggu sinkronisasi (driver)
GET  /api/driver/gps/pending-syncs    # Lihat data GPS yang pending sync ke server
POST /api/driver/gps/confirm-sync     # Konfirmasi bahwa device sudah menyelesaikan sync (body: {"sync_ids":[...]}
POST /api/driver/gps/log-status       # Kirim log/status GPS (device health)
GET  /api/driver/gps/status           # Dapatkan status GPS/assignment saat ini
```

**Headers:**

```
Authorization: Bearer DRIVER_TOKEN
Content-Type: application/json
```

**Deskripsi singkat:** endpoint di atas mendukung mekanisme retry/sinkronisasi aplikasi mobile ketika koneksi tidak stabil. `confirm-sync` biasanya dipanggil setelah app berhasil mengirim batch data offline ke server.

---

#### 2. Admin View Latest GPS All Buses

**Endpoint:**

```
GET /api/gps-tracks/latest?limit=20
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "data": [
        {
            "id": 100,
            "bus_id": 1,
            "latitude": -7.6315,
            "longitude": 111.4944,
            "speed": 45.5,
            "recorded_at": "2026-02-28T08:30:45Z"
        }
    ],
    "count": 1,
    "recorded_at": "2026-02-28T08:31:00Z"
}
```

---

#### 3. Admin View GPS Dashboard

**Endpoint:**

```
GET /api/gps-tracks/dashboard
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "count": 5,
    "data": [
        {
            "bus_id": 1,
            "bus_code": "BUSMADIUN001",
            "bus_plate": "AD 5001 BA",
            "gps_status": "on",
            "last_gps_update": "2026-02-28T08:30:45Z",
            "current_position": {
                "latitude": -7.6315,
                "longitude": 111.4944,
                "speed": 45.5,
                "recorded_at": "2026-02-28T08:30:45Z"
            },
            "driver": {
                "id": 1,
                "name": "Pak Budi",
                "phone": "081234567890"
            }
        }
    ],
    "timestamp": "2026-02-28T08:31:00Z"
}
```

**Contoh untuk mapping (Google Maps):**

```javascript
const mapElement = document.getElementById("map");
const map = new google.maps.Map(mapElement, {
    zoom: 12,
    center: { lat: -7.6315, lng: 111.4944 },
});

// Fetch GPS dashboard
const response = await fetch("http://localhost:8000/api/gps-tracks/dashboard", {
    headers: { Authorization: `Bearer ${token}` },
});

const data = await response.json();

// Add markers untuk setiap bus
data.data.forEach((bus) => {
    if (bus.current_position) {
        new google.maps.Marker({
            position: {
                lat: bus.current_position.latitude,
                lng: bus.current_position.longitude,
            },
            map: map,
            title: `${bus.bus_code} - ${bus.driver.name}`,
        });
    }
});
```

---

#### 4. Toggle GPS Status

Perubahan: Endpoint toggle GPS untuk driver berada di bawah prefix `driver` tanpa busId.

**Endpoint:**

```
PATCH /api/driver/gps
```

**Headers:**

```
Authorization: Bearer DRIVER_TOKEN
Content-Type: application/json
```

**Request Body (contoh):**

```json
{
    "gps_status": "on"
}
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "message": "Status GPS berhasil diperbarui",
    "data": {
        "gps_status": "on",
        "last_gps_update": "2026-02-28T08:30:45Z"
    }
}
```

---

### F. STUDENT MANAGEMENT & ATTENDANCE

#### Overview

Modul absensi diimplementasikan dengan alur di mana _siswa_ menghasilkan barcode/QR, namun _driver_ yang melakukan pemindaian (scan) untuk mencatat check-in dan check-out. Oleh karena itu endpoint absensi yang melakukan penulisan berada di bawah prefix `driver`.

#### Student-side endpoints

```
POST /api/student/barcode        # generate barcode/QR untuk siswa (authenticated student)
GET  /api/student/profile        # lihat profile siswa
GET  /api/student/bus            # lihat informasi bus yang ditugaskan
GET  /api/student/bus/tracking   # lihat posisi bus yang ditugaskan
```

Contoh: siswa memanggil `POST /api/student/barcode` untuk mendapatkan `qr_id` yang valid sementara.

---

#### Driver-side attendance (actual check-in / check-out)

**Endpoints:**

```
POST /api/driver/attendance/scan     # driver memindai QR/Barcode siswa (check-in)
PUT  /api/driver/attendance/checkout # driver mencatat check-out siswa (turun)
```

**Headers:**

```
Authorization: Bearer DRIVER_TOKEN
Content-Type: application/json
```

**Request body (scan/check-in) — contoh:**

```json
{
    "qr_id": "<qr_id>",
    "student_id": 5,
    "bus_id": 1,
    "halte_id": 2,
    "latitude": -7.6315,
    "longitude": 111.4944
}
```

**Request body (checkout) — contoh:**

```json
{
    "qr_id": "<qr_id>",
    "waktu_turun": "2026-03-02 17:30:00",
    "latitude": -7.64,
    "longitude": 111.5
}
```

**Responses:** sama seperti format response standar (success / error). Duplicate check-in/checkout ditangani oleh backend dan akan mengembalikan status error yang sesuai (409/422).

---

#### Alur Sistem QR Code & Attendance (Revisi Final)

Catatan awal: saya menyesuaikan path/method dengan `routes/api.php` yang ada di repo (`POST /api/student/barcode` untuk generate QR, bukan `GET /student/barcode`). Jika Anda ingin endpoint berbeda (mis. GET atau tanpa `/api`), ubah routes sesuai kebutuhan.

1. Generate QR Code oleh Siswa

- Endpoint (sesuai routes): `POST /api/student/barcode` (authenticated student)
- Tujuan: QR hanya dibuat oleh siswa bersangkutan dan berisi data berikut:
    - `id` : id unik karakter random (gunakan string, bukan integer)
    - `student_id`
    - `bus_id`
    - `halte_id`
    - `tanggal` : harus hari ini (YYYY-MM-DD)
    - `latitude_naik`, `longitude_naik`

Mekanisme Generate:

- Siswa wajib mengaktifkan GPS di device.
- Sistem memeriksa jarak antara lokasi siswa dan lokasi halte (berdasarkan `halte_id`).
- Jika jarak > 100 meter → GAGAL (response message: "Anda belum di sekitar halte naik").
- Jika jarak ≤ 100 meter → QR berhasil dibuat dan `qr_id` dikembalikan bersama payload.

Refresh QR Code:

- QR harus di-refresh setiap kali siswa mau naik bus (tujuan: memastikan tanggal & lokasi realtime).
- QR tidak bisa di-refresh jika siswa belum melakukan scan turun pada sesi sebelumnya.
- Jika siswa lupa scan turun, maka QR baru hanya bisa dibuat keesokan hari setelah 00:00.

2. Proses Scan Naik oleh Driver

- Endpoint (sesuai routes): `POST /api/driver/attendance/scan`
- Contoh Request Body yang disarankan:

```json
{
    "id": "<qr_id_random_string>",
    "student_id": 1,
    "bus_id": 3,
    "halte_id": 1,
    "tanggal": "2026-02-11",
    "waktu_naik": "2026-03-02 16:58:21",
    "latitude": -7.6315,
    "longitude": 111.4944
}
```

Validasi server saat scan naik:

- `tanggal` harus sama dengan hari ini.
- `bus_id` harus sesuai dengan bus yang ditugaskan untuk driver yang melakukan scan.
- Jarak antara lokasi driver (device) dan lokasi siswa yang ter-embed di QR ≤ 100 meter.
- QR harus valid dan belum expired (belum dipakai untuk checkout dan masih pada hari yang sama).
- Jika valid: buat atau perbarui record `attendance` (masukkan/waktu_naik, qr_id, student_id, bus_id, halte_id).

Catatan tambahan:

- Siswa boleh melakukan beberapa kali kenaikan dalam satu hari hanya jika mereka sudah melakukan scan turun pada sesi sebelumnya.
- Setiap scan oleh driver akan membuat atau memperbarui baris di tabel `attendance`.

3. Proses Scan Turun oleh Driver

- Endpoint (sesuai routes): `PUT /api/driver/attendance/checkout` (perhatikan: route di repo tidak menggunakan `:attendanceId` di path; gunakan `attendance_id` atau `qr_id` di body sesuai implementasi backend)
- Contoh Request Body:

```json
{
    "id": "<qr_id_random_string>",
    "attendance_id": 123,
    "waktu_turun": "2026-03-02 17:30:00",
    "latitude": -7.64,
    "longitude": 111.5
}
```

Mekanisme:

- Tidak perlu generate QR ulang untuk turun.
- Sistem akan mencari record `attendance` yang relevan (mis. berdasarkan `attendance_id` atau `qr_id`) dan mengisi `waktu_turun`, `lat_turun`, `long_turun`.

Aturan:

- Semua siswa wajib scan turun; lokasi turun dapat berbeda dari halte.

4. Expiry & Lifecycle QR

- QR akan expired segera setelah scan turun (sistem menandai QR/attendance selesai).
- Jika siswa tidak melakukan scan turun, QR akan otomatis expire pada hari yang sama pukul 23:59.

5. Handling siswa lupa scan turun (proses otomatis pada 00:00)

- Pada pergantian hari (00:00) sistem akan otomatis:
    - Menyimpan data attendance lama (tidak menghapus)
    - Update status attendance menjadi `NOT_CHECKED_OUT`
    - Set `waktu_turun = "-"`, `lat_turun = "-"`, `long_turun = "-"`
    - (Opsional) Buat record attendance baru untuk hari berikutnya jika diperlukan
    - Setelah itu siswa boleh generate QR baru untuk hari berikutnya

6. Dampak ke Laporan

- Laporan harian akan menampilkan siswa yang tidak melakukan scan turun dengan status `NOT_CHECKED_OUT`.
- Data tetap disimpan untuk keperluan audit dan monitoring.

Inti sistem:

- QR Code berfungsi untuk validasi identitas, lokasi, dan waktu.
- Driver bertanggung jawab melakukan scan naik dan turun.
- GPS wajib diaktifkan untuk mencegah kecurangan lokasi.

Jika Anda ingin saya, saya akan:

- menyesuaikan endpoint di README agar persis sama dengan route (`POST /api/student/barcode` sudah digunakan), atau
- kalau Anda ingin endpoint berbeda (mis. `GET /student/barcode` atau `PUT /driver/attendance/:attendanceId/checkout`) saya bisa update route definitions agar sinkron.

#### Admin-side student management

```
GET  /api/students
GET  /api/students/pending
GET  /api/students/{id}
POST /api/students
PUT  /api/students/{id}
POST /api/students/{id}/approve
POST /api/students/{id}/reject
DELETE /api/students/{id}
GET  /api/students/{id}/barcode   # admin can fetch barcode for a student
```

Semua endpoint admin di atas memerlukan `Authorization: Bearer ADMIN_TOKEN`.

---

#### Notes

- Siswa tidak memanggil endpoint `student/attendance/*` untuk menulis absensi; baca alur di atas.
- Pastikan aplikasi mobile/web menyimpan `qr_id` dan mengirimkannya bersama dengan metadata lokasi saat driver scan.

---

### G. BUS MANAGEMENT (Admin)

#### 1. Get All Buses

**Endpoint:**

```
GET /api/buses
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "message": "Data berhasil diambil",
    "data": [
        {
            "id": 1,
            "kode_bus": "BUSMADIUN001",
            "plat_nomor": "AD 5001 BA",
            "status": "operational",
            "routes": [
                {
                    "id": 1,
                    "nama_rute": "Rute A: Rumah - Sekolah"
                }
            ],
            "created_at": "2026-01-01T08:00:00Z"
        }
    ],
    "pagination": {
        "total": 5,
        "per_page": 15,
        "current_page": 1
    }
}
```

---

#### 2. Create Bus (Auto-create Route)

**Endpoint:**

```
POST /api/buses
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{
    "kode_bus": "BUSMADIUN002",
    "plat_nomor": "AD 5002 BA",
    "status": "operational",
    "nama_rute": "Rute B: Jalan Alternatif" // optional
}
```

**Response Sukses (201 Created):**

```json
{
    "success": true,
    "message": "Data berhasil dibuat",
    "data": {
        "id": 2,
        "kode_bus": "BUSMADIUN002",
        "plat_nomor": "AD 5002 BA",
        "status": "operational",
        "routes": [
            {
                "id": 2,
                "nama_rute": "Rute B: Jalan Alternatif"
            }
        ]
    }
}
```

---

#### 3. Assign Student to Bus

**Endpoint:**

```
POST /api/buses/{busId}/students
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{
    "student_id": 5,
    "halte_id": 2
}
```

**Response Sukses (201 Created):**

```json
{
    "success": true,
    "message": "Siswa berhasil ditugaskan ke bus",
    "data": {
        "id": 1,
        "student_id": 5,
        "bus_id": 1,
        "halte_id": 2,
        "created_at": "2026-02-28T08:00:00Z"
    }
}
```

**Response Error - Duplicate Assignment (409):**

```json
{
    "success": false,
    "message": "Siswa sudah terdaftar di bus lain"
}
```

---

#### 4. Assign Driver to Bus

**Endpoint:**

```
POST /api/buses/{busId}/drivers
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{
    "driver_id": 1,
    "tanggal_mulai": "2026-02-28",
    "tanggal_selesai": null
}
```

**Response Sukses (201 Created):**

```json
{
    "success": true,
    "message": "Driver berhasil ditugaskan",
    "data": {
        "id": 1,
        "bus_id": 1,
        "driver_id": 1,
        "tanggal_mulai": "2026-02-28",
        "tanggal_selesai": null,
        "gps_status": "off",
        "last_gps_update": null
    }
}
```

---

#### 5. Get Bus Students

**Endpoint:**

```
GET /api/buses/{busId}/students
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "student": {
                "id": 5,
                "nis": "2024001",
                "name": "Budi Santoso"
            },
            "halte_id": 2,
            "halte_naik": "Halte Pusat Kota"
        }
    ]
}
```

---

#### 6. Get Bus Drivers (History)

**Endpoint:**

```
GET /api/buses/{busId}/drivers
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "driver": {
                "id": 1,
                "name": "Pak Budi",
                "no_hp": "081234567890"
            },
            "tanggal_mulai": "2026-01-01",
            "tanggal_selesai": "2026-02-28",
            "status": "completed"
        },
        {
            "id": 2,
            "driver": {
                "id": 2,
                "name": "Pak Joko",
                "no_hp": "081234567891"
            },
            "tanggal_mulai": "2026-02-28",
            "tanggal_selesai": null,
            "status": "active"
        }
    ]
}
```

---

#### 7. Get Active Driver For a Bus (Admin)

**Endpoint:**

```
GET /api/buses/{busId}/driver
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "data": {
        "driver": { "id": 2, "name": "Pak Joko", "no_hp": "081234567891" },
        "tanggal_mulai": "2026-02-28",
        "gps_status": "on"
    }
}
```

#### 8. Bus GPS (per-bus) - Latest & History (Admin)

**Endpoints:**

```
GET /api/buses/{id}/gps/latest   # latest position for a bus
GET /api/buses/{id}/gps          # GPS history for a bus (query params: from,to,limit)
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses:** JSON list / object sesuai kebutuhan (see `gps-tracks` examples earlier).

---

---

#### 9. Bus-Driver Assignments (Admin)

**Endpoints:**

```
GET    /api/bus-driver          # list penugasan driver <-> bus
POST   /api/bus-driver          # buat penugasan (body: {bus_id, driver_id, tanggal_mulai, tanggal_selesai})
PUT    /api/bus-driver/{id}     # update penugasan
DELETE /api/bus-driver/{id}     # hapus penugasan
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
Content-Type: application/json
```

**Deskripsi singkat:** CRUD penugasan driver ke bus; berguna untuk riwayat dan penugasan aktif.

#### 10. Student-Bus Assignments (Admin)

**Endpoint:**

```
GET /api/student-bus   # list semua assignment siswa ke bus
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response:** pagination list assignment (student, bus, halte, tanggal dibuat).

---

### H. HALTE MANAGEMENT (Admin)

> **Note:** route data is now handled directly when creating or updating a bus via `nama_rute`. The separate `/api/routes` endpoints have been removed. When you supply a `nama_rute` in the bus payload, a route record will be created or updated automatically. If you send an empty value for `nama_rute` during update, the existing route (if any) will be deleted.

#### 5. Get All Haltes

**Endpoint:**

```
GET /api/haltes
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "nama_halte": "Halte Pusat Kota",
            "latitude": -7.6315,
            "longitude": 111.4944,
            "description": "Halte utama di pusat kota"
        }
    ]
}
```

---

#### 6. Create Halte

**Endpoint:**

```
POST /api/haltes
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{
    "nama_halte": "Halte Baru",
    "latitude": -7.64,
    "longitude": 111.5,
    "description": "Deskripsi halte"
}
```

**Response Sukses (201 Created):**

```json
{
    "success": true,
    "data": {
        "id": 3,
        "route_id": 1,
        "halte_id": 3,
        "urutan": 3
    }
}
```

---

---

### I. ATTENDANCE MANAGEMENT (Admin + Student)

#### 1. Get Attendance Today (by Bus)

**Endpoint:**

```
GET /api/buses/{busId}/attendance/today
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "student": {
                "id": 5,
                "nis": "2024001",
                "name": "Budi Santoso"
            },
            "waktu_naik": "08:30:45",
            "waktu_turun": "09:15:30",
            "halte_naik_id": 1
        }
    ]
}
```

---

#### 2. Get Student's Attendance Today

**Endpoint:**

```
GET /api/students/{studentId}/attendance/today
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "data": {
        "tanggal": "2026-02-28",
        "attendance": [
            {
                "id": 1,
                "bus_id": 1,
                "waktu_naik": "08:30:45",
                "waktu_turun": "09:15:30",
                "status": "completed"
            }
        ]
    }
}
```

---

### J. REPORTS & ANALYTICS (Admin + Driver)

#### 1. Get Admin Report (All Buses)

**Endpoint:**

```
GET /api/reports/admin?tanggal=YYYY-MM-DD
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Query Parameters:**

```
?tanggal=2026-02-28    # Format: YYYY-MM-DD
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "message": "Laporan berhasil dihasilkan",
    "data": {
        "tanggal": "2026-02-28",
        "total_buses": 5,
        "total_drivers": 5,
        "total_students_assigned": 50,
        "total_passengers": 48,
        "buses": [
            {
                "bus_code": "BUSMADIUN001",
                "bus_plate": "AB-1234-CD",
                "driver_name": "Pak Budi",
                "driver_phone": "081234567890",
                "total_penumpang": 10,
                "boarding_count": 10,
                "alighting_count": 9
            }
        ]
    }
}
```

Ringkasan keseluruhan mencakup jumlah bus, driver aktif, siswa yang ditugaskan,
dan jumlah penumpang tercatat. Data per-bus menampilkan kode, plat,
nama/HP driver serta statistik naik/turun.

Jika tidak ada driver _aktif_ pada tanggal laporan, sistem akan mencoba
menampilkan penugasan terbaru (misalnya jika kamu baru saja menambah entry
di `bus_driver` tapi waktunya belum berlaku). Jika benar‑benar tidak ada
penugasan sama sekali, kolom nama dan HP akan berisi `-` sebagai placeholder.

---

#### 2. Download Admin Report as PDF

**Note:** hasilnya adalah file PDF dengan tabel rapi. Di bagian atas
terdapat ringkasan totals, lalu tabel per-bus dengan kolom:
`Kode Bus`, `Plat`, `Driver`, `No HP`, `Penumpang`, `Naik`, `Turun`.

Format tanggal dapat dikirim lewat query string (GET) atau body JSON (POST).

**Endpoint:**

```
GET or POST /api/reports/admin/download-pdf?tanggal=YYYY-MM-DD
```

_POST body (JSON)_

```json
{ "tanggal": "YYYY-MM-DD" }
```

**Headers:**

The same report is also available as an Excel spreadsheet. When the
[maatwebsite/excel](https://github.com/Maatwebsite/Laravel-Excel) package is
installed the API generates a _true_ `.xlsx` workbook with styled headers.
If the package is **not** present (composer dependency missing) the endpoint
still works: it returns CSV data but names the file with a `.xlsx` extension
so Excel can open it (you may see a format‑mismatch warning).

> To enable full XLSX support run `composer require maatwebsite/excel`
> and provide a GitHub OAuth token if prompted.

**Endpoint:**

```
GET or POST /api/reports/admin/download-excel?tanggal=YYYY-MM-DD
```

_POST body (JSON)_

```json
{ "tanggal": "YYYY-MM-DD" }
```

```
Authorization: Bearer ADMIN_TOKEN
```

**Response:**

- File PDF biner (`Content-Type: application/pdf`)
- Nama file: `admin_report_{tanggal}.pdf`

---

#### 3. Get Driver Report (Per Bus)

Driver reports are much more detailed, suitable for operational use. The
JSON response includes bus/driver info, counts of students assigned/present/
absent, and a list of each student with attendance details.

When called by a user with role `driver`, the handler verifies that the
`bus_id` (if provided) belongs to the driver; if no bus is given the first
active assignment is used automatically.

**Endpoint:**

```
GET /api/reports/driver?bus_id=X&tanggal=YYYY-MM-DD
```

Driver users may also hit this same path; authorization only requires a valid
API token (the middleware no longer enforces the admin role). Admins continue
to be permitted as before.

You can also send the same parameters as **JSON body** via a `POST` request. Bus
ID is optional for drivers – the system will pick the active bus assignment for
the authenticated driver if `bus_id` is omitted.

Example POST payload:

```json
{
    "tanggal": "2026-03-03",
    "bus_id": 1 // optional for drivers
}
```

**Headers:**

```
Authorization: Bearer DRIVER_TOKEN (or ADMIN_TOKEN)
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "message": "Laporan berhasil dihasilkan",
    "data": {
        "tanggal": "2026-02-28",
        "bus_id": 1,
        "bus_code": "BUSMADIUN001",
        "bus_plate": "AB-1234-CD",
        "driver_name": "Pak Budi",
        "driver_phone": "081234567890",
        "total_assigned": 10,
        "present_count": 9,
        "absent_count": 1,
        "students": [
            {
                "student_id": 5,
                "student_name": "Siti",
                "student_phone": "081234567890",
                "status": "checked_in",
                "halte_naik": "Halte A",
                "waktu_naik": "07:15:00",
                "waktu_turun": "07:45:00",
                "durasi_perjalanan": "30m",
                "lat_naik": -7.55,
                "lng_naik": 111.53,
                "lat_turun": -7.56,
                "lng_turun": 111.54
            },
            {
                "student_id": 6,
                "student_name": "Budi",
                "status": "absent",
                "halte_naik": "Halte C",
                "waktu_naik": "-"
            }
        ]
    }
}
```

Di laporan driver PDF kolom-kolom yang sama akan muncul; selain itu status
`absent` memudahkan memantau siapa yang belum cek in.

---

#### 4. Download Driver Report as PDF

_Accessible by both admin and driver roles via the same path_.

The PDF mirrors the JSON structure but renders a clean table per student
with all fields listed above.

Supports both **GET** and **POST** (the latter accepts JSON payload, just like
`/api/reports/driver`). Driver users can omit `bus_id` when POSTing and the
active assignment will be detected automatically.

Example POST body:

```json
{
    "tanggal": "2026-03-03"
}
```

**Endpoint:**

```
GET or POST /api/reports/driver/download-pdf?bus_id=X&tanggal=YYYY-MM-DD
```

_POST body (JSON)_

```json
{ "bus_id": X, "tanggal": "YYYY-MM-DD" }
```

**Headers:**

```
Authorization: Bearer DRIVER_TOKEN (or ADMIN_TOKEN)
```

**Response:**

- PDF file (`report_driver_{bus}_{tanggal}.pdf`)

```json
{
    "total_duration": "1h 30m",
    "average_speed": "30.3 km/h",
    "attendance_details": [
        {
            "student_id": 5,
            "student_name": "Budi Santoso",
            "waktu_naik": "08:30:45",
            "waktu_turun": "09:15:30"
        }
    ]
}
```

---

#### 5. Download Driver Report as Excel (XLSX)

_Accessible by both admin and driver roles via the same path_.

By default the controller will attempt to use the
`maatwebsite/excel` package to produce a native `.xlsx` workbook with
coloured header rows. If the package has not been installed the route
falls back to emitting CSV data with an `.xlsx` filename – Excel will still
open the file but may warn about the mismatch.

> Install `maatwebsite/excel` via Composer for the full experience.

**Endpoint:**

```
GET or POST /api/reports/driver/download-excel?bus_id=X&tanggal=YYYY-MM-DD
```

_POST body (JSON)_

```json
{ "bus_id": X, "tanggal": "YYYY-MM-DD" }
```

**Headers:**

```
Authorization: Bearer DRIVER_TOKEN (or ADMIN_TOKEN)
```

**Response:**

- Excel file (`report_driver_{bus}_{tanggal}.xlsx`)

---

#### 4. Get Daily Report (by Bus)

**Endpoint:**

```
GET /api/driver/buses/{busId}/report?date=YYYY-MM-DD
```

**Headers:**

```
Authorization: Bearer DRIVER_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "data": {
        "bus": {
            "id": 1,
            "kode_bus": "BUSMADIUN001",
            "plat_nomor": "AD 5001 BA"
        },
        "report_date": "2026-02-28",
        "total_students_assigned": 10,
        "total_attendance": 9,
        "attendance_details": [
            {
                "student_id": 5,
                "student_name": "Budi Santoso",
                "student_nis": "2024001",
                "tanggal": "2026-02-28",
                "waktu_naik": "08:30:45",
                "waktu_turun": "09:15:30"
            }
        ]
    }
}
```

---

### K. ACTIVITY LOGGING & SECURITY (Admin)

#### 1. Security Dashboard

**Endpoint:**

```
GET /api/activity/dashboard
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "message": "Security dashboard retrieved successfully",
    "summary": {
        "recent_logins_24h": 25,
        "failed_logins_24h": 3,
        "blocked_logins_24h": 1,
        "suspended_accounts": 2
    },
    "top_active_users": [
        {
            "user_id": 1,
            "activity_count": 45,
            "user": {
                "id": 1,
                "name": "Admin",
                "email": "admin@example.com",
                "role": "admin"
            }
        }
    ],
    "recent_failed_logins": [
        {
            "id": 1,
            "action": "login_failed",
            "user_id": 5,
            "ip_address": "192.168.1.100",
            "description": "Login failed for user student@test.com",
            "created_at": "2026-02-28T08:30:00Z"
        }
    ],
    "activity_by_type": [
        {
            "action": "login",
            "count": 85
        },
        {
            "action": "attendance_check_in",
            "count": 234
        }
    ]
}
```

---

#### 2. Get Activity Logs

**Endpoint:**

```
GET /api/activity/logs?[filters]
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Query Parameters:**

```
?action=login                    # Filter by action
?status=failed                   # Filter by status (success/failed)
?user_id=5                       # Filter by user ID
?start_date=2026-02-20           # Date range (from)
?end_date=2026-02-28             # Date range (to)
?search=password                 # Text search
?page=1                          # Pagination
?per_page=50                     # Per page items
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "summary": {
        "total": 150,
        "actions": {
            "login": 85,
            "attendance_check_in": 50,
            "password_changed": 15
        }
    },
    "data": [
        {
            "id": 1,
            "action": "login",
            "status": "success",
            "user_id": 1,
            "user_name": "Admin",
            "ip_address": "127.0.0.1",
            "user_agent": "Mozilla/5.0...",
            "description": "User login: admin@example.com",
            "created_at": "2026-02-28T08:30:45Z"
        }
    ],
    "pagination": {
        "total": 150,
        "per_page": 50,
        "current_page": 1,
        "last_page": 3
    }
}
```

---

#### 3. Get User Activity

**Endpoint:**

```
GET /api/activity/users/{userId}
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "user": {
        "id": 1,
        "name": "Admin User",
        "email": "admin@example.com",
        "role": "admin",
        "last_login_at": "2026-02-28T08:30:45Z"
    },
    "summary": {
        "login": 25,
        "password_changed": 2,
        "profile_updated": 5
    },
    "recent_activity": [
        {
            "action": "login",
            "status": "success",
            "ip_address": "127.0.0.1",
            "created_at": "2026-02-28T08:30:45Z"
        }
    ]
}
```

---

#### 4. Export Activity Logs (CSV)

**Endpoint:**

```
GET /api/activity/export?[filters]
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
```

**Response:**

- File CSV biner (Content-Type: text/csv)
- Nama file: `activity_logs_export.csv`

**CSV Content:**

```
id,action,status,user_id,user_name,ip_address,description,created_at
1,login,success,1,Admin,127.0.0.1,User login,2026-02-28T08:30:45Z
2,attendance_check_in,success,5,Budi,127.0.0.1,Student check-in,2026-02-28T08:31:00Z
```

---

#### 5. Cleanup Old Logs

**Endpoint:**

```
DELETE /api/activity/cleanup
```

**Headers:**

```
Authorization: Bearer ADMIN_TOKEN
Content-Type: application/json
```

**Request Body:**

```json
{
    "days": 90
}
```

**Response Sukses (200 OK):**

```json
{
    "success": true,
    "message": "Old logs successfully deleted",
    "data": {
        "deleted_count": 1250,
        "kept_count": 500
    }
}
```

---

## Response Format & Error Handling

### Standard Response Format

Semua endpoint return JSON dengan struktur:

#### Success Response

```json
{
    "success": true,
    "message": "Deskripsi sukses",
    "data": {
        /* response data */
    },
    "code": 200
}
```

#### Error Response

```json
{
    "success": false,
    "message": "Deskripsi error",
    "errors": {
        /* validation errors jika ada */
    },
    "code": 400
}
```

### HTTP Status Codes

| Code | Meaning              | Use Case                                              |
| ---- | -------------------- | ----------------------------------------------------- |
| 200  | OK                   | Request sukses (GET, PUT, DELETE)                     |
| 201  | Created              | Resource berhasil dibuat (POST)                       |
| 202  | Accepted             | Request accepted, processing (offline mode)           |
| 400  | Bad Request          | Invalid input/format                                  |
| 401  | Unauthorized         | Token missing/invalid/expired                         |
| 403  | Forbidden            | User tidak memiliki akses (suspend/role tidak sesuai) |
| 404  | Not Found            | Resource tidak ditemukan                              |
| 409  | Conflict             | Duplicate data (contoh: siswa sudah di bus lain)      |
| 422  | Unprocessable Entity | Validation error                                      |
| 500  | Server Error         | Internal server error                                 |

### Validation Error Response

```json
{
    "message": "Validasi gagal",
    "errors": {
        "email": ["Email wajib diisi", "Format email tidak valid"],
        "password": ["Password minimal 8 karakter"]
    }
}
```

### Error Message Format

Semua error message dalam bahasa Indonesia dan standardized:

```json
{
    "success": false,
    "message": "Email atau password salah",
    "code": 401
}
```

---

## Catatan Penting & Tips

### Security

1. **Jangan simpan token langsung di code**
    - Simpan di localStorage/sessionStorage (browser)
    - Simpan di Secure Cookie dengan HttpOnly flag
    - Jangan hardcode di mobile app

2. **Token Expiration**
    - Token berlaku 24 jam
    - Implementasi refresh mechanism atau ask user to re-login
    - Implementasi token rotation untuk production

3. **Rate Limiting**
    - Login: 5 percobaan per menit per IP
    - Register: 3 percobaan per menit per IP
    - Jika exceed: response 429 Too Many Requests

4. **Data Sensitivity**
    - Jangan kirim password di response
    - Jangan log password atau personal data
    - All communications harus HTTPS di production

### Offline Mode (GPS Tracking)

- Jika GPS send request gagal (no internet), data otomatis antri di device
- Saat koneksi kembali, semua data di-sync otomatis
- Gunakan endpoint: `GET /api/driver/gps/offline-queue`
- Gunakan endpoint: `POST /api/driver/gps/confirm-sync` untuk confirm sync selesai

### Barcode Scanner

- Barcode NIS student format: `{NIS}` (contoh: `2024001`)
- QR Code dapat generate dari endpoint: `GET /api/students/{id}/barcode`
- Gunakan library seperti `html5-qrcode` atau `quagga` untuk scan

### Pagination

Semua list endpoint support pagination:

```
GET /api/buses?page=1&per_page=15
```

Response include pagination info:

```json
{
    "pagination": {
        "total": 100,
        "per_page": 15,
        "current_page": 1,
        "last_page": 7
    }
}
```

### Timestamp Format

Semua timestamp dalam format ISO 8601 UTC:

```
2026-02-28T08:30:45Z
```

Untuk konversi di JavaScript:

```javascript
const date = new Date(timestamp);
const localDate = date.toLocaleString("id-ID");
```

### Common Errors & Solutions

| Error                  | Penyebab                | Solusi                                     |
| ---------------------- | ----------------------- | ------------------------------------------ |
| "Token expired"        | Token sudah 24 jam      | Login ulang untuk dapat token baru         |
| "Unauthorized"         | Token tidak ada/invalid | Pastikan Authorization header benar        |
| "Akun disuspend"       | Admin suspend akun      | Contact admin untuk undo suspend           |
| "Tidak ada assignment" | Driver belum ditugaskan | Admin assign driver ke bus terlebih dahulu |
| "Duplikasi siswa"      | Siswa sudah di bus lain | Remove dari bus sebelumnya terlebih dahulu |
| "Validation failed"    | Ada input yang invalid  | Lihat response.errors untuk detail         |

### Performance Tips

1. **Batch Requests**
    - Jangan send ratusan request sekaligus
    - Gunakan pagination dengan per_page=50

2. **Caching**
    - Cache route & driver data di device (24 jam)
    - Refresh saat app startup

3. **GPS Interval**
    - Tidak perlu send setiap detik
    - 3-10 detik interval untuk tracking real-time yang responsif
    - Adjust berdasarkan kebutuhan dan battery

4. **Images/Files**
    - Jangan upload besar-besaran
    - Compress sebelum upload
    - Max file size: 5 MB

### Testing Endpoints

Gunakan tools:

- **Postman** - GUI client
- **Insomnia** - Alternative POST client
- **curl** - Terminal command
- **Thunder Client** - VS Code extension

Contoh curl script:

```bash
#!/bin/bash

# Login
RESPONSE=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password123"}')

TOKEN=$(echo $RESPONSE | jq -r '.data.api_token')

# Get Buses
curl -X GET http://localhost:8000/api/buses \
  -H "Authorization: Bearer $TOKEN" | jq '.'

# Create Bus
curl -X POST http://localhost:8000/api/buses \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "kode_bus": "BUSMADIUN003",
    "plat_nomor": "AD 5003 BA",
    "status": "operational"
  }' | jq '.'
```

---

## Support & Documentation

### Additional Resources

- **API Routes**: `php artisan route:list`
- **Database Schema**: Check `/database/migrations/`
- **Models**: Check `/app/Models/`
- **Controllers**: Check `/app/Http/Controllers/API/`

### Troubleshooting

**Jika API tidak bisa diakses:**

```bash
# Check if Laravel server running
curl http://localhost:8000

# Check database connection
php artisan tinker
>>> DB::connection()->getPdo();

# Check for errors in log
tail -f storage/logs/laravel.log
```

**Token tidak valid:**

```bash
# Verify token adalah JWT
echo $TOKEN | cut -d'.' -f1 | base64 -d | jq '.'

# Check token expiration di response
curl -H "Authorization: Bearer $TOKEN" \
  http://localhost:8000/api/auth/me | jq '.data.token_expires_at'
```

---

## License

This project is open-source software licensed under the MIT license.

---

**Last Updated**: March 5, 2026
**Version**: 1.0.0
**Status**: Production Ready ✅

Untuk pertanyaan atau kontribusi, hubungi tim development.
