<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ResolveAccount extends Controller
{
    // Method to resolve account number by making an API request
    public function resolveAccount(Request $request)
    {
        // Get account number and bank code from the POST request
        $data = json_decode(file_get_contents('php://input'), true);

        $accountNumber = isset($data['accountNumber']) ? $data['accountNumber'] : '';
        $bankCode = isset($data['bankCode']) ? $data['bankCode'] : '';

        // Retrieve the access token from the service container
        $accessToken = app('AccessToken');

        // API endpoint for the account name enquiry
        $url = 'https://api.safehavenmfb.com/transfers/name-enquiry';

        // Headers for the API request
        $headers = [
            'ClientID: 9e0763f989fd3090583662e05117fdca',
            'accept: application/json',
            'authorization: Bearer ' . $accessToken,
            'content-type: application/json',
        ];

        // Data for the POST request
        $requestData = [
            'bankCode' => $bankCode,
            'accountNumber' => $accountNumber,
        ];

        // Initialize cURL session
        $ch = curl_init($url);

        // Set cURL options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));

        // Execute cURL session
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            return response()->json(['error' => 'Curl error: ' . curl_error($ch)], 500);
        }

        // Close cURL session
        curl_close($ch);

        // Return the response as JSON
        return response()->json(json_decode($response, true));
    }
}

