<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f9f9f9;
            padding: 20px;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">Hello {{ $username }},</div>

        <p>You have received a transfer of <strong>₦{{ number_format($amount, 2) }}</strong> from <strong>{{ $debitAccountName }}</strong>.</p>
        <p>Your updated TamoPei account balance is <strong>₦{{ number_format($balance, 2) }}</strong>.</p>

        <p>Thank you for using TamoPei!</p>

        <div class="footer">
            <p>If you have any questions, feel free to contact us at support@tamopei.com.</p>
        </div>
    </div>
</body>
</html>
