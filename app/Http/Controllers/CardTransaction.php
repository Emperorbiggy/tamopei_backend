<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class CardTransaction extends Controller
{
    protected $secretKey;

    public function __construct()
    {
        // Retrieve the secret key from the config file
        $this->secretKey = Config::get('app.map_secret_key');
    }

    public function getCardTransactions(Request $request)
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

        // Step 4: Prepare the URL and headers for the cURL request
        $url = "https://sandbox.api.maplerad.com/v1/issuing/{$cardId}/transactions";
        $headers = [
            'Authorization' => 'Bearer ' . $this->secretKey,
            'accept' => 'application/json',
        ];

        // Fetch the card transactions from the API
        $transactionsResponse = Http::withHeaders($headers)->get($url);

        if (!$transactionsResponse->successful()) {
            return response()->json([
                'error' => 'Failed to fetch card transactions',
                'details' => $transactionsResponse->json(),
                'url' => $url,
                'headers' => $headers,
            ], $transactionsResponse->status());
        }

        // Step 5: Return the list of transactions along with URL and headers
        $transactions = $transactionsResponse->json()['data'];

        return response()->json([
            'message' => 'Card transactions fetched successfully',
            'transactions' => $transactions,
        ], 200);
    }
}
