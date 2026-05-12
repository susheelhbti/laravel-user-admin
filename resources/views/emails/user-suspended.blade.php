<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Suspended</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 480px; margin: 40px auto; background: #fff; border-radius: 8px; padding: 32px; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
        .badge { display: inline-block; background: #fed7d7; color: #c53030; padding: 4px 12px; border-radius: 4px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h2 style="color:#2d3748;">Account Suspension Notice</h2>
        <p>Hello <strong>{{ $user->name }}</strong>,</p>
        <p>Your account has been <span class="badge">suspended</span>.</p>

        @if($reason)
            <p><strong>Reason:</strong> {{ $reason }}</p>
        @endif

        @if($user->suspended_until)
            <p><strong>Suspension ends:</strong> {{ $user->suspended_until->format('F j, Y \a\t H:i T') }}</p>
        @else
            <p>This suspension is indefinite. Please contact support for more information.</p>
        @endif

        <p style="margin-top: 24px; color: #718096; font-size: 14px;">
            If you believe this was a mistake, please contact our support team.
        </p>
    </div>
</body>
</html>
