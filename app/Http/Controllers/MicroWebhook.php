<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class MicroWebhook extends Controller
{
    public function handle(Request $request)
    {
        // Get the raw JSON data from the request
        $jsonData = $request->getContent();

        // Decode the JSON data
        $data = json_decode($jsonData, true);

        // Validate the incoming data (you can modify this based on your needs)
        if (!isset($data['data']['creditAccountNumber']) || !isset($data['data']['amount'])) {
            return response()->json(['status' => 'error', 'message' => 'Missing required fields'], 400);
        }

        try {
            // Check if creditAccountNumber exists in virtual_accounts table
            $user = DB::table('virtual_accounts')
                      ->where('accountNumber', $data['data']['creditAccountNumber'])
                      ->first();

            if ($user) {
                $user_id = $user->user_id;

                // Insert data into the funding table
                DB::table('funding')->insert([
                    'sessionId' => $data['data']['sessionId'],
                    'nameEnquiryReference' => $data['data']['nameEnquiryReference'],
                    'paymentReference' => $data['data']['paymentReference'],
                    'creditAccountName' => $data['data']['creditAccountName'],
                    'creditAccountNumber' => $data['data']['creditAccountNumber'],
                    'narration' => $data['data']['narration'],
                    'amount' => $data['data']['amount'],
                    'user_id' => $user_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Update user wallet in the wallet table
                DB::table('wallet')
                    ->where('user_id', $user_id)
                    ->increment('Naira', $data['data']['amount']);

                // Get the updated wallet balance
                $balance = DB::table('wallet')
                             ->where('user_id', $user_id)
                             ->value('Naira');

                // Send email to the user
                $this->sendEmail($user_id, $data, $balance);

                // Send a response (success)
                return response()->json(['status' => 'success']);
            } else {
                // User not found, return error
                return response()->json(['status' => 'error', 'message' => 'User with the specified account number not found'], 400);
            }
        } catch (\Exception $e) {
            // Handle exceptions, log the error if necessary
            return response()->json(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    // Function to send email to user
    protected function sendEmail($user_id, $data, $balance)
    {
        // Fetch user credentials from the user_credentials table
        $userData = DB::table('user_credentials')->where('user_id', $user_id)->first();

        if ($userData) {
            $email = $userData->email;
            $username = $userData->first_name . ' ' . $userData->middle_name . ' ' . $userData->last_name;

            // Build the email body with HTML formatting
            $emailBody = "
                <html>
                    <head>
                        <style>
                            /* Add your custom styles here */
                            body { font-family: Arial, sans-serif; }
                            h1 { color: #4CAF50; }
                            p { font-size: 16px; }
                        </style>
                    </head>
                    <body>
                        <p>Dear $username,</p>
                        <p>You received a transfer of <strong>₦{$data['data']['amount']}</strong> from {$data['data']['debitAccountName']}.</p>
                        <p>Your available TamoPei Account balance is <strong>₦$balance</strong>.</p>
                    </body>
                </html>
            ";

            // Send the email
            Mail::send([], [], function ($message) use ($email, $username, $emailBody) {
                $message->to($email, $username)
                        ->subject('Credit Alert')
                        ->setBody($emailBody, 'text/html'); // Send as HTML email
            });
        }
    }
}
