<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pendaftaran Ditolak - {{ $appName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#F4F6F5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#F4F6F5;padding:40px 0;">
    <tr>
      <td align="center">
        
        <!-- Top logo header -->
        <table width="520" cellpadding="0" cellspacing="0" style="margin-bottom:20px;text-align:center;">
          <tr>
            <td align="center">
              <img src="{{ $message->embed(public_path('images/Logo1.png')) }}" 
                   alt="Mobitra Logo" 
                   style="height:52px; width:auto; display:block; margin:0 auto 8px; border:none; outline:none;">
              <span style="font-size:18px; font-weight:800; color:#1B5E37; letter-spacing:-0.4px;">Mobitra</span>
            </td>
          </tr>
        </table>

        <!-- Main Card Container -->
        <table width="520" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 6px 24px rgba(0,0,0,0.05);border:1px solid #E2E9E5;">

          <!-- Hero Banner (Crimson/Red Gradient) -->
          <tr>
            <td style="background:linear-gradient(135deg, #7F1D1D 0%, #DC2626 60%, #EF4444 100%);padding:36px 40px;text-align:center;color:#ffffff;">
              <!-- Circle X decoration -->
              <table cellpadding="0" cellspacing="0" style="margin:0 auto 14px;">
                <tr>
                  <td width="64" height="64"
                      style="width:64px;height:64px;background-color:rgba(255,255,255,0.18);border-radius:50%;text-align:center;vertical-align:middle;font-size:28px;line-height:64px;color:#ffffff;font-weight:bold;">
                    &#10007;
                  </td>
                </tr>
              </table>
              <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:800;letter-spacing:-0.3px;line-height:1.25;">Pendaftaran Ditolak</h1>
              <p style="margin:6px 0 0;color:rgba(255,255,255,0.78);font-size:13.5px;font-weight:500;">{{ $appName }} &middot; Bus Sekolah Kota Madiun</p>
            </td>
          </tr>

          <!-- Card Body -->
          <tr>
            <td style="padding:36px 40px;">
              <p style="margin:0 0 16px;font-size:16px;color:#1a1a1a;line-height:1.4;">Halo, <strong>{{ $studentName }}</strong> 👋</p>
              <p style="margin:0 0 22px;font-size:14px;color:#4F5E55;line-height:1.65;">
                Terima kasih telah mendaftar. Mohon maaf, pendaftaran akun Anda di Mobitra - Bus Sekolah Kota Madiun <strong>{{ $appName }}</strong> belum dapat disetujui saat ini.
              </p>

              <!-- Rejection Reason Alert Box -->
              <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#FFF5F5;border-left:4px solid #E53E3E;border-radius:0 12px 12px 0;margin-bottom:24px;border-top:1px solid #FED7D7;border-right:1px solid #FED7D7;border-bottom:1px solid #FED7D7;">
                <tr>
                  <td style="padding:18px 20px;">
                    <p style="margin:0 0 8px;font-size:11px;font-weight:700;color:#E53E3E;letter-spacing:0.8px;text-transform:uppercase;line-height:1;">Alasan Penolakan</p>
                    <p style="margin:0;font-size:13.5px;color:#1A1A1A;line-height:1.6;font-weight:500;">
                      {{ $reason ?: 'Tidak ada keterangan tambahan dari admin.' }}
                    </p>
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 24px;font-size:14px;color:#4F5E55;line-height:1.65;">
                Anda dapat mencoba melakukan pendaftaran kembali menggunakan aplikasi mobile dengan memastikan data diri, NIS, dan sekolah sudah dimasukkan dengan benar.
              </p>

              <p style="margin:0;font-size:12.5px;color:#8B9B92;line-height:1.6;border-top:1px solid #EBF0ED;padding-top:20px;">
                Jika Anda merasa penolakan ini merupakan kesalahan atau membutuhkan informasi lebih lanjut, silakan hubungi pihak sekolah Anda secara langsung.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color:#F8FAF9;padding:22px 40px;text-align:center;border-top:1px solid #EBF0ED;">
              <p style="margin:0;font-size:12px;color:#ABB8B0;line-height:1.5;">&copy; {{ date('Y') }} {{ $appName }} &middot; Dinas Komunikasi dan Informatika Kota Madiun</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>