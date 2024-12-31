<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class FundCard extends Controller
{
    protected $secretKey;

    public function __construct()
    {
        // Retrieve the secret key from the config file
        $this->secretKey = Config::get('app.map_secret_key');
    }

    public function fundCard(Request $request)
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

        // Step 4: Retrieve the amount from the request data
        $amount = $request->input('amount');
        
        if (!$amount) {
            return response()->json(['error' => 'Amount is required'], 400);
        }

        // Convert the amount to Kobo (multiply by 100)
        $amountInKobo = $amount * 100;

        // Step 5: Check if the user has sufficient balance in the wallet
        $walletBalance = DB::table('wallet')->where('user_id', $user->user_id)->value('Dollar');

        // The required balance is the requested amount + 0.60 USD (60 cents)
        $requiredBalance = $amount + 0.60; // Add 0.6 USD to the amount

        if ($walletBalance < $requiredBalance) {
            return response()->json(['error' => 'Insufficient balance. Please fund your wallet.'], 400);
        }

        // Step 6: Fund the card using the API
        $fundCardResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post("https://sandbox.api.maplerad.com/v1/issuing/{$cardId}/fund", [
            'amount' => $amountInKobo,
        ]);

        // Step 7: Check if the API request was successful
        if (!$fundCardResponse->successful()) {
            return response()->json([
                'error' => 'Failed to fund the card',
                'details' => $fundCardResponse->json(),
            ], $fundCardResponse->status());
        }

        // Step 8: Deduct the total amount (requested amount + 0.60 USD) from the wallet balance
        $newBalance = $walletBalance - $requiredBalance;

        DB::table('wallet')
            ->where('user_id', $user->user_id)
            ->update(['Dollar' => $newBalance]);

        // Step 9: Return success response
        return response()->json([
            'message' => 'Card funded successfully',
            'details' => $fundCardResponse->json(),
        ], 200);
    }
}
