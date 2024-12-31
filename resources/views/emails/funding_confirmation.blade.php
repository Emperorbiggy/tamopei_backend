<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Funding Confirmation</title>
</head>
<body>
    <h1>Funding Confirmation</h1>
    <p>Hello {{ $user->name }},</p>
    <p>Your account has been credited with the following details:</p>
    <ul>
        <li>Amount: {{ $data['data']['amount'] }}</li>
        <li>Payment Reference: {{ $data['data']['paymentReference'] }}</li>
        <li>Narration: {{ $data['data']['narration'] }}</li>
        <li>New Balance: {{ $balance }}</li>
    </ul>
    <p>Thank you for using our service.</p>
</body>
</html>
