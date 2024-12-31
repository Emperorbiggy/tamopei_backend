<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchTransactionController extends Controller
{
    /**
     * Fetch all transactions for a specific user.
     */
    public function fetchTransactions(Request $request)
    {
        // Log incoming request data for debugging purposes
        Log::info('Incoming request to fetch transactions', $request->all());

        // Validate the incoming request for the token
        $request->validate([
            'token' => 'required|string',
        ]);

        $token = $request->input('token');

        try {
            // Retrieve user ID using the token
            $user = DB::table('user_credentials')->where('token', $token)->first(['user_id']);

            if (!$user) {
                // Log invalid token attempt
                Log::warning('Invalid token received', ['token' => $token]);
                return response()->json(['success' => false, 'message' => 'Invalid token'], 404);
            }

            $user_id = $user->user_id;

            // Fetch transactions
            $debitTransactions = DB::table('micro_transfer')
                ->where('createdBy', $user_id)
                ->select('*')
                ->get();

            $walletDebitTransactions = DB::table('wallet_transaction')
                ->where('sender_id', $user_id)
                ->select('*')
                ->get();

            $walletCreditTransactions = DB::table('wallet_transaction')
                ->where('receiver_id', $user_id)
                ->select('*')
                ->get();

            $creditTransactions = DB::table('funding')
                ->where('user_id', $user_id)
                ->select('*')
                ->get();

            $billTransactions = DB::table('bill')
                ->where('user_id', $user_id)
                ->select('*')
                ->get();

            // Fetch receiver names from the user_credentials table
            $receiverNames = DB::table('user_credentials')
                ->whereIn('user_id', function ($query) use ($user_id) {
                    $query->select('receiver_id')
                        ->distinct()
                        ->from('wallet_transaction')
                        ->where('sender_id', $user_id);
                })
                ->select('user_id', DB::raw("CONCAT_WS(' ', first_name, middle_name, last_name) as name"))
                ->get()
                ->pluck('name', 'user_id');

            // Fetch sender names when the user is the receiver
            $senderNames = DB::table('user_credentials')
                ->whereIn('user_id', function ($query) use ($user_id) {
                    $query->select('sender_id')
                        ->distinct()
                        ->from('wallet_transaction')
                        ->where('receiver_id', $user_id);
                })
                ->select('user_id', DB::raw("CONCAT_WS(' ', first_name, middle_name, last_name) as name"))
                ->get()
                ->pluck('name', 'user_id');

            // Log fetched data
            Log::info('Fetched transactions', [
                'debit_transactions' => $debitTransactions,
                'wallet_debit_transactions' => $walletDebitTransactions,
                'wallet_credit_transactions' => $walletCreditTransactions,
                'credit_transactions' => $creditTransactions,
                'bill_transactions' => $billTransactions,
                'receiver_names' => $receiverNames,
                'sender_names' => $senderNames,
            ]);

            // Add receiver names to wallet transactions
            $walletDebitTransactions->each(function ($transaction) use ($receiverNames) {
                $transaction->receiver_name = $receiverNames[$transaction->receiver_id] ?? 'Unknown';
            });

            $walletCreditTransactions->each(function ($transaction) use ($senderNames) {
                $transaction->sender_name = $senderNames[$transaction->sender_id] ?? 'Unknown';
            });

            // Combine all transactions
            $transactions = [];

            // Append debit transactions
            foreach ($debitTransactions as $transaction) {
                $transaction->type = 'debit';
                $transaction->title = $transaction->creditAccountName ?? 'No Title';
                $transaction->createdAt = $transaction->createdAt;
                $transactions[] = $transaction;
            }

            // Append wallet debit transactions
            foreach ($walletDebitTransactions as $transaction) {
                $transaction->type = 'debit';
                $transaction->title = $transaction->receiver_name ?? 'No Title';
                $transaction->createdAt = $transaction->date_time ?? $transaction->createdAt;
                $transactions[] = $transaction;
            }

            // Append wallet credit transactions
            foreach ($walletCreditTransactions as $transaction) {
                $transaction->type = 'credit';
                $transaction->title = $transaction->sender_name ?? 'No Title';
                $transaction->createdAt = $transaction->date_time ?? $transaction->createdAt;
                $transactions[] = $transaction;
            }

            // Append credit transactions
            foreach ($creditTransactions as $transaction) {
                $transaction->type = 'credit';
                $transaction->title = $transaction->narration ?? 'No Title';
                $transaction->createdAt = $transaction->createdAt;
                $transactions[] = $transaction;
            }

            // Append bill transactions
            foreach ($billTransactions as $transaction) {
                $transaction->type = 'bills';
                $transaction->title = 'Bill Payment'; // Default title for bill transactions
                $transactions[] = $transaction;
            }

            // Sort all transactions by createdAt
            usort($transactions, function ($a, $b) {
                return strtotime($b->createdAt) - strtotime($a->createdAt); // Sort by createdAt in descending order
            });

            // Return the combined transactions as JSON
            return response()->json([
                'success' => true,
                'transactions' => $transactions
            ]);

        } catch (\Exception $e) {
            // Log the error and return a generic error message
            Log::error("Error fetching transactions: " . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred'
            ], 500);
        }
    }
}
