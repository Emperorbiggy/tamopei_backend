<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\TransactionNotification;

class TamopeiTransfer extends Controller
{
    public function transfer(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'currency' => 'required|string|max:3',
            'amount' => 'required|numeric|min:1',
            'accountNumber' => 'required|integer',
        ]);

        // Currency mapping
        $currencyMapping = [
            'NGN' => 'Naira',
            'USD' => 'Dollar',
            'GBP' => 'Pound',
            'ZAR' => 'Rand',
            'GHS' => 'Cedi',
            'EUR' => 'Euro',
            'CNY' => 'Yuan',
            'CAD' => 'CAD',
            'KES' => 'KES',
            'XAF' => 'XAF'
        ];

        $currencyCode = $request->currency;
        $currency = $currencyMapping[$currencyCode] ?? null;
        if (!$currency) {
            return response()->json(['status' => 'error', 'message' => 'Unsupported currency code'], 400);
        }

        // Extracting values from the request
        $amount = $request->amount;
        $accountNumber = $request->accountNumber;

        // Extract token from the header
        $token = str_replace('Bearer ', '', $request->header('Authorization'));
        $sender = DB::table('user_credentials')->where('token', $token)->first();

        if (!$sender) {
            return response()->json(['status' => 'error', 'message' => 'Invalid token'], 403);
        }

        // Check if sender has sufficient balance
        $walletBalance = DB::table('wallet')->where('user_id', $sender->user_id)->value($currency);
        if ($walletBalance < $amount) {
            return response()->json(['status' => 'error', 'message' => 'Insufficient balance'], 400);
        }

        // Fetch receiver details
        $receiver = DB::table('user_credentials')->where('user_id', $accountNumber)->first();
        if (!$receiver) {
            return response()->json(['status' => 'error', 'message' => 'Receiver not found'], 404);
        }

        DB::beginTransaction();
        try {
            // Deduct amount from sender's wallet
            DB::table('wallet')->where('user_id', $sender->user_id)->decrement($currency, $amount);

            // Add amount to receiver's wallet
            DB::table('wallet')->where('user_id', $receiver->user_id)->increment($currency, $amount);

            // Generate a unique transaction ID
            $transactionId = $this->generateTransactionId();

            // Record the transaction in the database
            DB::table('wallet_transaction')->insert([
                'sender_id' => $sender->user_id,
                'receiver_id' => $receiver->user_id,
                'amount' => $amount,
                'currency' => $currency,
                'transaction_id' => $transactionId,
                'status' => 'success',
                'date_time' => now()
            ]);

            // Send email notifications to sender and receiver
            // For received transaction
            Mail::to($receiver->email)->send(new TransactionNotification(
                $receiver->first_name,
                $amount,
                $currency,
                $transactionId,
                'received'
            ));
            // For sent transaction
            Mail::to($sender->email)->send(new TransactionNotification(
                $sender->first_name,
                $amount,
                $currency,
                $transactionId,
                'sent'
            ));

            DB::commit();

            // Return success response
            return response()->json(['status' => 'success', 'message' => 'Transaction successful', 'transaction_id' => $transactionId]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => 'Transaction failed: ' . $e->getMessage()], 500);
        }
    }

    // Function to generate a unique transaction ID
    private function generateTransactionId()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}