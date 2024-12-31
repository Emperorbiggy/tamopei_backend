<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\NairaTransactionNotification;

class Webhook extends Controller
{
    public function handle(Request $request)
    {
        // Get the raw JSON data from the request
        $data = $request->json()->all();

        // Check if the JSON decoding was successful
        if ($data && isset($data['data']['creditAccountNumber'])) {
            // Fetch the user_id from the virtual_accounts table
            $user = DB::table('virtual_accounts')
                ->where('accountNumber', $data['data']['creditAccountNumber'])
                ->first();

            if ($user) {
                // User found, proceed with the transaction
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
                ]);

                // Update the user's wallet balance in the wallet table
                DB::table('wallet')
                    ->where('user_id', $user_id)
                    ->increment('Naira', $data['data']['amount']);

                // Get the updated wallet balance
                $balance = DB::table('wallet')
                    ->where('user_id', $user_id)
                    ->value('Naira');

                // Fetch user details for the email
                $userDetails = DB::table('user_credentials')
                    ->where('user_id', $user_id)
                    ->first();

                if ($userDetails) {
                    $username = trim("{$userDetails->first_name} {$userDetails->middle_name} {$userDetails->last_name}");
                    $email = $userDetails->email;

                    // Send email notification
                    Mail::to($email)->send(new NairaTransactionNotification(
                        $username,
                        $data['data']['amount'],
                        $data['data']['debitAccountName'],
                        $balance
                    ));
                }

                // Return a success response
                return response()->json(['status' => 'success']);
            } else {
                // User not found
                return response()->json(['status' => 'error', 'message' => 'User with the specified account number not found'], 400);
            }
        } else {
            // Invalid JSON data
            return response()->json(['status' => 'error', 'message' => 'Invalid JSON data'], 400);
        }
    }
}
