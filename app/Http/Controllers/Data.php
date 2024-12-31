<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Data extends Controller
{
    // Function to get the access token (assuming you have a service for this)
    protected function getAccessToken()
    {
        return app('AccessToken'); // Retrieve the access token from your service
    }

    // Function to update the wallet
    public function updateWallet($userId, $amount)
    {
        DB::table('wallet')
            ->where('user_id', $userId)
            ->decrement('Naira', $amount);

        // Log the wallet update
        Log::info("Wallet Updated", ['userId' => $userId, 'amount' => $amount]);
    }

    // Function to get user_id from token
    public function getUserIdFromToken($token)
    {
        $userId = DB::table('user_credentials')
            ->where('token', $token)
            ->value('user_id');
        return $userId;
    }

    // Function to purchase data
    public function purchaseData($amount, $id, $mobileNumber, $bundle)
    {
        // Fetch the access token (Bearer token)
        $accessToken = $this->getAccessToken();
        $clientId = "9e0763f989fd3090583662e05117fdca"; // Your ClientID
        $apiEndpoint = "https://api.safehavenmfb.com/vas/pay/data"; // The API endpoint

        // Prepare the data for the API request
        $data = [
            "amount" => (int)$amount, // Ensure the amount is an integer
            "channel" => "WEB",
            "serviceCategoryId" => $id,
            "bundleCode" => $bundle,
            "phoneNumber" => $mobileNumber,
            "debitAccountNumber" => "0115724310", // Use your debit account number here
        ];

        // Set headers for the cURL request
        $headers = [
            "ClientID: $clientId",
            "accept: application/json",
            "authorization: Bearer $accessToken", // Bearer token in the header
            "content-type: application/json"
        ];

        // Initialize cURL for sending the request
        $ch = curl_init($apiEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Get the response as a string
        curl_setopt($ch, CURLOPT_POST, true); // Set the request method to POST
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); // Send the data as JSON
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); // Set the headers

        // Execute the cURL request and capture the response
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Get HTTP status code
        $error = curl_error($ch); // Get any error message from cURL
        curl_close($ch); // Close cURL session

        // If cURL returned an error, log and return it
        if ($error) {
            Log::error("cURL Error: $error");
            return [
                'statusCode' => $statusCode,
                'error' => $error,
                'curl_response' => $response, // Return the actual cURL response
            ];
        }

        // Decode the response to make it readable
        $decodedResponse = json_decode($response, true);

        // Return the response with both status code, message, and the real cURL response
        return [
            'statusCode' => $statusCode,
            'response' => $decodedResponse, // Response from the external API
            'curl_response' => $response, // The raw cURL response
        ];
    }

    // Handle the POST request from the frontend
    public function handlePostRequest(Request $request)
    {
        // Get input data directly from the request
        $amount = $request->input('amount');
        $id = $request->input('networkId'); // Correct key for the service category ID
        $mobileNumber = $request->input('mobileNumber');
        $bundle = $request->input('bundleCode');
        $token = $request->input('token'); // Token provided by the frontend, optional to use

        // Log incoming request data for debugging
        Log::info("Incoming Request Data", ['data' => $request->all()]);

        // Call purchase data method to process the request
        $result = $this->purchaseData($amount, $id, $mobileNumber, $bundle);

        // If API response status code is 200 or 201, proceed
        if (in_array($result['statusCode'], [200, 201])) {
            $responseData = $result['response'];

            // If data purchase status is successful
            if (isset($responseData['data']['status']) && $responseData['data']['status'] === 'successful') {
                // Get the user ID from the token
                $userId = $this->getUserIdFromToken($token);

                // Update wallet after successful data purchase
                $this->updateWallet($userId, $amount);

                // Log the success
                Log::info("Data purchase successful and wallet updated", [
                    'userId' => $userId,
                    'amount' => $amount,
                    'reference' => $responseData['data']['reference']
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Data purchased and wallet updated successfully',
                    'data' => $responseData
                ]);
            } else {
                // If purchase failed, return error
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to purchase data bundle. Please try again.',
                    'data' => $responseData
                ]);
            }
        } else {
            // If the status code is not 200 or 201
            return response()->json([
                'status' => 'error',
                'message' => 'API request failed.',
                'statusCode' => $result['statusCode'],
                'curl_response' => $result['curl_response']
            ]);
        }
    }
}
