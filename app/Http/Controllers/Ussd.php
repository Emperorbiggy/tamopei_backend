<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http; // Use Laravel's HTTP client for cURL requests
use Illuminate\Support\Facades\Log;

class Ussd extends Controller
{
    // Protected function to get access token
    protected function getAccessToken()
    {
        return app('AccessToken'); // Assuming 'AccessToken' is registered in the service container
    }

    // Function to get service categories using the access token
    public function getUssd()
    {
        $accessToken = $this->getAccessToken(); // Retrieve the access token

        // Log the access token being sent
        Log::info("Access Token Sent: " . $accessToken);

        if (!$accessToken) {
            return response()->json([
                'message' => 'Failed to retrieve access token.'
            ], 500);
        }

        // Perform the API request using Laravel's HTTP client
        $response = Http::withHeaders([
            'ClientID' => '9e0763f989fd3090583662e05117fdca',
            'accept' => 'application/json',
            'authorization' => "Bearer $accessToken", // Include the access token
        ])->get('https://api.safehavenmfb.com/ussd-payment/banks');

        // Log the real API response
        Log::info("API Response: " . $response->body());

        // Check if the request was successful
        if ($response->successful()) {
            return response()->json([
                'access_token' => $accessToken, // Return the access token
                'api_response' => $response->json(), // Return the API response data
            ], 200);
        }

        // Return error response if the API call failed
        return response()->json([
            'message' => 'Failed to fetch banks.',
            'error' => $response->json(),
            'access_token' => $accessToken, 
        ], $response->status());
    }
}
