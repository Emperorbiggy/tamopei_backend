<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\File;
use App\Mail\VerificationEmail; // Ensure you have this Mailable
use Illuminate\Support\Facades\Config; // Import the Config facade

class Vs2SignUpController extends Controller
{
    private $secretKey;

    public function __construct()
    {
        // Retrieve the secret key from the config file
        $this->secretKey = Config::get('app.secret_key'); // Fetch the secret key from config
    }

    private function logError($message, $filename)
    {
        if (!empty($filename)) {
            $timestamp = now();
            Log::channel('custom')->error("[$timestamp] $message");
            File::append(storage_path('logs/' . $filename), "[$timestamp] $message" . PHP_EOL);
        }
    }

    private function generateUserId($pdo)
    {
        $year = now()->year;
        $month = now()->month;
        $randomNumber = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $userId = $year . $month . $randomNumber;

        $stmt = $pdo->prepare("SELECT user_id FROM user_credentials WHERE user_id = ?");
        while ($stmt->execute([$userId]) && $stmt->rowCount() > 0) {
            $userId = substr_replace($userId, mt_rand(0, 9), -1);
        }
        return $userId;
    }

    private function sendVerificationEmail($email, $verificationCode, $username)
    {
        try {
            // Create a new Mailable instance (you need to create this Mailable class)
            $emailData = [
                'username' => $username,
                'verificationCode' => $verificationCode,
            ];

            Mail::to($email)->send(new VerificationEmail($emailData));
        } catch (\Exception $e) {
            $this->logError('Error sending email: ' . $e->getMessage(), 'emailerror.txt');
        }
    }

    private function sanitizeInput($input)
    {
        return htmlspecialchars(stripslashes(trim($input)));
    }

    public function register(Request $request)
    {
        // Log the secret key to verify it's being read correctly
        Log::info("Secret key being used: " . $this->secretKey);
        try {
            $receivedData = [
                'firstname' => $this->sanitizeInput($request->input('firstname')),
                'middlename' => $this->sanitizeInput($request->input('middlename')),
                'lastname' => $this->sanitizeInput($request->input('lastname')),
                'email' => $this->sanitizeInput($request->input('email')),
                'country' => $this->sanitizeInput($request->input('country')),
                'username' => $this->sanitizeInput($request->input('username')),
                'phone' => $this->sanitizeInput($request->input('phone')),
                'dob' => $this->sanitizeInput($request->input('dob'))
            ];

            Log::channel('custom')->info('Received data: ' . json_encode($receivedData));

            $requiredFields = ['firstname', 'lastname', 'middlename', 'email', 'phone', 'country', 'username', 'dob'];
            foreach ($requiredFields as $field) {
                if (empty($receivedData[$field])) {
                    $errorMessage = 'Required fields missing: ' . $field;
                    $this->logError($errorMessage, 'validationerror.txt');
                    return response()->json(['success' => false, 'message' => $errorMessage]);
                }
            }

            $dob = \DateTime::createFromFormat('Y-m-d', $receivedData['dob']);
            if (!$dob || $dob->format('Y-m-d') !== $receivedData['dob']) {
                $errorMessage = 'Invalid date format for date of birth.';
                $this->logError($errorMessage, 'validationerror.txt');
                return response()->json(['success' => false, 'message' => $errorMessage]);
            }
            $receivedData['dob'] = $dob->format('Y-m-d');

            $client = new \GuzzleHttp\Client();
            $response = $client->post('https://easinovation.com.ng/key/create/', [
                'form_params' => ['secret_key' => $this->secretKey]
            ]);

            $body = $response->getBody();
            $tokenResponse = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errorMessage = 'Failed to decode token response: ' . json_last_error_msg();
                $this->logError($errorMessage, 'tokenerror.txt');
                return response()->json(['success' => false, 'message' => $errorMessage]);
            }
            $token = $tokenResponse['token'] ?? '';

            $userId = $this->generateUserId(DB::getPdo());
            $verificationCode = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

            $result = DB::table('user_credentials')->insert([
                'user_id' => $userId,
                'first_name' => $receivedData['firstname'],
                'middle_name' => $receivedData['middlename'],
                'last_name' => $receivedData['lastname'],
                'username' => $receivedData['username'],
                'email' => $receivedData['email'],
                'phone' => $receivedData['phone'],
                'country' => $receivedData['country'],
                'dob' => $receivedData['dob'],
                'level' => 'Tier 1',
                'kyc_status' => 'unverified',
                'verification_status' => 0,
                'token' => $token,
                'verification_code' => $verificationCode
            ]);

            if (!$result) {
                $errorMessage = 'Failed to insert user credentials.';
                $this->logError($errorMessage, 'databaseerror.txt');
                return response()->json(['success' => false, 'message' => $errorMessage]);
            }

            $result = DB::table('wallet')->insert([
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
                'XAF' => 0.00
            ]);

            if (!$result) {
                $errorMessage = 'Failed to initialize wallet.';
                $this->logError($errorMessage, 'walleterror.txt');
                return response()->json(['success' => false, 'message' => $errorMessage]);
            }

            // Send the verification email using Laravel's Mail functionality
            $this->sendVerificationEmail($receivedData['email'], $verificationCode, $receivedData['username']);

            Log::channel('custom')->info("User registered: $userId");

            return response()->json([
                'success' => true,
                'message' => 'Registration successful. Verification code sent to your email.',
                'token' => $token,
                'userId' => $userId
            ]);
        } catch (\Exception $e) {
            $this->logError('Exception caught: ' . $e->getMessage(), 'error.txt');
            return response()->json(['success' => false, 'message' => 'An unexpected error occurred.']);
        }
    }
}
