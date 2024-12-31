<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http; // Use Laravel's HTTP client for cURL requests
use Illuminate\Support\Facades\Log;

class BillCableVerify extends Controller
{
    // Protected function to get access token
    protected function getAccessToken()
    {
        // Assuming you have a service or method to fetch the access token
        $accessToken = app('AccessToken'); // You can replace this with a direct call to fetch token from an external service if needed
        return $accessToken;
    }

    // Method to fetch provider details
    public function getProviderProducts(Request $request)
    {
        // Get the ID and entity number from the request
        $id = $request->input('id');
        $number = $request->input('number');

        // Check if both ID and number are provided
        if (!$id || !$number) {
            return response()->json([
                'success' => false,
                'message' => 'Provider ID and Entity Number are required',
            ], 400);
        }

        // Get the access token
        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve access token',
            ], 500);
        }

        // Perform the API request using Laravel's HTTP client with Bearer token
        try {
            $response = Http::withHeaders([
                'ClientID' => '9e0763f989fd3090583662e05117fdca',
                'Accept' => 'application/json',
                'Authorization' => "Bearer $accessToken", // Use the Bearer token
                'Content-Type' => 'application/json',
            ])->post('https://api.safehavenmfb.com/vas/verify', [
                'serviceCategoryId' => $id,
                'entityNumber' => $number,
            ]);

            // Log the request and response for debugging
            Log::info("Request Data: ", ['serviceCategoryId' => $id, 'entityNumber' => $number]);
            Log::info("API Response: " . $response->body());

            // Check if the request was successful
            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $id,
                        'number' => $number,
                    ],
                    'api_response' => $response->json(),
                ], 200);
            }

            // If the API call failed, return the error response from the API
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify details',
                'error' => $response->json(),
                 'id' => $id,
                'number' => $number,
            ], $response->status());

        } catch (\Exception $e) {
            // Log and return exception errors if the request fails
            Log::error("API Request Failed: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while verifying the provider',
                 'id' => $id,
                'number' => $number,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
