<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class CreateCard extends Controller
{
    protected $secretKey;

    public function __construct()
    {
        // Retrieve the secret key from the config file
        $this->secretKey = Config::get('app.map_secret_key');
    }

    public function createCard(Request $request)
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

        // Step 3: Check if the user has at least 4 USD in their wallet
        $walletBalance = DB::table('wallet')->where('user_id', $user->user_id)->value('Dollar');

        if ($walletBalance < 4) {
            return response()->json(['error' => 'Insufficient balance. You need at least 4 USD to create a card.'], 400);
        }

        // Step 4: Retrieve the user_id and fetch customer_id from 'kyc_tier' table
        $customerId = DB::table('kyc_tier')->where('user_id', $user->user_id)->value('customer_id');

        if (!$customerId) {
            return response()->json(['error' => 'Customer ID not found'], 404);
        }

        // Step 5: Make the cURL POST request to create a card
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post('https://sandbox.api.maplerad.com/v1/issuing', [
            'customer_id' => $customerId,
            'currency' => 'USD',
            'type' => 'VIRTUAL',
            'auto_approve' => true,
            'brand' => 'MASTERCARD',
            'amount' => 200,
        ]);

        if (!$response->successful()) {
            return response()->json([
                'error' => 'Failed to create card',
                'details' => $response->json(),
            ], $response->status());
        }

        $responseData = $response->json();
        $reference = $responseData['data']['reference'];

        // Step 6: Fetch the card details using the reference
        $cardDetailsResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'accept' => 'application/json',
        ])->get("https://sandbox.api.maplerad.com/v1/issuing/{$reference}");

        if (!$cardDetailsResponse->successful()) {
            return response()->json([
                'error' => 'Failed to fetch card details',
                'details' => $cardDetailsResponse->json(),
            ], $cardDetailsResponse->status());
        }

        $cardData = $cardDetailsResponse->json()['data'];

        // Step 7: Insert card details into the virtual_card_holder table
        DB::table('virtual_card_holder')->insert([
            'id' => $cardData['id'],
            'user_id' => $user->user_id,
            'customer_id' => $customerId,
            'name' => $cardData['name'],
            'masked_pan' => $cardData['masked_pan'],
            'expiry' => $cardData['expiry'],
            'cvv' => $cardData['cvv'],
            'status' => $cardData['status'],
            'type' => $cardData['type'],
            'issuer' => $cardData['issuer'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Step 8: Deduct 4 USD from the user's wallet balance
        $newBalance = $walletBalance - 4;

        DB::table('wallet')
            ->where('user_id', $user->user_id)
            ->update(['Dollar' => $newBalance]);

        // Step 9: Return success response
        return response()->json([
            'message' => 'Card created successfully and 4 USD deducted from your wallet',
            'card_details' => $cardData,
        ], 200);
    }
}
