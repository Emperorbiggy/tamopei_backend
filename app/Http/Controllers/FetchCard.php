<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class FetchCard extends Controller
{
    protected $secretKey;

    public function __construct()
    {
        // Retrieve the secret key from the config file
        $this->secretKey = Config::get('app.map_secret_key');
    }

    // This function returns the masked card details (masked_pan, expiry, and cvv)
    public function getCardDetails(Request $request)
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

        // Step 5: Extract the masked details
        $cardDetails = [
            'masked_pan' => $cardData['masked_pan'],
            'expiry' => $cardData['expiry'],
            'cvv' => $cardData['cvv'],
            'status' => $cardData['status'],
        ];

        // Step 6: Return the masked details
        return response()->json([
            'message' => 'Card details fetched successfully',
            'card_details' => $cardDetails,
        ], 200);
    }

    // This function returns the full card details (card_number, expiry, cvv, and balance without kobo)
    public function fetchCardDetails(Request $request)
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

        $fullCardDetails = [
            'card_number' => $cardData['card_number'],
            'expiry' => $cardData['expiry'],
            'cvv' => $cardData['cvv'],
            'balance' => $balanceWithoutKobo, // Balance after removing kobo (last two zeros)
            'status' => $cardData['status'],
            'name' => $cardData['name'],
        ];

        // Step 6: Return the full card details
        return response()->json([
            'message' => 'Card details fetched successfully',
            'card_details' => $fullCardDetails,
        ], 200);
    }
}
