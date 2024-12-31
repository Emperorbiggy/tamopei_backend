<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FetchBank extends Controller
{
    // Method to fetch the access token and fetch the banks
    public function fetchBanks()
    {
        // Retrieve the access token from the service container
        $accessToken = app('AccessToken');

        // API endpoint for the second request
        $url = 'https://api.safehavenmfb.com/transfers/banks';

        // Headers for the second request
        $headers = [
            'ClientID: 9e0763f989fd3090583662e05117fdca',
            'accept: application/json',
            'authorization: Bearer ' . $accessToken,
        ];

        // Initialize cURL for the second request
        $ch = curl_init($url);

        // Set cURL options for the second request
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Execute the second cURL session
        $response = curl_exec($ch);

        // Check for cURL errors in the second request
        if (curl_errno($ch)) {
            return response()->json(['error' => 'Curl error: ' . curl_error($ch)], 500);
        }

        // Close the second cURL session
        curl_close($ch);

        // Decode the JSON response
        $responseData = json_decode($response, true);

        // Check if decoding was successful
        if ($responseData === null) {
            return response()->json(['error' => 'Invalid JSON response'], 500);
        }

        // Extract the relevant data
        $bankData = $responseData['data'];

        // Return the bank data as a JSON response
        return response()->json(['status' => true, 'data' => $bankData]);
    }
}

