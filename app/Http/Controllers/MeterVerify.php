<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MeterVerify extends Controller
{
    // Protected function to get access token
    protected function getAccessToken()
    {
        return app('AccessToken'); // Assuming 'AccessToken' is registered in the service container
    }

    // Method to verify meter
    public function verifyMeter(Request $request)
    {
        // Get ID and smart card number from the request
        $id = $request->input('id');
        $smartCardNumber = $request->input('smartCardNumber');

        if (!$id || !$smartCardNumber) {
            return response()->json([
                'success' => false,
                'message' => 'Provider ID and Smart Card Number are required',
            ], 400);
        }

        // Get the access token
        $accessToken = $this->getAccessToken();

        // API endpoint
        $url = "https://safehavenmfb.com/vas/service-category/{$id}/products";

        try {
            // Make the GET request with headers
            $response = Http::withHeaders([
                'ClientID' => '9e0763f989fd3090583662e05117fdca',
                'Accept' => 'application/json',
                'Authorization' => "Bearer $accessToken",
            ])->get($url, [
                'smartCardNumber' => $smartCardNumber, // Attach smart card number as a query parameter
            ]);

            // Check if the request was successful
            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to verify meter',
                    'error' => $response->json(),
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while verifying the meter',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
