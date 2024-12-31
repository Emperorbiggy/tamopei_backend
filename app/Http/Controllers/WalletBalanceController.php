<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletBalanceController extends Controller
{
    /**
     * Get wallet balance for a specific user and currency.
     */
    public function getBalance(Request $request)
    {
        // Log incoming request data for debugging purposes
        Log::info('Incoming request to get wallet balance', $request->all());

        // Validate the incoming request for the token and currency
        $request->validate([
            'token' => 'required|string',
            'currency' => 'required|string',
        ]);

        $token = $request->input('token');
        $currency = $request->input('currency');

        // Define valid currencies
        $validCurrencies = ['Rand', 'Cedi', 'Dollar', 'Naira', 'Pound', 'Euro', 'Yuan', 'CAD', 'KES', 'XAF'];

        if (!in_array($currency, $validCurrencies)) {
            return response()->json(['success' => false, 'message' => 'Invalid currency'], 400);
        }

        try {
            // Retrieve user ID using the token
            $user = DB::table('user_credentials')->where('token', $token)->first(['user_id']);

            if (!$user) {
                // Return an error if the token is invalid
                return response()->json(['success' => false, 'message' => 'Invalid token'], 404);
            }

            $user_id = $user->user_id;

            // Retrieve the user's balance in the specified currency
            $balance = DB::table('wallet')->where('user_id', $user_id)->value($currency);

            if ($balance !== null) {
                // Return success with the balance
                return response()->json(['success' => true, 'balance' => $balance], 200);
            } else {
                // Return an error if the balance is not found
                return response()->json(['success' => false, 'message' => 'Balance not found for this user'], 404);
            }

        } catch (\Exception $e) {
            // Log and return a generic error
            Log::error("Error retrieving wallet balance: " . $e->getMessage(), ['exception' => $e]);

            return response()->json(['success' => false, 'message' => 'An unexpected error occurred'], 500);
        }
    }
}
