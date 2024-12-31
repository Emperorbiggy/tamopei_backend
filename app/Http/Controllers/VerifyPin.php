<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;

class VerifyPin extends Controller
{
    public function verify(Request $request)
    {
        // Validate the request input
        $request->validate([
            'token' => 'required|string',
            'pin' => 'required|string',
        ]);

        // Retrieve input data
        $token = $request->input('token');
        $pin = $request->input('pin');

        try {
            // Query the database to check if a user with the given token and pin exists
            $user = DB::table('user_credentials')
                ->where('token', $token)
                ->where('pin', $pin)
                ->first();

            // Check if a matching user is found
            if ($user) {
                // PIN is correct
                return response()->json([
                    'success' => true,
                    'message' => 'PIN successfully authenticated.',
                ]);
            } else {
                // Incorrect PIN or token
                return response()->json([
                    'success' => false,
                    'message' => 'Incorrect PIN or authentication failed.',
                ]);
            }
        } catch (\Exception $e) {
            // Handle any database errors
            return response()->json([
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
