<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;

class Vs1SignUpController extends Controller
{
    private $secretKey;

    public function __construct()
    {
        // Retrieve the secret key from the config file
        $this->secretKey = Config::get('app.secret_key');
    }

    public function signUp(Request $request)
    {
        // Log the secret key to verify it's being read correctly
        Log::info("Secret key being used: " . $this->secretKey);

        // Define log file paths
        $dataLogFile = storage_path('logs/data_received.log');
        $tokenErrorLogFile = storage_path('logs/token_error.log');
        $databaseErrorLogFile = storage_path('logs/database_error.log');

        // Validate the request data
        $validatedData = $request->validate([
            'firstname' => 'required|string',
            'lastname' => 'required|string',
            'middlename' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
            'country' => 'required|string',
            'username' => 'required|string',
            'deviceInfo.deviceType' => 'required|string',
            'deviceInfo.browser' => 'required|string',
            'deviceInfo.os' => 'required|string',
            'deviceInfo.deviceId' => 'required|string',
        ]);

        // Log received data
        Log::info("Received data", $validatedData);

        // Generate a unique user ID
        $userId = $this->generateUserId();

        // Generate token using Guzzle
        $token = $this->generateToken($this->secretKey, $tokenErrorLogFile);
        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Token generation failed.'], 500);
        }

        try {
            // Insert user credentials with device information in JSON format
            DB::table('user_credentials')->insert([
                'user_id' => $userId,
                'first_name' => $validatedData['firstname'],
                'middle_name' => $validatedData['middlename'],
                'last_name' => $validatedData['lastname'],
                'username' => $validatedData['username'],
                'email' => $validatedData['email'],
                'phone' => $validatedData['phone'],
                'country' => $validatedData['country'],
                'level' => 'Tier 1',
                'kyc_status' => 'unverified',
                'verification_status' => 0,
                'token' => $token,
                'device_info' => json_encode($validatedData['deviceInfo']), // Store device info as JSON
            ]);

            // Initialize wallet balances
            DB::table('wallet')->insert([
                'user_id' => $userId,
                'Rand' => 0.00,
                'Cedi' => 0.00,
                'Dollar' => 0.00,
                'Naira' => 0.00,
                'Pound' => 0.00,
                'Euro' => 0.00,
                'Yuan' => 0.00,
                'CAD' => 0.00,
                'KES' => 0.00,
                'XAF' => 0.00,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Signup successful',
                'token' => $token,
                'userId' => $userId,
            ]);

        } catch (\Exception $e) {
            Log::error('Database error: ' . $e->getMessage(), ['file' => $databaseErrorLogFile]);
            return response()->json(['success' => false, 'message' => 'Database insertion failed.'], 500);
        }
    }

    private function generateUserId()
    {
        $year = date('Y');
        $month = date('m');
        $randomNumber = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $userId = $year . $month . $randomNumber;

        // Check if user_id already exists
        while (DB::table('user_credentials')->where('user_id', $userId)->exists()) {
            $userId = substr_replace($userId, mt_rand(0, 9), -1);
        }

        return $userId;
    }

    private function generateToken($secretKey, $logFile)
    {
        $client = new Client();
        try {
            $response = $client->post('https://easinovation.com.ng/key/create/', [
                'form_params' => [
                    'secret_key' => $secretKey,
                ],
                'verify' => true,
            ]);

            if ($response->getStatusCode() === 200) {
                $responseData = json_decode($response->getBody(), true);
                return $responseData['token'] ?? null;
            }
        } catch (\Exception $e) {
            Log::error('Token generation error: ' . $e->getMessage(), ['file' => $logFile]);
        }

        return null;
    }
}
