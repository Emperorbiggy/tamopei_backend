<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class UnfreezeCard extends Controller
{
    protected $secretKey;

    public function __construct()
    {
        // Retrieve the secret key from the config file
        $this->secretKey = Config::get('app.map_secret_key');
    }

    public function unfreezeCard(Request $request)
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

        // Step 4: Unfreeze the card by making a PATCH request to the API
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'accept' => 'application/json',
        ])->patch("https://sandbox.api.maplerad.com/v1/issuing/{$cardId}/unfreeze");

        // Step 5: Check if the request was successful
        if ($response->successful()) {
            return response()->json([
                'message' => 'Card unfrozen successfully',
                'data' => $response->json(),
            ], 200);
        }

        // If the request failed, return the error response from the API
        return response()->json([
            'error' => 'Failed to unfreeze card',
            'details' => $response->json(),
        ], $response->status());
    }
}
