<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Akun Disetujui - Mobitra</title>
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
        <table width="520" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 6px 24px rgba(15,61,34,0.06);border:1px solid #E2E9E5;">

          <!-- Hero Banner (Forest Green Gradient) -->
          <tr>
            <td style="background:linear-gradient(135deg, #0F3D22 0%, #1B5E37 60%, #2E7D52 100%);padding:36px 40px;text-align:center;color:#ffffff;">
              <!-- Circle checkmark decoration -->
              <table cellpadding="0" cellspacing="0" style="margin:0 auto 14px;">
                <tr>
                  <td width="64" height="64"
                      style="width:64px;height:64px;background-color:rgba(255,255,255,0.18);border-radius:50%;text-align:center;vertical-align:middle;font-size:28px;line-height:64px;color:#ffffff;font-weight:bold;">
                    &#10003;
                  </td>
                </tr>
              </table>
              <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:800;letter-spacing:-0.3px;line-height:1.25;">Pendaftaran Disetujui!</h1>
              <p style="margin:6px 0 0;color:rgba(255,255,255,0.78);font-size:13.5px;font-weight:500;">Mobitra &middot; Bus Sekolah Kota Madiun</p>
            </td>
          </tr>

          <!-- Card Body -->
          <tr>
            <td style="padding:36px 40px;">
              <p style="margin:0 0 16px;font-size:16px;color:#1a1a1a;line-height:1.4;">Halo, <strong>{{ $studentName }}</strong> 👋</p>
              <p style="margin:0 0 22px;font-size:14px;color:#4F5E55;line-height:1.65;">
                Selamat! Pendaftaran akun Anda di sistem monitoring bus sekolah <strong>Mobitra</strong> telah <strong style="color:#1B5E37;">disetujui</strong> oleh admin.
                Akun Anda sekarang telah aktif dan siap digunakan untuk melacak posisi bus secara real-time.
              </p>

              <!-- Account Details Box -->
              <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#F0F6F2;border:1px solid #D1E5D9;border-radius:12px;margin-bottom:24px;">
                <tr>
                  <td style="padding:18px 20px;">
                    <p style="margin:0 0 12px;font-size:11px;font-weight:700;color:#1B5E37;letter-spacing:0.8px;text-transform:uppercase;line-height:1;">Detail Registrasi</p>
                    <table width="100%" cellpadding="0" cellspacing="0">
                      <tr>
                        <td style="font-size:13px;color:#6B7B73;padding:5px 0;width:90px;font-weight:500;">Email</td>
                        <td style="font-size:13px;color:#0F3D22;font-weight:700;padding:5px 0;">{{ $email }}</td>
                      </tr>
                      <tr>
                        <td style="font-size:13px;color:#6B7B73;padding:5px 0;font-weight:500;">Sekolah</td>
                        <td style="font-size:13px;color:#0F3D22;font-weight:700;padding:5px 0;">{{ $sekolah }}</td>
                      </tr>
                      <tr>
                        <td style="font-size:13px;color:#6B7B73;padding:6px 0;font-weight:500;">Status</td>
                        <td style="padding:6px 0;">
                          <span style="background-color:#1B5E37;color:#ffffff;font-size:10.5px;font-weight:700;padding:3px 9px;border-radius:20px;text-transform:uppercase;letter-spacing:0.5px;">Aktif</span>
                        </td>
                      </tr>
                    </table>
                  </td>
                </tr>
              </table>

              <p style="margin:0 0 24px;font-size:14px;color:#4F5E55;line-height:1.65;">
                Silakan buka aplikasi <strong>Mobitra</strong> di smartphone Anda, lalu login menggunakan akun email di atas untuk mulai memantau rute dan halte bus penjemputan.
              </p>

              <!-- Action button -->
              <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;text-align:center;">
                <tr>
                  <td align="center">
                    <a href="#" style="background:linear-gradient(135deg, #1B5E37 0%, #2E7D52 100%);color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:10px;font-size:14px;font-weight:700;display:inline-block;box-shadow:0 4px 14px rgba(27,94,55,0.22);">
                      Buka Aplikasi Mobitra
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin:0;font-size:12.5px;color:#8B9B92;line-height:1.6;border-top:1px solid #EBF0ED;padding-top:20px;">
                Jika Anda tidak merasa mendaftarkan akun ini, silakan hubungi pihak sekolah atau abaikan saja email ini.
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="background-color:#F8FAF9;padding:22px 40px;text-align:center;border-top:1px solid #EBF0ED;">
              <p style="margin:0;font-size:12px;color:#ABB8B0;line-height:1.5;">&copy; {{ date('Y') }} Mobitra &middot; Dinas Komunikasi dan Informatika Kota Madiun</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>