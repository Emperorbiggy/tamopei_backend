<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class MapCountries extends Controller
{
    protected $secretKey;

    public function __construct()
    {
        // Retrieve the secret key from the config file
        $this->secretKey = Config::get('app.map_secret_key');
    }

    public function getCountries()
    {
        try {
            // Make the API request using Laravel's HTTP client
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->secretKey,
                'accept' => 'application/json',
            ])->get('https://sandbox.api.maplerad.com/v1/countries');

            // Check if the response was successful
            if ($response->successful()) {
                // Return the countries data
                return response()->json($response->json(), 200);
            } else {
                // Handle errors
                return response()->json([
                    'error' => 'Failed to fetch countries',
                    'details' => $response->json(),
                ], $response->status());
            }
        } catch (\Exception $e) {
            // Handle exceptions
            return response()->json([
                'error' => 'An error occurred',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
