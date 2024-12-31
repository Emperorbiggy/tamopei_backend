<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VerifyBvnOtp extends Controller
{
    private $encryption_key = "xPF0rPHQiRSLn3KD3OVq++GtW11ag7R2G8J0owl+xsE=";

    public function verify(Request $request)
    {
        // Retrieve the access token from the service container
        $accessToken = app('AccessToken');

        // Extract data from the request (ensure it matches the frontend format)
        $token = $request->input('token');
        $verificationCode = $request->input('verification_code');
        $bvnId = $request->input('id');

        // Validate the incoming request
        if (!$token || !$verificationCode || !$bvnId) {
            return response()->json(['success' => false, 'message' => 'Missing required data.']);
        }

        // Fetch user_id and decrypted BVN using the token
        $userData = $this->getUserDataFromToken($token);
        if (!$userData) {
            return response()->json(['success' => false, 'message' => 'Invalid token or user not found.']);
        }

        $user_id = $userData->user_id;
        $bvn = $this->decryptBVN($userData->bvn_hash);

        if (!$bvn) {
            return response()->json(['success' => false, 'message' => 'Failed to decrypt BVN.']);
        }

        // Create the virtual account and include the postData in the response
        $result = $this->createVirtualAccount($user_id, $bvn, $bvnId, $verificationCode, $accessToken);

        return response()->json($result);
    }

    // Function to get user data (including BVN hash) from token
    private function getUserDataFromToken($token)
    {
        return DB::table('user_credentials')
            ->select('user_id', 'bvn_hash')
            ->where('token', $token)
            ->first();
    }

    // Function to decrypt BVN
    private function decryptBVN($encrypted_bvn)
    {
        $cipher = "aes-256-cbc";
        list($encrypted_data, $iv) = explode('::', base64_decode($encrypted_bvn), 2);
        return openssl_decrypt($encrypted_data, $cipher, $this->encryption_key, 0, $iv);
    }

    // Function to create virtual account
    private function createVirtualAccount($user_id, $bvn, $bvnId, $verificationCode, $accessToken)
    {
        $user = DB::table('user_credentials')->where('user_id', $user_id)->first();

        if (!$user) {
            return [
                'success' => false, 
                'message' => 'User not found.',
                'postData' => null
            ];
        }

        $url = "https://api.safehavenmfb.com/accounts/subaccount";

        $postData = [
    "phoneNumber" => $user->phone,
    "emailAddress" => $user->email,
    "identityType" => "BVN",
    "autoSweep" => "true",
    "autoSweepDetails" => [
        "schedule" => "Instant",
        "accountNumber" => "0113693092"
    ],
    "externalReference" => (string)$user_id, // Cast to string
    "identityNumber" => $bvn,
    "identityId" => $bvnId,
    "otp" => $verificationCode
];


        // Perform cURL request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "accept: application/json",
            "Authorization: Bearer $accessToken",
            "ClientID: 6593da409e5cbb0024545f09",
            "content-type: application/json"
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            Log::error("cURL Error: " . $error);
            return [
                'success' => false, 
                'message' => 'cURL Error: ' . $error, 
                'postData' => $postData
            ];
        }

        curl_close($ch);

        $responseData = json_decode($response, true);

        // Check if the response is successful
        if (isset($responseData['statusCode']) && $responseData['statusCode'] === 200) {
            $this->insertData($user_id, $responseData['data'], $bvn);
            return [
                'success' => true, 
                'message' => 'Virtual account created successfully.',
                'postData' => $postData,
                'response' => $responseData
            ];
        }

        $errorMessage = $responseData['message'] ?? 'Unknown error occurred.';
        Log::error("Virtual account creation failed: " . $errorMessage);
        return [
            'success' => false, 
            'message' => $errorMessage, 
            'postData' => $postData,
            'response' => $responseData
        ];
    }

    // Function to insert data into virtual_accounts table
    private function insertData($user_id, $accountData, $bvn)
    {
        try {
            DB::table('virtual_accounts')->insert([
                'accountName' => $accountData['accountName'],
                'accountNumber' => $accountData['accountNumber'],
                'accountProduct' => $accountData['accountProduct'],
                'accountType' => $accountData['accountType'],
                'user_id' => $user_id,
                'bankName' => 'Safe Haven Microfinance Bank',
                'bvn' => $bvn
            ]);
        } catch (\Exception $e) {
            Log::error('Database error: ' . $e->getMessage());
        }
    }
}

