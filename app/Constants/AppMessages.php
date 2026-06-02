<?php

namespace App\Constants;

class AppMessages
{
    // ==================== SUCCESS ====================

    const SUCCESS_LOGIN              = 'Login berhasil';
    const SUCCESS_LOGOUT             = 'Logout berhasil';
    const SUCCESS_REGISTER           = 'Registrasi berhasil';
    const SUCCESS_DATA_RETRIEVED     = 'Data berhasil diambil';
    const SUCCESS_CREATED            = 'Data berhasil dibuat';
    const SUCCESS_UPDATED            = 'Data berhasil diperbarui';
    const SUCCESS_DELETED            = 'Data berhasil dihapus';
    const SUCCESS_PASSWORD_CHANGED   = 'Password berhasil diubah';
    const SUCCESS_PROFILE_UPDATED    = 'Profil berhasil diperbarui';
    const SUCCESS_DRIVER_CREATED     = 'Driver berhasil dibuat';
    const SUCCESS_DRIVER_UPDATED     = 'Driver berhasil diperbarui';
    const SUCCESS_DRIVER_DELETED     = 'Driver berhasil dihapus';
    const SUCCESS_GPS_STATUS_UPDATED = 'Status GPS berhasil diperbarui';
    const SUCCESS_RETRIEVED          = 'Data berhasil diambil';

    // ==================== VALIDASI ====================

    const ERROR_VALIDATION        = 'Validasi gagal';
    const ERROR_EMAIL_REQUIRED    = 'Email wajib diisi';
    const ERROR_EMAIL_INVALID     = 'Format email tidak valid';
    const ERROR_EMAIL_TAKEN       = 'Email sudah terdaftar';
    const ERROR_PASSWORD_REQUIRED = 'Password wajib diisi';
    const ERROR_PASSWORD_WEAK     = 'Password minimal 8 karakter';
    const ERROR_PASSWORD_MISMATCH = 'Konfirmasi password tidak cocok';
    const ERROR_NAME_REQUIRED     = 'Nama wajib diisi';
    const ERROR_NAME_TOO_LONG     = 'Nama maksimal 255 karakter';

    // ==================== AUTENTIKASI & OTORISASI ====================

    const ERROR_INVALID_CREDENTIALS = 'Email atau password salah';
    const ERROR_UNAUTHORIZED        = 'Tidak terautentikasi';
    const ERROR_FORBIDDEN           = 'Akses ditolak';
    const ERROR_ADMIN_ONLY          = 'Hanya admin yang dapat mengakses resource ini';
    const ERROR_DRIVER_ONLY         = 'Hanya driver yang dapat mengakses resource ini';
    const ERROR_DRIVER_ACCESS_ONLY  = 'Hanya driver yang dapat mengakses endpoint ini';
    const ERROR_STUDENT_ONLY        = 'Hanya siswa yang dapat mengakses resource ini';
    const ERROR_SUSPENDED_ACCOUNT   = 'Akun Anda telah disuspend';
    const ERROR_ACCOUNT_REJECTED    = 'Akun Anda telah ditolak oleh admin';
    const ERROR_ACCOUNT_PENDING     = 'Akun Anda masih menunggu persetujuan admin';

    // ==================== NOT FOUND ====================

    const ERROR_NOT_FOUND                = 'Resource tidak ditemukan';
    const ERROR_USER_NOT_FOUND           = 'User tidak ditemukan';
    const ERROR_STUDENT_NOT_FOUND        = 'Siswa tidak ditemukan';
    const ERROR_DRIVER_NOT_FOUND         = 'Driver tidak ditemukan';
    const ERROR_DRIVER_PROFILE_NOT_FOUND = 'Profil driver tidak ditemukan';
    const ERROR_BUS_NOT_ASSIGNED         = 'Anda tidak ditugaskan ke bus ini';
    const ERROR_BUS_NOT_FOUND            = 'Bus tidak ditemukan';
    const ERROR_BUS_ACCESS_DENIED        = 'Anda tidak memiliki akses ke bus ini';
    const ERROR_HALTE_NOT_FOUND          = 'Halte tidak ditemukan';
    const ERROR_ROUTE_NOT_FOUND          = 'Rute tidak ditemukan';

    // ==================== BUSINESS LOGIC ====================

    const ERROR_STUDENT_ALREADY_ASSIGNED = 'Siswa sudah terdaftar di bus lain';
    const ERROR_STUDENT_NOT_APPROVED     = 'Status siswa belum disetujui admin';
    const ERROR_DRIVER_CONFLICT          = 'Driver sudah memiliki pengaturan bus pada tanggal yang sama';
    const ERROR_BUS_NOT_OPERATIONAL      = 'Bus tidak dalam status operasional';
    const ERROR_STUDENT_NOT_IN_BUS       = 'Siswa tidak terdaftar di bus ini';
    const ERROR_STUDENT_NOT_IN_HALTE     = 'Siswa tidak terdaftar di halte ini';
    const ERROR_DUPLICATE_ATTENDANCE     = 'Siswa sudah melakukan absensi di waktu yang sama';
    const ERROR_INVALID_SCAN_TYPE        = 'Tipe scan (naik/turun) tidak valid';
    const ERROR_ALREADY_EXISTS           = 'Data sudah ada';

    // ==================== SERVER ====================

    const ERROR_INTERNAL_SERVER  = 'Terjadi kesalahan pada server';
    const ERROR_SERVER_ERROR     = 'Terjadi kesalahan pada server';
    const ERROR_OPERATION_FAILED = 'Operasi gagal';

    // ==================== BISNIS ====================

    const MSG_STUDENT_APPROVED        = 'Siswa berhasil disetujui';
    const MSG_STUDENT_REJECTED        = 'Siswa berhasil ditolak';
    const MSG_DRIVER_ASSIGNED         = 'Driver berhasil ditugaskan';
    const MSG_STUDENT_ASSIGNED_TO_BUS = 'Siswa berhasil ditugaskan ke bus';
    const MSG_ATTENDANCE_RECORDED     = 'Absensi berhasil tercatat';
    const MSG_GPS_RECORDED            = 'GPS berhasil tercatat';
    const MSG_REPORT_GENERATED        = 'Laporan berhasil dibuat';
}