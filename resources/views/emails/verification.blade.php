<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verification Email</title>
</head>
<body style="background-color: #f5f5f5; padding: 20px; text-align: center;">

    <div>
        <img src="https://dashboard.tamopei.com.ng/assets/img/tamopeinew.png" alt="Logo" width="150" height="auto">
        <h1 style="color: #333;">Dear {{ $username }},</h1>
        <p style="color: #333;">Thank you for registering with us.</p>
        <p style="color: #333;">Here is your verification code: <strong>{{ $verificationCode }}</strong></p>
    </div>

</body>
</html>
