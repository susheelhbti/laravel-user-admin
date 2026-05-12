<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Deletion Scheduled</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 480px; margin: 40px auto; background: #fff; border-radius: 8px; padding: 32px; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .warning { background: #fff3cd; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 4px; margin: 16px 0; }
        .date { font-size: 20px; font-weight: bold; color: #dc2626; }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="color:#2d3748;">Account Deletion Scheduled</h2>
        <p>Hello <strong>{{ $user->name }}</strong>,</p>
        <p>We received a request to permanently delete your account.</p>

        <div class="warning">
            <p style="margin:0;">Your account will be <strong>permanently deleted</strong> on:</p>
            <p class="date" style="margin:8px 0 0;">{{ $scheduledAt->format('F j, Y \a\t H:i T') }}</p>
        </div>

        <p>If you did not request this or have changed your mind, you can cancel deletion by logging back in within the grace period.</p>
        <p style="color:#718096; font-size:14px;">After this date, all your data will be permanently removed and cannot be recovered.</p>
    </div>
</body>
</html>
