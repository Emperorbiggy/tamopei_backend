<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class MapMOMO extends Controller
{
    protected $secretKey;

    public function __construct()
    {
        // Retrieve the secret key from the config file
        $this->secretKey = Config::get('app.map_secret_key');
    }

    public function getInstitutions(Request $request)
    {
        // Validate the request input
        $request->validate([
            'country' => 'required|string',
        ]);

        // Get the country code from the request
        $countryCode = $request->input('country');

        try {
            // Make the API request using Laravel's HTTP client
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'Accept' => 'application/json',
            ])->get('https://sandbox.api.maplerad.com/v1/institutions', [
                'country' => $countryCode,
                'type' => 'MOMO',
            ]);

            // Check if the response was successful
            if ($response->successful()) {
                // Return the institutions data
                return response()->json($response->json(), 200);
            } 

            // Handle errors
            return response()->json([
                'error' => 'Failed to fetch institutions',
                'details' => $response->json(),
            ], $response->status());
        } catch (\Exception $e) {
            // Handle exceptions
            return response()->json([
                'error' => 'An error occurred',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
