<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ZarBanks extends Controller
{
    public function fetchBanks()
    {
        $url = 'https://gate.klasapps.com/wallet/merchant/bank/transfer/request/banks/ZAR';
        
        // Fetch the Klasha token (cleaning it up if necessary)
        $klashaToken = app('KlashaToken');
        $cleanKlashaToken = trim($klashaToken, '"'); // Remove quotes if they exist
        
       
        // Initialize cURL
        $ch = curl_init($url);

        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $cleanKlashaToken,  // Using the cleaned token here
            'X-Forwarded-For: 91.134.244.67'
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        // Execute cURL request
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            echo "Curl Error: " . curl_error($ch);
            return response()->json(['error' => curl_error($ch)], 500);
        }

        // Close cURL session
        curl_close($ch);

        // Decode the response
        $responseData = json_decode($response, true);

        // Return the response data
        return response()->json($responseData);
    }
}
