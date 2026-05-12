<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your OTP Code</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 480px; margin: 40px auto; background: #fff; border-radius: 8px; padding: 32px; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .code { font-size: 36px; font-weight: bold; letter-spacing: 8px; color: #2d3748; text-align: center; margin: 24px 0; }
        .footer { font-size: 12px; color: #a0aec0; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="color:#2d3748;">Your Login Code</h2>
        <p>Use the following one-time code to log in. It expires in {{ config('user_admin.otp.expires_in_minutes', 5) }} minutes.</p>
        <div class="code">{{ $code }}</div>
        <p>If you didn't request this code, you can safely ignore this email.</p>
        <p class="footer">This code was requested for <strong>{{ $email }}</strong>.</p>
    </div>
</body>
</html>
