<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;
use Log; // Include Log facade for logging

class CablePay extends Controller
{
    // Protected function to get the access token
    protected function getAccessToken()
    {
        return app('AccessToken'); // Retrieve the access token from the service container
    }

    // Function to handle the cable purchase via API call
    protected function handleCablePurchase($amount, $id, $number, $code)
    {
        $accessToken = $this->getAccessToken(); // Get access token
        $clientId = "9e0763f989fd3090583662e05117fdca"; // Client ID
        $apiEndpoint = "https://api.safehavenmfb.com/vas/pay/cable-tv"; // API endpoint

        // Prepare the data for the API call
        $data = [
            "amount" => intval($amount), // Ensure amount is an integer
            "channel" => "WEB", // Channel through which the purchase is made
            "serviceCategoryId" => $id, // Service provider ID
            "bundleCode" => $code, // Bundle code for the cable
            "debitAccountNumber" => "0113693092", // Static debit account number or can be dynamic
            "cardNumber" => $number, // Card number used for payment
        ];

        // Log the request data for debugging
        Log::info('Cable purchase request data:', $data); // Logs the data being sent to the API

        // Set up the headers for the API request
        $headers = [
            "ClientID: $clientId", // Client ID header
            "accept: application/json", // Accept header for JSON response
            "authorization: Bearer $accessToken", // Authorization header with Bearer token
            "content-type: application/json", // Content type for the request
        ];

        // Initialize cURL session
        $ch = curl_init($apiEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Return the response as a string
        curl_setopt($ch, CURLOPT_POST, true); // Set request method to POST
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); // Pass the data as JSON
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Attach the headers

        // Execute cURL request
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get HTTP status code
        $error = curl_error($ch); // Capture any cURL error
        curl_close($ch); // Close the cURL session

        // Log the API response for debugging
        Log::info('API response:', [
            'statusCode' => $statusCode,
            'response' => $response,
            'error' => $error,
        ]);

        // Check if there was any cURL error
        if ($error) {
            return response()->json([
                "error" => "An error occurred while making the API request: $error",
                "statusCode" => 500,
                "curlData" => [
                    'url' => $apiEndpoint,
                    'headers' => $headers,
                    'data' => $data
                ]
            ]);
        }

        // Return the status code and response from the API
        return [
            'statusCode' => $statusCode,
            'response' => json_decode($response, true), // Decode the response JSON
            'curlData' => [
                'url' => $apiEndpoint,
                'headers' => $headers,
                'data' => $data
            ]
        ];
    }

    // Function to handle the cable purchase request
    public function handleCablePurchaseRequest(Request $request)
    {
        // Validate input parameters
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'providerId' => 'required|string',
            'cardNumber' => 'required|string',
            'token' => 'required|string',
            'value' => 'required|string',
        ]);

        try {
            // Extract validated inputs
            $amount = $validated['amount'];
            $id = $validated['providerId'];
            $number = $validated['cardNumber'];
            $token = $validated['token'];
            $code = $validated['value'];

            // Get the user ID from the token
            $userId = $this->getUserIdFromToken($token);

            if (!$userId) {
                return response()->json([
                    "error" => "User not found.",
                    "statusCode" => 404,
                ]);
            }

            // Check user's wallet balance
            $wallet = DB::table('wallet')
                ->where('user_id', $userId)
                ->first();

            if (!$wallet || $wallet->Naira < $amount) {
                return response()->json([
                    "error" => "Insufficient wallet balance.",
                    "statusCode" => 400,
                ]);
            }

            // Make API call to purchase cable
            $result = $this->handleCablePurchase($amount, $id, $number, $code);

            // Check if the API call was successful
            if (in_array($result['statusCode'], [200, 201])) {
                $responseData = $result['response'];

                if (isset($responseData['data']['status']) && $responseData['data']['status'] === 'successful') {
                    // If successful, update wallet
                    $this->updateWallet($userId, $amount);

                    // Return success response with cURL data
                    return response()->json([
                        "message" => "Cable purchased successfully.",
                        "statusCode" => 200,
                        "data" => $responseData['data'], // Return the successful data from API response
                        "curlData" => $result['curlData'] // Include the cURL data used
                    ]);
                }

                // Return error if the API response indicates failure
                return response()->json([
                    "error" => "Cable purchase failed. Invalid API response status.",
                    "statusCode" => 400,
                    "apiResponse" => $responseData,
                    "curlData" => $result['curlData'] // Include the cURL data used
                ]);
            }

            // Return error if the API call failed
            return response()->json([
                "error" => "Cable purchase failed. API status code: " . $result['statusCode'],
                "statusCode" => $result['statusCode'],
                "apiResponse" => $result['response'],
                "curlData" => $result['curlData'] // Include the cURL data used
            ]);
        } catch (Exception $e) {
            // Catch any unexpected exceptions and return error message
            return response()->json([
                "error" => "An unexpected error occurred: " . $e->getMessage(),
                "statusCode" => 500,
                "curlData" => [] // Send empty cURL data if exception occurs
            ]);
        }
    }

    // Function to update the user's wallet balance after a successful purchase
    protected function updateWallet($userId, $amount)
    {
        DB::table('wallet')
            ->where('user_id', $userId)
            ->decrement('Naira', $amount); // Decrement wallet balance
    }

    // Function to retrieve the user ID from the token
    protected function getUserIdFromToken($token)
    {
        $user = DB::table('user_credentials')
            ->where('token', $token)
            ->first();

        return $user ? $user->user_id : null; // Return user ID if found, otherwise null
    }
}
