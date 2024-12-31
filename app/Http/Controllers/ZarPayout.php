<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZarPayout extends Controller
{
    public function resolveAccount(Request $request)
    {
        // Define the URL for the API endpoint
        $url = 'https://gate.klasapps.com/wallet/merchant/bank/transfer/request/resolve/account';

        // Fetch the Klasha token
        $klashaToken = app('KlashaToken');
        $cleanKlashaToken = trim($klashaToken, '"'); // Clean the token if it contains extra quotes

        // Log the token for debugging purposes
        Log::debug('Using Token:', ['token' => $cleanKlashaToken]);

        // Get data from the request (ensure the required parameters are present)
        $data = [
            'bankCode' => $request->input('bankCode'),  // Get bankCode from the request
            'countryCode' => $request->input('countryCode'),  // Get countryCode from the request
            'accountNumber' => $request->input('accountNumber'),  // Get accountNumber from the request
            'accountType' => $request->input('accountType'),  // Get accountType from the request
            'documentType' => $request->input('documentType'),  // Get documentType from the request
            'businessId' => $request->input('businessId'),  // Get businessId from the request
            'documentNumber' => $request->input('documentNumber')  // Get documentNumber from the request
        ];

        // Initialize cURL
        $ch = curl_init($url);

        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $cleanKlashaToken,
            'X-Forwarded-For: 91.134.244.67'
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));  // Send JSON body

        // Execute cURL request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            Log::error("Curl Error: " . curl_error($ch));  // Log cURL error
            return response()->json(['error' => curl_error($ch)], 500);
        }

        // Close cURL session
        curl_close($ch);

        // Log the raw response for debugging
        Log::debug('Raw API Response:', ['response' => $response]);

        // Return the token and raw response for debugging purposes
        return response()->json([
            'raw_response' => $response
        ]);
    }
}
