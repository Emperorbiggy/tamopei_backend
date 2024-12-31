<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchTrades extends Controller
{
    // Fetch all trades related to the user
    public function fetchUsersTrades(Request $request)
    {
        // Log raw input data for debugging
        $rawData = $request->getContent();
        Log::info("Raw POST Data: " . $rawData);

        // Decode the JSON data
        $data = json_decode($rawData, true);

        // Check if JSON decoding was successful
        if ($data === null) {
            Log::error("JSON decoding failed. Invalid JSON format.");
            return response()->json(["error" => "Invalid JSON format."], 400);
        }

        // Check if token is provided and log it
        if (!isset($data['token'])) {
            Log::error("Token is missing in the request data.");
            return response()->json(["error" => "Token is required."], 400);
        }

        $token = $data['token'];
        Log::info("Received token: " . $token);

        try {
            // Step 1: Fetch user_id using token
            $user = DB::table('user_credentials')
                ->where('token', $token)
                ->select('user_id')
                ->first();

            if (!$user) {
                Log::error("Invalid token or user not found for token: " . $token);
                return response()->json(["error" => "Invalid token or user not found."], 404);
            }

            $user_id = $user->user_id;
            Log::info("User ID fetched for token: " . $user_id);

            // Step 2: Fetch all trades related to this user
            $trades = DB::table('p2p_trades')
                ->where('user_id', $user_id)
                ->orderByDesc('created_at')
                ->get();

            if ($trades->isNotEmpty()) {
                // Prepare all trades data
                $tradeDataArray = $trades->map(function ($trade) {
                    return [
                        "id" => $trade->transaction_id,
                        "date" => date("Y-m-d", strtotime($trade->created_at)),
                        "time" => date("H:i:s", strtotime($trade->created_at)),
                        "up_date" => date("Y-m-d", strtotime($trade->updated_at)),
                        "up_time" => date("H:i:s", strtotime($trade->updated_at)),
                        "payCurrency" => $trade->pay_currency,
                        "receiveCurrency" => $trade->receive_currency,
                        "receiveAmount" => $trade->receive_amount,
                        "payAmount" => $trade->pay_amount,
                        "rate" => number_format($trade->custom_rate, 2) . "NGN",
                        "limits" => [(int)$trade->limit_min, (int)$trade->limit_max],
                        "available" => (int)$trade->limit_max,
                        "status" => $trade->status,
                    ];
                });

                Log::info("Trade Data Array: " . json_encode($tradeDataArray));

                return response()->json([
                    "success" => true,
                    "data" => $tradeDataArray,
                ], 200);
            } else {
                Log::warning("No trades found for user_id: " . $user_id);
                return response()->json([
                    "success" => false,
                    "message" => "No trades found for the user.",
                ], 404);
            }
        } catch (\Exception $e) {
            Log::error("Error fetching trades: " . $e->getMessage());
            return response()->json(["error" => "An error occurred while fetching the data."], 500);
        }
    }

    public function fetchMarketTrades(Request $request)
    {
        // Log raw input data for debugging
        $rawData = $request->getContent();
        Log::info("Raw POST Data: " . $rawData);

        // Decode the JSON data
        $data = json_decode($rawData, true);

        // Check if JSON decoding was successful
        if ($data === null) {
            Log::error("JSON decoding failed. Invalid JSON format.");
            return response()->json(["error" => "Invalid JSON format."], 400);
        }

        // Check if token is provided and log it
        if (!isset($data['token'])) {
            Log::error("Token is missing in the request data.");
            return response()->json(["error" => "Token is required."], 400);
        }

        $token = $data['token'];
        Log::info("Received token: " . $token);

        try {
            // Step 1: Fetch user_id using token
            $user = DB::table('user_credentials')
                ->where('token', $token)
                ->select('user_id')
                ->first();

            if (!$user) {
                Log::error("Invalid token or user not found for token: " . $token);
                return response()->json(["error" => "Invalid token or user not found."], 404);
            }

            $user_id = $user->user_id;
            Log::info("User ID fetched for token: " . $user_id);

            // Step 2: Fetch all market trades, excluding trades with the user_id from the token
            $marketTrades = DB::table('p2p_trades')
                ->where('user_id', '!=', $user_id) // Exclude trades that belong to the user
                ->orderByDesc('created_at')
                ->get();

            if ($marketTrades->isNotEmpty()) {
                // Prepare market trade data
                $marketTradeDataArray = $marketTrades->map(function ($trade) {
                    // Swap pay_currency and receive_currency
                    $payCurrency = $trade->receive_currency;
                    $receiveCurrency = $trade->pay_currency;
                    $payAmount = $trade->receive_amount;
                    $receiveAmount = $trade->pay_amount;

                    // Remove unnecessary decimals from custom_rate
                    $customRate = rtrim(rtrim((string) $trade->custom_rate, '0'), '.');

                    // Adjust limits based on the provided logic
                    $adjustedLimitMin = $trade->limit_min;
                    $adjustedLimitMax = $trade->limit_max;

                    // Use the original market_rate from the table
                    $marketRate = $trade->market_rate;

                    if (
                        $trade->receive_currency === 'NGN' ||
                        ($trade->receive_currency === 'XOF' && $trade->pay_currency !== 'NGN') ||
                        ($trade->receive_currency === 'XAF' && $trade->pay_currency !== 'NGN') ||
                        ($trade->receive_currency === 'GHS' && $trade->pay_currency === 'USD') ||
                        ($trade->receive_currency === 'KES' &&
                         ($trade->pay_currency === 'USD' || $trade->pay_currency === 'GHS'))
                    ) {
                        // Perform multiplication and keep the market rate calculation as addition
                        $adjustedLimitMin = $marketRate * $trade->limit_min;
                        $adjustedLimitMax = $marketRate * $trade->limit_max;

                        // Calculate market rate using addition
                        $marketOrgRate = (float) $customRate + ((float) $customRate * 0.01);
                        // $available =  $trade->limit_max + ($trade->limit_max * 0.01);
                       
                    } else {
                        // Perform division and change the market rate calculation to subtraction
                        $adjustedLimitMin = $trade->limit_min / $marketRate;
                        $adjustedLimitMax = $trade->limit_max / $marketRate;
                        // $available =  $trade->limit_max + ($trade->limit_max * 0.01);

                        // Calculate market rate using subtraction
                        $marketOrgRate = (float) $customRate - ((float) $customRate * 0.01);
                    }
                   
                    // Use the original market_rate from the table
                   

                    // Return the formatted trade data
                    return [
                        "id" => $trade->id,
                        "transaction_id" => $trade->transaction_id,
                        "user_id" => $trade->user_id,
                        "custom_rate" => number_format((float) $customRate, 2), // formatted custom rate
                        "market_rate" => $marketOrgRate, // formatted market rate
                        "limit_min" => $adjustedLimitMin,
                        "limit_max" => $adjustedLimitMax,
                        "pay_amount" => $payAmount,
                        "pay_currency" => $payCurrency,
                        "rate_increase_percentage" => $trade->rate_increase_percentage,
                        "receive_amount" => $receiveAmount,
                        "receive_currency" => $receiveCurrency,
                        "status" => $trade->status,
                        "created_at" => $trade->created_at,
                        "updated_at" => $trade->updated_at,
                        "target" => $trade->target,
                        "source" => $trade->source,
                        "available" => $trade->limit_max,
                    ];
                });
                Log::info("Market Trade Data Array: " . json_encode($marketTradeDataArray));

                // Return the response with the user_id, token, and market trade data
                return response()->json([
                    "success" => true,
                    "user_id" => $user_id,  // Return the user_id fetched from the token
                    "token" => $token,  // Return the token
                    "data" => $marketTradeDataArray,
                ], 200);
            } else {
                Log::warning("No market trades found.");
                return response()->json([
                    "success" => false,
                    "message" => "No market trades found.",
                    "user_id" => $user_id,  // Return the user_id fetched from the token
                    "token" => $token,  // Return the token
                ], 404);
            }
        } catch (\Exception $e) {
            Log::error("Error fetching market trades: " . $e->getMessage());
            return response()->json(["error" => "An error occurred while fetching the data."], 500);
        }
    }
}
