<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FetchUserDetails extends Controller
{
    /**
     * Fetch user details based on token.
     */
    public function fetchUserWithToken(Request $request)
    {
        // Validate that the token is provided
        $request->validate([
            'token' => 'required|string',
        ]);

        $token = $request->input('token');

        try {
            // Query the user_credentials table to find a user with the provided token
            $user = DB::table('user_credentials')->where('token', $token)->first();

            if ($user) {
                // Return a successful response with all user details if the token is valid
                return response()->json([
                    'success' => true,
                    'user' => $user,
                ], 200);
            } else {
                // Return an error response if the token is invalid or user not found
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token or user not found.',
                ], 404);
            }
        } catch (\Exception $e) {
            // Log the error and return a server error response
            Log::error('Database error during token verification: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
            ], 500);
        }
    }

    /**
     * Fetch user account details based on token.
     */
    public function UsersAccountDetails(Request $request)
    {
        // Validate that the token is provided
        $request->validate([
            'token' => 'required|string',
        ]);

        $token = $request->input('token');

        try {
            // Query the user_credentials table to find a user with the provided token
            $user = DB::table('user_credentials')
                ->where('token', $token)
                ->first();

            // Check if the user exists
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid token or user not found.',
                ], 404);
            }

            $userId = $user->user_id;

            // Fetch account details from virtual_accounts using the user_id
            $accountDetails = DB::table('virtual_accounts')
                ->select(
                    'virtual_account_id',
                    'accountName',
                    'accountNumber',
                    'accountProduct',
                    'accountType',
                    'user_id',
                    'bankName'
                )
                ->where('user_id', $userId)
                ->get();

            // Return the account details
            return response()->json([
                'status' => 'success',
                'data' => $accountDetails
            ], 200);
        } catch (\Exception $e) {
            // Log any errors for debugging
            Log::error('Error fetching user account details: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching account details.'
            ], 500);
        }
    }
}
