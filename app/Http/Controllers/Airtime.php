<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class Airtime extends Controller
{
    /**
     * Retrieve the access token from the service container
     */
    protected function getAccessToken()
    {
        return app('AccessToken');
    }

    /**
     * Function to purchase airtime
     */
    protected function handleAirtimePurchase($amount, $id, $mobileNumber)
    {
        $accessToken = $this->getAccessToken();
        $clientId = "9e0763f989fd3090583662e05117fdca";
        $apiEndpoint = "https://api.safehavenmfb.com/vas/pay/airtime";

        $data = [
            "amount" => intval($amount),
            "channel" => "WEB",
            "serviceCategoryId" => $id,
            "debitAccountNumber" => "0115724310",
            "phoneNumber" => $mobileNumber,
        ];

        $headers = [
            "ClientID: $clientId",
            "accept: application/json",
            "authorization: Bearer $accessToken",
            "content-type: application/json",
        ];

        $ch = curl_init($apiEndpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'statusCode' => $statusCode,
            'response' => json_decode($response, true),
        ];
    }

    /**
     * Function to update the user's wallet
     */
    protected function updateWallet($userId, $amount)
    {
        DB::table('wallet')
            ->where('user_id', $userId)
            ->decrement('Naira', $amount);
    }

    /**
     * Function to get user_id from token
     */
    protected function getUserIdFromToken($token)
    {
        $user = DB::table('user_credentials')
            ->where('token', $token)
            ->first();

        return $user ? $user->user_id : null;
    }

    /**
     * Handle the Airtime Purchase Request
     */
    public function handleAirtimePurchaseRequest(Request $request)
    {
        // Retrieve input parameters
        $amount = $request->input('amount');
        $id = $request->input('providerId');  // Renamed to match the input parameter
        $mobileNumber = $request->input('mobileNumber');
        $token = $request->input('token');

        // Check for missing required fields
        if (!$amount || !$id || !$mobileNumber || !$token) {
            return response()->json([
                "error" => "Missing required fields",
                "statusCode" => 400,
            ]);
        }

        try {
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

            // Make API call to purchase airtime
            $result = $this->handleAirtimePurchase($amount, $id, $mobileNumber);

            // If API response status code is 200 or 201, proceed
            if (in_array($result['statusCode'], [200, 201])) {
                $responseData = $result['response'];

                // If airtime purchase status is successful
                if (isset($responseData['data']['status']) && $responseData['data']['status'] === 'successful') {
                    try {
                        // Deduct the amount from the wallet
                        $this->updateWallet($userId, $amount);

                        // Return success response
                        return response()->json([
                            "message" => "Airtime purchased and wallet updated successfully.",
                            "statusCode" => 200,
                            "data" => $responseData['data'],
                        ]);
                    } catch (Exception $e) {
                        // Database error handling if wallet update fails
                        return response()->json([
                            "error" => "Database error: " . $e->getMessage(),
                            "statusCode" => 500,
                        ]);
                    }
                }

                // If the airtime purchase is not successful in the response, return failure response
                return response()->json([
                    "error" => "Airtime purchase failed. Invalid API response status.",
                    "statusCode" => 400,
                    "apiResponse" => $responseData,
                ]);
            }

            // If API status code is not 200 or 201, return failure response
            return response()->json([
                "error" => "Airtime purchase failed. API status code: " . $result['statusCode'],
                "statusCode" => $result['statusCode'],
                "apiResponse" => $result['response'],
            ]);
        } catch (Exception $e) {
            // General error handling
            return response()->json([
                "error" => "An unexpected error occurred: " . $e->getMessage(),
                "statusCode" => 500,
            ]);
        }
    }
}
