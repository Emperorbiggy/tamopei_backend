<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BvnVerification extends Controller
{
    // Encryption key
    private $encryption_key = "xPF0rPHQiRSLn3KD3OVq++GtW11ag7R2G8J0owl+xsE=";

    // Function to encrypt BVN
    private function encryptBVN($bvn) {
        $cipher = "aes-256-cbc";
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $encrypted_bvn = openssl_encrypt($bvn, $cipher, $this->encryption_key, 0, $iv);
        return base64_encode($encrypted_bvn . '::' . $iv);
    }

    // Function to decrypt BVN
    private function decryptBVN($encrypted_bvn) {
        $cipher = "aes-256-cbc";
        list($encrypted_data, $iv) = explode('::', base64_decode($encrypted_bvn), 2);
        return openssl_decrypt($encrypted_data, $cipher, $this->encryption_key, 0, $iv);
    }

    public function verify(Request $request)
    {
        // Retrieve the access token from the service container
        $accessToken = app('AccessToken');

        // Get and log raw POST data
        $data = $request->getContent();
        Log::channel('single')->info("Raw POST data: " . $data);

        $data = json_decode($data, true);

        // Log received data for debugging
        Log::channel('single')->info("Decoded data: " . print_r($data, true));

        if (!$data) {
            Log::error("No data received");
            return response()->json(['success' => false, 'message' => 'No data received']);
        }

        $token = $data['token'] ?? '';
        $bvn = $data['bvn'] ?? '';
        $dob = $data['dob'] ?? '';

        if (!$token || !$bvn || !$dob) {
            Log::error("Token, BVN, or DOB missing");
            return response()->json(['success' => false, 'message' => 'Token, BVN, or DOB missing']);
        }

        $encrypted_bvn = $this->encryptBVN($bvn);
        Log::channel('single')->info("Encrypted BVN: " . $encrypted_bvn);

        // Assuming you have a database connection already set up via Eloquent, or $pdo is initialized
        try {
    $affectedRows = \DB::table('user_credentials')
        ->where('token', $token)
        ->update(['bvn_hash' => $encrypted_bvn, 'dob' => $dob]);

    if ($affectedRows) {
        Log::channel('single')->info("BVN and DOB updated successfully");
    } else {
        Log::error("Failed to update BVN and DOB");
        return response()->json(['success' => false, 'message' => 'Failed to update BVN and DOB']);
    }
} catch (\Exception $e) {
    $errorMessage = $e->getMessage();
    Log::error("Database error: " . $errorMessage);
    return response()->json([
        'success' => false,
        'message' => 'Database error',
        'error' => $errorMessage, // Include the real error message
    ]);
}

        $decrypted_bvn = $this->decryptBVN($encrypted_bvn);
        Log::channel('single')->info("Decrypted BVN: " . $decrypted_bvn);

        // Call the external API using cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.safehavenmfb.com/identity/v2");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "type" => "BVN",
            "number" => $bvn,
            "debitAccountNumber" => "0113970209"
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $accessToken",
            "ClientID: 6593da409e5cbb0024545f09",
            "accept: application/json",
            "content-type: application/json"
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            Log::error("cURL error: " . curl_error($ch));
        }
        curl_close($ch);

        $responseData = json_decode($response, true);
        $responseData['bvn'] = $decrypted_bvn;

        Log::channel('single')->info("Final Response: " . print_r($responseData, true));

        return response()->json($responseData);
    }
}

