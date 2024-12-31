<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Vs2SignInController extends Controller
{
    /**
     * Handle user sign-in with token and PIN authentication.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function signIn(Request $request)
    {
        // Validate incoming request data
        $validatedData = $request->validate([
            'token' => 'required|string',
            'pin' => 'required|string',
        ]);

        $token = $validatedData['token'];
        $pin = $validatedData['pin'];

        try {
            // Check if user exists with the provided token and pin
            $user = DB::table('user_credentials')
                ->where('token', $token)
                ->where('passcode', $pin)
                ->first();

            if ($user) {
                // User found and PIN is correct
                $response = [
                    'success' => true,
                    'message' => 'PIN successfully authenticated.',
                ];
                return response()->json($response, 200);
            } else {
                // Incorrect token or PIN
                $response = [
                    'success' => false,
                    'message' => 'Incorrect PIN or authentication failed.',
                ];
                return response()->json($response, 401);
            }
        } catch (\Exception $e) {
            // Handle any database errors
            Log::error('Database error: ' . $e->getMessage());
            $response = [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage(),
            ];
            return response()->json($response, 500);
        }
    }
}
