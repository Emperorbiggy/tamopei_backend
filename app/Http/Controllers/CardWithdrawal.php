<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class CardWithdrawal extends Controller
{
    protected $secretKey;

    public function __construct()
    {
        // Retrieve the secret key from the config file
        $this->secretKey = Config::get('app.map_secret_key');
    }

    public function withdraw(Request $request)
    {
        // Step 1: Retrieve the token from the request data
        $token = $request->input('token');
        
        if (!$token) {
            return response()->json(['error' => 'Token is required'], 400);
        }

        // Step 2: Fetch user using the token from 'user_credentials' table
        $user = DB::table('user_credentials')->where('token', $token)->first();

        if (!$user) {
            return response()->json(['error' => 'Invalid token or user not found'], 404);
        }

        // Step 3: Fetch the card ID using the user_id from 'virtual_card_holder' table
        $cardId = DB::table('virtual_card_holder')->where('user_id', $user->user_id)->value('id');

        if (!$cardId) {
            return response()->json(['error' => 'No virtual card found for the user'], 404);
        }

        // Step 4: Fetch the card details from the API
        $cardDetailsResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'accept' => 'application/json',
        ])->get("https://sandbox.api.maplerad.com/v1/issuing/{$cardId}");

        if (!$cardDetailsResponse->successful()) {
            return response()->json([
                'error' => 'Failed to fetch card details',
                'details' => $cardDetailsResponse->json(),
            ], $cardDetailsResponse->status());
        }

        $cardData = $cardDetailsResponse->json()['data'];

        // Step 5: Extract the full card details and balance (removing kobo, i.e., last two zeros)
        $balance = $cardData['balance'];
        // Remove the last two zeros from the balance (i.e., assume the balance is in Kobo and divide by 100)
        $balanceWithoutKobo = (int)($balance / 100);

        // Step 6: Retrieve the withdrawal amount from the request data
        $withdrawAmount = $request->input('amount');

        if (!$withdrawAmount) {
            return response()->json(['error' => 'Withdrawal amount is required'], 400);
        }

        // Step 7: Add 2 to the balance if the requested withdrawal amount exceeds the current balance
        if ($withdrawAmount > $balanceWithoutKobo + 2) {
            return response()->json(['error' => 'Insufficient funds for the withdrawal'], 400);
        }

        // Step 8: Proceed to withdraw the amount from the virtual card
        $convertedAmount = $withdrawAmount * 100;  // Convert to Kobo

        $withdrawResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post("https://sandbox.api.maplerad.com/v1/issuing/{$cardId}/withdraw", [
            'amount' => $convertedAmount,
        ]);

        if (!$withdrawResponse->successful()) {
            return response()->json([
                'error' => 'Failed to withdraw from the card',
                'details' => $withdrawResponse->json(),
            ], $withdrawResponse->status());
        }

        // Step 9: Add the withdrawn amount to the user's wallet balance
        $walletBalance = DB::table('wallet')->where('user_id', $user->user_id)->value('Dollar');
        
        // Add the withdrawn amount to the wallet (in USD, so we divide by 100)
        $newWalletBalance = $walletBalance + ($withdrawAmount); 

        DB::table('wallet')
            ->where('user_id', $user->user_id)
            ->update(['Dollar' => $newWalletBalance]);

        // Step 10: Return success response
        return response()->json([
            'message' => 'Withdrawal successful and funds added to wallet',
            'withdrawn_amount' => $withdrawAmount,
            'new_wallet_balance' => $newWalletBalance,
        ], 200);
    }
}
