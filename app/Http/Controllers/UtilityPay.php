<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UtilityPay extends Controller
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
        return DB::table('user_credentials')
            ->where('token', $token)
            ->value('user_id');
    }

    // Function to check wallet balance
    public function checkWalletBalance($userId, $amount)
    {
        $balance = DB::table('wallet')
            ->where('user_id', $userId)
            ->value('Naira');

        return $balance >= $amount; // Returns true if balance is sufficient
    }

    // Function to purchase utility
    public function purchaseUtility($amount, $id, $number, $vendor)
    {
        // Fetch the access token (Bearer token)
        $accessToken = $this->getAccessToken();
        $clientId = "9e0763f989fd3090583662e05117fdca"; // Your ClientID
        $apiEndpoint = "https://api.safehavenmfb.com/vas/pay/utility"; // The API endpoint

        // Prepare the data for the API request
        $data = [
            "amount" => (int)$amount, // Ensure the amount is an integer
            "channel" => "WEB",
            "serviceCategoryId" => $id,
            "debitAccountNumber" => "0113693092",
            "meterNumber" => $number,
            "vendType" => $vendor,
        ];

        // Set headers for the cURL request
        $headers = [
            "ClientID: $clientId",
            "accept: application/json",
            "authorization: Bearer $accessToken",
            "content-type: application/json"
        ];

        // Initialize cURL for sending the request
        $ch = curl_init($apiEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Execute the cURL request and capture the response
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Handle cURL errors
        if ($error) {
            Log::error("cURL Error: $error");
            return [
                'statusCode' => $statusCode,
                'error' => $error,
                'curl_response' => $response,
            ];
        }

        // Decode the response to make it readable
        $decodedResponse = json_decode($response, true);

        return [
            'statusCode' => $statusCode,
            'response' => $decodedResponse,
            'curl_response' => $response,
        ];
    }

    // Handle the POST request from the frontend
    public function purchaseUtilityRequest(Request $request)
    {
        $amount = $request->input('amount');
        $id = $request->input('id');
        $number = $request->input('smartCardNumber');
        $vendor = $request->input('vendType');
        $token = $request->input('token');

        Log::info("Incoming Request Data", ['data' => $request->all()]);

        $userId = $this->getUserIdFromToken($token);

        // Check if the user has sufficient balance
        if (!$this->checkWalletBalance($userId, $amount)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient wallet balance.'
            ], 400);
        }

        $result = $this->purchaseUtility($amount, $id, $number, $vendor);

        if (in_array($result['statusCode'], [200, 201])) {
            $responseData = $result['response'];

            if (isset($responseData['data']['status']) && $responseData['data']['status'] === 'successful') {
                $this->updateWallet($userId, $amount);

                Log::info("Utility purchase successful and wallet updated", [
                    'userId' => $userId,
                    'amount' => $amount,
                    'reference' => $responseData['data']['reference']
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Utility purchased and wallet updated successfully',
                    'data' => $responseData
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to purchase utility. Please try again.',
                    'data' => $responseData
                ]);
            }
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'API request failed.',
                'statusCode' => $result['statusCode'],
                'curl_response' => $result['curl_response']
            ]);
        }
    }
}
