<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Tidak Disetujui</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #F0F4F8; padding: 32px 16px; }
        .container { max-width: 520px; margin: 0 auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #EF5350 0%, #B71C1C 100%); padding: 36px 32px; text-align: center; }
        .header .icon { font-size: 48px; margin-bottom: 12px; }
        .header h1 { color: #fff; font-size: 22px; font-weight: 700; margin: 0; }
        .body { padding: 32px; }
        .greeting { font-size: 16px; color: #1A1A2E; font-weight: 600; margin-bottom: 12px; }
        .message { font-size: 14px; color: #555; line-height: 1.7; margin-bottom: 24px; }
        .reason-box { background: #FFF3E0; border: 1px solid #FFCC80; border-left: 4px solid #FF9800; border-radius: 8px; padding: 16px 20px; margin-bottom: 24px; }
        .reason-box .reason-label { font-size: 12px; color: #E65100; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .reason-box .reason-text { font-size: 14px; color: #4E342E; line-height: 1.6; }
        .info-box { background: #FFF8F8; border: 1px solid #FFCDD2; border-radius: 12px; padding: 20px; margin-bottom: 24px; }
        .info-box p { font-size: 13px; color: #666; line-height: 1.7; }
        .footer { text-align: center; padding: 20px 32px; background: #F8F9FA; border-top: 1px solid #E9ECEF; }
        .footer p { font-size: 12px; color: #999; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">😔</div>
            <h1>Pendaftaran Tidak Disetujui</h1>
        </div>
        <div class="body">
            <p class="greeting">Halo, {{ $studentName }}.</p>
            <p class="message">
                Mohon maaf, pendaftaran akun <strong>{{ $appName }}</strong> kamu dengan email
                <strong>{{ $email }}</strong> tidak dapat disetujui oleh Admin.
            </p>
            <div class="reason-box">
                <div class="reason-label">Alasan Penolakan</div>
                <div class="reason-text">{{ $reason }}</div>
            </div>
            <div class="info-box">
                <p>
                    Jika kamu merasa ada kesalahan atau ingin mendaftar ulang dengan data yang diperbaiki,
                    silakan hubungi pihak sekolah atau daftar kembali melalui aplikasi {{ $appName }}.
                </p>
            </div>
        </div>
        <div class="footer">
            <p>Email ini dikirim otomatis oleh sistem <strong>{{ $appName }}</strong>.<br>Jangan balas email ini.</p>
        </div>
    </div>
</body>
</html>