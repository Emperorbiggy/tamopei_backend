<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BillsProvider extends Controller
{
    // Protected function to get access token
    protected function getAccessToken()
    {
        return app('AccessToken'); // Assuming 'AccessToken' is registered in the service container
    }

    // Method to fetch provider details
    public function getProviderProducts(Request $request)
    {
        // Get the ID from the request
        $id = $request->input('id');

        if (!$id) {
            return response()->json([
                'success' => false,
                'message' => 'Provider ID is required',
            ], 400);
        }

        // Get the access token
        $accessToken = $this->getAccessToken();

        // API endpoint
        $url = "https://api.safehavenmfb.com/vas/service/{$id}/service-categories";

        try {
            // Make the GET request with headers
            $response = Http::withHeaders([
                'ClientID' => '9e0763f989fd3090583662e05117fdca',
                'Accept' => 'application/json',
                'Authorization' => "Bearer $accessToken",
            ])->get($url);

            // Check if the request was successful
            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'data' => $response->json(),
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch provider products',
                    'error' => $response->json(),
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching provider products',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
