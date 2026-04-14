<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akun Disetujui</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #F0F4F8; padding: 32px 16px; }
        .container { max-width: 520px; margin: 0 auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%); padding: 36px 32px; text-align: center; }
        .header .icon { font-size: 48px; margin-bottom: 12px; }
        .header h1 { color: #fff; font-size: 22px; font-weight: 700; margin: 0; }
        .body { padding: 32px; }
        .greeting { font-size: 16px; color: #1A1A2E; font-weight: 600; margin-bottom: 12px; }
        .message { font-size: 14px; color: #555; line-height: 1.7; margin-bottom: 24px; }
        .info-box { background: #F1F8E9; border: 1px solid #C5E1A5; border-radius: 12px; padding: 20px; margin-bottom: 24px; }
        .info-box .row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; }
        .info-box .row:last-child { margin-bottom: 0; }
        .info-box .label { color: #666; }
        .info-box .value { color: #1A1A2E; font-weight: 600; }
        .cta { text-align: center; margin-bottom: 24px; }
        .cta-btn { display: inline-block; background: #4CAF50; color: #fff; text-decoration: none; padding: 14px 32px; border-radius: 8px; font-size: 15px; font-weight: 600; }
        .footer { text-align: center; padding: 20px 32px; background: #F8F9FA; border-top: 1px solid #E9ECEF; }
        .footer p { font-size: 12px; color: #999; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">🎉</div>
            <h1>Akun Kamu Telah Disetujui!</h1>
        </div>
        <div class="body">
            <p class="greeting">Halo, {{ $studentName }}!</p>
            <p class="message">
                Kabar baik! Admin telah menyetujui pendaftaran akun <strong>{{ $appName }}</strong> kamu.
                Sekarang kamu sudah bisa login dan menggunakan semua fitur aplikasi.
            </p>
            <div class="info-box">
                <div class="row">
                    <span class="label">Email Login</span>
                    <span class="value">{{ $email }}</span>
                </div>
                <div class="row">
                    <span class="label">Sekolah</span>
                    <span class="value">{{ $sekolah }}</span>
                </div>
                <div class="row">
                    <span class="label">Status</span>
                    <span class="value" style="color:#2E7D32;">✅ Disetujui</span>
                </div>
            </div>
            <div class="cta">
                <p style="font-size:13px;color:#555;margin-bottom:12px;">Buka aplikasi {{ $appName }} dan login sekarang.</p>
            </div>
        </div>
        <div class="footer">
            <p>Email ini dikirim otomatis oleh sistem <strong>{{ $appName }}</strong>.<br>Jangan balas email ini.</p>
        </div>
    </div>
</body>
</html>