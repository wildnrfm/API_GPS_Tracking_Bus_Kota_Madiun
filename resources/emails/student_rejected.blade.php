<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pendaftaran Ditolak - Mobitra</title>
</head>
<body style="margin:0;padding:0;background:#F0F2F0;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#F0F2F0;padding:32px 0;">
    <tr>
      <td align="center">
        <table width="520" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">

          <!-- Header merah -->
          <tr>
            <td style="background:#E53E3E;padding:36px 40px;text-align:center;">
              <div style="font-size:40px;line-height:1;margin-bottom:16px;">❌</div>
              <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;letter-spacing:-0.3px;">Pendaftaran Ditolak</h1>
              <p style="margin:8px 0 0;color:rgba(255,255,255,0.85);font-size:14px;">{{ $appName }}</p>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:36px 40px;">
              <p style="margin:0 0 16px;font-size:15px;color:#1A1A1A;">Halo, <strong>{{ $studentName }}</strong></p>
              <p style="margin:0 0 20px;font-size:14px;color:#555;line-height:1.7;">
                Mohon maaf, pendaftaran akun kamu di aplikasi <strong>{{ $appName }}</strong> <strong style="color:#E53E3E;">tidak dapat disetujui</strong> oleh admin saat ini.
              </p>

              <!-- Alasan box -->
              <table width="100%" cellpadding="0" cellspacing="0" style="background:#FFF5F5;border-left:4px solid #E53E3E;border-radius:0 8px 8px 0;margin-bottom:24px;">
                <tr>
                  <td style="padding:16px 20px;">
                    <p style="margin:0 0 6px;font-size:12px;font-weight:700;color:#E53E3E;letter-spacing:0.5px;text-transform:uppercase;">Alasan Penolakan</p>
                    <p style="margin:0;font-size:14px;color:#1A1A1A;line-height:1.6;">{{ $reason ?: 'Tidak ada keterangan tambahan dari admin.' }}</p>
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 20px;font-size:14px;color:#555;line-height:1.7;">
                Kamu dapat mendaftar ulang dengan data yang benar atau menghubungi admin sekolah untuk informasi lebih lanjut.
              </p>

              <p style="margin:0;font-size:13px;color:#888;line-height:1.6;">
                Jika kamu merasa ini adalah kesalahan, silakan hubungi admin sekolah secara langsung.
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