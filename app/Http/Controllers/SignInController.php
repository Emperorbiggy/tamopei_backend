<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;  // Use Guzzle for HTTP requests

class SignInController extends Controller
{
    public function signIn(Request $request)
    {
        // Set appropriate headers for CORS
        return response()->json([], 200)->header("Access-Control-Allow-Origin", "*")
                                        ->header("Access-Control-Allow-Methods", "POST, GET, OPTIONS")
                                        ->header("Access-Control-Allow-Headers", "Content-Type, Authorization, X-Requested-With");
    }

    public function postSignIn(Request $request)
    {
        // Validate email input
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);

        // Log the email
        Log::info("Received email: $email");

        try {
            // Check if the user exists in the user_credentials table
            $user = DB::table('user_credentials')->where('email', $email)->first();

            if ($user) {
                $token = $user->token;

                // If token is empty, generate a new one
                if (empty($token)) {
                    // Get the secret key from the .env file
                    $secretKey = env('SECRET_KEY');  // Fetch the secret key from .env file

                    // Generate a new token using Guzzle HTTP client
                    $client = new Client();
                    $response = $client->post('https://easinovation.com.ng/key/create/', [
                        'form_params' => [
                            'secret_key' => $secretKey,
                        ]
                    ]);

                    if ($response->getStatusCode() == 200) {
                        $responseData = json_decode($response->getBody()->getContents(), true);
                        $token = $responseData['token'];

                        // Update the user with the new token
                        DB::table('user_credentials')
                            ->where('email', $email)
                            ->update(['token' => $token]);

                        // Get user_id
                        $userId = DB::table('user_credentials')->where('email', $email)->value('user_id');

                        // Update wallet balances
                        DB::table('wallet')->where('user_id', $userId)->update([
                            'Euro' => 0.00,
                            'Yuan' => 0.00,
                            'CAD' => 0.00,
                            'KES' => 0.00,
                            'XAF' => 0.00
                        ]);

                        return response()->json([
                            'success' => true,
                            'message' => 'Old user, token created and wallet updated.',
                            'email' => $email,
                            'token' => $token,
                        ]);
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'Failed to generate token.',
                        ], 500);
                    }
                } else {
                    return response()->json([
                        'success' => true,
                        'message' => 'User found with existing token.',
                        'email' => $email,
                        'token' => $token,
                    ]);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'User not registered.',
                ], 404);
            }
        } catch (\Exception $e) {
            Log::error('Error during sign-in: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.',
            ], 500);
        }
    }
}
