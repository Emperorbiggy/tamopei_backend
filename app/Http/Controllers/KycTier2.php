<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class KycTier2 extends Controller
{
    private $secretKey;

    public function __construct()
    {
        // Retrieve the secret key from the config file
        $this->secretKey = Config::get('app.map_secret_key');
    }

    public function handleKycTier2Upload(Request $request)
    {
        // Validate the incoming request data
        $validated = $request->validate([
            'documentType' => 'required|string',
            'documentNumber' => 'required|string',
            'frontImage' => 'required|image|mimes:jpeg,png,jpg|max:2048', // Image validation for front side
            'backImage' => 'required|image|mimes:jpeg,png,jpg|max:2048',  // Image validation for back side
            'token' => 'required|string', // Token validation
        ]);

        // Get the token from request
        $token = $request->input('token');

        if (!$token) {
            return response()->json(['error' => 'No token provided'], 400);
        }

        try {
            // Fetch user data using the token (search by user_id field instead of id)
            $user = DB::table('user_credentials')->where('token', $token)->first();
            if (!$user) {
                return response()->json(['error' => 'Invalid token or user not found'], 400);
            }

            // Get country and other details from user data
            $countryCode = ($user->country === 'Nigeria') ? 'NG' : 'US';

            // Fetch customer_id from kyc_tier2 table using user_id (since user_id is the correct field)
            $customerId = DB::table('kyc_tier')->where('user_id', $user->user_id)->value('customer_id');
            if (!$customerId) {
                return response()->json(['error' => 'Customer ID not found in KYC table'], 400);
            }
        } catch (\Exception $e) {
            // Return the real error message if there is a database error
            return response()->json(['error' => 'Database error: ' . $e->getMessage()], 500);
        }

        // Ensure the uploads folder exists
        $uploadsPath = storage_path('app/public/uploads');
        if (!file_exists($uploadsPath)) {
            mkdir($uploadsPath, 0777, true); // Create the folder if it doesn't exist
        }

        // Handle file uploads for both front and back images
        $frontImage = $request->file('frontImage');
        $backImage = $request->file('backImage');

        try {
            // Store the uploaded files in the 'uploads' folder
            $frontImagePath = $frontImage->storeAs('uploads', 'front_' . time() . '.' . $frontImage->getClientOriginalExtension(), 'public');
            $backImagePath = $backImage->storeAs('uploads', 'back_' . time() . '.' . $backImage->getClientOriginalExtension(), 'public');
        } catch (\Exception $e) {
            // Handle file upload errors
            return response()->json(['error' => 'File upload error: ' . $e->getMessage()], 500);
        }

        // Create the full URL paths for the uploaded images
        $frontImageUrl = url('storage/' . $frontImagePath);
        $backImageUrl = url('storage/' . $backImagePath);

        // Prepare data for sending to the external API
        $requestData = [
            "identity" => [
                "type" => $validated['documentType'],
                "image" => $frontImageUrl,
                "number" => $validated['documentNumber'],
                "country" => $countryCode
            ],
            "customer_id" => $customerId
        ];

        try {
            // Send the data to the external API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])->patch('https://sandbox.api.maplerad.com/v1/customers/upgrade/tier2', $requestData);

            // Check if the response is successful
            if ($response->successful()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Documents uploaded and tier upgrade initiated successfully!',
                    'frontImageUrl' => $frontImageUrl,
                    'backImageUrl' => $backImageUrl,
                    'apiResponse' => $response->json(),
                ]);
            } else {
                // Handle unsuccessful API response
                return response()->json([
                    'error' => 'Failed to upgrade customer tier',
                    'apiError' => $response->json(),
                ], 500);
            }
        } catch (\Exception $e) {
            // Catch any errors that occur during the request to the external API
            return response()->json([
                'error' => 'An error occurred while making the API request',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
