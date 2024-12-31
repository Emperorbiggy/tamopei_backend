<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Services extends Controller
{
    // Protected function to get access token
    protected function getAccessToken()
    {
        return app('AccessToken'); // Assuming 'AccessToken' is registered in the service container
    }

    // Function to get all services using the access token
    public function GetAllServices()
    {
        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve access token.',
            ], 500);
        }

        try {
            $response = Http::withHeaders([
                'ClientID' => '9e0763f989fd3090583662e05117fdca',
                'Accept' => 'application/json',
                'Authorization' => "Bearer $accessToken",
            ])->get('https://api.safehavenmfb.com/vas/services');

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json(),
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch services.',
                'error' => $response->json(),
            ], $response->status());

        } catch (\Exception $e) {
            Log::error("API Request Failed: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching services.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
