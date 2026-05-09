<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Akun Disetujui - Mobitra</title>
</head>
<body style="margin:0;padding:0;background:#F0F2F0;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#F0F2F0;padding:32px 0;">
    <tr>
      <td align="center">
        <table width="520" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">

          <!-- Header hijau -->
          <tr>
            <td style="background:#7CBF2F;padding:36px 40px;text-align:center;">
              <div style="width:64px;height:64px;background:rgba(255,255,255,0.2);border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:32px;line-height:64px;">✅</div>
              <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;letter-spacing:-0.3px;">Akun Kamu Disetujui!</h1>
              <p style="margin:8px 0 0;color:rgba(255,255,255,0.85);font-size:14px;">{{ $appName }}</p>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:36px 40px;">
              <p style="margin:0 0 16px;font-size:15px;color:#1A1A1A;">Halo, <strong>{{ $studentName }}</strong> 👋</p>
              <p style="margin:0 0 20px;font-size:14px;color:#555;line-height:1.7;">
                Selamat! Pendaftaran akun kamu di aplikasi <strong>{{ $appName }}</strong> telah <strong style="color:#7CBF2F;">disetujui</strong> oleh admin.
                Sekarang kamu sudah bisa menggunakan aplikasi untuk melacak bus sekolah secara realtime.
              </p>

              <!-- Info box -->
              <table width="100%" cellpadding="0" cellspacing="0" style="background:#F0F9E8;border-radius:12px;margin-bottom:24px;">
                <tr>
                  <td style="padding:20px 24px;">
                    <p style="margin:0 0 10px;font-size:12px;font-weight:700;color:#7CBF2F;letter-spacing:0.5px;text-transform:uppercase;">Detail Akun</p>
                    <table width="100%" cellpadding="0" cellspacing="0">
                      <tr>
                        <td style="font-size:13px;color:#888;padding:4px 0;width:100px;">Email</td>
                        <td style="font-size:13px;color:#1A1A1A;font-weight:600;padding:4px 0;">{{ $email }}</td>
                      </tr>
                      <tr>
                        <td style="font-size:13px;color:#888;padding:4px 0;">Sekolah</td>
                        <td style="font-size:13px;color:#1A1A1A;font-weight:600;padding:4px 0;">{{ $sekolah }}</td>
                      </tr>
                      <tr>
                        <td style="font-size:13px;color:#888;padding:4px 0;">Status</td>
                        <td style="padding:4px 0;">
                          <span style="background:#7CBF2F;color:#fff;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;">Aktif</span>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 24px;font-size:14px;color:#555;line-height:1.7;">
                Buka aplikasi <strong>{{ $appName }}</strong> dan login menggunakan email kamu untuk mulai menggunakannya.
              </p>

              <p style="margin:0;font-size:13px;color:#888;line-height:1.6;">
                Jika kamu tidak mendaftar akun ini, abaikan email ini atau hubungi admin sekolah.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background:#F8F8F8;padding:20px 40px;text-align:center;border-top:1px solid #EEE;">
              <p style="margin:0;font-size:12px;color:#aaa;">© {{ date('Y') }} {{ $appName }} · Sistem Pelacak Bus Sekolah</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>