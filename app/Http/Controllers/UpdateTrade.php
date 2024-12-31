<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateTrade extends Controller
{
    public function handleTrade(Request $request)
    {
        $responseDebug = []; // To store debug information

        $data = $request->validate([
            'payCurrency' => 'required|string',
            'pay_id' => 'required',
            'payamount' => 'required|numeric',
            'amount' => 'required|numeric',
            'receiveCurrency' => 'required|string',
            'receiving' => 'required|numeric',
            'token' => 'required|string',
            'trade_fee' => 'required|numeric',
            'transaction_id' => 'required|string',
        ]);

        $responseDebug['request_data'] = $data;

        $payCurrency = $data['payCurrency'];
        $pay_id = $data['pay_id'];
        $payAmount = $data['payamount'];
        $amount = $data['amount'];
        $receiveCurrency = $data['receiveCurrency'];
        $receiving = $data['receiving'];
        $token = $data['token'];
        $tradeFee = $data['trade_fee'];
        $transactionId = $data['transaction_id'];

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

        try {
            DB::beginTransaction();

            // Check if trade exists with row locking
            $trade = DB::table('p2p_trades')->where('transaction_id', $transactionId)->lockForUpdate()->first();

            if (!$trade) {
                $responseDebug['trade_check'] = 'Trade not found';
                return response()->json(["error" => "Trade not found.", "debug" => $responseDebug], 404);
            }
            $responseDebug['trade_data'] = (array)$trade;

            // Check if receiving exceeds limit_max
            if ($receiving > $trade->limit_max) {
                $responseDebug['limit_check'] = 'Amount exceeds maximum limit';
                return response()->json(["error" => "Amount exceeds maximum limit.", "debug" => $responseDebug], 400);
            }

            // Fetch trader_id from p2p_trades table using transaction_id
            $trader_id = $trade->user_id;
            $responseDebug['trader_id'] = $trader_id;

            // Fetch user_id using token
            $user = DB::table('user_credentials')->where('token', $token)->first();
            if (!$user) {
                $responseDebug['user_check'] = 'Invalid token';
                return response()->json(["error" => "Invalid token.", "debug" => $responseDebug], 404);
            }

            $payUserId = $user->user_id;
            $responseDebug['payUserId'] = $payUserId;

            // Check balances in wallet table
            $payColumn = $currencyMap[$payCurrency];
            $receiveColumn = $currencyMap[$receiveCurrency];

            $payUserWallet = DB::table('wallet')->where('user_id', $pay_id)->first();
            $traderWallet = DB::table('wallet')->where('user_id', $trader_id)->first();

            if (!$payUserWallet || !$traderWallet) {
                $responseDebug['wallet_check'] = 'Wallet not found';
                return response()->json(["error" => "Wallet not found for one or both users.", "debug" => $responseDebug], 404);
            }

            if ($payUserWallet->$payColumn < $payAmount) {
                $responseDebug['payer_balance'] = 'Insufficient balance';
                return response()->json(["error" => "Insufficient balance in payer's wallet.", "debug" => $responseDebug], 400);
            }

            if ($traderWallet->$receiveColumn < $receiving) {
                $responseDebug['trader_balance'] = 'Insufficient balance';
                return response()->json(["error" => "Insufficient balance in trader's wallet.", "debug" => $responseDebug], 400);
            }

            // Perform wallet updates
            DB::table('wallet')
                ->where('user_id', $pay_id)
                ->decrement($payColumn, $amount);

            DB::table('wallet')
                ->where('user_id', $trader_id)
                ->increment($payColumn, $payAmount);

            DB::table('wallet')
                ->where('user_id', $trader_id)
                ->decrement($receiveColumn, $receiving);

            DB::table('wallet')
                ->where('user_id', $pay_id)
                ->increment($receiveColumn, $receiving);

            // Update trade based on limits
            if (abs($receiving - $trade->limit_max) < 1e-12) { // Use epsilon for comparison
                DB::table('p2p_trades')->where('transaction_id', $transactionId)->update([
                    'limit_max' => 0,
                    'limit_min' => 0,
                    'status' => 'Completed',
                ]);
            } elseif ($receiving < $trade->limit_min) {
                DB::rollBack();
                $responseDebug['limit_check'] = 'receiving cannot be less than limit_min';
                return response()->json(["error" => "receiving cannot be less than limit_min.", "debug" => $responseDebug], 400);
            } else {
                $newLimitMax = $trade->limit_max - $receiving;
                DB::table('p2p_trades')->where('transaction_id', $transactionId)->update([
                    'limit_max' => $newLimitMax,
                ]);
            }

            // Insert the fee into p2p_fee table
            DB::table('p2p_fee')->insert([
                $currencyMap[$payCurrency] => $tradeFee,
            ]);

            DB::commit();

            // Final check for limit_max and update status
            $updatedTrade = DB::table('p2p_trades')->where('transaction_id', $transactionId)->first();
            if (abs($updatedTrade->limit_max) < 1e-12) { // Use epsilon for comparison
                DB::table('p2p_trades')->where('transaction_id', $transactionId)->update([
                    'status' => 'Completed',
                ]);
            }

            $responseDebug['status'] = 'Transaction completed successfully';
            return response()->json([
                "success" => true,
                "message" => "Trade successfully updated.",
                "debug" => $responseDebug,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error updating trade: " . $e->getMessage());
            $responseDebug['error'] = $e->getMessage();
            return response()->json([
                "error" => "An error occurred while processing the trade.",
                "debug" => $responseDebug,
            ], 500);
        }
    }
}
