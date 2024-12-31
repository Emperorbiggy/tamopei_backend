<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreateTrade extends Controller
{
    public function store(Request $request)
{
    // Validate incoming request data
    $validatedData = $request->validate([
        'customRate' => 'required|numeric',
        'marketRate' => 'required|numeric',
        'inverseRate' => 'required|numeric',
        'limits' => 'required|array|min:2',
        'payAmount' => 'required|numeric|min:0',
        'payCurrency' => 'required|string|max:3',
        'rateIncreasePercentage' => 'required|numeric|max:5',
        'receiveAmount' => 'required|numeric',
        'receiveCurrency' => 'required|string|max:3',
        'token' => 'required|string',
        'target' => 'required|string|max:255',
        'source' => 'required|string|max:255',
    ]);

    try {
        // Map currency codes to full names
        $currencyNames = [
            'NGN' => 'Naira',
            'USD' => 'Dollar',
            'GBP' => 'Pound',
            'ZAR' => 'Rand',
            'GHS' => 'Cedi',
            'EUR' => 'Euro',
            'CNY' => 'Yuan',
        ];

        $payCurrencyName = $currencyNames[$validatedData['payCurrency']] ?? $validatedData['payCurrency'];

        // Get user ID from token
        $user = DB::table('user_credentials')->where('token', $validatedData['token'])->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Invalid token'], 400);
        }

        $userId = $user->user_id;

        // Get user's wallet balance for the given currency
        $wallet = DB::table('wallet')->where('user_id', $userId)->first();
        if (!$wallet || !isset($wallet->$payCurrencyName)) {
            return response()->json(['success' => false, 'message' => 'Wallet not found or invalid currency'], 404);
        }

        $currentBalance = (float)$wallet->$payCurrencyName;

        // Check balance validity
        if ($validatedData['payAmount'] > $currentBalance) {
            return response()->json(['success' => false, 'message' => 'Insufficient balance'], 400);
        }

        // Insert trade record
        $transactionId = uniqid('trade_', true);

        DB::table('p2p_trades')->insert([
            'transaction_id' => $transactionId,
            'user_id' => $userId,
            'custom_rate' => $validatedData['customRate'], // Store exactly as provided
            'live_rate' => $validatedData['inverseRate'], // Store exactly as provided
            'market_rate' => $validatedData['marketRate'], // Store exactly as provided
            'limit_min' => $validatedData['limits'][0], // Store exactly as provided
            'limit_max' => $validatedData['limits'][1], // Store exactly as provided
            'pay_amount' => $validatedData['payAmount'], // Store exactly as provided
            'pay_currency' => $validatedData['payCurrency'],
            'rate_increase_percentage' => $validatedData['rateIncreasePercentage'], // Store exactly as provided
            'receive_amount' => $validatedData['receiveAmount'], // Store exactly as provided
            'receive_currency' => $validatedData['receiveCurrency'],
            'target' => $validatedData['target'],
            'source' => $validatedData['source'],
        ]);

        // Update wallet balance
        $newBalance = $currentBalance - $validatedData['payAmount'];
        DB::table('wallet')
            ->where('user_id', $userId)
            ->update([$payCurrencyName => $newBalance]);

        return response()->json([
            'success' => true,
            'message' => 'Trade created successfully',
            'data' => [
                'transaction_id' => $transactionId,
                'pay_amount' => $validatedData['payAmount'],
                'pay_currency' => $validatedData['payCurrency'],
                'receive_amount' => $validatedData['receiveAmount'],
                'receive_currency' => $validatedData['receiveCurrency'],
                'target' => $validatedData['target'],
                'source' => $validatedData['source'],
            ]
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to create trade',
            'error' => $e->getMessage(),
        ], 500);
    }
}


    // Method to cancel trades
    public function cancelTrades(Request $request)
    {
        // Log raw input data for debugging
        $rawData = file_get_contents('php://input');
        error_log("Raw POST Data: " . $rawData);

        // Decode the JSON data
        $data = json_decode($rawData, true);

        // Check if JSON decoding was successful
        if ($data === null) {
            error_log("JSON decoding failed. Invalid JSON format.");
            return response()->json(["error" => "Invalid JSON format."], 400);
        }

        // Get transaction ID
        $transactionId = $data['transaction_id'] ?? null;

        if (!$transactionId) {
            error_log("Transaction ID not provided.");
            return response()->json(["error" => "Transaction ID not provided."], 400);
        }

        try {
            // Map currency codes to wallet column names
            $currencyMap = [
                "NGN" => "Naira",
                "USD" => "Dollar",
                "GBP" => "Pound",
                "ZAR" => "Rand",
                "GHS" => "Cedi",
                "EUR" => "Euro",
                "CNY" => "Yuan",
                "CAD" => "CAD",
                "KES" => "KES",
                "XAF" => "XAF",
            ];

            // Begin transaction
            DB::beginTransaction();

            // Step 1: Update trade status to 'Cancelled'
            $updated = DB::table('p2p_trades')
                ->where('transaction_id', $transactionId)
                ->update(['status' => 'Cancelled', 'updated_at' => now()]);

            if (!$updated) {
                DB::rollBack();
                return response()->json(["error" => "Trade not found or update failed."], 404);
            }

            // Step 2: Retrieve trade details
            $trade = DB::table('p2p_trades')
                ->where('transaction_id', $transactionId)
                ->first(['pay_amount', 'pay_currency', 'user_id']);

            if (!$trade) {
                DB::rollBack();
                return response()->json(["error" => "Trade details not found."], 404);
            }

            $payAmount = $trade->pay_amount;
            $payCurrency = $trade->pay_currency;
            $userId = $trade->user_id;

            // Step 3: Validate currency mapping
            $currencyColumn = $currencyMap[$payCurrency] ?? null;

            if (!$currencyColumn) {
                DB::rollBack();
                return response()->json(["error" => "Currency not supported."], 400);
            }

            // Step 4: Update wallet balance
            $updatedWallet = DB::table('wallet')
                ->where('user_id', $userId)
                ->increment($currencyColumn, $payAmount);

            if (!$updatedWallet) {
                DB::rollBack();
                return response()->json(["error" => "Failed to update wallet balance."], 500);
            }

            // Commit transaction
            DB::commit();

            // Return success response
            return response()->json([
                "success" => true,
                "transaction_id" => $transactionId,
                "status" => "Cancelled",
                "amount_returned" => $payAmount,
                "currency" => $currencyColumn,
                "data" => [
                    'transaction_id' => $transactionId,
                    'pay_amount' => $payAmount,
                    'pay_currency' => $payCurrency,
                    'user_id' => $userId,
                ]
            ], 200);

        } catch (\Exception $e) {
            // Rollback on error
            DB::rollBack();
            error_log("Error: " . $e->getMessage());
            return response()->json(["error" => "An error occurred. Please try again."], 500);
        }
    }
}
